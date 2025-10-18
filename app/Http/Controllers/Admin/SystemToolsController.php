<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Carbon\Carbon;
use Inertia\Response;

class SystemToolsController extends Controller
{

    /**
     * @return Response
     */
    public function cacheClear(): Response
    {
        return Inertia::render('Admin/SystemTools/CacheClear', [
            'cacheStats' => $this->getCacheStats()
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function executeCacheClear(Request $request): RedirectResponse
    {
        $request->validate([
            'cache_types' => 'required|array|min:1',
            'cache_types.*' => 'in:application,config,route,view,compiled'
        ]);

        $results = [];
        $cacheTypes = $request->cache_types;

        Cache::forget('all_frontend_settings');
        Cache::forget('frontend_menu');
        Cache::forget('frontend_languages');

        try {
            foreach ($cacheTypes as $type) {
                switch ($type) {
                    case 'application':
                        Artisan::call('cache:clear');
                        $results[] = ['type' => 'Application Cache', 'status' => 'success', 'message' => 'Cleared successfully'];
                        break;
                    case 'config':
                        Artisan::call('config:clear');
                        $results[] = ['type' => 'Config Cache', 'status' => 'success', 'message' => 'Cleared successfully'];
                        break;
                    case 'route':
                        Artisan::call('route:clear');
                        $results[] = ['type' => 'Route Cache', 'status' => 'success', 'message' => 'Cleared successfully'];
                        break;
                    case 'view':
                        Artisan::call('view:clear');
                        $results[] = ['type' => 'View Cache', 'status' => 'success', 'message' => 'Cleared successfully'];
                        break;
                    case 'compiled':
                        Artisan::call('clear-compiled');
                        $results[] = ['type' => 'Compiled Classes', 'status' => 'success', 'message' => 'Cleared successfully'];
                        break;
                }
            }

            return back()->with('success', 'Cache cleared successfully')->with('results', $results);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }


    /**
     * @return Response
     */
    public function systemInfo(): Response
    {
        return Inertia::render('Admin/SystemTools/SystemInfo', [
            'systemInfo' => $this->getSystemInfo()
        ]);
    }


    public function cron(): Response
    {
        return Inertia::render('Admin/SystemTools/Cron');
    }

    /**
     * @return array
     */
    private function getCacheStats(): array
    {
        return [
            'application_cache_size' => $this->formatBytes($this->getCacheSize()),
            'config_cached' => file_exists(base_path('bootstrap/cache/config.php')),
            'routes_cached' => file_exists(base_path('bootstrap/cache/routes-v7.php')),
            'views_cached' => count(glob(storage_path('framework/views/*.php'))),
        ];
    }

    /**
     * @return array
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_version' => DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'disk_space' => [
                'total' => $this->formatBytes(disk_total_space('.')),
                'free' => $this->formatBytes(disk_free_space('.')),
                'used' => $this->formatBytes(disk_total_space('.') - disk_free_space('.'))
            ],
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
        ];
    }

    /**
     * @return int
     */
    private function getCacheSize(): int
    {
        $size = 0;
        $path = storage_path('framework/' . 'cache');

        if (is_dir($path)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * @param $bytes
     * @return string
     */
    private function formatBytes($bytes): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
