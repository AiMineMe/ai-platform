<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SystemUpdateController extends Controller
{
    /**
     * Display the system update page
     */
    public function index(): \Inertia\Response
    {
        $currentVersion = $this->getCurrentVersion();
        $migrateVersion = $this->getMigrateVersion();

        return Inertia::render('Admin/SystemUpdate', [
            'currentVersion' => $currentVersion,
            'migrateVersion' => $migrateVersion,
            'needsUpdate' => $this->needsUpdate($currentVersion, $migrateVersion),
            'pendingMigrations' => $this->getPendingMigrationsCount(),
        ]);
    }

    /**
     * Perform system migration and update
     */
    public function systemMigrate(Request $request): RedirectResponse
    {
        try {
            $pendingMigrations = $this->getPendingMigrationsCount();
            if ($pendingMigrations > 0) {
                Log::info("Running {$pendingMigrations} pending migrations");
                $exitCode = Artisan::call('migrate', [
                    '--force' => true,
                ]);

                if ($exitCode !== 0) {
                    throw new \Exception('Migration command failed with exit code: ' . $exitCode);
                }

                $migrationOutput = Artisan::output();
                Log::info('Migration output: ' . $migrationOutput);
            }

            $this->updateCurrentVersion();
            $this->clearSystemCaches();

            Log::info('System update completed successfully', [
                'new_version' => $this->getCurrentVersion(),
                'migrations_run' => $pendingMigrations
            ]);

            return redirect()->back()->with([
                'success' => 'System has been updated successfully.',
                'message' => "Updated to version {$this->getCurrentVersion()}. {$pendingMigrations} migrations were executed."
            ]);

        } catch (\Exception $e) {
            Log::error('System migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->user()->id
            ]);

            return redirect()->back()->with([
                'error' => 'System update failed: ' . $e->getMessage(),
                'message' => 'Please check the logs for detailed information.'
            ]);
        }
    }

    /**
     * Update the current version in environment file
     * @throws \Exception
     */
    private function updateCurrentVersion(): void
    {
        $newVersion = $this->getMigrateVersion();
        $this->putPermanentEnv('APP_CURRENT_VERSION', $newVersion);

        Log::info("Updated APP_CURRENT_VERSION to: {$newVersion}");
    }

    /**
     * Clear system caches
     */
    private function clearSystemCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');

            Log::info('System caches cleared successfully');
        } catch (\Exception $e) {
            Log::warning('Cache clearing failed: ' . $e->getMessage());
        }
    }

    /**
     * Update environment file with new value
     */
    private function putPermanentEnv($key, $value): void
    {
        $path = app()->environmentFilePath();

        if (!file_exists($path)) {
            throw new \Exception('Environment file not found');
        }

        $content = file_get_contents($path);
        $oldValue = env($key);
        $formattedValue = preg_match('/\s/', $value) ? "\"{$value}\"" : $value;
        $keyExists = preg_match("/^{$key}=/m", $content);

        if ($keyExists) {
            $quotedKey = preg_quote($key, '/');
            $patterns = [
                "/^{$quotedKey}=.*$/m",
            ];

            $replaced = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, "{$key}={$formattedValue}", $content);
                    $replaced = true;
                    break;
                }
            }

            if (!$replaced) {
                throw new \Exception("Failed to update existing {$key} in environment file");
            }
        } else {
            $content = rtrim($content) . "\n{$key}={$formattedValue}\n";
        }

        if (file_put_contents($path, $content) === false) {
            throw new \Exception('Failed to update environment file');
        }

        Log::info("Environment variable updated: {$key}={$value}");
    }


    /**
     * Get the current system version
     */
    private function getCurrentVersion(): string
    {
        return config('app.app_version');
    }

    /**
     * Get the target migration version
     */
    private function getMigrateVersion(): string
    {
        return config('app.migrate_version', env('APP_MIGRATE_VERSION', '1.0.0'));
    }

    /**
     * Check if system needs update
     */
    private function needsUpdate(string $currentVersion, string $migrateVersion): bool
    {
        return version_compare($currentVersion, $migrateVersion, '<') ||
            $this->getPendingMigrationsCount() > 0;
    }

    /**
     * Get count of pending migrations
     */
    private function getPendingMigrationsCount(): int
    {
        try {
            $migrationPath = database_path('migrations');
            $migrationFiles = glob($migrationPath . '/*.php');

            $executedMigrations = DB::table('migrations')
                ->pluck('migration')
                ->toArray();

            $pendingCount = 0;
            foreach ($migrationFiles as $file) {
                $migrationName = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($migrationName, $executedMigrations)) {
                    $pendingCount++;
                }
            }

            return $pendingCount;

        } catch (\Exception $e) {
            Log::warning('Could not determine pending migrations count: ' . $e->getMessage());
            return 0;
        }
    }
}
