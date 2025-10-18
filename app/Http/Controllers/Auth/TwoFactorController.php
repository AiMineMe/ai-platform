<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;
    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * @return Response|RedirectResponse
     */
    public function show(): Response|RedirectResponse
    {
        $user = Auth::user();

        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->intended($this->getRedirectUrl($user));
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request)
    {
        $user = Auth::user();
        $key = '2fa-verify:' . $user->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['code' => "Too many attempts. Please try again in {$seconds} seconds."]);
        }

        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        if (!$user || !$user->hasTwoFactorEnabled()) {
            RateLimiter::hit($key, 300);
            return redirect()->intended($this->getRedirectUrl($user));
        }

        $code = preg_replace('/[^a-zA-Z0-9]/', '', $request->code);
        if (empty($code)) {
            RateLimiter::hit($key, 300);
            return back()->withErrors(['code' => 'The provided two-factor authentication code was invalid.']);
        }

        if (strlen($code) > 6 && $this->verifyRecoveryCode($user, $code)) {
            session(['2fa_verified' => true]);
            RateLimiter::clear($key);
            return redirect()->intended($this->getRedirectUrl($user))
                ->with('success', 'Logged in using recovery code.');
        }

        if ($this->verifyTotpCode($user, $code)) {
            session(['2fa_verified' => true]);
            RateLimiter::clear($key);
            return redirect()->intended($this->getRedirectUrl($user))
                ->with('success', 'Two-factor authentication verified.');
        }

        RateLimiter::hit($key, 300);
        return back()->withErrors(['code' => 'The provided two-factor authentication code was invalid.']);
    }

    /**
     * @param $user
     * @return string
     */
    private function getRedirectUrl($user): string
    {
        if (!$user) {
            return '/login';
        }

        if ($user->role === 'admin') {
            return '/admin/dashboard';
        }

        return '/user/dashboard';
    }

    /**
     * @param $user
     * @param $code
     * @return bool
     */
    private function verifyTotpCode($user, $code): bool
    {
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        try {
            $secret = decrypt($user->two_factor_secret);
            return $this->twoFactorService->verifyCode($secret, $code);
        } catch (\Exception $e) {
            Log::error('Two-factor secret decryption failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param $user
     * @param $code
     * @return bool
     */
    private function verifyRecoveryCode($user, $code): bool
    {
        $recoveryCodes = $user->two_factor_recovery_codes;

        if (!$recoveryCodes || !is_array($recoveryCodes)) {
            return false;
        }

        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                array_splice($recoveryCodes, $index, 1);
                $user->update(['two_factor_recovery_codes' => $recoveryCodes]);
                return true;
            }
        }

        return false;
    }
}
