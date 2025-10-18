<script setup>
import {computed, nextTick, onMounted, onUnmounted, reactive, ref, watch} from 'vue';
import {Link, router, usePage} from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import {useToast} from "@/composables/useToast.js";
import {useTranslation} from '@/composables/useTranslation';

const props = defineProps({
    trades: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    symbols: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    statuses: {
        type: Array,
        default: () => ['active', 'won', 'lost', 'cancelled', 'expired'],
        validator: (value) => Array.isArray(value)
    },
    stats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    userBalance: {
        type: Number,
        default: 0
    }
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
const selectedTrade = ref(null);
const refreshing = ref(false);
const isCancelling = ref(false);
const tradeToCancel = ref(null);
const filterForm = reactive({
    search: props.filters.search || '',
    symbol: props.filters.symbol || '',
    direction: props.filters.direction || '',
    status: props.filters.status || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    per_page: props.filters.per_page || '10'
});
const modalTitleId = computed(() => 'modal-title-' + Math.random().toString(36).substr(2, 9));
const cancelModalTitleId = computed(() => 'cancel-modal-title-' + Math.random().toString(36).substr(2, 9));
const applyFilters = () => {
    const cleanFilters = Object.fromEntries(
        Object.entries(filterForm).filter(([key, value]) => value !== '')
    );

    router.get('/user/trading/history', cleanFilters, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    Object.assign(filterForm, {
        search: '',
        symbol: '',
        direction: '',
        status: '',
        start_date: '',
        end_date: '',
        per_page: '10'
    });
    applyFilters();
};

const changePage = (url) => {
    if (!url) return;

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true
    });
};

const viewTrade = async (trade) => {
    selectedTrade.value = trade;

    await nextTick();
    const modal = document.querySelector('[role="dialog"]');
    if (modal) {
        modal.focus();
    }
};

const closeModal = () => {
    selectedTrade.value = null;
};

const canCancelTrade = (trade) => {
    if (!trade || trade.status !== 'active') return false;

    try {
        const openTime = new Date(trade.open_time);
        const now = new Date();
        const timeDiff = (now - openTime) / 1000;
        return timeDiff <= 30;
    } catch (error) {
        console.error('Error checking trade cancellation eligibility:', error);
        return false;
    }
};

const showCancelModal = (trade) => {
    tradeToCancel.value = trade;
};


const closeCancelModal = () => {
    tradeToCancel.value = null;
};

const confirmCancelTrade = () => {
    if (!tradeToCancel.value || isCancelling.value) return;

    isCancelling.value = true;
    const trade = tradeToCancel.value;

    router.post(`/user/trading/${trade.id}/cancel`, {}, {
        onSuccess: () => {
            selectedTrade.value = null;
            tradeToCancel.value = null;
            isCancelling.value = false;
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).flat();
            isCancelling.value = false;
        }
    });
};

const refreshTrades = () => {
    if (refreshing.value) return;

    refreshing.value = true;

    router.reload({
        onFinish: () => {
            refreshing.value = false;
            showToast(t('tradeHistoryRefreshed'), 'success');
        },
        onError: () => {
            refreshing.value = false;
            showToast(t('failedToRefreshHistory'), 'error');
        }
    });
};

const formatStatus = (status) => {
    const statusMap = {
        'active': t('active'),
        'won': t('won'),
        'lost': t('lost'),
        'cancelled': t('cancelled'),
        'expired': t('expired')
    };
    return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
};

const getStatusBadgeClasses = (status) => {
    return 'inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full border';
};

const getStatusBadgeStyle = (status) => {
    switch (status?.toLowerCase()) {
        case 'won':
            return {
                backgroundColor: derivedColors.value.success + '20',
                borderColor: derivedColors.value.success + '30',
                color: derivedColors.value.textPrimary
            };
        case 'lost':
            return {
                backgroundColor: derivedColors.value.danger + '20',
                borderColor: derivedColors.value.danger + '30',
                color: '#ef4444'
            };
        case 'active':
            return {
                backgroundColor: '#3b82f620',
                borderColor: '#3b82f630',
                color: '#60a5fa'
            };
        case 'cancelled':
            return {
                backgroundColor: derivedColors.value.surface + '50',
                borderColor: derivedColors.value.border + '30',
                color: derivedColors.value.textMuted
            };
        case 'expired':
            return {
                backgroundColor: derivedColors.value.warning + '20',
                borderColor: derivedColors.value.warning + '30',
                color: derivedColors.value.warning
            };
        default:
            return {
                backgroundColor: derivedColors.value.surface + '50',
                borderColor: derivedColors.value.border + '30',
                color: derivedColors.value.textMuted
            };
    }
};

