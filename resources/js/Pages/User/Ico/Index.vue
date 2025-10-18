<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    icoTokens: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    myPurchases: {
        type: Object,
        default: () => ({ data: [], total: 0, links: [] }),
        validator: (value) => value && typeof value === 'object' && Array.isArray(value.data)
    },
    statistics: {
        type: Object,
        default: () => ({
            total_invested: 0,
            total_tokens_purchased: 0,
            successful_purchases: 0,
            pending_purchases: 0
        }),
        validator: (value) => value && typeof value === 'object'
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const isProcessing = ref(false);
const isRefreshing = ref(false);
const currentFilter = ref('all');
const showPurchaseModal = ref(false);
const selectedToken = ref(null);
const showDetailsModal = ref(false);
const selectedPurchase = ref(null);
const purchaseForm = ref({
    amount_usd: 0
});

const { showToast } = useToast();
const { t } = useTranslation();
const page = usePage();

const primaryColor = computed(() => page.props.primaryColor || '#1f2937');
const derivedColors = computed(() => {
    const primary = primaryColor.value;
    const adjustColor = (color, amount) => {
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);

        const newR = Math.min(255, Math.max(0, r + amount));
        const newG = Math.min(255, Math.max(0, g + amount));
        const newB = Math.min(255, Math.max(0, b + amount));

        return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
    };

    return {
        primary: primary,
        secondary: adjustColor(primary, 40),
        background: adjustColor(primary, -30),
        surface: adjustColor(primary, -10),
        textPrimary: '#ffffff',
        textSecondary: '#e2e8f0',
        textMuted: '#94a3b8',
        border: adjustColor(primary, 60),
        accent: adjustColor(primary, 120),
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444'
    };
});

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));

const inputStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border,
    color: derivedColors.value.textPrimary
}));

const currencySymbol = computed(() => page.props.currencySymbol || '$');
const purchaseModalTitleId = computed(() => 'purchase-modal-title-' + Math.random().toString(36).substr(2, 9));
const detailsModalTitleId = computed(() => 'details-modal-title-' + Math.random().toString(36).substr(2, 9));

const availableBalance = computed(() => {
    try {
        const wallets = page.props.auth?.user?.wallets;
        if (!wallets || !wallets.main_balance) {
            return 0;
        }

        const balance = parseFloat(wallets.main_balance.toString().replace(/,/g, ''));
        return isNaN(balance) ? 0 : Math.max(0, balance);
    } catch (error) {
        console.error('Error getting available balance:', error);
        return 0;
    }
});

const filterOptions = computed(() => [
    { value: 'all', label: 'allTokens', count: props.icoTokens.length },
    { value: 'active', label: 'activeTokens', count: props.icoTokens.filter(token => isTokenSaleActive(token)).length },
    { value: 'featured', label: 'featuredTokens', count: props.icoTokens.filter(token => token.is_featured).length },
    { value: 'ending-soon', label: 'endingSoon', count: props.icoTokens.filter(token => {
            const daysLeft = parseInt(token.days_remaining);
            return daysLeft <= 7 && daysLeft > 0 && isTokenSaleActive(token);
        }).length }
]);

const filteredTokens = computed(() => {
    if (!props.icoTokens || !Array.isArray(props.icoTokens)) return [];

    switch (currentFilter.value) {
        case 'active':
            return props.icoTokens.filter(token => isTokenSaleActive(token));
        case 'featured':
            return props.icoTokens.filter(token => token.is_featured);
        case 'ending-soon':
            return props.icoTokens.filter(token => {
                const daysLeft = parseInt(token.days_remaining);
                return daysLeft <= 7 && daysLeft > 0 && isTokenSaleActive(token);
            });
        default:
            return props.icoTokens;
    }
});

const canPurchase = computed(() => {
    return selectedToken.value &&
        purchaseForm.value.amount_usd >= 1 &&
        purchaseForm.value.amount_usd <= availableBalance.value &&
        calculateTokensToReceive() > 0 &&
        calculateTokensToReceive() <= selectedToken.value.tokens_remaining &&
        !isProcessing.value &&
        isTokenSaleActive(selectedToken.value);
});

