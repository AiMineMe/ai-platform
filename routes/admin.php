<?php

use App\Http\Controllers\Admin\AdminSecurityController;
use App\Http\Controllers\Admin\IcoPurchaseController;
use App\Http\Controllers\Admin\IcoSaleController;
use App\Http\Controllers\Admin\MiningAchievementController;
use App\Http\Controllers\Admin\MiningAnalyticsController;
use App\Http\Controllers\Admin\MiningCompetitionController;
use App\Http\Controllers\Admin\MiningLeaderboardController;
use App\Http\Controllers\Admin\MiningSessionController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\TradeSettingsController;
use App\Http\Controllers\Admin\TradesController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PurchaseHistoryController;
use App\Http\Controllers\Admin\SystemToolsController;
use App\Http\Controllers\Admin\KycVerificationController;
use App\Http\Controllers\Admin\LoginAttemptController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserSubscriptionsController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Admin\WithdrawalGatewayController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IcoTokenController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\WalletsController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('schedule/run', function () {
    Illuminate\Support\Facades\Artisan::call('schedule:run');
});

Route::middleware(['auth','2fa','role:admin', 'throttle:200,1', 'xss', 'security.headers', 'demo'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [DashboardController::class, 'profile']);
    Route::post('/profile/update', [DashboardController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [DashboardController::class, 'updatePassword'])->name('profile.password');

    Route::resource('subscription-plans', SubscriptionPlanController::class);
    Route::post('subscription-plans/{subscriptionPlan}/toggle', [SubscriptionPlanController::class, 'toggleStatus'])->name('subscription-plans.toggle');
    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    Route::get('/revenue/export', [RevenueController::class, 'export'])->name('revenue.export');
    Route::get('/user-subscriptions', [UserSubscriptionsController::class, 'index'])->name('user-subscriptions.index');
    Route::post('/user-subscriptions/{user}/cancel', [UserSubscriptionsController::class, 'cancel'])->name('user-subscriptions.cancel');

    Route::prefix('blogs')->name('blogs.')->group(function () {
        Route::get('/', [BlogController::class, 'index'])->name('index');
        Route::get('/create', [BlogController::class, 'create'])->name('create');
        Route::post('/', [BlogController::class, 'store'])->name('store');
        Route::get('/{blog}/edit', [BlogController::class, 'edit'])->name('edit');
        Route::patch('/{blog}', [BlogController::class, 'update'])->name('update');
        Route::delete('/{blog}', [BlogController::class, 'destroy'])->name('destroy');
    });

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    Route::post('/users/{user}/send-mail', [UserController::class, 'sendMail'])->name('users.send-mail');
    Route::get('/users/{user}/login-as', [UserController::class, 'loginAs'])->name('users.login-as');

    Route::get('/wallets', [WalletsController::class, 'index'])->name('wallets.index');
    Route::put('/wallets/{wallet}/status', [WalletsController::class, 'updateStatus'])->name('wallets.update-status');
    Route::put('/wallets/{wallet}/adjust-balance', [WalletsController::class, 'adjustBalance'])->name('wallets.adjust-balance');

    Route::get('/login-attempts', [LoginAttemptController::class, 'index'])->name('login-attempts.index');
    Route::post('/login-attempts/cleanup', [LoginAttemptController::class, 'clearOld'])->name('login-attempts.cleanup');

    Route::get('/kyc-verifications', [KycVerificationController::class, 'index'])->name('kyc-verifications.index');
    Route::put('/kyc-verifications/{kycVerification}/status', [KycVerificationController::class, 'updateStatus'])->name('kyc-verifications.update-status');
    Route::get('/kyc-verifications/{kycVerification}/download/{type}', [KycVerificationController::class, 'downloadDocument'])->name('kyc-verifications.download');
    Route::get('/kyc-verifications/{kycVerification}/download-all', [KycVerificationController::class, 'downloadAllDocuments'])->name('kyc-verifications.download-all');

    Route::resource('payment-gateways', PaymentGatewayController::class);

    Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
    Route::patch('/deposits/{deposit}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
    Route::patch('/deposits/{deposit}/reject', [DepositController::class, 'reject'])->name('deposits.reject');
    Route::post('/deposits/bulk-action', [DepositController::class, 'bulkAction'])->name('deposits.bulk-action');
    Route::get('/deposits/{deposit}/view/{fieldName}', [DepositController::class, 'viewFile'])->name('deposits.view');

    Route::resource('withdrawal-gateways', WithdrawalGatewayController::class);
    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::patch('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::patch('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
    Route::post('withdrawals/bulk-action', [WithdrawalController::class, 'bulkAction'])->name('withdrawals.bulk-action');

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
    });

    Route::get('mining-leaderboards', [MiningLeaderboardController::class, 'index'])->name('mining-leaderboards.index');
    Route::get('mining-analytics', [MiningAnalyticsController::class, 'index'])->name('mining-analytics.index');
    Route::resource('mining-sessions', MiningSessionController::class)->except(['create', 'store']);
    Route::patch('mining-sessions/{miningSession}/status', [MiningSessionController::class, 'updateStatus'])->name('mining-sessions.update-status');
    Route::patch('mining-sessions/{miningSession}/reset', [MiningSessionController::class, 'resetSession'])->name('mining-sessions.reset');
    Route::resource('mining-achievements', MiningAchievementController::class);
    Route::patch('mining-achievements/{miningAchievement}/toggle-status', [MiningAchievementController::class, 'toggleStatus'])->name('mining-achievements.toggle-status');
    Route::resource('mining-competitions', MiningCompetitionController::class);

    Route::resource('ico-tokens', IcoTokenController::class);
    Route::patch('ico-tokens/{icoToken}/toggle-featured', [IcoTokenController::class, 'toggleFeatured'])->name('ico-tokens.toggle-featured');
    Route::patch('ico-tokens/{icoToken}/status', [IcoTokenController::class, 'updateStatus'])->name('ico-tokens.update-status');
    Route::get('/purchase-history', [PurchaseHistoryController::class, 'index'])->name('purchase-history.index');
    Route::get('/ico-purchases', [IcoPurchaseController::class, 'index']);
    Route::get('/ico-sales', [IcoSaleController::class, 'index']);

    Route::get('/market-data', [CurrencyController::class, 'index']);

    Route::prefix('trade-settings')->name('trade-settings.')->group(function () {
        Route::get('/', [TradeSettingsController::class, 'index'])->name('index');
        Route::get('/create', [TradeSettingsController::class, 'create'])->name('create');
        Route::post('/', [TradeSettingsController::class, 'store'])->name('store');
        Route::get('/{tradeSetting}', [TradeSettingsController::class, 'show'])->name('show');
        Route::get('/{tradeSetting}/edit', [TradeSettingsController::class, 'edit'])->name('edit');
        Route::put('/{tradeSetting}', [TradeSettingsController::class, 'update'])->name('update');
        Route::delete('/{tradeSetting}', [TradeSettingsController::class, 'destroy'])->name('destroy');
        Route::get('/{tradeSetting}/stats', [TradeSettingsController::class, 'stats'])->name('stats');
    });

    Route::prefix('trades')->name('trades.')->group(function () {
        Route::get('/', [TradesController::class, 'index'])->name('index');
        Route::get('/{trade}', [TradesController::class, 'show'])->name('show');
        Route::post('/{trade}/settle', [TradesController::class, 'settle'])->name('settle');
        Route::post('/{trade}/cancel', [TradesController::class, 'cancel'])->name('cancel');
        Route::post('/bulk-action', [TradesController::class, 'bulkAction'])->name('bulk-action');
        Route::post('/set-result-setting', [TradesController::class, 'setResult']);
    });

    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::patch('settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
    Route::patch('settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email');
    Route::patch('settings/sms', [SettingsController::class, 'updateSms'])->name('settings.sms');
    Route::patch('settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security');
    Route::patch('settings/trading', [SettingsController::class, 'updateTrading'])->name('settings.trading');
    Route::patch('settings/email-template/{emailTemplate}', [SettingsController::class, 'updateEmailTemplate'])->name('settings.email-template');
    Route::patch('settings/sms-template/{smsTemplate}', [SettingsController::class, 'updateSmsTemplate'])->name('settings.sms-template');
    Route::patch('/settings/seo', [SettingsController::class, 'updateSeo'])->name('admin.settings.seo');
    Route::post('settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
    Route::post('settings/test-sms', [SettingsController::class, 'testSms'])->name('settings.test-sms');

    Route::get('/security/2fa', [AdminSecurityController::class, 'twoFactorIndex'])->name('security.2fa');
    Route::post('/security/2fa/enable', [AdminSecurityController::class, 'enableTwoFactor'])->name('security.2fa.enable');
    Route::post('/security/2fa/disable', [AdminSecurityController::class, 'disableTwoFactor'])->name('security.2fa.disable');
    Route::post('/security/2fa/verify', [AdminSecurityController::class, 'verifyTwoFactor'])->name('security.2fa.verify');

    Route::get('/cache-clear', [SystemToolsController::class, 'cacheClear'])->name('cache-clear');
    Route::post('/cache-clear', [SystemToolsController::class, 'executeCacheClear'])->name('cache-clear.execute');
    Route::get('/system-info', [SystemToolsController::class, 'systemInfo'])->name('system-info');
    Route::get('/system/cron', [SystemToolsController::class, 'cron'])->name('cron');

    Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
    Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
    Route::put('/languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
    Route::delete('/languages/{language}', [LanguageController::class, 'destroy'])->name('languages.destroy');
    Route::put('/languages/{language}/set-default', [LanguageController::class, 'setDefault'])->name('languages.set-default');

    Route::get('/languages/{language}/translations', [LanguageController::class, 'translations']);
    Route::put('/languages/{language}/translations/{translation}', [LanguageController::class, 'updateTranslation']);
    Route::post('/languages/{language}/translations', [LanguageController::class, 'storeTranslation']);
    Route::delete('/languages/{language}/translations/{translation}', [LanguageController::class, 'deleteTranslation']);

    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    Route::patch('menus/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])->name('menus.toggle-status');

    Route::get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter.index');
    Route::get('/newsletter/compose', [NewsletterController::class, 'compose'])->name('newsletter.compose');
    Route::post('/newsletter/send', [NewsletterController::class, 'send'])->name('newsletter.send');
    Route::delete('/newsletter/subscribers/{subscriber}', [NewsletterController::class, 'destroy'])->name('newsletter.destroy');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::patch('/contacts/{contact}/status', [ContactController::class, 'updateStatus'])->name('contacts.status');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::patch('/support-tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('support-tickets.update-status');
    Route::patch('/support-tickets/{ticket}/priority', [SupportTicketController::class, 'updatePriority'])->name('support-tickets.update-priority');
    Route::patch('/support-tickets/{ticket}/assign', [SupportTicketController::class, 'assign'])->name('support-tickets.assign');
    Route::post('/support-tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply');
    Route::delete('/support-tickets/{ticket}', [SupportTicketController::class, 'destroy'])->name('support-tickets.destroy');
    Route::patch('/support-tickets/bulk-status', [SupportTicketController::class, 'bulkUpdateStatus'])->name('support-tickets.bulk-status');
    Route::get('/support-tickets/attachments/{attachment}/download', [SupportTicketController::class, 'downloadAttachment'])->name('support-tickets.download-attachment');

    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::patch('/referrals/{referral}/status', [ReferralController::class, 'updateStatus'])->name('referrals.update-status');
    Route::patch('/referrals/bulk-status', [ReferralController::class, 'bulkUpdateStatus'])->name('referrals.bulk-status');

    Route::get('/system/update', [SystemUpdateController::class, 'index'])->name('system.update');
    Route::post('/system/migrate', [SystemUpdateController::class, 'systemMigrate'])->name('system.migrate');
});
