<?php

namespace App\Services;

use App\Mail\GlobalMail;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateService
{
    public static function sendTemplateEmail(string $templateSlug, User $user, array $variables = []): bool
    {

        try {
            MailConfigService::configure();

            $template = EmailTemplate::where('slug', $templateSlug)->where('is_active', true)->first();

            if (!$template) {
                Log::error("Email template not found: {$templateSlug}");
                return false;
            }

            $variables = array_merge([
                'site_name' => Setting::get('site_name', 'TokenHive'),
                'currency_symbol' => Setting::get('currency_symbol', '$'),
                'contact_email' => Setting::get('contact_email', 'support@gmail.com'),
            ], $variables);

            $subject = self::replaceVariables($template->subject, $variables);
            $body = self::replaceVariables($template->body, $variables);

            Mail::send(new GlobalMail($user, $subject, $body));

            return true;
        } catch (\Exception $e) {
            Log::error('Email send failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }
}