const isTokenSaleActive = (token) => {
    try {
        if (!token) return false;
        const now = new Date();
        const startDate = new Date(token.sale_start_date);
        const endDate = new Date(token.sale_end_date);
        return now >= startDate && now <= endDate && token.tokens_remaining > 0;
    } catch (error) {
        return false;
    }
};

const formatNumber = (num) => {
    if (num === null || num === undefined || num === '') return '0.00';
    const number = parseFloat(num);
    if (isNaN(number)) return '0.00';

    if (number >= 1000000000) {
        return (number / 1000000000).toFixed(2) + 'B';
    } else if (number >= 1000000) {
        return (number / 1000000).toFixed(2) + 'M';
    } else if (number >= 1000) {
        return (number / 1000).toFixed(2) + 'K';
    } else {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 8
        }).format(number);
    }
};

const formatDate = (dateString) => {
    if (!dateString) return t('notAvailable');
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return t('invalidDate');

        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        return t('invalidDate');
    }
};

const formatTime = (dateString) => {
    if (!dateString) return t('notAvailable');
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return t('invalidTime');

        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return t('invalidTime');
    }
};

const formatDateTime = (dateString) => {
    if (!dateString) return t('notAvailable');
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return t('invalidDate');

        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return t('invalidDate');
    }
};


const formatStatus = (status) => {
    if (!status || typeof status !== 'string') return t('unknown');
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
};

const getStatusBadgeClasses = () => {
    return 'px-2 py-1 text-xs font-medium rounded-full border';
};

const getStatusBadgeColor = (status) => {
    if (!status || typeof status !== 'string') {
        return derivedColors.value.textMuted;
    }

    switch (status.toLowerCase()) {
        case 'completed':
        case 'success':
            return derivedColors.value.success;
        case 'pending':
        case 'processing':
            return derivedColors.value.warning;
        case 'failed':
        case 'cancelled':
            return '#ef4444';
        default:
            return derivedColors.value.textMuted;
    }
};

const getDaysRemainingColor = (daysRemaining) => {
    const days = parseInt(daysRemaining);
    if (days <= 1) return '#ef4444';
    if (days <= 7) return derivedColors.value.warning;
    return derivedColors.value.success;
};

const getTimeRemaining = (endDate) => {
    try {
        const now = new Date();
        const end = new Date(endDate);
        const diff = end - now;

        if (diff <= 0) return 'Ended';

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

        if (days > 0) {
            return days === 1 ? '1 day left' : `${days} days left`;
        }
        if (hours > 0) {
            return hours === 1 ? '1 hour left' : `${hours} hours left`;
        }
        return 'Less than 1 hour left';
    } catch (error) {
        return 'Not available';
    }
};

const setFilter = (filter) => {
    try {
        currentFilter.value = filter;
    } catch (error) {
    }
};

const openPurchaseModal = async (token) => {
    try {
        if (!isTokenSaleActive(token)) {
            showToast(t('tokenSaleNotActive'), 'warning');
            return;
        }

        if (token.tokens_remaining <= 0) {
            showToast(t('tokenSaleSoldOut'), 'warning');
            return;
        }

        selectedToken.value = token;
        showPurchaseModal.value = true;
        const defaultAmount = Math.min(100, availableBalance.value);
        purchaseForm.value.amount_usd = Math.max(1, defaultAmount);
        await nextTick();
        const modal = document.querySelector('[role="dialog"]');
        if (modal) {
            modal.focus();
        }
    } catch (error) {
        showToast(t('failedToOpenPurchaseModal'), 'error');
    }
};

const closePurchaseModal = () => {
    try {
        showPurchaseModal.value = false;
        setTimeout(() => {
            selectedToken.value = null;
            purchaseForm.value.amount_usd = 0;
        }, 300);
    } catch (error) {
    }
};

const calculateTokensToReceive = () => {
    try {
        if (!selectedToken.value || !purchaseForm.value.amount_usd) return 0;
        return Math.floor(purchaseForm.value.amount_usd / selectedToken.value.price);
    } catch (error) {
        return 0;
    }
};

