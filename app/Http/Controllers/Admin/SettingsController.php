<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\UploadedFile;
use App\Http\Controllers\Controller;
use App\Mail\GlobalMail;
use App\Models\Setting;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Twilio\Exceptions\TwilioException;

class SettingsController extends Controller
{
    use UploadedFile;

    public function __construct(protected readonly SmsService $smsService)
    {

    }

    /**
     * @return Response
     */
    public function index(): Response
    {
        $logo = Setting::get('site_logo', '');
        $favicon = Setting::get('site_favicon', '');
        $ogImage = Setting::get('og_image', '');

        return Inertia::render('Admin/Settings/Index', [
            'generalSettings' => Setting::getByGroup('general'),
            'emailSettings' => Setting::getByGroup('email'),
            'smsSettings' => Setting::getByGroup('sms'),
            'securitySettings' => Setting::getByGroup('security'),
            'seoSettings' => Setting::getByGroup('seo'),
            'tradingSettings' => Setting::getByGroup('trading'),
            'emailTemplates' => EmailTemplate::all(),
            'smsTemplates' => SmsTemplate::all(),
            'site_logo_url' => $logo ? $this->fullPath($logo) : null,
            'site_favicon_url' => $favicon ? $this->fullPath($favicon) : null,
            'seo_og_image_url' => $ogImage ? $this->fullPath($ogImage) : null,
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateGeneral(Request $request): RedirectResponse
    {
        try {
            $rules = [
                'site_name' => 'required|string|max:255',
                'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'site_favicon' => 'nullable|image|mimes:ico,png,gif|max:1024',
                'primary_color' => 'required|string',
                'balance_transfer_charge' => 'required|numeric|min:0',
                'default_currency' => 'required|string|max:3',
                'currency_symbol' => 'required|string|max:5',
                'default_timezone' => 'required|string|max:255',
                'referral_deposit_commission_rate' => 'required|numeric|min:0|max:100',
                'mining_fee' => 'required|numeric|min:0|max:100',
                'mining_rate_multiplier' => 'required|numeric|min:0.1|max:10',
                'tawk_property_id' => 'nullable|string|max:255',
                'tawk_widget_id' => 'nullable|string|max:255',
            ];

            Cache::forget('all_frontend_settings');
            $validated = $request->validate($rules);
            $validated['maintenance_mode'] = $request->boolean('maintenance_mode');
            $validated['user_registration'] = $request->boolean('user_registration');
            $validated['site_logo'] =  Setting::get('site_logo', '');
            $validated['site_favicon'] = Setting::get('site_favicon', '');

            if ($request->hasFile('site_logo')) {
                $logoFileName = Setting::get('site_logo', '');
                $logoPath = $this->move($request->file('site_logo'), null, $logoFileName);
                $validated['site_logo'] = $logoPath;
            }

            if ($request->hasFile('site_favicon')) {
                $faviconName = Setting::get('site_favicon', '');
                $faviconPath = $this->move($request->file('site_favicon'), null, $faviconName);
                $validated['site_favicon'] = $faviconPath;
            }

            foreach ($validated as $key => $value) {
                Setting::set(
                    $key,
                    $value,
                    is_bool($value) ? 'boolean' : (in_array($key, ['site_logo', 'site_favicon']) ? 'file' : 'text'),
                    ucwords(str_replace('_', ' ', $key)),
                    'general'
                );
            }

            return back()->with('success', 'General settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating general settings: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update general settings. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        try {
            $settings = $request->validate([
                'mail_driver' => 'required|in:smtp,mailgun,ses',
                'mail_host' => 'required_if:mail_driver,smtp|string',
                'mail_port' => 'required_if:mail_driver,smtp|integer',
                'mail_username' => 'required_if:mail_driver,smtp|string',
                'mail_password' => 'required_if:mail_driver,smtp|string',
                'mail_encryption' => 'nullable|in:tls,ssl',
                'mail_from_address' => 'required|email',
                'mail_from_name' => 'required|string',
            ]);

            Cache::forget('all_frontend_settings');
            foreach ($settings as $key => $value) {
                Setting::set(
                    $key,
                    $value,
                    'text',
                    ucwords(str_replace('_', ' ', $key)),
                    'email'
                );
            }

            return back()->with('success', 'Email settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating email settings: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update email settings. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateSms(Request $request): RedirectResponse
    {
        try {
            $settings = $request->validate([
                'sms_driver' => 'required|in:twilio,nexmo,aws',
                'sms_api_key' => 'required|string',
                'sms_api_secret' => 'required|string',
                'sms_from' => 'required|string',
            ]);

            foreach ($settings as $key => $value) {
                Setting::set(
                    $key,
                    $value,
                    'text',
                    ucwords(str_replace('_', ' ', $key)),
                    'sms'
                );
            }

            return back()->with('success', 'SMS settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating SMS settings: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update SMS settings. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateSecurity(Request $request): RedirectResponse
    {
        try {
            $rules = [
                'login_attempts' => 'required|integer|min:3|max:10',
                'session_timeout' => 'required|integer|min:15|max:1440',
                'password_min_length' => 'required|integer|min:6|max:32',
            ];

            Cache::forget('all_frontend_settings');
            $validated = $request->validate($rules);
            $validated['two_factor_auth'] = $request->boolean('two_factor_auth');
            $validated['require_email_verification'] = $request->boolean('require_email_verification');
            $validated['kyc_status'] = $request->boolean('kyc_status');

            foreach ($validated as $key => $value) {
                Setting::set(
                    $key,
                    $value,
                    is_bool($value) ? 'boolean' : 'text',
                    ucwords(str_replace('_', ' ', $key)),
                    'security'
                );
            }

            return back()->with('success', 'Security settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating security settings: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update security settings. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateSeo(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'meta_title' => 'required|string|max:255',
                'meta_description' => 'required|string|max:255',
                'meta_keywords' => 'nullable|string',
                'og_title' => 'required|string|max:255',
                'og_description' => 'required|string|max:255',
                'og_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'twitter_card' => 'required|string|max:255',
                'google_analytics' => 'nullable|string|max:255',
                'google_tag_manager' => 'nullable|string|max:255',
                'facebook_pixel' => 'nullable|string|max:255',
            ]);

            Cache::forget('all_frontend_settings');
            $validated['og_image'] = Setting::get('og_image', '');

            if ($request->hasFile('og_image')) {
                $ogImageName = Setting::get('og_image', '');
                $ogImagePath = $this->move($request->file('og_image'), null, $ogImageName);
                $validated['og_image'] = $ogImagePath;
            }

            foreach ($validated as $key => $value) {
                Setting::set(
                    $key,
                    $value,
                    $key === 'og_image' ? 'file' : 'text',
                    ucwords(str_replace('_', ' ', $key)),
                    'seo'
                );
            }

            return back()->with('success', 'SEO settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating SEO settings: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update SEO settings. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param EmailTemplate $emailTemplate
     * @return RedirectResponse
     */
    public function updateEmailTemplate(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'is_active' => 'boolean'
            ]);

            $emailTemplate->update($validated);
            return back()->with('success', 'Email template updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating email template: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update email template. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param SmsTemplate $smsTemplate
     * @return RedirectResponse
     */
    public function updateSmsTemplate(Request $request, SmsTemplate $smsTemplate): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'message' => 'required|string',
                'is_active' => 'boolean'
            ]);

            $smsTemplate->update($validated);
            return back()->with('success', 'SMS template updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating SMS template: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update SMS template. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function testEmail(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'message' => 'required|max:255',
            ]);

            $user = User::where('role', 'admin')->first();
            Mail::to($request->input('email'))->send(new GlobalMail($user, 'Test Email', $request->input('message')));

            return back()->with('success', 'Test email sent successfully.');
        } catch (\Exception $e) {
            Log::error('Error sending test email: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to send test email. Please check your email settings.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function testSms(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'message' => 'nullable|string'
            ]);

            $this->smsService->sendTestSms($request->input('phone'), $request->input('message'));

            return back()->with('success', 'Test SMS sent successfully.');
        } catch (TwilioException $e) {
            Log::error('Twilio error sending test SMS: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to send test SMS. Twilio error: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Error sending test SMS: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to send test SMS. Please check your SMS settings.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateTrading(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'trading_enabled' => 'boolean',
                'min_trade_amount' => 'required|numeric|min:0',
                'max_trade_amount' => 'required|numeric|min:0',
                'default_trade_duration' => 'required|integer|min:1',
                'trading_commission' => 'required|numeric|min:0|max:100',
            ]);

            Cache::forget('all_frontend_settings');

            foreach ($validated as $key => $value) {
                Setting::set(
                    $key,
                    $value,
                    is_bool($value) ? 'boolean' : 'text',
                    ucwords(str_replace('_', ' ', $key)),
                    'trading'
                );
            }

            return back()->with('success', 'Trading settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating trading settings: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update trading settings. Please try again.']);
        }
    }
}
