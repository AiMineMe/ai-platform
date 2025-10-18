<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Services\LoginHistoryService;
use App\Services\TwoFactorService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SecurityController extends Controller
{
    private const RATE_LIMIT_2FA_ENABLE = '2fa-enable';
    private const RATE_LIMIT_2FA_VERIFY = '2fa-verify';
    private const RATE_LIMIT_PASSWORD_CHANGE = 'password-change';

    public function __construct(
        protected readonly TwoFactorService $twoFactorService,
        protected readonly LoginHistoryService $loginHistoryService
    ){

    }

    /**
     * @return Response|RedirectResponse
     */
    public function twoFactorIndex(): Response | RedirectResponse
    {
        try {
            $user = Auth::user();

            $secret = null;
            $qrCode = null;
            $recoveryCodes = null;

            if (!$user->hasTwoFactorEnabled()) {
                $secret = $this->twoFactorService->generateSecretKey();
                $qrCode = $this->twoFactorService->generateQRCodeUrl(
                    e(config('app.name')),
                    e($user->email),
                    $secret
                );
            } else {
                try {
                    $recoveryCodes = $user->two_factor_recovery_codes ?
                        json_decode(decrypt($user->two_factor_recovery_codes), true) : null;
                } catch (Exception $e) {
                    Log::error('Failed to decrypt recovery codes: ' . $e->getMessage());
                    $recoveryCodes = null;
                }
            }

            return Inertia::render('User/Security/TwoFactor', [
                'is_enabled' => $user->hasTwoFactorEnabled(),
                'qr_code' => $qrCode,
                'secret' => $secret,
                'recovery_codes' => $recoveryCodes,
            ]);

        } catch (Exception $e) {
            Log::error('2FA index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to load two-factor authentication page. Please try again.');
        }
    }

    /**
     * Display password change page
     * @return Response|RedirectResponse
     */
    public function passwordIndex(): Response | RedirectResponse
    {
        try {
            return Inertia::render('User/Security/Password');
        } catch (Exception $e) {
            Log::error('Password index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to load password change page. Please try again.');
        }
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $rateLimitKey = self::RATE_LIMIT_2FA_ENABLE . ':' . $user->id . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 100)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()->withErrors(['error', "Too many attempts. Please try again in {$seconds} seconds."]);
        }

        try {
            $validatedData = $request->validate([
                'secret' => [
                    'required',
                    'string',
                    'size:32',
                    'regex:/^[A-Z2-7]+$/'
                ],
                'code' => [
                    'required',
                    'string',
                    'size:6',
                    'regex:/^[0-9]{6}$/'
                ],
            ], [
                'secret.regex' => 'Invalid secret format.',
                'secret.size' => 'Invalid secret length.',
                'code.regex' => 'Code must be 6 digits.',
                'code.size' => 'Code must be 6 digits.',
            ]);

            if ($user->hasTwoFactorEnabled()) {
                return redirect()->back()->withErrors(['error', 'Two-factor authentication is already enabled.']);
            }

            $secret = strtoupper(trim($validatedData['secret']));
            $code = trim($validatedData['code']);

            if (!preg_match('/^[A-Z2-7]{32}$/', $secret)) {
                RateLimiter::hit($rateLimitKey, 900);
                return redirect()->back()->withErrors(['error', 'Invalid secret format.']);
            }

            try {
                $isValid = $this->twoFactorService->verifyCode($secret, $code, $user->id);
            } catch (Exception $e) {
                Log::error('2FA verification service error: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'trace' => $e->getTraceAsString()
                ]);
                RateLimiter::hit($rateLimitKey, 900);
                return redirect()->back()->withErrors(['error', 'Unable to verify code. Please try again.']);
            }

            if (!$isValid) {
                RateLimiter::hit($rateLimitKey, 900);
                return redirect()->back()->withErrors(['error', 'Invalid verification code. Please ensure your device time is synchronized and try again.']);
            }

            DB::beginTransaction();


            try {
                $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
                $user->update([
                    'two_factor_secret' => encrypt($secret),
                    'two_factor_confirmed_at' => now(),
                    'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                ]);

                DB::commit();
                RateLimiter::clear($rateLimitKey);
                Log::info('2FA enabled successfully', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip()
                ]);
                return redirect()->back()->with('success', 'Two-factor authentication has been enabled successfully. Please save your recovery codes in a secure location.');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            RateLimiter::hit($rateLimitKey, 900);
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            RateLimiter::hit($rateLimitKey, 900);

            Log::error('2FA enable error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to enable two-factor authentication. Please try again.');
        }
    }

    /**
     * Disable two-factor authentication
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $rateLimitKey = 'disable-2fa:' . $user->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()->with('error', "Too many attempts. Please try again in {$seconds} seconds.");
        }

        try {
            $validatedData = $request->validate([
                'password' => [
                    'required',
                    'string',
                    'min:6',
                    'max:255'
                ],
            ]);

            if (!$user->hasTwoFactorEnabled()) {
                return redirect()->back()->with('error', 'Two-factor authentication is not enabled.');
            }

            $password = $validatedData['password'];
            if (!Hash::check($password, $user->password)) {
                RateLimiter::hit($rateLimitKey, 900);

                Log::warning('2FA disable failed - incorrect password', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->back()->with('error', 'Invalid password. Please try again.');
            }

            DB::beginTransaction();

            try {
                $user->update([
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_recovery_codes' => null,
                ]);

                DB::commit();
                RateLimiter::clear($rateLimitKey);

                Log::info('2FA disabled successfully', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->back()->with('success', 'Two-factor authentication has been disabled.');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            RateLimiter::hit($rateLimitKey, 900);
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            RateLimiter::hit($rateLimitKey, 900);

            Log::error('2FA disable error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to disable two-factor authentication. Please try again.');
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $rateLimitKey = self::RATE_LIMIT_2FA_VERIFY . ':' . $user->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()->with('error', "Too many attempts. Please try again in {$seconds} seconds.");
        }

        try {
            $validatedData = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'size:6',
                    'regex:/^[0-9]{6}$/'
                ],
            ], [
                'code.regex' => 'Code must be 6 digits.',
                'code.size' => 'Code must be 6 digits.',
            ]);

            if (!$user->hasTwoFactorEnabled()) {
                return redirect()->back()->with('error', 'Two-factor authentication is not enabled.');
            }

            $code = trim($validatedData['code']);
            try {
                $secret = decrypt($user->two_factor_secret);
            } catch (Exception $e) {
                Log::error('Failed to decrypt 2FA secret: ' . $e->getMessage(), [
                    'user_id' => $user->id
                ]);
                RateLimiter::hit($rateLimitKey, 900);
                return redirect()->back()->with('error', 'Unable to verify code. Please contact support.');
            }

            try {
                $isValid = $this->twoFactorService->verifyCode($secret, $code, $user->id);
            } catch (Exception $e) {
                Log::error('2FA verification service error: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'trace' => $e->getTraceAsString()
                ]);
                RateLimiter::hit($rateLimitKey, 900);
                return redirect()->back()->with('error', 'Unable to verify code. Please try again.');
            }

            if ($isValid) {
                RateLimiter::clear($rateLimitKey);

                Log::info('2FA code verified successfully', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->back()->with('success', 'Code verified successfully.');
            } else {
                RateLimiter::hit($rateLimitKey, 900);
                Log::warning('2FA verification failed', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                ]);

                return redirect()->back()->with('error', 'Invalid verification code. Please ensure your device time is synchronized and try again.');
            }

        } catch (ValidationException $e) {
            RateLimiter::hit($rateLimitKey, 900);
            throw $e;
        } catch (Exception $e) {
            RateLimiter::hit($rateLimitKey, 900);

            Log::error('2FA verify error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to verify code. Please try again.');
        }
    }

    /**
     * Update user password
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $rateLimitKey = self::RATE_LIMIT_PASSWORD_CHANGE . ':' . $user->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);
            return redirect()->back()->with('error', "Too many password change attempts. Please try again in {$minutes} minutes.");
        }

        try {
            $validatedData = $request->validate([
                'current_password' => [
                    'required',
                    'string',
                    'min:6',
                    'max:255'
                ],
                'password' => [
                    'required',
                    'confirmed',
                    'max:255',
                    Password::min(8)
                ],
            ], [
                'password.uncompromised' => 'This password has appeared in a data breach. Please choose a different password.',
            ]);

            $currentPassword = $validatedData['current_password'];
            $newPassword = $validatedData['password'];

            if (!Hash::check($currentPassword, $user->password)) {
                RateLimiter::hit($rateLimitKey, 3600);
                Log::warning('Password change failed - incorrect current password', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->back()->with('error', 'Current password is incorrect.');
            }

            if (Hash::check($newPassword, $user->password)) {
                return redirect()->back()->with('error', 'New password must be different from your current password.');
            }

            DB::beginTransaction();

            try {
                $user->update([
                    'password' => Hash::make($newPassword),
                    'password_changed_at' => now(),
                ]);

                DB::commit();
                RateLimiter::clear($rateLimitKey);

                Log::info('Password updated successfully', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip()
                ]);

                return redirect()->back()->with('success', 'Password updated successfully. Please use your new password for future logins.');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            RateLimiter::hit($rateLimitKey, 3600);
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            RateLimiter::hit($rateLimitKey, 3600);

            Log::error('Password update error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to update password. Please try again.');
        }
    }

    /**
     * Display login history page
     * @return Response|RedirectResponse
     */
    public function loginHistoryIndex(): Response | RedirectResponse
    {
        try {
            $user = Auth::user();
            $history = $this->loginHistoryService->getHistoryForUser($user->id);
            if ($history && isset($history['data'])) {
                $history['data'] = collect($history['data'])->map(function ($item) {
                    if (is_object($item)) {
                        $item->ip_address = e($item->ip_address ?? '');
                        $item->user_agent = e(substr($item->user_agent ?? '', 0, 255));
                        $item->location = e($item->location ?? '');
                    } elseif (is_array($item)) {
                        $item['ip_address'] = e($item['ip_address'] ?? '');
                        $item['user_agent'] = e(substr($item['user_agent'] ?? '', 0, 255));
                        $item['location'] = e($item['location'] ?? '');
                    }
                    return $item;
                })->toArray();
            }

            $stats = $this->calculateLoginStats($user->email);
            return Inertia::render('User/Security/LoginHistory', [
                'history' => $history,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Login history index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to load login history. Please try again.');
        }
    }

    /**
     * Calculate login statistics for a user
     * @param string $email
     * @return array
     */
    private function calculateLoginStats(string $email): array
    {
        try {
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (!$email) {
                throw new Exception('Invalid email format');
            }

            $baseQuery = LoginAttempt::where('email', $email);

            return [
                'total_attempts' => (int) $baseQuery->count(),
                'successful_logins' => (int) LoginAttempt::where('email', $email)->where('successful', 1)->count(),
                'failed_attempts' => (int) LoginAttempt::where('email', $email)->where('successful', 0)->count(),
                'unique_ips' => (int) LoginAttempt::where('email', $email)->distinct('ip_address')->count(),
                'last_login' => $baseQuery->where('successful', true)
                    ->latest('attempted_at')
                    ->value('attempted_at'),
                'recent_failures' => (int) $baseQuery->where('successful', false)
                    ->where('attempted_at', '>=', now()->subDays(7))
                    ->count(),
            ];

        } catch (Exception $e) {
            Log::error('Login stats calculation error: ' . $e->getMessage());

            return [
                'total_attempts' => 0,
                'successful_logins' => 0,
                'failed_attempts' => 0,
                'unique_ips' => 0,
                'last_login' => null,
                'recent_failures' => 0,
            ];
        }
    }
}