const submitPurchase = () => {
    try {
        if (!canPurchase.value) {
            showToast(t('ensurePurchaseDetailsValid'), 'warning');
            return;
        }

        if (purchaseForm.value.amount_usd > availableBalance.value) {
            showToast(t('insufficientBalanceForPurchase'), 'error');
            return;
        }

        if (calculateTokensToReceive() > selectedToken.value.tokens_remaining) {
            showToast(t('notEnoughTokensAvailable'), 'error');
            return;
        }

        if (calculateTokensToReceive() <= 0) {
            showToast(t('purchaseAmountTooSmall'), 'error');
            return;
        }

        isProcessing.value = true;

        const purchaseData = {
            ico_token_id: selectedToken.value.id,
            amount_usd: parseFloat(purchaseForm.value.amount_usd)
        };

        router.post('/user/investment/ico-purchase', purchaseData, {
            onSuccess: () => {
                closePurchaseModal();
                showToast(t('tokensPurchasedSuccessfully'), 'success');
            },
            onError: (errors) => {
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('failedToPurchaseTokens');
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                isProcessing.value = false;
            },
            preserveScroll: true
        });
    } catch (error) {
        showToast(t('failedToSubmitPurchase'), 'error');
        isProcessing.value = false;
    }
};

const showPurchaseDetails = (purchase) => {
    try {
        if (!purchase?.id) {
            showToast(t('invalidPurchaseSelected'), 'error');
            return;
        }
        selectedPurchase.value = purchase;
        showDetailsModal.value = true;
    } catch (error) {
        showToast(t('failedToShowPurchaseDetails'), 'error');
    }
};

const closeDetailsModal = () => {
    try {
        showDetailsModal.value = false;
        setTimeout(() => {
            selectedPurchase.value = null;
        }, 300);
    } catch (error) {
    }
};

const refreshData = () => {
    try {
        if (isRefreshing.value) return;

        isRefreshing.value = true;

        router.reload({
            only: ['icoTokens', 'myPurchases', 'statistics'],
            onSuccess: () => {
                showToast(t('dataRefreshedSuccessfully'), 'success');
            },
            onError: () => {
                showToast(t('failedToRefreshData'), 'error');
            },
            onFinish: () => {
                isRefreshing.value = false;
            },
            preserveScroll: true
        });
    } catch (error) {
        showToast(t('failedToRefreshData'), 'error');
        isRefreshing.value = false;
    }
};

const goToPage = (url) => {
    try {
        if (!url || typeof url !== 'string') return;

        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                showToast(t('failedToLoadPage'), 'error');
            }
        });
    } catch (error) {
        showToast(t('navigationFailed'), 'error');
    }
};

onMounted(() => {
    try {
        const flash = page.props.flash;
        if (flash?.success) showToast(flash.success, 'success');
        if (flash?.error) showToast(flash.error, 'error');
        if (flash?.warning) showToast(flash.warning, 'warning');
        if (flash?.info) showToast(flash.info, 'info');
    } catch (error) {
        showToast(t('applicationInitializationFailed'), 'error');
    }
});


onUnmounted(() => {
    try {
        isProcessing.value = false;
        isRefreshing.value = false;
    } catch (error) {

    }
});
</script>

