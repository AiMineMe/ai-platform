<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminSecurityController extends Controller
{

    public function __construct(protected readonly  TwoFactorService $twoFactorService){

    }

    /**
     * @return Response
     */
    public function twoFactorIndex(): Response
    {
        try {
            $user = Auth::user();

            $secret = null;
            $qrCode = null;

            if (!$user->hasTwoFactorEnabled()) {
                $secret = $this->twoFactorService->generateSecretKey();
                $qrCode = $this->twoFactorService->generateQRCodeUrl(
                    config('app.name'),
                    $user->email,
                    $secret
                );
            }

            return Inertia::render('Admin/Security/TwoFactor', [
                'is_enabled' => $user->hasTwoFactorEnabled(),
                'qr_code' => $qrCode,
                'secret' => $secret,
                'recovery_codes' => $user->hasTwoFactorEnabled() ? $user->two_factor_recovery_codes : null,
            ]);

        } catch (\Exception $e) {
            return Inertia::render('Admin/Security/TwoFactor', [
                'is_enabled' => false,
                'qr_code' => null,
                'secret' => null,
                'recovery_codes' => null,
                'error' => 'Unable to load security settings. Please try again.'
            ]);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'secret' => 'required|string|min:16|max:32',
            'code' => 'required|string|size:6|regex:/^[0-9]+$/',
        ]);

        try {
            $user = Auth::user();
            if ($user->hasTwoFactorEnabled()) {
                return redirect()->back()->withErrors(['error' => 'Two-factor authentication is already enabled.']);
            }

            $valid = $this->twoFactorService->verifyCode($request->secret, $request->code);
            if (!$valid) {
                return redirect()->back()->withErrors(['error' => 'Invalid verification code. Please try again.']);
            }

            $user->update([
                'two_factor_secret' => encrypt($request->secret),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => $this->twoFactorService->generateRecoveryCodes(),
            ]);

            return redirect()->back()->with('success', 'Two-factor authentication has been enabled successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to enable two-factor authentication. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        try {
            $user = Auth::user();
            if (!$user->hasTwoFactorEnabled()) {
                return redirect()->back()->withErrors(['error' => 'Two-factor authentication is not enabled.']);
            }

            if (!Hash::check($request->password, $user->password)) {
                return redirect()->back()->withErrors(['error' => 'Invalid password. Please try again.']);
            }

            $user->update([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
            ]);

            return redirect()->back()->with('success', 'Two-factor authentication has been disabled.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to disable two-factor authentication. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]+$/',
        ]);

        try {
            $user = Auth::user();
            if (!$user->hasTwoFactorEnabled()) {
                return redirect()->back()->withErrors(['error' => 'Two-factor authentication is not enabled.']);
            }

            $secret = decrypt($user->two_factor_secret);
            $valid = $this->twoFactorService->verifyCode($secret, $request->code);

            if ($valid) {
                return redirect()->back()->with('success', 'Code verified successfully.');
            } else {
                return redirect()->back()->withErrors(['error' => 'Invalid verification code.']);
            }

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Verification failed. Please try again.']);
        }
    }
}