const getDirectionBadgeStyle = (direction) => {
    switch (direction?.toLowerCase()) {
        case 'up':
            return {
                backgroundColor: derivedColors.value.success + '20',
                borderColor: derivedColors.value.success + '30',
                color: derivedColors.value.success
            };
        case 'down':
            return {
                backgroundColor: derivedColors.value.danger + '20',
                borderColor: derivedColors.value.danger + '30',
                color: derivedColors.value.danger
            };
        default:
            return {
                backgroundColor: derivedColors.value.surface + '50',
                borderColor: derivedColors.value.border + '30',
                color: derivedColors.value.textMuted
            };
    }
};

const getProfitLossColor = (profitLoss) => {
    const amount = parseFloat(profitLoss) || 0;
    if (amount > 0) return derivedColors.value.success;
    if (amount < 0) return derivedColors.value.danger;
    return derivedColors.value.textSecondary;
};

const getProfitLossPrefix = (profitLoss) => {
    const amount = parseFloat(profitLoss) || 0;
    if (amount > 0) return '+';
    if (amount < 0) return '-';
    return '';
};

const formatNumber = (num) => {
    const number = parseFloat(num) || 0;
    if (number >= 1000000000) {
        return (number / 1000000000).toFixed(2) + 'B';
    } else if (number >= 1000000) {
        return (number / 1000000).toFixed(2) + 'M';
    } else if (number >= 1000) {
        return (number / 1000).toFixed(2) + 'K';
    } else if (Number.isInteger(number)) {
        return number.toString();
    } else {
        return number.toFixed(2);
    }
};

const formatPrice = (price) => {
    const number = parseFloat(price) || 0;
    return number.toFixed(6);
};

const formatDuration = (duration) => {
    if (!duration) return t('notAvailable');

    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;

    if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
    }
    return `${seconds}s`;
};

const formatDate = (timestamp) => {
    if (!timestamp) return t('notAvailable');
    try {
        return new Date(timestamp).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        return t('invalidDate');
    }
};

const formatTime = (timestamp) => {
    if (!timestamp) return t('notAvailable');
    try {
        return new Date(timestamp).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    } catch (error) {
        return t('invalidTime');
    }
};

const getPaginationLabel = (link) => {
    if (link.label.includes('Previous')) {
        return t('goToPreviousPage');
    }
    if (link.label.includes('Next')) {
        return t('goToNextPage');
    }
    if (link.active) {
        return t('currentPageNumber', { page: link.label });
    }
    if (link.url) {
        return t('goToPageNumber', { page: link.label });
    }
    return link.label;
};

const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        if (tradeToCancel.value) {
            closeCancelModal();
            return;
        }
        if (selectedTrade.value) {
            closeModal();
            return;
        }
    }

    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        refreshTrades();
    }
};

const handleModalKeydown = (event) => {
    if (event.key !== 'Tab') return;

    const modal = document.querySelector('[role="dialog"]');
    if (!modal) return;

    const focusableElements = modal.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (event.shiftKey) {
        if (document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
        }
    } else {
        if (document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    }
};

onMounted(() => {
    const flash = page.props.flash;

    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');

    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('keydown', handleModalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('keydown', handleModalKeydown);
});

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        Object.assign(filterForm, {
            search: newFilters.search || '',
            symbol: newFilters.symbol || '',
            direction: newFilters.direction || '',
            status: newFilters.status || '',
            start_date: newFilters.start_date || '',
            end_date: newFilters.end_date || '',
            per_page: newFilters.per_page || '10'
        });
    }
}, { deep: true });
</script>