<template>
    <UserLayout
        :page-title="t('icoTokens')"
        :page-section="t('ico')"
    >
        <div class="max-w-none mx-auto space-y-6 px-2 sm:px-4">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between space-y-6 lg:space-y-0">
                    <div class="flex items-center space-x-3 sm:space-x-4 w-full lg:w-auto">
                        <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }">
                            <svg
                                class="w-5 h-5 sm:w-6 sm:h-6"
                                :style="{ color: derivedColors.background }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ t('icoTokens') }}
                            </h1>
                            <p class="mt-1 text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                                {{ t('discoverInvestInTokens') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full lg:w-auto">
                        <div class="text-center lg:text-right">
                            <div class="text-lg sm:text-xl lg:text-2xl font-bold" :style="{ color: derivedColors.accent }">
                                {{ filteredTokens.length }}
                            </div>
                            <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('activeTokens') }}
                            </div>
                        </div>
                        <div class="text-center lg:text-right">
                            <div class="text-lg sm:text-xl lg:text-2xl font-bold break-all" :style="{ color: derivedColors.success }">
                                {{ currencySymbol }}{{ formatNumber(statistics.total_invested) }}
                            </div>
                            <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('totalInvested') }}
                            </div>
                        </div>
                        <div class="text-center lg:text-right">
                            <div class="text-lg sm:text-xl lg:text-2xl font-bold" :style="{ color: derivedColors.secondary }">
                                {{ formatNumber(statistics.total_tokens_purchased) }}
                            </div>
                            <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('tokensOwned') }}
                            </div>
                        </div>
                        <div class="text-center lg:text-right">
                            <div class="text-lg sm:text-xl lg:text-2xl font-bold" :style="{ color: derivedColors.warning }">
                                {{ statistics.successful_purchases }}
                            </div>
                            <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('successful') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 sm:gap-6">
                <div class="xl:col-span-3 space-y-4 sm:space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="filter in filterOptions"
                                :key="filter.value"
                                class="px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-medium transition-all duration-300 hover:scale-105 flex-shrink-0"
                                :class="currentFilter === filter.value ? 'shadow-lg' : ''"
                                :style="{
                                    backgroundColor: currentFilter === filter.value ? derivedColors.accent : derivedColors.surface + '50',
                                    color: currentFilter === filter.value ? derivedColors.background : derivedColors.textSecondary,
                                    border: '1px solid ' + (currentFilter === filter.value ? derivedColors.accent : derivedColors.border + '30')
                                }"
                                @click="setFilter(filter.value)"
                            >
                                <span class="truncate">{{ t(filter.label) }}</span>
                                <span v-if="filter.count > 0" class="ml-1 sm:ml-2 px-1.5 sm:px-2 py-0.5 text-xs rounded-full flex-shrink-0"
                                      :style="{ backgroundColor: currentFilter === filter.value ? derivedColors.background + '30' : derivedColors.accent + '30' }">
                                    {{ filter.count }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!filteredTokens || filteredTokens.length === 0" class="backdrop-blur-sm rounded-xl shadow-lg border p-8 sm:p-12 text-center" :style="cardStyle">
                        <h3 class="text-lg sm:text-xl font-semibold mb-2" :style="{ color: derivedColors.textPrimary }">
                            {{ t('noTokensFound') }}
                        </h3>
                        <p class="text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                            {{ t('tryDifferentFilter') }}
                        </p>
                    </div>

                    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        <div
                            v-for="token in filteredTokens"
                            :key="token.id"
                            class="backdrop-blur-sm rounded-xl shadow-lg border overflow-hidden transition-all duration-300 hover:scale-[1.02] hover:shadow-xl"
                            :style="cardStyle"
                        >
                            <div class="p-4 sm:p-6 pb-4">
                                <div v-if="token.is_featured" class="absolute top-3 sm:top-4 right-3 sm:right-4 px-2 sm:px-3 py-1 rounded-full text-xs font-bold" :style="{ backgroundColor: derivedColors.warning, color: derivedColors.background }">
                                    {{ t('featured') }}
                                </div>

                                <div class="flex items-start space-x-3 sm:space-x-4 mb-4">
                                    <div class="relative flex-shrink-0">
                                        <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-xl flex items-center justify-center shadow-lg" :style="{ background: `linear-gradient(135deg, ${derivedColors.accent}, ${derivedColors.secondary})` }">
                                            <span class="text-lg sm:text-2xl font-bold" :style="{ color: derivedColors.background }">
                                                {{ token.symbol?.slice(0, 2) || '??' }}
                                            </span>
                                        </div>
                                        <div v-if="isTokenSaleActive(token)" class="absolute -bottom-1 -right-1 w-4 h-4 sm:w-5 sm:h-5 rounded-full border-2 animate-pulse" :style="{ backgroundColor: derivedColors.success, borderColor: derivedColors.surface }"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-base sm:text-lg font-bold mb-1 truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ token.name }}
                                        </h3>
                                        <p class="text-sm font-mono mb-2 truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ token.symbol }}
                                        </p>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-lg sm:text-xl font-bold" :style="{ color: derivedColors.accent }">
                                                {{ currencySymbol }}{{ formatNumber(token.price) }}
                                            </span>
                                            <span class="text-xs" :style="{ color: derivedColors.textMuted }">
                                                {{ t('perToken') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="token.description" class="text-sm leading-relaxed mb-4 line-clamp-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ token.description }}
                                </p>
                            </div>

                            <div class="px-4 sm:px-6 pb-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">
                                        {{ t('progress') }}
                                    </span>
                                    <span class="text-sm font-bold" :style="{ color: derivedColors.accent }">
                                        {{ token.progress_percentage }}%
                                    </span>
                                </div>
                                <div class="relative h-2 rounded-full overflow-hidden" :style="{ backgroundColor: derivedColors.border + '40' }">
                                    <div class="absolute inset-0 rounded-full transition-all duration-1000" :style="{ width: Math.min(token.progress_percentage, 100) + '%', background: `linear-gradient(90deg, ${derivedColors.accent}, ${derivedColors.secondary})` }"></div>
                                </div>
                                <div class="flex justify-between text-xs mt-2" :style="{ color: derivedColors.textMuted }">
                                    <span class="truncate">{{ formatNumber(token.tokens_sold) }} {{ t('sold') }}</span>
                                    <span class="truncate">{{ formatNumber(token.tokens_remaining) }} {{ t('remaining') }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 p-4 sm:p-6 pt-4 border-t" :style="{ borderColor: derivedColors.border + '20' }">
                                <div class="text-center">
                                    <div class="text-base sm:text-lg font-bold break-all" :style="{ color: derivedColors.textPrimary }">
                                        {{ currencySymbol }}{{ formatNumber(token.total_raised) }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('raised') }}
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-base sm:text-lg font-bold" :style="{ color: getDaysRemainingColor(token.days_remaining) }">
                                        {{ getTimeRemaining(token.sale_end_date) || `${token.days_remaining}d` }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('timeLeft') }}
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 sm:p-6 pt-4">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 rounded-full" :class="isTokenSaleActive(token) ? 'animate-pulse' : ''" :style="{ backgroundColor: isTokenSaleActive(token) ? derivedColors.success : '#ef4444' }"></div>
                                        <span class="text-sm font-medium" :style="{ color: isTokenSaleActive(token) ? derivedColors.success : '#ef4444' }">
                                            {{ isTokenSaleActive(token) ? t('live') : t('ended') }}
                                        </span>
                                    </div>
                                    <div v-if="token.days_remaining <= 7 && token.days_remaining > 0" class="px-2 sm:px-3 py-1 rounded-full text-xs font-bold animate-pulse" :style="{ backgroundColor: '#ef4444' + '20', color: '#ef4444' }">
                                        {{ t('endingSoon') }}
                                    </div>
                                </div>

                                <button
                                    :disabled="!isTokenSaleActive(token) || token.tokens_remaining <= 0 || isProcessing"
                                    class="w-full py-3 px-4 sm:px-6 rounded-xl font-bold text-base sm:text-lg transition-all duration-300 transform disabled:opacity-50 disabled:cursor-not-allowed hover:scale-[1.02] active:scale-[0.98]"
                                    :style="{
                                        background: (!isTokenSaleActive(token) || token.tokens_remaining <= 0 || isProcessing) ? derivedColors.surface : `linear-gradient(135deg, ${derivedColors.accent}, ${derivedColors.secondary})`,
                                        color: (!isTokenSaleActive(token) || token.tokens_remaining <= 0 || isProcessing) ? derivedColors.textMuted : derivedColors.background,
                                        boxShadow: (!isTokenSaleActive(token) || token.tokens_remaining <= 0 || isProcessing) ? 'none' : `0 10px 30px ${derivedColors.accent}40`
                                    }"
                                    @click="openPurchaseModal(token)"
                                >
                                    {{ token.tokens_remaining <= 0 ? t('soldOut') : !isTokenSaleActive(token) ? t('saleEnded') : t('investNow') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 sm:space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-base sm:text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            <span class="truncate">{{ t('accountBalance') }}</span>
                        </h3>
                        <div class="text-center">
                            <div class="text-2xl sm:text-3xl font-bold mb-2 break-all" :style="{ color: derivedColors.accent }">
                                {{ currencySymbol }}{{ formatNumber(availableBalance) }}
                            </div>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('availableForInvestment') }}
                            </p>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-base sm:text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            <span class="truncate">{{ t('quickActions') }}</span>
                        </h3>
                        <div class="space-y-3">
                            <button
                                :disabled="isRefreshing"
                                class="w-full py-2 px-4 rounded-lg font-medium transition-all duration-300 flex items-center justify-center space-x-2 hover:scale-105 disabled:opacity-50"
                                :style="{ backgroundColor: derivedColors.surface + '80', color: derivedColors.textPrimary }"
                                @click="refreshData"
                            >
                                <svg
                                    :class="isRefreshing ? 'animate-spin' : ''"
                                    class="w-4 h-4 flex-shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>{{ isRefreshing ? t('refreshing') : t('refresh') }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-base sm:text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            <span class="truncate">{{ t('myStatistics') }}</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm truncate pr-2" :style="{ color: derivedColors.textMuted }">{{ t('totalInvested') }}:</span>
                                <span class="font-bold text-right break-all" :style="{ color: derivedColors.success }">{{ currencySymbol }}{{ formatNumber(statistics.total_invested) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm truncate pr-2" :style="{ color: derivedColors.textMuted }">{{ t('tokensOwned') }}:</span>
                                <span class="font-bold text-right" :style="{ color: derivedColors.secondary }">{{ formatNumber(statistics.total_tokens_purchased) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm truncate pr-2" :style="{ color: derivedColors.textMuted }">{{ t('successfulPurchases') }}:</span>
                                <span class="font-bold text-right" :style="{ color: derivedColors.accent }">{{ statistics.successful_purchases }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm truncate pr-2" :style="{ color: derivedColors.textMuted }">{{ t('pendingPurchases') }}:</span>
                                <span class="font-bold text-right" :style="{ color: derivedColors.warning }">{{ statistics.pending_purchases }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border overflow-hidden" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-2 sm:space-y-0">
                        <div>
                            <h2 class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('purchaseHistory') }}
                            </h2>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('trackTokenPurchases') }}
                            </p>
                        </div>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ myPurchases?.total || 0 }} {{ t('totalPurchases') }}
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px]">
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('token') }}
                            </th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('amount') }}
                            </th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('tokens') }}
                            </th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('price') }}
                            </th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('status') }}
                            </th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('date') }}
                            </th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('actions') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody class="divide-y" :style="{ borderColor: derivedColors.border + '50' }">
                        <tr v-if="!myPurchases?.data || myPurchases.data.length === 0">
                            <td colspan="7" class="px-4 sm:px-6 py-8 sm:py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                        {{ t('noPurchasesFound') }}
                                    </p>
                                    <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('makeFirstTokenPurchase') }}
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="purchase in myPurchases?.data"
                            :key="purchase.id"
                            class="hover:opacity-80 transition-colors"
                        >
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-2 sm:space-x-3">
                                    <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                        <span class="text-xs font-bold" :style="{ color: derivedColors.accent }">
                                            {{ purchase.ico_token?.symbol?.slice(0, 2) || '??' }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ purchase.ico_token?.name || t('notAvailable') }}
                                        </div>
                                        <div class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ purchase.ico_token?.symbol || t('notAvailable') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(purchase.amount_usd) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.accent }">
                                    {{ formatNumber(purchase.tokens_purchased) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ currencySymbol }}{{ formatNumber(purchase.token_price) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="getStatusBadgeClasses()"
                                    :style="{
                                        backgroundColor: getStatusBadgeColor(purchase.status) + '20',
                                        borderColor: getStatusBadgeColor(purchase.status) + '30',
                                        color: getStatusBadgeColor(purchase.status)
                                    }"
                                >
                                    {{ formatStatus(purchase.status) }}
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(purchase.created_at) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(purchase.created_at) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <button
                                    class="font-medium transition-colors duration-200"
                                    :style="{ color: derivedColors.accent }"
                                    @click="showPurchaseDetails(purchase)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="myPurchases?.data && myPurchases.data.length > 0 && myPurchases.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20' }"
                >
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: myPurchases.from || 0,
                            to: myPurchases.to || 0,
                            total: myPurchases.total || 0
                        }) }}
                        </div>
                        <div class="flex flex-wrap justify-center gap-1">
                            <button
                                v-for="(link, index) in myPurchases.links"
                                :key="index"
                                :disabled="!link.url"
                                :class="[
                                    'px-2 sm:px-3 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors',
                                    link.active ? 'cursor-default' : link.url ? 'hover:opacity-80' : 'cursor-not-allowed opacity-50'
                                ]"
                                :style="{
                                    backgroundColor: link.active ? derivedColors.accent : link.url ? derivedColors.surface : derivedColors.surface + '50',
                                    color: link.active ? derivedColors.background : link.url ? derivedColors.textSecondary : derivedColors.textMuted
                                }"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showPurchaseModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="purchaseModalTitleId"
            @click.self="closePurchaseModal"
            @keydown.escape="closePurchaseModal"
        >
            <div
                class="rounded-2xl shadow-2xl border max-w-md w-full backdrop-blur-md max-h-[90vh] overflow-y-auto"
                :style="{ backgroundColor: derivedColors.background + '95', borderColor: derivedColors.border + '50' }"
            >
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="purchaseModalTitleId"
                            class="text-lg sm:text-xl font-semibold"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('investNow') }}
                        </h3>
                        <button
                            class="p-1 transition-colors"
                            :style="{ color: derivedColors.textMuted }"
                            @click="closePurchaseModal"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <form
                        class="space-y-4 sm:space-y-6"
                        novalidate
                        @submit.prevent="submitPurchase"
                    >
                        <div v-if="selectedToken" class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                                <div class="text-center">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('token') }}
                                    </div>
                                    <div class="font-semibold truncate" :style="{ color: derivedColors.textPrimary }">
                                        {{ selectedToken.name }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.accent }">
                                        {{ selectedToken.symbol }}
                                    </div>
                                </div>
                                <div class="flex-shrink-0 mx-4 hidden sm:block">
                                    <svg
                                        class="w-6 h-6"
                                        :style="{ color: derivedColors.accent }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 8l4 4m0 0l-4 4m4-4H3"
                                        />
                                    </svg>
                                </div>
                                <div class="text-center">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('price') }}
                                    </div>
                                    <div class="font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ currencySymbol }}{{ formatNumber(selectedToken.price) }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.secondary }">
                                        {{ t('perToken') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-3" :style="{ color: derivedColors.textPrimary }">
                                {{ t('investmentAmount') }}
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 font-bold text-base sm:text-lg" :style="{ color: derivedColors.accent }">{{ currencySymbol }}</span>
                                <input
                                    v-model.number="purchaseForm.amount_usd"
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    :max="Math.min(availableBalance, selectedToken?.tokens_remaining * selectedToken?.price || 0)"
                                    class="w-full pl-10 sm:pl-12 pr-4 py-3 sm:py-4 border rounded-xl text-base sm:text-lg font-medium placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    placeholder="0.00"
                                    required
                                    autocomplete="off"
                                >
                            </div>
                            <div class="text-sm mt-2" :style="{ color: derivedColors.textMuted }">
                                {{ t('availableBalance') }}: {{ currencySymbol }}{{ formatNumber(availableBalance) }}
                            </div>
                        </div>

                        <div v-if="purchaseForm.amount_usd && selectedToken" class="rounded-xl p-4 border" :style="{ backgroundColor: derivedColors.accent + '10', borderColor: derivedColors.accent + '30' }">
                            <div class="text-center">
                                <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('tokensYoullGet') }}
                                </div>
                                <div class="text-lg sm:text-xl font-bold break-all" :style="{ color: derivedColors.accent }">
                                    {{ formatNumber(calculateTokensToReceive()) }}
                                </div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ selectedToken.symbol }}
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canPurchase || isProcessing"
                            class="w-full py-3 sm:py-4 px-4 sm:px-6 rounded-xl font-bold text-base sm:text-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-lg relative overflow-hidden group"
                            :style="{
                        backgroundColor: canPurchase ? derivedColors.accent : derivedColors.surface,
                        color: canPurchase ? derivedColors.background : derivedColors.textMuted
                    }"
                        >
                    <span
                        v-if="isProcessing"
                        class="flex items-center justify-center relative z-10"
                    >
                        <svg
                            class="animate-spin -ml-1 mr-3 h-5 w-5 sm:h-6 sm:w-6"
                            :style="{ color: derivedColors.background }"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ t('processing') }}
                    </span>
                            <span
                                v-else
                                class="relative z-10"
                            >{{ t('confirmPurchase') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showDetailsModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="detailsModalTitleId"
            @click.self="closeDetailsModal"
            @keydown.escape="closeDetailsModal"
        >
            <div class="rounded-2xl shadow-2xl border max-w-2xl w-full max-h-[90vh] overflow-y-auto backdrop-blur-md" :style="{ backgroundColor: derivedColors.background + '95', borderColor: derivedColors.border + '50' }">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3 :id="detailsModalTitleId" class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('purchaseDetails') }}
                        </h3>
                        <button class="p-1 transition-colors" :style="{ color: derivedColors.textMuted }" @click="closeDetailsModal">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div v-if="selectedPurchase" class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                    <div class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('purchaseInformation') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('token') }}</label>
                                <div class="flex items-center space-x-2 mt-1">
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                        <span class="text-xs font-bold" :style="{ color: derivedColors.accent }">
                                            {{ selectedPurchase.ico_token?.symbol?.slice(0, 2) || '??' }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ selectedPurchase.ico_token?.name || t('notAvailable') }}
                                        </p>
                                        <p class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ selectedPurchase.ico_token?.symbol || t('notAvailable') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                                <div class="mt-1">
                                    <span
                                        :class="getStatusBadgeClasses()"
                                        :style="{
                                            backgroundColor: getStatusBadgeColor(selectedPurchase.status) + '20',
                                            borderColor: getStatusBadgeColor(selectedPurchase.status) + '30',
                                            color: getStatusBadgeColor(selectedPurchase.status)
                                        }"
                                    >
                                        {{ formatStatus(selectedPurchase.status) }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('purchaseDate') }}</label>
                                <p class="font-medium text-sm" :style="{ color: derivedColors.textPrimary }">
                                    {{ formatDateTime(selectedPurchase.created_at) }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('tokenPrice') }}</label>
                                <p class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(selectedPurchase.token_price) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('purchaseSummary') }}
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('investmentAmount') }}:</span>
                                <span class="font-medium text-base sm:text-lg break-all" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(selectedPurchase.amount_usd) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('tokensPurchased') }}:</span>
                                <span class="font-bold text-lg sm:text-xl" :style="{ color: derivedColors.accent }">
                                    {{ formatNumber(selectedPurchase.tokens_purchased) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('pricePerToken') }}:</span>
                                <span class="font-medium break-all" :style="{ color: derivedColors.textSecondary }">
                                    {{ currencySymbol }}{{ formatNumber(selectedPurchase.token_price) }}
                                </span>
                            </div>
                            <div class="rounded-lg p-3 mt-4" :style="{ backgroundColor: derivedColors.border + '30' }">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-sm" :style="{ color: derivedColors.textMuted }">{{ t('totalValue') }}:</span>
                                    <span class="font-bold text-base sm:text-lg break-all" :style="{ color: derivedColors.success }">
                                        {{ currencySymbol }}{{ formatNumber(selectedPurchase.tokens_purchased * selectedPurchase.token_price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPurchase.transaction_id" class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('transactionDetails') }}
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('transactionId') }}</label>
                                <p class="font-mono text-xs sm:text-sm break-all" :style="{ color: derivedColors.accent }">
                                    {{ selectedPurchase.transaction_id }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('timeline') }}
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('created') }}:</span>
                                <span class="font-medium text-sm" :style="{ color: derivedColors.textPrimary }">{{ formatDateTime(selectedPurchase.created_at) }}</span>
                            </div>
                            <div v-if="selectedPurchase.confirmed_at" class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('confirmed') }}:</span>
                                <span class="font-medium text-sm" :style="{ color: derivedColors.success }">{{ formatDateTime(selectedPurchase.confirmed_at) }}</span>
                            </div>
                            <div v-if="selectedPurchase.completed_at" class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('completed') }}:</span>
                                <span class="font-medium text-sm" :style="{ color: derivedColors.success }">{{ formatDateTime(selectedPurchase.completed_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPurchase.admin_notes" class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('adminNotes') }}
                        </h4>
                        <p class="leading-relaxed text-sm" :style="{ color: derivedColors.textSecondary }">
                            {{ selectedPurchase.admin_notes }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end p-4 sm:p-6 border-t" :style="{ borderColor: derivedColors.border + '50' }">
                    <button
                        class="py-3 px-4 sm:px-6 rounded-xl font-medium transition-colors duration-200"
                        :style="{
                            backgroundColor: derivedColors.surface + '80',
                            color: derivedColors.textPrimary
                        }"
                        @click="closeDetailsModal"
                    >
                        {{ t('close') }}
                    </button>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
