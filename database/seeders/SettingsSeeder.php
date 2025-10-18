<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generalSettings = [
            ['key' => 'site_name', 'value' => 'TokenHive ', 'type' => 'text', 'group' => 'general', 'label' => 'Site Name', 'description' => 'The name of your platform'],
            ['key' => 'site_logo', 'value' => '', 'type' => 'file', 'group' => 'general', 'label' => 'Site Logo', 'description' => 'Upload site logo image'],
            ['key' => 'site_favicon', 'value' => '', 'type' => 'file', 'group' => 'general', 'label' => 'Site Favicon', 'description' => 'Upload site favicon (16x16 or 32x32 pixels)'],
            ['key' => 'primary_color', 'value' => '#00032e', 'type' => 'color', 'group' => 'general', 'label' => 'Primary Theme Color', 'description' => 'Primary color for the theme'],
            ['key' => 'user_registration', 'value' => '1', 'type' => 'boolean', 'group' => 'general', 'label' => 'User Registration', 'description' => 'Allow new user registration'],
            ['key' => 'balance_transfer_charge','value' => '1','type' => 'number','group' => 'general','label' => 'Balance Transfer Charge','description' => 'Amount charged for transferring balance',],
            ['key' => 'default_currency', 'value' => 'USD', 'type' => 'text', 'group' => 'general', 'label' => 'Default Currency', 'description' => 'Default platform currency code (e.g., USD, EUR, GBP)'],
            ['key' => 'currency_symbol', 'value' => '$', 'type' => 'text', 'group' => 'general', 'label' => 'Currency Symbol', 'description' => 'Currency symbol for display (e.g., $, €, £)'],
            ['key' => 'referral_deposit_commission_rate', 'value' => '10', 'type' => 'text', 'group' => 'general', 'label' => 'Referral Deposit Commission Rate', 'description' => 'Percentage commission earned from referral deposits'],
            ['key' => 'mining_fee', 'value' => '10', 'type' => 'text', 'group' => 'general', 'label' => 'Mining Fee', 'description' => 'Fee applied during mining operations'],
            ['key' => 'mining_rate_multiplier', 'value' => '1', 'type' => 'text', 'group' => 'general', 'label' => 'Mining Rate Multiplier', 'description' => 'Multiplier to adjust the mining rate'],
            ['key' => 'default_timezone', 'value' => 'America/New_York', 'type' => 'text', 'group' => 'general', 'label' => 'Default Timezone', 'description' => 'Default platform timezone (e.g., America/New_York, Europe/London)'],
            ['key' => 'tawk_property_id', 'value' => '', 'type' => 'text', 'group' => 'general', 'label' => 'Tawk Property ID', 'description' => 'Tawk.to Property ID'],
            ['key' => 'tawk_widget_id', 'value' => '', 'type' => 'text', 'group' => 'general', 'label' => 'Tawk Widget ID', 'description' => 'Tawk.to Widget ID'],
            ['key' => 'trade_result', 'value' => 'automated', 'type' => 'select', 'group' => 'trading', 'label' => 'Trade Result', 'description' => 'Binary trade outcome']
        ];

        $emailSettings = [
            ['key' => 'mail_driver', 'value' => 'smtp', 'type' => 'text', 'group' => 'email', 'label' => 'Mail Driver', 'description' => 'Email service provider'],
            ['key' => 'mail_host', 'value' => 'smtp.hostinger.com', 'type' => 'text', 'group' => 'email', 'label' => 'Mail Host', 'description' => 'SMTP server host'],
            ['key' => 'mail_port', 'value' => '465', 'type' => 'text', 'group' => 'email', 'label' => 'Mail Port', 'description' => 'SMTP server port'],
            ['key' => 'mail_username', 'value' => 'support@apprizo.xyz', 'type' => 'text', 'group' => 'email', 'label' => 'Mail Username', 'description' => 'SMTP username'],
            ['key' => 'mail_password', 'value' => '####', 'type' => 'password', 'group' => 'email', 'label' => 'Mail Password', 'description' => 'SMTP password'],
            ['key' => 'mail_encryption', 'value' => 'ssl', 'type' => 'text', 'group' => 'email', 'label' => 'Mail Encryption', 'description' => 'Email encryption method'],
            ['key' => 'mail_from_address', 'value' => 'support@apprizo.xyz', 'type' => 'text', 'group' => 'email', 'label' => 'From Email', 'description' => 'Default sender email'],
            ['key' => 'mail_from_name', 'value' => 'TokenHive', 'type' => 'text', 'group' => 'email', 'label' => 'From Name', 'description' => 'Default sender name'],
        ];

        $smsSettings = [
            ['key' => 'sms_driver', 'value' => 'twilio', 'type' => 'text', 'group' => 'sms', 'label' => 'SMS Driver', 'description' => 'SMS service provider'],
            ['key' => 'twilio_sid', 'value' => '', 'type' => 'text', 'group' => 'sms', 'label' => 'API Key', 'description' => 'SMS service API key'],
            ['key' => 'twilio_token', 'value' => '', 'type' => 'password', 'group' => 'sms', 'label' => 'API Secret', 'description' => 'SMS service API secret'],
            ['key' => 'sms_from', 'value' => '+1234567890', 'type' => 'text', 'group' => 'sms', 'label' => 'From Number', 'description' => 'Default sender phone number'],
        ];

        $securitySettings = [
            ['key' => 'two_factor_auth', 'value' => '1', 'type' => 'boolean', 'group' => 'security', 'label' => 'Two Factor Auth', 'description' => 'Enable 2FA for users'],
            ['key' => 'login_attempts', 'value' => '5', 'type' => 'text', 'group' => 'security', 'label' => 'Max Login Attempts', 'description' => 'Maximum failed login attempts'],
            ['key' => 'session_timeout', 'value' => '60', 'type' => 'text', 'group' => 'security', 'label' => 'Session Timeout', 'description' => 'Session timeout in minutes'],
            ['key' => 'password_min_length', 'value' => '8', 'type' => 'text', 'group' => 'security', 'label' => 'Min Password Length', 'description' => 'Minimum password length'],
            ['key' => 'require_email_verification', 'value' => '1', 'type' => 'boolean', 'group' => 'security', 'label' => 'Email Verification', 'description' => 'Require email verification for new users'],
            ['key' => 'kyc_status', 'value' => '1', 'type' => 'boolean', 'group' => 'security', 'label' => 'KYC Verification', 'description' => 'Enable KYC (Know Your Customer) verification for users'],
        ];

        $seoSettings = [
            [
                'key' => 'meta_title',
                'value' => 'TokenHive – Professional Cryptocurrency Trading Platform with Mining Rewards',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Meta Title',
                'description' => 'SEO meta title for homepage'
            ],
            [
                'key' => 'meta_description',
                'value' => 'TokenHive – A complete cryptocurrency ecosystem with gamified mining, binary trading, ICO management, and multi-currency wallets.',
                'type' => 'textarea',
                'group' => 'seo',
                'label' => 'Meta Description',
                'description' => 'SEO meta description for homepage'
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'cryptocurrency, gamified mining, trading, ICO, token sales, crypto wallets, binary options',
                'type' => 'textarea',
                'group' => 'seo',
                'label' => 'Meta Keywords',
                'description' => 'SEO meta keywords (comma separated)'
            ],
            [
                'key' => 'og_title',
                'value' => 'TokenHive - Professional Crypto Platform with Mining, Trading & ICO',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Open Graph Title',
                'description' => 'Social media title'
            ],
            [
                'key' => 'og_description',
                'value' => 'Trade, mine, and invest securely with TokenHive – featuring gamified mining, binary trade options, ICO management, and multi-wallet support.',
                'type' => 'textarea',
                'group' => 'seo',
                'label' => 'Open Graph Description',
                'description' => 'Social media description'
            ],
            [
                'key' => 'og_image',
                'value' => '',
                'type' => 'file',
                'group' => 'seo',
                'label' => 'Open Graph Image',
                'description' => 'Social media sharing image (recommended 1200x630 pixels)'
            ],
            [
                'key' => 'google_analytics',
                'value' => '',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Google Analytics ID',
                'description' => 'Google Analytics tracking ID (GA4)'
            ],
        ];


        $allSettings = array_merge($generalSettings, $emailSettings, $smsSettings, $securitySettings, $seoSettings);
        foreach ($allSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $emailTemplates = [
            [
                'slug' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {site_name}!',
                'body' => '<h1>Welcome {user_name}!</h1><p>Thank you for joining {site_name}. We are excited to have you on board.</p><p>Your account has been successfully created and you can now start trading.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name'],
                'is_active' => true
            ],
            [
                'slug' => 'email_verification',
                'name' => 'Email Verification',
                'subject' => 'Verify Your Email - {site_name}',
                'body' => '<h1>Email Verification</h1><p>Hi {user_name},</p><p>Please click the link below to verify your email address:</p><p><a href="{verification_link}">Verify Email</a></p><p>This link will expire in 24 hours.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'verification_link'],
                'is_active' => true
            ],
            [
                'slug' => 'password_reset',
                'name' => 'Password Reset',
                'subject' => 'Reset Your Password - {site_name}',
                'body' => '<h1>Password Reset Request</h1><p>Hi {user_name},</p><p>You have requested to reset your password. Click the link below to reset it:</p><p><a href="{reset_link}">Reset Password</a></p><p>If you did not request this, please ignore this email.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'reset_link'],
                'is_active' => true
            ],
            [
                'slug' => 'deposit_confirmation',
                'name' => 'Deposit Confirmation',
                'subject' => 'Deposit Confirmed - {site_name}',
                'body' => '<h1>Deposit Confirmed</h1><p>Hi {user_name},</p><p>Your deposit of {currency_symbol}{amount} has been successfully processed.</p><p>Transaction ID: {transaction_id}</p><p>Your account balance has been updated.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'balance_transfer',
                'name' => 'Balance Transfer',
                'subject' => 'Balance Transfer Completed - {site_name}',
                'body' => '<h1>Balance Transfer Completed</h1><p>Hi {user_name},</p><p>Your balance transfer of {currency_symbol}{amount} has been completed successfully.</p><p>Transaction ID: {transaction_id}</p><p>The transferred amount should now be available in your account.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'withdrawal_request',
                'name' => 'Withdrawal Request',
                'subject' => 'Withdrawal Request Received - {site_name}',
                'body' => '<h1>Withdrawal Request Received</h1><p>Hi {user_name},</p><p>We have received your withdrawal request for {currency_symbol}{amount}.</p><p>Reference ID: {transaction_id}</p><p>Your request is being processed and you will receive another notification once it has been completed.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'withdrawal_approved',
                'name' => 'Withdrawal Approved',
                'subject' => 'Withdrawal Approved - {site_name}',
                'body' => '<h1>Withdrawal Approved</h1><p>Hi {user_name},</p><p>Great news! Your withdrawal request of {currency_symbol}{amount} has been approved and processed.</p><p>Transaction ID: {transaction_id}</p><p>Please allow 1-3 business days for the funds to appear in your account.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'withdrawal_rejected',
                'name' => 'Withdrawal Rejected',
                'subject' => 'Withdrawal Request Declined - {site_name}',
                'body' => '<h1>Withdrawal Request Declined</h1><p>Hi {user_name},</p><p>Unfortunately, your withdrawal request of {currency_symbol}{amount} has been declined.</p><p>Reference ID: {transaction_id}</p><p>Please contact our support team for more information about why this request was declined and next steps.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'subscription_purchased',
                'name' => 'Subscription Purchased',
                'subject' => 'Subscription Activated',
                'body' => '<h1>Subscription Successfully Activated!</h1><p>Hi {user_name},</p><p>Great news! Your subscription to the <strong>{plan_name}</strong> plan has been successfully activated.</p><p><strong>Subscription Details:</strong></p><ul><li>Plan: {plan_name}</li><li>Amount Paid: {currency_symbol}{amount}</li><li>Transaction ID: {transaction_id}</li><li>Activated On: {purchase_date}</li><li>Expires On: {expires_date}</li></ul><p>You now have access to all the premium features included in your plan. Start exploring and make the most of your subscription!</p><p>If you have any questions about your subscription, feel free to contact our support team.</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'plan_name', 'amount', 'currency_symbol', 'transaction_id', 'purchase_date', 'expires_date'],
                'is_active' => true
            ],
            [
                'slug' => 'subscription_expired',
                'name' => 'Subscription Expired',
                'subject' => 'Your Subscription Has Expired',
                'body' => '<h1>Subscription Expired</h1><p>Hi {user_name},</p><p>We wanted to let you know that your <strong>{plan_name}</strong> subscription has expired on {expired_date}.</p><p><strong>What happens now?</strong></p><ul><li>Your account will continue to work with basic features</li><li>Premium features are no longer accessible</li><li>You can reactivate your subscription anytime</li></ul><p>To continue enjoying all the premium features, you can renew your subscription by visiting your account dashboard.</p><p><a href="{renewal_link}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Renew Subscription</a></p><p>Thank you for being a valued member of {site_name}. We hope to see you back soon!</p><p>Best regards,<br>The {site_name} Team</p>',
                'variables' => ['user_name', 'site_name', 'plan_name', 'expired_date', 'renewal_link'],
                'is_active' => true
            ]
        ];

        foreach ($emailTemplates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }

        $smsTemplates = [
            [
                'slug' => 'welcome',
                'name' => 'Welcome SMS',
                'message' => 'Welcome {user_name}! Thank you for joining {site_name}. Your account is ready and you can start trading now.',
                'variables' => ['user_name', 'site_name'],
                'is_active' => true
            ],
            [
                'slug' => 'email_verification',
                'name' => 'Email Verification SMS',
                'message' => 'Hi {user_name}, please verify your email for {site_name}: {verification_link} (Link expires in 24 hours)',
                'variables' => ['user_name', 'site_name', 'verification_link'],
                'is_active' => true
            ],
            [
                'slug' => 'password_reset',
                'name' => 'Password Reset SMS',
                'message' => 'Hi {user_name}, reset your {site_name} password: {reset_link} Ignore if you didn\'t request this.',
                'variables' => ['user_name', 'site_name', 'reset_link'],
                'is_active' => true
            ],
            [
                'slug' => 'deposit_confirmation',
                'name' => 'Deposit Confirmation SMS',
                'message' => 'Hi {user_name}, your {currency_symbol}{amount} deposit is confirmed. Transaction ID: {transaction_id}. Account updated. - {site_name}',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'balance_transfer',
                'name' => 'Balance Transfer SMS',
                'message' => 'Hi {user_name}, your {currency_symbol}{amount} balance transfer is completed. Transaction ID: {transaction_id}. Funds are now available. - {site_name}',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'withdrawal_request',
                'name' => 'Withdrawal Request SMS',
                'message' => 'Hi {user_name}, we received your {currency_symbol}{amount} withdrawal request. Reference ID: {transaction_id}. Processing in progress. - {site_name}',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'withdrawal_approved',
                'name' => 'Withdrawal Approved SMS',
                'message' => 'Hi {user_name}, your {currency_symbol}{amount} withdrawal is approved and processed. Transaction ID: {transaction_id}. Allow 1-3 business days. - {site_name}',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'withdrawal_rejected',
                'name' => 'Withdrawal Rejected SMS',
                'message' => 'Hi {user_name}, your {currency_symbol}{amount} withdrawal request (ID: {transaction_id}) was declined. Contact support for details. - {site_name}',
                'variables' => ['user_name', 'site_name', 'amount', 'currency_symbol', 'transaction_id'],
                'is_active' => true
            ],
            [
                'slug' => 'subscription_purchased',
                'name' => 'Subscription Purchased SMS',
                'message' => 'Hi {user_name}, your {plan_name} subscription is now active! Amount: {currency_symbol}{amount}, Transaction: {transaction_id}, Expires: {expires_date}. Enjoy premium features! - {site_name}',
                'variables' => ['user_name', 'site_name', 'plan_name', 'amount', 'currency_symbol', 'transaction_id', 'expires_date'],
                'is_active' => true
            ],
            [
                'slug' => 'subscription_expired',
                'name' => 'Subscription Expired SMS',
                'message' => 'Hi {user_name}, your {plan_name} subscription expired on {expired_date}. You can renew anytime to restore premium features. Visit your dashboard to reactivate. - {site_name}',
                'variables' => ['user_name', 'site_name', 'plan_name', 'expired_date'],
                'is_active' => true
            ]
        ];

        foreach ($smsTemplates as $template) {
            SmsTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }

        $this->command->info('Settings seeder completed successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . count($allSettings) . ' system settings');
        $this->command->info('- ' . count($emailTemplates) . ' email templates');
        $this->command->info('- ' . count($smsTemplates) . ' SMS templates');
    }
}
