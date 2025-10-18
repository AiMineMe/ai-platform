<?php

namespace App\Http\Middleware;

use App\Concerns\UploadedFile;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\Wallet;
use App\Services\DefaultImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use UploadedFile;

    // Add settings cache to prevent multiple Setting::get() calls
    private $settings = null;
    private $currentLanguage = null;
    private $userWallets = null;

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $this->loadSettings();

        $logo = $this->getSetting('site_logo', '');
        $favicon = $this->getSetting('site_favicon', '');

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'message' => $request->session()->get('message'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'role' => $user->role,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'wallets' => $this->getUserWallets($user->id),
                    'avatar_url' => $user->avatar ? asset('assets/files/'.$user->avatar) : null,
                    'initials' => $this->getUserInitials($user->name),
                ] : null,
            ],
            'currencySymbol' => $this->getSetting('currency_symbol', '$'),
            'currencyName' => $this->getSetting('default_currency', 'USD'),
            'appName' => $this->getSetting('site_name', 'App Name'),
            'primaryColor' => $this->getSetting('primary_color', '#1f2937'),
            'logoUrl' => $logo ? $this->fullPath($logo) : DefaultImageService::getImageUrl(null, 'logo', 200, 80),
            'faviconUrl' => $favicon ? $this->fullPath($favicon) : DefaultImageService::getImageUrl(null, 'favicon', 16, 16),
            'languages' => $this->getLanguagesData(),
            'currentLanguage' => $this->getCurrentLanguage($request),
            'translations' => $this->getCurrentTranslations($request),
            'navigationItems' => $this->getMenu(),
            'seo' => $this->getSeoData(),
            'cryptoPrices' => $this->getCryptoPrices(),
            'kyc_status' => Setting::get('kyc_status', true),
            'tawk' => [
                'property_id' => $this->getSetting('tawk_property_id', ''),
                'widget_id' => $this->getSetting('tawk_widget_id', ''),
            ],
        ]);
    }

    /**
     * Load all settings at once to prevent multiple Setting::get() calls
     */
    private function loadSettings(): void
    {
        if ($this->settings === null) {
            $this->settings = Cache::remember('all_frontend_settings', 600, function () {
                return Setting::pluck('value', 'key')->toArray();
            });
        }
    }

    /**
     * Get setting value from cached settings
     */
    private function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * @param string $name
     * @return string
     */
    private function getUserInitials(string $name): string
    {
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
        } elseif (count($words) === 1) {
            $firstWord = $words[0];
            if (strlen($firstWord) >= 2) {
                return strtoupper(substr($firstWord, 0, 2));
            } else {
                return strtoupper(str_repeat(substr($firstWord, 0, 1), 2));
            }
        }

        return 'AD';
    }

    /**
     * @return array
     */
    private function getCryptoPrices(): array
    {
        return Cache::remember('crypto_prices', 300, function () {
            $currencies = Currency::whereIn('symbol', ['BTC', 'ETH'])
                ->pluck('current_price', 'symbol')
                ->toArray();

            return [
                'btc' => isset($currencies['BTC']) ? floatval($currencies['BTC']) : 109195,
                'eth' => isset($currencies['ETH']) ? floatval($currencies['ETH']) : 2570,
            ];
        });
    }

    /**
     * @return array
     */
    private function getSeoData(): array
    {
        $ogImage = $this->getSetting('og_image', '');
        return [
            'meta_title' => $this->getSetting('meta_title', 'TokenHive - Professional Trading Platform with Gamified Mining & Trading'),
            'meta_description' => $this->getSetting('meta_description', 'TokenHive - Comprehensive cryptocurrency ecosystem with gamified mining, binary trading, ICO platform & multi-currency wallets.'),
            'meta_keywords' => $this->getSetting('meta_keywords', 'cryptocurrency, gamified mining, binary trading, ICO, token sales, crypto wallet'),
            'og_title' => $this->getSetting('og_title', 'TokenHive - Professional Crypto Platform with Mining, Trading & ICO'),
            'og_description' => $this->getSetting('og_description', 'Trade, mine, and invest with TokenHive — a secure and advanced crypto platform featuring gamified mining, binary trade options, ICO management, and multi-wallet support.'),
            'og_image_url' => $ogImage ? $this->fullPath($ogImage) : null,
            'google_analytics' => $this->getSetting('google_analytics', ''),
        ];
    }

    /**
     * @return mixed
     */
    private function getMenu(): mixed
    {
        return Cache::remember('frontend_menu', 3600, function () {
            return Menu::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'identifier', 'menu_name', 'path', 'components'])
                ->map(function ($menu) {
                    return [
                        'id' => $menu->identifier,
                        'label' => $menu->menu_name,
                        'path' => $menu->path,
                        'components' => $menu->components,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * @param $userId
     * @return string[]|null
     */
    private function getUserWallets($userId): ?array
    {
        if (!$userId) return null;

        if ($this->userWallets === null) {
            $wallets = Wallet::where('user_id', $userId)
                ->whereIn('type', ['main', 'trade'])
                ->pluck('balance', 'type')
                ->toArray();

            $this->userWallets = [
                'main_balance' => isset($wallets['main']) ? number_format($wallets['main'], 2) : '0.00',
                'trade_balance' => isset($wallets['trade']) ? number_format($wallets['trade'], 2) : '0.00',
            ];
        }

        return $this->userWallets;
    }

    /**
     * @return array
     */
    private function getLanguagesData(): array
    {
        return Cache::remember('frontend_languages', 3600, function () {
            return Language::where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get([
                    'code',
                    'name',
                    'native_name',
                    'flag',
                    'is_default',
                    'is_active'
                ])
                ->map(function ($lang) {
                    return [
                        'code' => $lang->code,
                        'name' => $lang->name,
                        'nativeName' => $lang->native_name,
                        'flag' => $lang->flag,
                        'is_default' => $lang->is_default,
                        'is_active' => $lang->is_active,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getCurrentLanguage(Request $request): array
    {
        if ($this->currentLanguage === null) {
            $languageCode = '';
            if (session()->has('language')) {
                $languageCode = session('language');
            }

            $language = Language::where('code', $languageCode)
                ->where('is_active', true)
                ->first(['code', 'name', 'native_name', 'flag']);

            if (!$language) {
                $language = Language::where('is_default', true)
                    ->first(['code', 'name', 'native_name', 'flag']);
            }

            $this->currentLanguage = [
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'flag' => $language->flag,
            ];
        }

        return $this->currentLanguage;
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getCurrentTranslations(Request $request): array
    {
        $currentLanguage = $this->getCurrentLanguage($request);
        $languageCode = $currentLanguage['code'];
        $cacheKey = "translations_{$languageCode}";

        return Cache::remember($cacheKey, 3600, function () use ($languageCode) {
            $language = Language::where('code', $languageCode)->first();

            if (!$language) {
                return [];
            }

            $translations = $language->getTranslationsArray();
            if (empty($translations)) {
                $filePath = resource_path("js/lang/{$languageCode}.json");
                if (file_exists($filePath)) {
                    $jsonContent = file_get_contents($filePath);
                    $fileTranslations = json_decode($jsonContent, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($fileTranslations)) {
                        return $fileTranslations;
                    }
                }
            }

            return $translations;
        });
    }
}
