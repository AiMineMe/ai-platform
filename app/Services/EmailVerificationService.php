<?php

namespace App\Services;

use App\Enums\User\Status;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    public static function sendVerificationEmail(User $user): bool
    {
        if (!Setting::get('require_email_verification', true)) {
            return true;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        return EmailTemplateService::sendTemplateEmail('email_verification', $user, [
            'user_name' => $user->name,
            'verification_link' => $verificationUrl,
        ]);
    }

    public static function isVerificationRequired(): bool
    {
        return (bool) Setting::get('require_email_verification', true);
    }

    public static function markEmailAsVerified(User $user): bool
    {
        return $user->update(['email_verified_at' => now(), 'status' => Status::ACTIVE->value]);
    }
}
