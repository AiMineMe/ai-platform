<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        try {
            $languages = Language::withCount('translations')
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get();

            return Inertia::render('Admin/Language/Index', [
                'languages' => $languages,
            ]);

        } catch (\Exception $e) {
            return Inertia::render('Admin/Language/Index', [
                'languages' => [],
                'error' => 'Unable to load languages. Please try again.'
            ]);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:255',
            'native_name' => 'required|string|max:255',
            'flag' => 'required|string|max:10',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        try {
            Cache::forget('frontend_languages');
            $language = Language::create($request->all());
            $this->createBaseTranslations($language);
            return redirect()->back()->with('success', 'Language created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create language. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param Language $language
     * @return RedirectResponse
     */
    public function update(Request $request, Language $language): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:languages,code,' . $language->id,
            'name' => 'required|string|max:255',
            'native_name' => 'required|string|max:255',
            'flag' => 'required|string|max:10',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        try {
            Cache::forget('frontend_languages');
            $language->update($request->all());
            return redirect()->back()->with('success', 'Language updated successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update language. Please try again.']);
        }
    }

    /**
     * @param Language $language
     * @return RedirectResponse
     */
    public function destroy(Language $language): RedirectResponse
    {
        try {
            if ($language->is_default) {
                return redirect()->back()->withErrors(['error' => 'Cannot delete default language.']);
            }

            $language->translations()->delete();
            $language->delete();

            return redirect()->back()->with('success', 'Language deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete language. Please try again.']);
        }
    }

    public function translations(Request $request, Language $language): Response
    {
        try {
            $search = $request->get('search', '');
            $status = $request->get('status', 'all');
            $component = $request->get('component', 'hero');
            $perPage = $request->get('per_page', 20);
            $query = Translation::where('language_id', $language->id);

            if ($component === 'dynamic') {
                $query->where('is_dynamic', 1);
            } else {
                $componentKeys = $this->getComponentKeys($component);
                if (!empty($componentKeys)) {
                    $query->whereIn('key', $componentKeys);
                } else {
                    $query->where('id', 0);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('value', 'like', "%{$search}%");
                });
            }

            if ($status !== 'all') {
                switch ($status) {
                    case 'translated':
                        $query->whereNotNull('value')->where('value', '!=', '');
                        break;
                    case 'untranslated':
                        $query->where(function($q) {
                            $q->whereNull('value')->orWhere('value', '');
                        });
                        break;
                }
            }

            $translations = $query->orderBy('key')
                ->paginate($perPage)
                ->withQueryString();

            $statsQuery = Translation::where('language_id', $language->id);
            if (!empty($componentKeys)) {
                $statsQuery->whereIn('key', $componentKeys);
            }

            $totalTranslations = $statsQuery->count();
            $translatedCount = $statsQuery->whereNotNull('value')
                ->where('value', '!=', '')
                ->count();

            $stats = [
                'total' => $totalTranslations,
                'translated' => $translatedCount,
                'untranslated' => $totalTranslations - $translatedCount,
                'progress' => $totalTranslations > 0 ? round(($translatedCount / $totalTranslations) * 100) : 0
            ];

            return Inertia::render('Admin/Language/Translations', [
                'language' => $language,
                'translations' => $translations,
                'stats' => $stats,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'component' => $component,
                    'per_page' => $perPage
                ]
            ]);

        } catch (\Exception $e) {
            return Inertia::render('Admin/Language/Translations', [
                'language' => $language,
                'translations' => [],
                'stats' => ['total' => 0, 'translated' => 0, 'untranslated' => 0, 'progress' => 0],
                'error' => 'Unable to load translations. Please try again.'
            ]);
        }
    }

    /**
     * @param Request $request
     * @param Language $language
     * @param $translationId
     * @return RedirectResponse
     */
    public function updateTranslation(Request $request, Language $language, $translationId): RedirectResponse
    {
        $request->validate([
            'value' => 'nullable|string',
        ]);

        try {
            Cache::forget('frontend_languages');
            $translation = Translation::where('language_id', $language->id)
                ->where('id', $translationId)
                ->firstOrFail();

            $translation->update([
                'value' => $request->value
            ]);

            return redirect()->back()->with('success', 'Translation updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update translation. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param Language $language
     * @return RedirectResponse
     */
    public function storeTranslation(Request $request, Language $language): RedirectResponse
    {
        $request->validate([
            'key' => 'required|string|unique:translations,key,NULL,id,language_id,' . $language->id,
            'value' => 'nullable|string',
        ]);

        try {
            Cache::forget('frontend_languages');
            Translation::create([
                'language_id' => $language->id,
                'key' => $request->key,
                'value' => $request->value ?? '',
                'is_dynamic' => true,
            ]);

            return redirect()->back()->with('success', 'Translation key added successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add translation key. Please try again.']);
        }
    }

    /**
     * @param Language $language
     * @param $translationId
     * @return RedirectResponse
     */
    public function deleteTranslation(Language $language, $translationId): RedirectResponse
    {
        try {
            Cache::forget('frontend_languages');
            $translation = Translation::where('language_id', $language->id)
                ->where('id', $translationId)
                ->firstOrFail();

            $translation->delete();

            return redirect()->back()->with('success', 'Translation key deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete translation key. Please try again.']);
        }
    }

    /**
     * @param Language $language
     * @return RedirectResponse
     */
    public function setDefault(Language $language): RedirectResponse
    {
        try {
            Cache::forget('frontend_languages');
            Language::query()->update(['is_default' => false]);
            $language->update(['is_default' => true]);

            return redirect()->back()->with('success', 'Default language set successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to set default language. Please try again.']);
        }
    }

    /**
     * @param Language $language
     * @return void
     */
    private function createBaseTranslations(Language $language): void
    {
        $englishLanguage = Language::where('code', 'en')->first();
        if ($englishLanguage && $englishLanguage->translations()->count() > 0) {
            $englishTranslations = $englishLanguage->translations()->get();
            foreach ($englishTranslations as $translation) {
                Translation::create([
                    'language_id' => $language->id,
                    'key' => $translation->key,
                    'value' => ''
                ]);
            }
        }
    }

    /**
     * @param $component
     * @return array|string[]
     */
    private function getComponentKeys($component): array
    {
        $componentMapping = [
            'hero' => [
                'heroTitle1', 'heroTitle2', 'heroTitle3', 'heroDescription', 'uptime', 'networks', 'tvl',
                'startBuilding', 'watchDemo', 'networkOverview', 'livePrices', 'performance', 'avgResponseTime',
                'primaryActionUrl', 'demoVideoUrl'
            ],
            'crypto_prices' => [
                'liveMarketData', 'liveCryptoPrices', 'cryptoPricesDescription', 'totalMarketCap', 'tradingVolume24h',
                'bitcoinDominance', 'marketOverview', 'updateFrequency', 'searchCryptos', 'all', 'topGainers',
                'topLosers', 'highVolume', 'rank', 'name', 'price', 'change24h', 'volume24h', 'marketCap',
                'chart', 'dataProvider'
            ],
            'services' => [
                'ourServices', 'servicesTitle', 'servicesDescription', 'gamifiedMiningTitle', 'gamifiedMiningDescription',
                'gamingBadge', 'realTimeRewards', 'multiLevelSystem', 'achievementSystem', 'competitiveRanking',
                'tradeTradingTitle', 'tradeTradingDescription', 'tradingBadge', 'instantTrades', 'multipleAssets',
                'riskManagement', 'tradingSignals', 'icoPlatformTitle', 'icoPlatformDescription', 'launchpadBadge',
                'tokenLaunch', 'smartContracts', 'complianceTools', 'investorProtection', 'stakingRewardsTitle',
                'stakingRewardsDescription', 'stakingBadge', 'flexibleStaking', 'competitiveAPY', 'autoCompounding',
                'instantUnstaking', 'referralCommissionTitle', 'referralCommissionDescription', 'affiliateBadge',
                'multiLevelCommission', 'realTimeTracking', 'bonusRewards', 'leaderboards', 'securitySuiteTitle',
                'securitySuiteDescription', 'securityBadge', 'multiFactorAuth', 'coldStorage', 'insuranceFund', 'auditReports'
            ],
            'sidebar' => ['tradingPanel'],
            'advanced_features' => [
                'advancedFeatures', 'advancedFeaturesTitle', 'advancedFeaturesDescription', 'gamingInfrastructure',
                'tradingSecurity', 'stakingPerformance', 'realTimeRewardsTitle', 'realTimeRewardsDescription',
                'rewardLatency', 'availability', 'achievementSystemTitle', 'achievementSystemDescription',
                'achievements', 'multiplier', 'competitiveRankingTitle', 'competitiveRankingDescription',
                'players', 'leaderboard', 'advancedEncryptionTitle', 'advancedEncryptionDescription',
                'riskManagementTitle', 'riskManagementDescription', 'instantExecutionTitle', 'instantExecutionDescription',
                'securityLayers', 'coreSecurity', 'encrypted', 'monitoring', 'breaches', 'apyRate', 'stakingRewards',
                'validatorUptime', 'live', 'autoCompoundingTitle', 'autoCompoundingDescription', 'autoCompoundingBenefit',
                'flexibleStakingTitle', 'flexibleStakingDescription', 'flexibleStakingBenefit', 'instantUnstakingTitle',
                'instantUnstakingDescription', 'instantUnstakingBenefit', 'ctaTitle', 'ctaDescription', 'getEnterpriseAccess'
            ],
            'blog' => [
                'blogBadge', 'blogTitle', 'blogDescription', 'minRead', 'readMore', 'noBlogsTitle', 'noBlogsMessage',
                'newsletterTitle', 'newsletterDescription', 'emailPlaceholder', 'subscribeButton', 'subscribingText', 'privacyNote'
            ],
            'blog_detail' => ['backToHome', 'relatedArticles'],
            'footer' => [
                'appName', 'footerDescriptionShort', 'allRightsReserved', 'privacy', 'terms', 'contact', 'cookiesPolicy',
                'cookiesBannerTitle', 'cookiesBannerDescription', 'acceptCookies', 'declineCookies'
            ],
            'gaming_mining' => [
                'gamingTeam', 'gamifiedMiningTeam', 'gamingTeamDescription', 'tokenomicsTitle', 'miningRewards',
                'tournamentPrizes', 'teamDevelopment', 'earningCalculator', 'dailyEarnings', 'monthlyEarnings',
                'miningProcessTitle', 'connectWallet', 'connectWalletDescription', 'chooseMiningPlan',
                'chooseMiningPlanDescription', 'startMiningProcess', 'startMiningProcessDescription', 'earnRewards',
                'earnRewardsDescription', 'howMiningWorksTitle', 'howMiningWorksDescription', 'setupMining',
                'setupMiningDescription', 'processMining', 'processMiningDescription', 'collectRewards',
                'collectRewardsDescription', 'activeMiners', 'totalRewards', 'systemUptime', 'miningSupport',
                'miningFeatures', 'miningSystemTitle', 'miningSystemHighlight', 'miningSystemDescription',
                'instantRewardsTitle', 'instantRewardsDescription', 'competitiveMiningTitle', 'competitiveMiningDescription',
                'achievementSystemFeature', 'achievementSystemFeatureDescription', 'miningDashboard', 'activeMining',
                'hashRate', 'dailyRewards', 'difficulty', 'topMiners', 'joinMiningTitle', 'joinMiningDescription',
                'startMining', 'joinMining', 'startMiningUrl', 'joinMiningUrl'
            ],
            'navigation' => ['dashboard', 'language', 'signIn'],
            'networks' => [
                'supportedNetworks', 'networksTitle', 'networksDescription', 'totalNetworks', 'averageUptime',
                'valueSecured', 'activeDelegators', 'layer1Network', 'cosmosEcosystem', 'highPerformance',
                'active', 'networkCtaTitle', 'networkCtaDescription', 'viewAllNetworks', 'requestIntegration',
                'viewAllNetworksUrl', 'requestIntegrationUrl'
            ],
            'contact' => [
                'contactBadge', 'contactTitle', 'contactSubtitle', 'sendMessageTitle', 'sendMessageDescription',
                'fullName', 'fullNamePlaceholder', 'emailAddress', 'subject', 'subjectPlaceholder', 'message',
                'messagePlaceholder', 'sendMessage', 'sending', 'messageSuccess', 'messageError', 'emailSupportTitle',
                'emailSupportDescription', 'liveChatTitle', 'liveChatDescription', 'phoneTitle', 'phoneDescription',
                'frequentlyAskedTitle', 'frequentlyAskedSubTitle', 'faqQuestion1', 'faqAnswer1', 'faqQuestion2',
                'faqAnswer2', 'faqQuestion3', 'faqAnswer3', 'faqQuestion4', 'faqAnswer4', 'faqQuestion5', 'faqAnswer5',
                'faqQuestion6', 'faqAnswer6', 'contactUs'
            ],
            'cookies' => [
                'cookiesBadge', 'cookiesPageTitle', 'cookiesPageSubtitle', 'cookiesLastUpdatedDate', 'lastUpdated',
                'essentialCookiesTitle', 'essentialCookiesContent1', 'essentialCookiesContent2', 'analyticsCookiesTitle',
                'analyticsCookiesContent1', 'analyticsCookiesContent2', 'marketingCookiesTitle', 'marketingCookiesContent1',
                'marketingCookiesContent2', 'preferencesCookiesTitle', 'preferencesCookiesContent1', 'preferencesCookiesContent2',
                'examples', 'essentialExample1', 'essentialExample2', 'essentialExample3', 'analyticsExample1',
                'analyticsExample2', 'analyticsExample3', 'marketingExample1', 'marketingExample2', 'marketingExample3',
                'preferencesExample1', 'preferencesExample2', 'preferencesExample3', 'dataRetention', 'essentialRetention',
                'analyticsRetention', 'marketingRetention', 'preferencesRetention', 'managingCookiesTitle',
                'managingCookiesDescription', 'chromeInstructions', 'firefoxInstructions', 'safariInstructions',
                'edgeInstructions', 'cookiesContactTitle', 'cookiesContactDescription', 'required', 'optional', 'viewPrivacy'
            ],
            'privacy' => [
                'privacyBadge', 'privacyTitle', 'privacySubtitle', 'privacyLastUpdatedDate', 'informationCollectionTitle',
                'informationCollectionContent1', 'informationCollectionContent2', 'personalInfoTitle', 'personalInfoContent',
                'usageDataTitle', 'usageDataContent', 'dataUsageTitle', 'dataUsageContent1', 'dataUsageContent2',
                'dataProtectionTitle', 'dataProtectionContent1', 'dataProtectionContent2', 'cookiesTitle', 'cookiesContent1',
                'cookiesContent2', 'thirdPartyTitle', 'thirdPartyContent1', 'thirdPartyContent2', 'userRightsTitle',
                'userRightsContent1', 'userRightsContent2', 'accessRightTitle', 'accessRightContent', 'deletionRightTitle',
                'deletionRightContent', 'policyUpdatesTitle', 'policyUpdatesContent1', 'policyUpdatesContent2',
                'privacyContactTitle', 'privacyContactDescription', 'viewTerms'
            ],
            'terms' => [
                'termsBadge', 'termsTitle', 'termsSubtitle', 'termsLastUpdatedDate', 'acceptanceTitle', 'acceptanceContent1',
                'acceptanceContent2', 'acceptanceImportant', 'servicesContent1', 'servicesContent2', 'serviceItem1',
                'serviceItem2', 'serviceItem3', 'serviceItem4', 'userAccountsTitle', 'userAccountsContent1',
                'userAccountsContent2', 'accountResponsibilityTitle', 'accountResponsibilityContent', 'accountSecurityTitle',
                'accountSecurityContent', 'prohibitedActivitiesTitle', 'prohibitedActivitiesContent1', 'prohibitedActivitiesContent2',
                'prohibitedItem1', 'prohibitedItem2', 'prohibitedItem3', 'prohibitedItem4', 'prohibitedItem5',
                'prohibitedImportant', 'paymentsFeesTitle', 'paymentsFeesContent1', 'paymentsFeesContent2',
                'feeStructureTitle', 'feeStructureContent', 'refundPolicyTitle', 'refundPolicyContent',
                'intellectualPropertyTitle', 'intellectualPropertyContent1', 'intellectualPropertyContent2',
                'limitationLiabilityTitle', 'limitationLiabilityContent1', 'limitationLiabilityContent2',
                'limitationImportant', 'terminationTitle', 'terminationContent1', 'terminationContent2',
                'terminationReason1', 'terminationReason2', 'terminationReason3', 'governingLawTitle',
                'governingLawContent1', 'governingLawContent2', 'changesTermsTitle', 'changesTermsContent1',
                'changesTermsContent2', 'termsContactTitle', 'termsContactDescription', 'importantNotice'
            ],
            'auth_login' => [
                'authSubtitle', 'welcomeBack', 'createNewAccount', 'resetPassword', 'loginToAccount', 'createYourAccount',
                'enterEmailForReset', 'authenticationRequired', 'signUp', 'namePlaceholder', 'confirmPassword',
                'confirmPasswordPlaceholder', 'rememberMe', 'forgotPassword', 'signingIn', 'creatingAccount',
                'createAccount', 'sendResetLink', 'sendingResetLink', 'backToSignIn', 'securityNotice'
            ],
            'auth_2fa' => [
                'twoFactorSubtitle', 'authenticatorTitle', 'recoveryCodeTitle', 'authenticatorDescription',
                'recoveryCodeDescription', 'authenticatorCode', 'recoveryCode', 'authenticationCode',
                'codePlaceholder', 'recoveryCodePlaceholder', 'recoveryCodeHelp', 'digitsEntered', 'verifying', 'verifyCode',
                'useRecoveryCode', 'signOut', 'referredBy', 'referralBonus'
            ],
            'user_sidebar' => [
                'mining', 'trading', 'marketData', 'liveTrading', 'tradingHistory', 'investment', 'icoTokens',
                'myPurchases', 'portfolio', 'wallet', 'myWallets', 'depositFunds', 'withdrawFunds',
                'transactionHistory', 'referral', 'referralDashboard', 'commissionHistory', 'security',
                'twoFactorAuth', 'changePassword', 'loginSessions', 'settings', 'profileSettings',
                'kycVerification', 'support', 'contactSupport', 'myTickets', 'premiumTrader', 'logout'
            ],
            'user_topbar' => [
                'mainBalance', 'tradeBalance', 'main', 'trade', 'walletManagement', 'securitySettings'
            ],
            'user_dashboard' => [
                'overview', 'portfolioOverview', 'portfolioValue', 'totalPortfolioValue', 'availableBalance',
                'availableTradingBalance', 'todaysPnL', 'todaysProfitAndLoss', 'activeTrades', 'numberOfActiveTrades',
                '24hChange', 'readyToTrade', 'sinceOpen', 'openPositions', 'quickActions', 'goToDepositsPage',
                'goToWithdrawalsPage', 'goToTradingPage', 'goToMiningPage', 'deposit', 'withdraw', 'quickStatistics',
                'winRate', 'totalTrades', 'monthlyProfit', 'miningBalance', 'recentActivity', 'refreshMarketData',
                'noMarketDataAvailable', 'marketDataWillAppearHere', 'viewAll', 'viewAllActivities',
                'noRecentActivities', 'recentActivitiesWillAppearHere', 'tradingPortfolioPerformance',
                'chartTimePeriodSelection', 'loading', 'portfolioPerformanceChart', 'noPortfolioDataAvailable',
                'tradingActivity', 'refreshTradingData', 'refreshingTradingData', 'tradingActivityChart',
                'noTradingDataAvailable', 'chartUpdated', 'view', 'failedToUpdateChart', 'tradingDataRefreshed',
                'failedToRefreshTradingData', 'marketDataRefreshed', 'failedToRefreshMarketData', 'value', 'trades'
            ],
            'user_mining' => [
                'miningStatistics', 'currentBalance', 'totalMined', 'miningLevel', 'level', 'xpToNextLevel',
                'days', 'streakDays', 'miningStreak', 'miningControl', 'standard', 'boost', 'premium',
                'idle', 'miningRate', 'sec', 'perDay', 'levelProgress', 'sessionTimeLeft', 'miningStatus',
                'starting', 'stopping', 'stopMining', 'claimTokens', 'lastClaim', 'claim', 'claimed',
                'availableToUnlock', 'noAchievementsYet', 'startMiningToUnlockAchievements', 'activeCompetitions',
                'prizePool', 'joinCompetition', 'noActiveCompetitions', 'checkBackLaterForCompetitions',
                'miningLeaderboard', 'yourRank', 'noLeaderboardData', 'startMiningToAppearOnLeaderboard',
                'readyToClaim', 'cancel', 'claiming', 'claimNow', 'miningStartedSuccessfully', 'failedToStartMining',
                'miningStoppedSuccessfully', 'failedToStopMining', 'claimedTokensSuccessfully', 'failedToClaimTokens',
                'achievementClaimed', 'failedToClaimAchievement', 'joinedCompetitionSuccessfully',
                'failedToJoinCompetition', 'never', 'expired', 'joined', 'full', 'participants', 'yourProgress', 'currentRank',
                'alreadyJoinedCompetition', 'competitionIsFull'
            ],
            'user_market' => [
                'market', 'cryptocurrencyPrices', 'totalCurrencies', 'marketDataTable', 'currency',
                'type', 'prev', 'noData', 'notAvailable', 'justNow', 'minutesAgo', 'hoursAgo', 'daysAgo',
                'noMarketDataFound', 'showingResults', 'pagination', 'goToPreviousPage', 'goToNextPage',
                'currentPageNumber', 'goToPageNumber'
            ],
            'user_trading' => [
                'tradingStatistics', 'accountBalance', 'selectAsset', 'available', 'searchSymbol',
                'showingFirst10Symbols', 'selectSymbolFor', 'payoutRate', 'noSymbolsFound', 'noSymbolsAvailable',
                'tryDifferentSearch', 'checkBackLater', 'clearSearch', 'selectAssetToViewChart',
                'chartPlaceholder', 'chooseAssetFromLeft', 'placeOrder', 'direction', 'callUp', 'call',
                'priceWillRise', 'putDown', 'put', 'priceWillFall', 'investmentAmount', 'enterAmount',
                'min', 'max', 'duration', 'selectDuration', 'tradeSummary', 'potentialProfit', 'placingTrade',
                'placeTrade', 'marketClosed', 'selectAssetToTrade', 'noActiveTrades', 'placeTradeToGetStarted',
                'calculating', 'cancelTrade', 'asset', 'amount', 'confirmCancellation', 'cancelTradeWarning',
                'cancellationTimeLimit', 'keepTrade', 'cancelling', 'yesCancelTrade', 'marketCurrentlyClosed',
                'ensureTradeDetailsValid', 'insufficientBalance', 'tradePlacedSuccessfully', 'failedToPlaceTrade',
                'tradeCancelledSuccessfully', 'failedToCancelTrade', 'tradingHoursNotConfigured',
                'invalidTradingHoursConfiguration', 'opensOnDay', 'tradingNotAvailable', 'opensToday',
                'closedToday', 'openUntil', 'chartLoadFailed', 'tradingViewSymbolNotConfigured',
                'addTradingViewSymbolToCurrency'
            ],
            'trading_history' => [
                'tradeHistory', 'totalProfitLoss', 'totalPL', 'totalVolume', 'filters', 'search',
                'tradeIdSymbolPlaceholder', 'allSymbols', 'allDirections', 'startDate', 'endDate',
                'applyFilters', 'clear', 'refresh', 'refreshingTrades', 'refreshTrades', 'totalTradesCount',
                'tradingHistoryTable', 'tradeId', 'symbol', 'status', 'profitLoss', 'date', 'actions',
                'won', 'lost', 'cancelled', 'viewDetails', 'viewDetailsFor', 'cancelTradeFor',
                'noTradesFound', 'noTradesYet', 'startTrading', 'tradeDetails', 'closeModal',
                'openPrice', 'closePrice', 'openTime', 'closeTime', 'close', 'tradeHistoryRefreshed',
                'failedToRefreshHistory', 'invalidDate', 'invalidTime'
            ],
            'ico_tokens' => [
                'ico', 'discoverInvestInTokens', 'activeTokens', 'totalInvested', 'tokensOwned',
                'successful', 'allTokens', 'featuredTokens', 'endingSoon', 'noTokensFound',
                'tryDifferentFilter', 'featured', 'perToken', 'progress', 'sold', 'remaining',
                'raised', 'timeLeft', 'ended', 'soldOut', 'saleEnded', 'investNow',
                'availableForInvestment', 'refreshing', 'myStatistics', 'successfulPurchases',
                'pendingPurchases', 'purchaseHistory', 'trackTokenPurchases', 'totalPurchases',
                'tokens', 'noPurchasesFound', 'makeFirstTokenPurchase', 'tokensYoullGet',
                'processing', 'confirmPurchase', 'purchaseDetails', 'purchaseInformation',
                'purchaseSummary', 'tokensPurchased', 'pricePerToken', 'totalValue',
                'transactionDetails', 'transactionId', 'timeline', 'created', 'confirmed',
                'completed', 'adminNotes', 'tokenSaleNotActive', 'tokenSaleSoldOut',
                'failedToOpenPurchaseModal', 'ensurePurchaseDetailsValid', 'insufficientBalanceForPurchase',
                'notEnoughTokensAvailable', 'purchaseAmountTooSmall', 'tokensPurchasedSuccessfully',
                'failedToPurchaseTokens', 'failedToSubmitPurchase', 'invalidPurchaseSelected',
                'failedToShowPurchaseDetails', 'dataRefreshedSuccessfully', 'failedToRefreshData',
                'failedToLoadPage', 'navigationFailed', 'applicationInitializationFailed', 'unknown'
            ],
            'ico_purchase_history' => [
                'icoPurchaseHistory', 'icoPurchaseStatistics', 'uniqueTokens', 'totalTokens',
                'purchaseIdTokenPlaceholder', 'refreshingPurchases', 'refreshPurchases', 'totalPurchasesCount',
                'purchaseHistoryTable', 'purchaseId', 'amountInvested', 'tokenPrice', 'purchaseDate',
                'noPurchasesYet', 'browseIcoTokens', 'transactionHash', 'noNotesAvailable',
                'purchaseHistoryRefreshed', 'failed', 'pending'
            ],
            'ico_portfolio' => [
                'myPortfolio', 'portfolioStatistics', 'currentValue', 'totalPLPercentage',
                'tokenHoldings', 'differentTokens', 'tokenHoldingsTable', 'holdings', 'avgPrice',
                'currentPrice', 'profitLossPercentage', 'purchase', 'sellTokens', 'sell',
                'noTokenHoldingsFound', 'startInvestingToSeePortfolio', 'sellToken', 'tokensToSell',
                'enterTokenAmount', 'maxTokens', 'saleSummary', 'salePrice', 'totalAmount',
                'enterValidTokensToSell', 'tokensSoldSuccessfully', 'failedToSellTokens'
            ],
            'wallet_overview' => [
                'walletOverview', 'totalBalance', 'mainWallet', 'tradeWallet', 'activeWallets',
                'primaryTradingWallet', 'tradeOptionsTrading', 'balance', 'walletAddress',
                'toTradeWallet', 'transferToUser', 'toMainWallet', 'mainToTrade', 'tradeToMain',
                'sendToUser', 'mainWalletNotFound', 'tradeWalletNotFound', 'transferFunds',
                'from', 'to', 'transferAmount', 'amountPlaceholder', 'searchUser', 'enterNameOrEmail',
                'selectedUser', 'recipientWallet', 'primaryWallet', 'tradingWallet', 'noteOptional',
                'enterTransferNote', 'unknownWallet', 'amountCannotBeNegative', 'amountMustBeGreaterThanZero',
                'minimumTransferAmount', 'validationErrorOccurred', 'pleaseSelectRecipientUser',
                'pleaseSelectRecipientWalletType', 'invalidSourceWallet', 'invalidUserSelected',
                'failedToTransferFunds'
            ],
            'deposits' => [
                'selectPaymentMethod', 'selectGateway', 'fee', 'depositAmount', 'paymentInformation',
                'selectOption', 'paymentSummary', 'processingFee', 'youWillReceive', 'gatewayInformation',
                'gateway', 'instant', 'manualReview', 'depositStats', 'submitRequest', 'payNow',
                'depositHistory', 'trackDepositTransactions', 'totalDepositsCount', 'trxId', 'toWallet',
                'depositDetails', 'transactionInformation', 'paymentGateway', 'amountDetails',
                'requestedAmount', 'totalPaid', 'addedToWallet', 'paymentDetails', 'approved',
                'rejected', 'adminResponse', 'paymentTransactionId', 'download', 'noDepositsFound',
                'makeFirstDeposit', 'invalidPaymentGateway', 'gatewaySelected', 'failedToSelectGateway',
                'minMax', 'ensureRequiredFieldsFilled', 'totalAmountMustBeGreaterThanZero',
                'depositSubmissionFailed', 'minimumDepositAmount', 'maximumDepositAmount',
                'invalidGatewayConfiguration', 'invalidAmountFormat', 'selectPaymentGateway',
                'depositRequestSubmitted', 'paymentProcessedSuccessfully', 'paymentFailed',
                'paymentProcessingFailed', 'invalidDepositSelected', 'failedToShowDepositDetails',
                'stripeRequiresHTTPS', 'failedToInitializePayment', 'paymentFormNotFound',
                'failedToInitializeStripe', 'fileSizeTooLarge', 'invalidFileType', 'fileNameTooLong',
                'fileTypeNotAllowed', 'fileSelectedSuccessfully', 'fileUploadFailed'
            ],
            'withdrawals' => [
                'selectWithdrawalMethod', 'withdrawalAmount', 'withdrawalInformation', 'submitWithdrawalRequest',
                'withdrawalSummary', 'youllReceive', 'totalDeductedFromWallet', 'withdrawalStats',
                'withdrawalHistory', 'trackWithdrawalTransactions', 'totalWithdrawalsCount', 'requested',
                'youReceive', 'deducted', 'fromWallet', 'noWithdrawalsFound', 'makeFirstWithdrawal',
                'cancelWithdrawal', 'cancelWithdrawalRequest', 'cancelWithdrawalConfirmation',
                'willBeRefunded', 'keepWithdrawal', 'yesCancelWithdrawal', 'withdrawalDetails',
                'withdrawalGateway', 'youReceived', 'deductedFromWallet', 'invalidWithdrawalGateway',
                'invalidWithdrawalSelected', 'onlyPendingWithdrawalsCanBeCancelled', 'withdrawalCancelledSuccessfully',
                'failedToCancelWithdrawal', 'failedToShowCancellationDialog', 'failedToShowWithdrawalDetails',
                'selectWithdrawalGateway', 'minimumWithdrawalAmount', 'maximumWithdrawalAmount',
                'insufficientWalletBalance', 'finalAmountMustBeGreaterThanZero', 'withdrawalRequestSubmitted',
                'withdrawalSubmissionFailed'
            ],
            'transactions' => [
                'transactions', 'transactionStatistics', 'totalTransactions', 'completedTransactions',
                'transactionIdPlaceholder', 'allTypes', 'debit', 'credit', 'payment', 'refund',
                'refreshingTransactions', 'refreshTransactions', 'totalTransactionsCount',
                'transactionHistoryTable', 'walletType', 'postBalance', 'details', 'noTransactionsFound',
                'noTransactionsYet', 'goToDashboard', 'transactionHistoryRefreshed'
            ],
            'commissions' => [
                'commissionStatistics', 'totalCommissions', 'paidCommissions', 'totalPaidAmount',
                'paidAmount', 'totalPendingAmount', 'pendingAmount', 'searchUserOrEmail',
                'totalCommissionsCount', 'commissionHistoryTable', 'commissionId', 'referredUser',
                'commission', 'commissionType', 'commissionDetails', 'commissionAmount', 'referredEmail',
                'earnedDate', 'paidDate', 'referralLevel', 'commissionRate', 'sourceAmount', 'notes',
                'noCommissionsFound', 'startReferringToEarnCommissions', 'viewReferralProgram',
                'commissionHistoryRefreshed', 'refreshingCommissions', 'refreshCommissions'
            ],
            'referral_dashboard' => [
                'totalReferrals', 'totalCommission', 'pendingCommission', 'thisMonth', 'yourReferralCode',
                'copy', 'referralLink', 'shareOn', 'recentReferrals', 'user', 'noReferralsYet',
                'copied', 'copyFailed'
            ],
            'login_history' => [
                'loginHistory', 'loginStatistics', 'totalLoginAttempts', 'totalAttempts',
                'successfulLogins', 'failedLoginAttempts', 'failedAttempts', 'uniqueIpAddresses',
                'recentLoginActivity', 'refreshingLoginHistory', 'refreshLoginHistory',
                'loginHistoryTable', 'dateTime', 'ipAddress', 'location', 'device',
                'browser', 'deviceType', 'loginSuccessfulAt', 'loginFailedAt', 'currentSession',
                'unknownOs', 'noLoginHistoryFound', 'loginAttemptsWillAppearHere', 'loginAttemptDetails',
                'platform', 'failureReason', 'userAgent', 'blocking', 'blockIp', 'securityAlert',
                'recentFailedAttemptsDetected', 'enable2FA', 'loginHistoryRefreshed', 'failedToRefreshLoginHistory',
                'confirmBlockIp', 'ipAddressBlocked', 'failedToBlockIp'
            ],
            'password_change' => [
                'updateAccountPassword', 'passwordRequirements', 'atLeast8Characters', 'containsUppercaseLowercase',
                'containsAtLeastOneNumber', 'containsSpecialCharacter', 'currentPassword', 'enterCurrentPassword',
                'hideCurrentPassword', 'showCurrentPassword', 'newPassword', 'enterNewPassword', 'hideNewPassword',
                'showNewPassword', 'confirmNewPassword', 'confirmNewPassword2', 'hidePasswordConfirmation',
                'showPasswordConfirmation', 'passwordStrengthVeryWeak', 'passwordStrengthWeak', 'passwordStrengthFair',
                'passwordStrengthGood', 'passwordStrengthStrong', 'passwordStrength', 'atLeast8CharactersShort',
                'upperLowercaseLetters', 'atLeastOneNumber', 'specialCharacter', 'passwordsMatch', 'passwordsDoNotMatch',
                'pleaseFixFollowingErrors', 'resetForm', 'resetFormClear', 'updatePassword', 'updatingPassword',
                'updating', 'passwordMustBe8Characters', 'passwordMustContainMixedCase', 'passwordMustContainNumber',
                'passwordMustContainSpecialChar', 'passwordConfirmationNoMatch', 'ensureAllFieldsFilled',
                'enterCurrentPasswordPlease', 'currentPasswordTooShort', 'newPasswordMustBeDifferent',
                'passwordUpdatedSuccessfully', 'currentPasswordIncorrect', 'failedToUpdatePassword',
                'passwordTooCommon', 'avoidSequentialCharacters', 'avoidRepeatedCharacters', 'securityTips',
                'useUniquePassword', 'dontReusePasswords', 'enableTwoFA', 'addExtraSecurity', 'regularUpdates',
                'changePasswordRegularly', 'avoidSharing', 'neverSharePassword'
            ],
            'two_factor_auth' => [
                'twoFactorAuthentication', 'addExtraSecurityLayer', 'enabled', 'disabled', 'manage2FA',
                'setup2FA', 'accountIsProtected', 'secureYourAccount', 'setupInstructions', 'installAuthenticatorApp',
                'scanQRCodeOrEnterSecret', 'enter6DigitCodeFromApp', 'saveRecoveryCodesSafely', 'qrCodeAlt',
                'scanQRCodeWithApp', 'copySecretKeyInstead', 'orEnterCodeManually', 'copySecretToClipboard',
                'enterVerificationCodeFromApp', 'enter6DigitVerificationCode', 'enabling', 'enableTwoFactorAuth',
                'twoFactorIsActive', 'accountProtectedWith2FA', 'testYour2FA', 'enterVerificationCodeToTest',
                'enter6DigitCodeToTest', 'enter6DigitCode', 'disableTwoFactorAuth', 'removeExtraSecurityLayer',
                'enterPasswordToDisable', 'enterPasswordToConfirmDisabling', 'enterPasswordToConfirm',
                'hidePassword', 'showPassword', 'disabling', 'disable2FA', 'recoveryCodes', 'useIfLoseAccess',
                'important', 'saveCodesSecurely', 'downloadRecoveryCodesAsFile', 'downloadRecoveryCodes',
                'generateNewRecoveryCodes', 'generating', 'generateNewCodes', 'enable2FAToSeeRecoveryCodes',
                'useAuthenticatorApp', 'downloadGoogleAuthOrAuthy', 'backupRecoveryCodes', 'storeCodesSecureLocation',
                'testRegularly', 'verifyCodesWork', 'keepDeviceSecure', 'protectPhoneWithLock', 'noSecretAvailable',
                'secretKeyCopied', 'failedToCopySecret', 'enterValid6DigitCode', 'twoFactorEnabledSuccessfully',
                'failedToEnable2FA', 'codeVerifiedSuccessfully', 'invalidVerificationCode', 'twoFactorDisabledSuccessfully',
                'failedToDisable2FA', 'noRecoveryCodesAvailable', 'twoFactorRecoveryCodes', 'generated',
                'recoveryCodesImportant', 'storeCodesSecurely', 'recoveryCodesDownloaded', 'failedToDownloadCodes',
                'enable2FAFirst', 'newRecoveryCodesGenerated', 'failedToGenerateNewCodes'
            ],
            'kyc_verification' => [
                'verifyIdentityToUnlock', 'verificationPending', 'underReview', 'verificationApproved',
                'verificationRejected', 'unknownStatus', 'pendingDescription', 'reviewingDescription',
                'approvedDescription', 'rejectedDescription', 'statusUnavailable', 'rejectionReason',
                'submitted', 'reviewed', 'resubmitKycDocuments', 'submitKycDocuments', 'provideAccurateInformation',
                'personalInformation', 'firstName', 'enterFirstName', 'lastName', 'enterLastName', 'dateOfBirth',
                'phoneNumber', 'enterPhoneNumber', 'addressInformation', 'address', 'enterAddress', 'city',
                'enterCity', 'stateProvince', 'enterState', 'postalCode', 'enterPostalCode', 'country',
                'selectCountry', 'documentInformation', 'documentType', 'selectDocumentType', 'passport',
                'driversLicense', 'nationalId', 'documentNumber', 'enterDocumentNumber', 'documentUpload',
                'documentFrontSide', 'uploadDocumentFrontSide', 'clickToUploadFrontSide', 'pngJpgUpTo8MB',
                'documentFrontPreview', 'removeDocumentFrontImage', 'documentBackSide', 'uploadDocumentBackSide',
                'clickToUploadBackSide', 'documentBackPreview', 'removeDocumentBackImage', 'selfieWithDocument',
                'uploadSelfieWithDocument', 'clickToUploadSelfie', 'holdDocumentNextToFace', 'selfiePreview',
                'removeSelfieImage', 'agreeToTermsText', 'agreeToTermsRequired', 'submittingKyc', 'resubmitKyc',
                'submitKyc', 'submitting', 'selectImageFileOnly', 'onlyJpegPngAllowed', 'fileUploadedSuccessfully',
                'imageCompressedSuccessfully', 'failedToCompressImage', 'failedToLoadImage', 'fileRemovedSuccessfully',
                'fillAllRequiredFields', 'kycSubmittedSuccessfully', 'failedToSubmitKyc', 'unexpectedError'
            ],
            'profile_settings' => [
                'uploadProfilePicture', 'emailVerified', 'emailNotVerified', 'memberSince', 'profileInformation',
                'updateAccountDetails', 'enterFullName', 'enterEmailAddress', 'profilePicture', 'updateProfile',
                'accountSecurity', 'manageSecuritySettings', 'selectValidImageFile', 'imageSizeTooLarge',
                'profilePictureUpdated', 'failedToUpdatePicture', 'profileUpdatedSuccessfully', 'failedToUpdateProfile',
                'profilePictureRemoved', 'failedToRemovePicture', 'verificationEmailSent', 'failedToSendVerification',
                'admin', 'reviewing'
            ],
            'create_ticket' => [
                'createSupportTicket', 'describeProblemGetHelp', 'back', 'enterTicketSubject', 'category',
                'selectCategory', 'technical', 'billing', 'account', 'general', 'other', 'priority',
                'low', 'medium', 'high', 'urgent', 'description', 'describeYourProblem', 'attachments',
                'optional', 'maxFileSize10MB', 'supportedFormats', 'selectedFiles', 'creating', 'createTicket',
                'helpfulTips', 'beSpecific', 'provideDetailedDescription', 'attachScreenshots',
                'visualsHelpUsUnderstand', 'setPriority', 'urgentForCriticalIssues', 'responseTime',
                'weRespondWithin24Hours', 'pleaseFillRequiredFields', 'ticketCreatedSuccessfully',
                'failedToCreateTicket'
            ],
            'ticket_list' => [
                'supportTickets', 'manageYourSupportRequests', 'searchTickets', 'allStatus', 'open',
                'inProgress', 'resolved', 'closed', 'clearFilters', 'ticket', 'reply', 'replies',
                'noTicketsFound', 'createYourFirstTicket', 'showing', 'of', 'results', 'previous', 'next'
            ],
            'ticket_details' => [
                'createdOn', 'unknownUser', 'conversation', 'noRepliesYet', 'addReply', 'typeYourReply',
                'sendReply', 'ticketClosedNoReply', 'ticketDetails', 'ticketNumber', 'quickActions',
                'replyToTicket', 'viewAllTickets', 'createNewTicket', 'supportInfo', 'within24Hours',
                'emergencySupport', 'forUrgentIssues', 'helpCenter', 'selfServiceOptions', 'ticketActivity',
                'totalReplies', 'lastActivity', 'day', 'hour', 'hours', 'minute', 'minutes', 'ago',
                'pleaseEnterReplyMessage', 'replySentSuccessfully', 'failedToSendReply'
            ]
        ];

        return $componentMapping[$component] ?? [];
    }
}
