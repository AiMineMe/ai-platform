<?php

use App\Http\Controllers\Payment\NowPaymentsWebhookController;
use App\Http\Controllers\User\MiningController;
use App\Http\Controllers\User\PortfolioController;
use App\Http\Controllers\User\ReferralController;
use App\Http\Controllers\User\SubscriptionController;
use App\Http\Controllers\User\SupportTicketController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\User\DepositController;
use App\Http\Controllers\User\WithdrawalController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\User\TradesController;
use App\Http\Controllers\User\SettingsController;
use App\Http\Controllers\User\IcoController;
use App\Http\Controllers\User\SecurityController;
use App\Http\Controllers\User\TransactionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserDashboardController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\User\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['security.headers'])->group(function () {
    Route::middleware(['web'])->group(function () {
        Route::post('language/change', [LanguageController::class, 'changeLanguage'])->name('language.change');
        Route::get('api/languages', [LanguageController::class, 'getLanguages'])->name('api.languages');
        Route::get('api/languages/{code}/translations', [LanguageController::class, 'getTranslations'])->name('api.translations');
    });

    Route::get('/', [FrontendController::class, 'index']);
    Route::get('/privacy-policy', [FrontendController::class, 'privacy']);
    Route::get('/terms', [FrontendController::class, 'terms']);
    Route::get('/cookies', [FrontendController::class, 'cookies']);
    Route::get('/contact', [ContactController::class, 'contacts']);
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
    Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
    Route::get('/blog/{blog:slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/page/{path}', [FrontendController::class, 'dynamicPage'])->name('dynamic.page');
    Route::get('/default-image/{type?}/{width?}/{height?}', function($type = 'default', $width = 400, $height = 400) {
        return App\Services\DefaultImageService::generate($type, $width, $height);
    })->name('default.image');

    Route::post('/webhook/nowpayments', [NowPaymentsWebhookController::class, 'handleCallback'])->name('nowpayments.callback');
    Route::middleware(['guest', 'throttle:10,1', 'xss'])->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::get('/reset-password', [AuthController::class, 'showLogin'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware(['throttle:6,1'])->name('verification.send');

    Route::middleware(['auth', 'xss'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/auth/2fa/verify', [TwoFactorController::class, 'show'])->name('auth.2fa.verify');
        Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verify'])->name('auth.2fa.verify.post');
    });

    Route::middleware(['auth', '2fa', 'role:user', 'throttle:300,1', 'xss'])->prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
        Route::get('trading/market', [TradesController::class, 'market'])->name('trades.market');
        Route::get('trading/live', [TradesController::class, 'index'])->name('trades.index');
        Route::post('trading', [TradesController::class, 'store'])->name('trades.store');
        Route::get('trading/history', [TradesController::class, 'history'])->name('trades.history');
        Route::post('trading/{trade}/cancel', [TradesController::class, 'cancel'])->name('trades.cancel');

        Route::get('investment/ico-tokens', [IcoController::class, 'index'])->name('ico.index');
        Route::post('investment/ico-purchase', [IcoController::class, 'purchase'])->name('ico.purchase');
        Route::get('investment/purchases', [IcoController::class, 'history'])->name('ico.history');
        Route::get('/investment/portfolio', [PortfolioController::class, 'index']);
        Route::post('/investment/portfolio/sell', [PortfolioController::class, 'sell']);

        Route::get('/wallets', [WalletController::class, 'index'])->name('wallet.index');
        Route::post('/wallet/transfer', [WalletController::class, 'transfer'])->name('wallet.transfer');
        Route::post('/wallet/transfer-to-user', [WalletController::class, 'transferToUser'])->name('wallet.transfer-to-user');
        Route::post('/wallet/search-users', [WalletController::class, 'searchUsers'])->name('wallet.search-users');
        Route::get('/wallet/transactions', [TransactionController::class, 'index'])->name('index');
        Route::get('/wallet/deposit', [DepositController::class, 'index']);
        Route::post('/wallet/deposit', [DepositController::class, 'store']);
        Route::get('/wallet/deposit/{trx}', [DepositController::class, 'details']);
        Route::post('/wallet/deposit/{trx}/cancel', [DepositController::class, 'cancel']);

        // Add NowPayments success route here
        Route::get('/deposits/success/{trx}', [DepositController::class, 'success'])->name('deposits.success');
        Route::get('/wallet/deposit/{deposit}/download/{fieldName}', [DepositController::class, 'downloadFile'])->name('deposits.download')->middleware('throttle:10,1');
        Route::get('wallet/deposit/{deposit}/view/{fieldName}', [DepositController::class, 'viewFile'])->name('deposits.view')->middleware('throttle:20,1');
        Route::get('/wallet/withdraw', [WithdrawalController::class, 'index']);
        Route::post('/wallet/withdraw', [WithdrawalController::class, 'store']);
        Route::get('/wallet/withdraw/{withdrawal}', [WithdrawalController::class, 'show']);
        Route::patch('/wallet/withdraw/{withdrawal}/cancel', [WithdrawalController::class, 'cancel']);

        Route::prefix('security')->name('security.')->group(function () {
            Route::get('/2fa', [SecurityController::class, 'twoFactorIndex'])->name('2fa');
            Route::post('/2fa/enable', [SecurityController::class, 'enableTwoFactor'])->name('2fa.enable');
            Route::post('/2fa/disable', [SecurityController::class, 'disableTwoFactor'])->name('2fa.disable');
            Route::post('/2fa/verify', [SecurityController::class, 'verifyTwoFactor'])->name('2fa.verify');
            Route::get('/password', [SecurityController::class, 'passwordIndex'])->name('password');
            Route::post('/password/update', [SecurityController::class, 'updatePassword'])->name('password.update');
            Route::get('/sessions', [SecurityController::class, 'loginHistoryIndex'])->name('history');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/profile', [SettingsController::class, 'profileIndex'])->name('profile');
            Route::post('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');
            Route::delete('/profile/avatar', [SettingsController::class, 'removeAvatar'])->name('profile.avatar.remove');
            Route::prefix('kyc')->name('kyc.')->group(function () {
                Route::get('/', [SettingsController::class, 'kycIndex'])->name('index');
                Route::post('/submit', [SettingsController::class, 'submitKyc'])->name('submit');
                Route::post('/resubmit', [SettingsController::class, 'resubmitKyc'])->name('resubmit');
            });
        });

        Route::get('/mining', [MiningController::class, 'index'])->name('mining.index');
        Route::post('/mining/start', [MiningController::class, 'startMining'])->name('mining.start');
        Route::post('/mining/stop', [MiningController::class, 'stopMining'])->name('mining.stop');
        Route::post('/mining/claim', [MiningController::class, 'claimTokens'])->name('mining.claim');
        Route::post('/mining/achievements/{achievement}/claim', [MiningController::class, 'claimAchievement'])->name('mining.achievements.claim');
        Route::post('/mining/competitions/{competition}/join', [MiningController::class, 'joinCompetition'])->name('mining.competitions.join');

        Route::prefix('support-tickets')->name('support-tickets.')->group(function () {
            Route::get('/list', [SupportTicketController::class, 'index'])->name('index');
            Route::get('/create', [SupportTicketController::class, 'create'])->name('create');
            Route::post('/', [SupportTicketController::class, 'store'])->name('store');
            Route::get('/{ticket}', [SupportTicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');
            Route::get('/attachments/{attachment}/download', [SupportTicketController::class, 'downloadAttachment'])->name('attachments.download');
        });

        Route::get('/referral/dashboard', [ReferralController::class, 'dashboard'])->name('referral.dashboard');
        Route::get('/referral/commissions', [ReferralController::class, 'commissions'])->name('referral.commissions');

        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscribe/{plan}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::post('/cancel-subscription', [SubscriptionController::class, 'cancel'])->name('cancel.subscription');
    });
});