<template>
    <UserLayout
        :page-title="t('tradeHistory')"
        :page-section="t('trading')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                role="region"
                :aria-label="t('tradingStatistics')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalTrades')"
                    >
                        {{ stats?.total_trades || 0 }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalTrades') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.success }"
                        :aria-label="t('winRate')"
                    >
                        {{ stats?.win_rate || 0 }}%
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('winRate') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: (stats?.total_profit || 0) >= 0 ? derivedColors.success : derivedColors.danger }"
                        :aria-label="t('totalProfitLoss')"
                    >
                        {{ currencySymbol }}{{ formatNumber(stats?.total_profit || 0) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalPL') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalVolume')"
                    >
                        {{ currencySymbol }}{{ formatNumber(stats?.total_volume || 0) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalVolume') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <h2 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('filters') }}
                </h2>
                <form class="space-y-4" @submit.prevent="applyFilters">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('search') }}</label>
                            <input
                                v-model="filterForm.search"
                                type="text"
                                :placeholder="t('tradeIdSymbolPlaceholder')"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('symbol') }}</label>
                            <select
                                v-model="filterForm.symbol"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                                <option value="">{{ t('allSymbols') }}</option>
                                <option
                                    v-for="symbol in symbols"
                                    :key="symbol"
                                    :value="symbol"
                                >
                                    {{ symbol }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('direction') }}</label>
                            <select
                                v-model="filterForm.direction"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                                <option value="">{{ t('allDirections') }}</option>
                                <option value="up">{{ t('call') }}</option>
                                <option value="down">{{ t('put') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('status') }}</label>
                            <select
                                v-model="filterForm.status"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                                <option value="">{{ t('all') }}</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ formatStatus(status) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('startDate') }}</label>
                            <input
                                v-model="filterForm.start_date"
                                type="date"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('endDate') }}</label>
                            <input
                                v-model="filterForm.end_date"
                                type="date"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                        <button
                            type="submit"
                            class="py-2 px-4 rounded-lg font-medium transition-colors duration-200"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.background
                            }"
                        >
                            {{ t('applyFilters') }}
                        </button>
                        <button
                            type="button"
                            class="py-2 px-4 rounded-lg transition-colors duration-200"
                            :style="{
                                backgroundColor: derivedColors.surface,
                                color: derivedColors.textPrimary
                            }"
                            @click="clearFilters"
                        >
                            {{ t('clear') }}
                        </button>
                        <button
                            :disabled="refreshing"
                            :aria-label="refreshing ? t('refreshingTrades') : t('refreshTrades')"
                            class="hover:opacity-80 disabled:opacity-50 transition-colors duration-200 px-4 py-2 flex items-center"
                            :style="{ color: derivedColors.accent }"
                            @click="refreshTrades"
                        >
                            <svg
                                :class="refreshing ? 'animate-spin' : ''"
                                class="w-5 h-5 mr-1"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                            <span class="hidden sm:inline">{{ t('refresh') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-2 sm:space-y-0">
                        <h2 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('tradingHistory') }}
                        </h2>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ trades.total || 0 }} {{ t('totalTradesCount') }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="trades.data && trades.data.length > 0"
                    class="overflow-x-auto"
                >
                    <!-- Mobile Cards View -->
                    <div class="block xl:hidden">
                        <div
                            v-for="(trade, index) in trades.data"
                            :key="trade.id"
                            class="border-b p-4 space-y-4 hover:opacity-80 transition-colors duration-200"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <!-- Trade Header -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium font-mono" :style="{ color: derivedColors.textPrimary }">
                                        {{ trade.trade_id }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(trade.open_time) }} {{ formatTime(trade.open_time) }}
                                    </div>
                                </div>
                                <span
                                    :class="getStatusBadgeClasses(trade.status)"
                                    :style="getStatusBadgeStyle(trade.status)"
                                    class="text-xs px-2 py-1 rounded-full"
                                >
                        {{ formatStatus(trade.status) }}
                    </span>
                            </div>

                            <!-- Trade Details Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('symbol') }}
                                    </div>
                                    <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ trade.symbol }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('direction') }}
                                    </div>
                                    <span
                                        :class="getStatusBadgeClasses(trade.direction)"
                                        :style="getDirectionBadgeStyle(trade.direction)"
                                        class="text-xs px-2 py-1 rounded-full"
                                    >
                            {{ trade.direction === 'up' ? t('call') : t('put') }}
                        </span>
                                </div>
                            </div>

                            <!-- Amount & Payout Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('amount') }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ currencySymbol }}{{ formatNumber(trade.amount) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('payoutRate') }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.success }">
                                        {{ trade.payout_rate }}%
                                    </div>
                                </div>
                            </div>

                            <!-- Profit/Loss Section -->
                            <div class="bg-opacity-50 rounded-lg p-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('profitLoss') }}
                                </div>
                                <div
                                    class="text-lg font-bold"
                                    :style="{ color: getProfitLossColor(trade.profit_loss) }"
                                >
                                    {{ getProfitLossPrefix(trade.profit_loss) }}{{ currencySymbol }}{{ formatNumber(Math.abs(trade.profit_loss || 0)) }}
                                </div>
                            </div>

                            <!-- Actions Row -->
                            <div class="flex justify-between items-center pt-2">
                                <div class="flex space-x-2">
                                    <button
                                        :aria-label="t('viewDetailsFor', { id: trade.trade_id })"
                                        class="text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-3 py-2"
                                        :style="{
                                color: derivedColors.accent,
                                backgroundColor: derivedColors.accent + '10',
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                                        @click="viewTrade(trade)"
                                    >
                                        {{ t('viewDetails') }}
                                    </button>

                                    <button
                                        v-if="canCancelTrade(trade)"
                                        :aria-label="t('cancelTradeFor', { id: trade.trade_id })"
                                        class="text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-3 py-2"
                                        :style="{
                                color: derivedColors.danger,
                                backgroundColor: derivedColors.danger + '10',
                                ':focus': { ringColor: derivedColors.danger }
                            }"
                                        @click="showCancelModal(trade)"
                                    >
                                        {{ t('cancel') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <table
                        class="w-full min-w-[800px] hidden xl:table"
                        role="table"
                        :aria-label="t('tradingHistoryTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('tradeId') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('symbol') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('direction') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('amount') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('payoutRate') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('profitLoss') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('date') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('actions') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody
                            class="divide-y"
                            :style="{ borderColor: derivedColors.border + '50' }"
                            role="rowgroup"
                        >
                        <tr
                            v-for="(trade, index) in trades.data"
                            :key="trade.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:opacity-80 transition-colors duration-200 focus-within:opacity-80"
                        >
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium font-mono truncate" :style="{ color: derivedColors.textPrimary }">
                                    {{ trade.trade_id }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ trade.symbol }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                    <span
                        :class="getStatusBadgeClasses(trade.direction)"
                        :style="getDirectionBadgeStyle(trade.direction)"
                        class="text-xs px-2 py-1 rounded-full"
                    >
                        {{ trade.direction === 'up' ? t('call') : t('put') }}
                    </span>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm" role="cell">
                                <div class="font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(trade.amount) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-semibold" :style="{ color: derivedColors.success }">
                                    {{ trade.payout_rate }}%
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                    <span
                        :class="getStatusBadgeClasses(trade.status)"
                        :style="getStatusBadgeStyle(trade.status)"
                        class="text-xs px-2 py-1 rounded-full"
                    >
                        {{ formatStatus(trade.status) }}
                    </span>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div
                                    class="text-xs sm:text-sm font-semibold"
                                    :style="{ color: getProfitLossColor(trade.profit_loss) }"
                                >
                                    {{ getProfitLossPrefix(trade.profit_loss) }}{{ currencySymbol }}{{ formatNumber(Math.abs(trade.profit_loss || 0)) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm" role="cell">
                                <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(trade.open_time) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(trade.open_time) }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-xs sm:text-sm" role="cell">
                                <div class="flex flex-col sm:flex-row space-y-1 sm:space-y-0 sm:space-x-2">
                                    <button
                                        :aria-label="t('viewDetailsFor', { id: trade.trade_id })"
                                        class="font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1 text-xs"
                                        :style="{
                                color: derivedColors.accent,
                                ':hover': { opacity: '0.8' },
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                                        @click="viewTrade(trade)"
                                    >
                                        {{ t('viewDetails') }}
                                    </button>

                                    <button
                                        v-if="canCancelTrade(trade)"
                                        :aria-label="t('cancelTradeFor', { id: trade.trade_id })"
                                        class="font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1 text-xs"
                                        :style="{
                                color: derivedColors.danger,
                                ':hover': { opacity: '0.8' },
                                ':focus': { ringColor: derivedColors.danger }
                            }"
                                        @click="showCancelModal(trade)"
                                    >
                                        {{ t('cancel') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-8 sm:py-12 px-4">
                    <div class="flex flex-col items-center">
                        <svg
                            class="w-10 h-10 sm:w-12 sm:h-12 mb-4"
                            :style="{ color: derivedColors.textMuted }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                        <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                            {{ t('noTradesFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('noTradesYet') }}
                        </p>
                        <Link
                            href="/user/trading/live"
                            class="mt-4 py-3 px-6 rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                            :style="{
                    backgroundColor: derivedColors.accent,
                    color: derivedColors.background,
                    ':focus': { ringColor: derivedColors.accent }
                }"
                        >
                            {{ t('startTrading') }}
                        </Link>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="trades.data && trades.data.length > 0 && trades.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm order-2 sm:order-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: trades.from || 0,
                            to: trades.to || 0,
                            total: trades.total || 0
                        }) }}
                        </div>
                        <nav
                            class="flex flex-wrap gap-1 justify-center sm:justify-end order-1 sm:order-2"
                            role="navigation"
                            :aria-label="t('pagination')"
                        >
                            <button
                                v-for="(link, index) in trades.links"
                                :key="index"
                                :disabled="!link.url"
                                :aria-current="link.active ? 'page' : null"
                                :aria-label="getPaginationLabel(link)"
                                :class="[
                        'px-2 sm:px-3 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 min-w-[2rem] flex-shrink-0',
                        link.active ? 'cursor-default' : link.url ? 'hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                                :style="{
                        backgroundColor: link.active ? derivedColors.accent : link.url ? derivedColors.surface : derivedColors.surface + '50',
                        color: link.active ? derivedColors.background : link.url ? derivedColors.textSecondary : derivedColors.textMuted,
                        ':focus': { ringColor: derivedColors.accent }
                    }"
                                @click="changePage(link.url)"
                                v-html="link.label"
                            />
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="selectedTrade"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="modalTitleId"
            @click.self="closeModal"
            @keydown.escape="closeModal"
        >
            <div
                class="rounded-xl shadow-2xl border max-w-4xl w-full max-h-[90vh] overflow-y-auto focus:outline-none"
                :style="{ backgroundColor: derivedColors.background, borderColor: derivedColors.border + '20' }"
                tabindex="-1"
            >
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="modalTitleId"
                            class="text-lg sm:text-xl font-semibold"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('tradeDetails') }} - {{ selectedTrade.trade_id }}
                        </h3>
                        <button
                            :aria-label="t('closeModal')"
                            class="hover:opacity-80 transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded p-1"
                            :style="{
                                color: derivedColors.textMuted,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                            @click="closeModal"
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

                <div class="p-4 sm:p-6 space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('tradeId') }}</label>
                            <div class="font-mono text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedTrade.trade_id }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('symbol') }}</label>
                            <div class="font-semibold text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedTrade.symbol }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('direction') }}</label>
                            <span
                                :class="getStatusBadgeClasses(selectedTrade.direction)"
                                :style="getDirectionBadgeStyle(selectedTrade.direction)"
                                class="inline-block px-3 py-1 rounded-full text-sm"
                            >
                                {{ selectedTrade.direction === 'up' ? t('callUp') : t('putDown') }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('amount') }}</label>
                            <div class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ currencySymbol }}{{ formatNumber(selectedTrade.amount) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('duration') }}</label>
                            <div class="text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedTrade.duration_formatted || formatDuration(selectedTrade.duration) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('payoutRate') }}</label>
                            <div class="font-semibold text-sm sm:text-base" :style="{ color: derivedColors.success }">
                                {{ selectedTrade.payout_rate }}%
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('openPrice') }}</label>
                            <div class="font-mono text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ currencySymbol }}{{ formatPrice(selectedTrade.open_price) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('closePrice') }}</label>
                            <div class="font-mono text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedTrade.close_price ? currencySymbol + formatPrice(selectedTrade.close_price) : t('notAvailable') }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                            <span
                                :class="getStatusBadgeClasses(selectedTrade.status)"
                                :style="getStatusBadgeStyle(selectedTrade.status)"
                                class="inline-block px-3 py-1 rounded-full text-sm"
                            >
                                {{ formatStatus(selectedTrade.status) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('profitLoss') }}</label>
                            <div
                                class="text-xl sm:text-2xl font-bold"
                                :style="{ color: getProfitLossColor(selectedTrade.profit_loss) }"
                            >
                                {{ getProfitLossPrefix(selectedTrade.profit_loss) }}{{ currencySymbol }}{{ formatNumber(Math.abs(selectedTrade.profit_loss || 0)) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('openTime') }}</label>
                            <div class="text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                <div>{{ formatDate(selectedTrade.open_time) }}</div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(selectedTrade.open_time) }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('closeTime') }}</label>
                            <div class="text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                <div>{{ selectedTrade.close_time ? formatDate(selectedTrade.close_time) : t('notAvailable') }}</div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ selectedTrade.close_time ? formatTime(selectedTrade.close_time) : '' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-end pt-4 border-t space-y-3 sm:space-y-0 sm:space-x-3" :style="{ borderColor: derivedColors.border + '20' }">
                        <button
                            v-if="canCancelTrade(selectedTrade)"
                            class="w-full sm:w-auto px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50"
                            :style="{
                                backgroundColor: derivedColors.danger,
                                color: derivedColors.textPrimary,
                                ':focus': { ringColor: derivedColors.danger }
                            }"
                            @click="showCancelModal(selectedTrade)"
                        >
                            {{ t('cancelTrade') }}
                        </button>
                        <button
                            class="w-full sm:w-auto px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50"
                            :style="{
                                backgroundColor: derivedColors.surface,
                                color: derivedColors.textPrimary,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                            @click="closeModal"
                        >
                            {{ t('close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="tradeToCancel"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="cancelModalTitleId"
            @click.self="closeCancelModal"
        >
            <div
                class="rounded-xl shadow-2xl border w-full max-w-md focus:outline-none"
                :style="{ backgroundColor: derivedColors.background, borderColor: derivedColors.border + '20' }"
                @click.stop
            >
                <!-- Header -->
                <div class="flex items-center justify-between p-4 sm:p-6 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <h3
                        :id="cancelModalTitleId"
                        class="text-base sm:text-lg font-semibold flex items-center"
                        :style="{ color: derivedColors.textPrimary }"
                    >
                        <svg
                            class="w-5 h-5 mr-3"
                            :style="{ color: derivedColors.danger }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"
                            />
                        </svg>
                        {{ t('cancelTrade') }}
                    </h3>
                    <button
                        class="hover:opacity-80 transition-colors duration-200 p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50"
                        :style="{
                            color: derivedColors.textMuted,
                            ':focus': { ringColor: derivedColors.accent }
                        }"
                        @click="closeCancelModal"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
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

                <div class="p-4 sm:p-6">
                    <div v-if="tradeToCancel" class="mb-6">
                        <div class="rounded-xl p-4 mb-4 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('asset') }}</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ tradeToCancel.symbol }}</span>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('direction') }}</span>
                                    <span
                                        class="font-medium px-2 py-1 rounded text-xs"
                                        :style="{
                                            backgroundColor: tradeToCancel.direction === 'up' ? derivedColors.success + '20' : derivedColors.danger + '20',
                                            color: tradeToCancel.direction === 'up' ? derivedColors.success : derivedColors.danger
                                        }"
                                    >
                                        {{ tradeToCancel.direction === 'up' ? t('callUp') : t('putDown') }}
                                    </span>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('amount') }}</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ currencySymbol }}{{ formatNumber(tradeToCancel.amount) }}</span>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('duration') }}</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ formatDuration(tradeToCancel.duration_seconds) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl p-4 mb-4 border" :style="{ backgroundColor: derivedColors.danger + '20', borderColor: derivedColors.danger + '50' }">
                            <div class="flex items-start space-x-3">
                                <svg
                                    class="w-5 h-5 mt-0.5 flex-shrink-0"
                                    :style="{ color: derivedColors.danger }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"
                                    />
                                </svg>
                                <div>
                                    <h4 class="font-medium mb-1" :style="{ color: derivedColors.danger }">
                                        {{ t('confirmCancellation') }}
                                    </h4>
                                    <p class="text-sm" :style="{ color: derivedColors.danger }">
                                        {{ t('cancelTradeWarning') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl p-4 border" :style="{ backgroundColor: derivedColors.warning + '20', borderColor: derivedColors.warning + '50' }">
                            <div class="flex items-center space-x-2">
                                <svg
                                    class="w-4 h-4"
                                    :style="{ color: derivedColors.warning }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.warning }">
                                    {{ t('cancellationTimeLimit') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                        <button
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50"
                            :style="{
                                backgroundColor: derivedColors.surface,
                                borderColor: derivedColors.border,
                                color: derivedColors.textSecondary,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                            @click="closeCancelModal"
                        >
                            {{ t('keepTrade') }}
                        </button>
                        <button
                            :disabled="isCancelling"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            :style="{
                                backgroundColor: derivedColors.danger,
                                color: derivedColors.textPrimary,
                                ':focus': { ringColor: derivedColors.danger }
                            }"
                            @click="confirmCancelTrade"
                        >
                            <svg
                                v-if="isCancelling"
                                class="animate-spin w-4 h-4"
                                fill="none"
                                viewBox="0 0 24 24"
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            <span>{{ isCancelling ? t('cancelling') : t('yesCancelTrade') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
