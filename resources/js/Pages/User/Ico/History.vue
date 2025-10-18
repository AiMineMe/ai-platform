<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    purchases: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    tokens: {
        type: Array,
        default: () => []
    },
    statuses: {
        type: Array,
        default: () => ['completed', 'pending', 'failed', 'cancelled']
    },
    stats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    }
});

const selectedPurchase = ref(null);
const refreshing = ref(false);
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
const filterForm = reactive({
    search: props.filters.search || '',
    token: props.filters.token || '',
    status: props.filters.status || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    per_page: props.filters.per_page || '10'
});

const modalTitleId = computed(() => 'modal-title-' + Math.random().toString(36).substr(2, 9));
const applyFilters = () => {
    const cleanFilters = Object.fromEntries(
        Object.entries(filterForm).filter(([key, value]) => value !== '')
    );

    router.get('/user/investment/purchases', cleanFilters, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    Object.assign(filterForm, {
        search: '',
        token: '',
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

const viewPurchase = async (purchase) => {
    selectedPurchase.value = purchase;

    await nextTick();
    const modal = document.querySelector('[role="dialog"]');
    if (modal) {
        modal.focus();
    }
};

const closeModal = () => {
    selectedPurchase.value = null;
};

const refreshPurchases = () => {
    if (refreshing.value) return;

    refreshing.value = true;

    router.reload({
        onFinish: () => {
            refreshing.value = false;
            showToast(t('purchaseHistoryRefreshed'), 'success');
        },
        onError: () => {
            refreshing.value = false;
            showToast(t('failedToRefreshHistory'), 'error');
        }
    });
};

const formatStatus = (status) => {
    const statusMap = {
        'completed': t('completed'),
        'pending': t('pending'),
        'failed': t('failed'),
        'cancelled': t('cancelled')
    };
    return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
};

const getStatusBadgeClasses = (status) => {
    const baseClasses = 'inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full border';

    switch (status?.toLowerCase()) {
        case 'completed':
            return `${baseClasses} text-white border-opacity-30`;
        case 'pending':
            return `${baseClasses} text-white border-opacity-30`;
        case 'failed':
            return `${baseClasses} text-red-300 border-red-500/30`;
        case 'cancelled':
            return `${baseClasses} text-gray-300 border-gray-500/30`;
        default:
            return `${baseClasses} text-gray-300 border-gray-500/30`;
    }
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
    if (event.key === 'Escape' && selectedPurchase.value) {
        closeModal();
        return;
    }

    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        refreshPurchases();
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
            token: newFilters.token || '',
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
        :page-title="t('icoPurchaseHistory')"
        :page-section="t('ico')"
    >
        <div class="max-w-none mx-auto space-y-4 sm:space-y-6 px-2 sm:px-4 lg:px-6">
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                role="region"
                :aria-label="t('icoPurchaseStatistics')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalPurchases')"
                    >
                        {{ stats?.total_purchases || 0 }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalPurchases') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.success }"
                        :aria-label="t('uniqueTokens')"
                    >
                        {{ stats?.unique_tokens || 0 }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('uniqueTokens') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalInvested')"
                    >
                        {{ currencySymbol }}{{ formatNumber(stats?.total_invested || 0) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalInvested') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalTokens')"
                    >
                        {{ formatNumber(stats?.total_tokens || 0) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalTokens') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-3 sm:p-4 lg:p-6" :style="cardStyle">
                <h2 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('filters') }}
                </h2>
                <form class="space-y-3 sm:space-y-4" @submit.prevent="applyFilters">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('search') }}</label>
                            <input
                                v-model="filterForm.search"
                                type="text"
                                :placeholder="t('purchaseIdTokenPlaceholder')"
                                class="w-full px-2 sm:px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-xs sm:text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('token') }}</label>
                            <select
                                v-model="filterForm.token"
                                class="w-full px-2 sm:px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-xs sm:text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                                <option value="">{{ t('allTokens') }}</option>
                                <option
                                    v-for="token in tokens"
                                    :key="token.id"
                                    :value="token.id"
                                >
                                    {{ token.name }} ({{ token.symbol }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('status') }}</label>
                            <select
                                v-model="filterForm.status"
                                class="w-full px-2 sm:px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-xs sm:text-sm"
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
                            <label class="block text-xs sm:text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('startDate') }}</label>
                            <input
                                v-model="filterForm.start_date"
                                type="date"
                                class="w-full px-2 sm:px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-xs sm:text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('endDate') }}</label>
                            <input
                                v-model="filterForm.end_date"
                                type="date"
                                class="w-full px-2 sm:px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-xs sm:text-sm"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2">
                        <button
                            type="submit"
                            class="py-2 px-3 sm:px-4 rounded-lg font-medium transition-colors duration-200 text-xs sm:text-sm"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.background
                            }"
                        >
                            {{ t('applyFilters') }}
                        </button>
                        <button
                            type="button"
                            class="py-2 px-3 sm:px-4 rounded-lg transition-colors duration-200 text-xs sm:text-sm"
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
                            :aria-label="refreshing ? t('refreshingPurchases') : t('refreshPurchases')"
                            class="hover:opacity-80 disabled:opacity-50 transition-colors duration-200 px-3 sm:px-4 py-2 text-xs sm:text-sm flex items-center"
                            :style="{ color: derivedColors.accent }"
                            @click="refreshPurchases"
                        >
                            <svg
                                :class="refreshing ? 'animate-spin' : ''"
                                class="w-3 sm:w-4 h-3 sm:h-4 mr-1 flex-shrink-0"
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
                <div class="px-3 sm:px-4 lg:px-6 py-3 sm:py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-2 sm:space-y-0">
                        <h2 class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('purchaseHistory') }}
                        </h2>
                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ purchases.total || 0 }} {{ t('totalPurchasesCount') }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="purchases.data && purchases.data.length > 0"
                    class="block sm:hidden"
                >
                    <div class="divide-y" :style="{ borderColor: derivedColors.border + '50' }">
                        <div
                            v-for="(purchase, index) in purchases.data"
                            :key="purchase.id"
                            class="p-4 hover:opacity-80 transition-colors duration-200"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <div class="h-6 w-6 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-bold text-xs">{{ purchase.token.symbol?.slice(0, 2) || '??' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ purchase.token.name }}
                                        </div>
                                        <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ purchase.token.symbol }}
                                        </div>
                                    </div>
                                </div>
                                <span
                                    :class="getStatusBadgeClasses(purchase.status)"
                                    :style="{
                                        backgroundColor: purchase.status === 'completed' ? derivedColors.success + '20' :
                                                       purchase.status === 'pending' ? derivedColors.warning + '20' :
                                                       purchase.status === 'failed' ? '#ef444420' :
                                                       derivedColors.surface + '50',
                                        borderColor: purchase.status === 'completed' ? derivedColors.success + '30' :
                                                   purchase.status === 'pending' ? derivedColors.warning + '30' :
                                                   purchase.status === 'failed' ? '#ef444450' :
                                                   derivedColors.border + '30'
                                    }"
                                    class="text-xs px-2 py-1 rounded-full"
                                >
                                    {{ formatStatus(purchase.status) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <div :style="{ color: derivedColors.textMuted }">{{ t('amountInvested') }}</div>
                                    <div class="font-semibold" :style="{ color: derivedColors.success }">
                                        {{ currencySymbol }}{{ formatNumber(purchase.amount_usd) }}
                                    </div>
                                </div>
                                <div>
                                    <div :style="{ color: derivedColors.textMuted }">{{ t('tokensPurchased') }}</div>
                                    <div class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ formatNumber(purchase.tokens_purchased) }}
                                    </div>
                                </div>
                                <div>
                                    <div :style="{ color: derivedColors.textMuted }">{{ t('tokenPrice') }}</div>
                                    <div :style="{ color: derivedColors.textSecondary }">
                                        {{ currencySymbol }}{{ purchase.token_price }}
                                    </div>
                                </div>
                                <div>
                                    <div :style="{ color: derivedColors.textMuted }">{{ t('date') }}</div>
                                    <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(purchase.purchased_at || purchase.created_at) }}</div>
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-t" :style="{ borderColor: derivedColors.border + '20' }">
                                <div class="flex justify-between items-center">
                                    <div class="text-xs font-mono truncate pr-2" :style="{ color: derivedColors.textMuted }">
                                        {{ purchase.purchase_id }}
                                    </div>
                                    <button
                                        :aria-label="t('viewDetailsFor', { id: purchase.purchase_id })"
                                        class="font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1 text-xs"
                                        :style="{
                                            color: derivedColors.accent,
                                            ':hover': { opacity: '0.8' },
                                            ':focus': { ringColor: derivedColors.accent }
                                        }"
                                        @click="viewPurchase(purchase)"
                                    >
                                        {{ t('viewDetails') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="purchases.data && purchases.data.length > 0"
                    class="hidden sm:block overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[800px]"
                        role="table"
                        :aria-label="t('purchaseHistoryTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('purchaseId') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('token') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('amountInvested') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('tokensPurchased') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('tokenPrice') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('date') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
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
                            v-for="(purchase, index) in purchases.data"
                            :key="purchase.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:opacity-80 transition-colors duration-200 focus-within:opacity-80"
                        >
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium font-mono truncate" :style="{ color: derivedColors.textPrimary }">
                                    {{ purchase.purchase_id }}
                                </div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="flex items-center space-x-2 lg:space-x-3">
                                    <div class="h-6 w-6 lg:h-8 lg:w-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-bold text-xs">{{ purchase.token.symbol?.slice(0, 2) || '??' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs sm:text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ purchase.token.name }}
                                        </div>
                                        <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ purchase.token.symbol }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs sm:text-sm" role="cell">
                                <div class="font-semibold" :style="{ color: derivedColors.success }">
                                    {{ currencySymbol }}{{ formatNumber(purchase.amount_usd) }}
                                </div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ formatNumber(purchase.tokens_purchased) }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('tokens') }}
                                </div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ currencySymbol }}{{ purchase.token_price }}
                                </div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <span
                                    :class="getStatusBadgeClasses(purchase.status)"
                                    :style="{
                                        backgroundColor: purchase.status === 'completed' ? derivedColors.success + '20' :
                                                       purchase.status === 'pending' ? derivedColors.warning + '20' :
                                                       purchase.status === 'failed' ? '#ef444420' :
                                                       derivedColors.surface + '50',
                                        borderColor: purchase.status === 'completed' ? derivedColors.success + '30' :
                                                   purchase.status === 'pending' ? derivedColors.warning + '30' :
                                                   purchase.status === 'failed' ? '#ef444450' :
                                                   derivedColors.border + '30'
                                    }"
                                    class="text-xs px-2 py-1 rounded-full"
                                >
                                    {{ formatStatus(purchase.status) }}
                                </span>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs sm:text-sm" role="cell">
                                <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(purchase.purchased_at || purchase.created_at) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(purchase.purchased_at || purchase.created_at) }}
                                </div>
                            </td>
                            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-xs sm:text-sm" role="cell">
                                <button
                                    :aria-label="t('viewDetailsFor', { id: purchase.purchase_id })"
                                    class="font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1 text-xs"
                                    :style="{
                                        color: derivedColors.accent,
                                        ':hover': { opacity: '0.8' },
                                        ':focus': { ringColor: derivedColors.accent }
                                    }"
                                    @click="viewPurchase(purchase)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-8 sm:py-12 px-4">
                    <div class="flex flex-col items-center">
                        <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                            {{ t('noPurchasesFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('noPurchasesYet') }}
                        </p>
                        <Link
                            href="/user/investment/ico-tokens"
                            class="mt-4 py-2 px-4 rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.background,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                        >
                            {{ t('browseIcoTokens') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-if="purchases.data && purchases.data.length > 0 && purchases.links"
                    class="px-3 sm:px-4 lg:px-6 py-3 sm:py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm order-2 sm:order-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: purchases.from || 0,
                            to: purchases.to || 0,
                            total: purchases.total || 0
                        }) }}
                        </div>
                        <nav
                            class="flex flex-wrap justify-center gap-1 order-1 sm:order-2"
                            role="navigation"
                            :aria-label="t('pagination')"
                        >
                            <button
                                v-for="(link, index) in purchases.links"
                                :key="index"
                                :disabled="!link.url"
                                :aria-current="link.active ? 'page' : null"
                                :aria-label="getPaginationLabel(link)"
                                :class="[
                                    'px-2 sm:px-3 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50',
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
            v-if="selectedPurchase"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-2 sm:p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="modalTitleId"
            @click.self="closeModal"
            @keydown.escape="closeModal"
        >
            <div
                class="rounded-xl shadow-2xl border w-full max-w-4xl max-h-[95vh] sm:max-h-[90vh] overflow-y-auto focus:outline-none"
                :style="{ backgroundColor: derivedColors.background, borderColor: derivedColors.border + '20' }"
                tabindex="-1"
            >
                <div class="px-3 sm:px-4 lg:px-6 py-3 sm:py-4 border-b sticky top-0 z-10" :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="modalTitleId"
                            class="text-base sm:text-lg lg:text-xl font-semibold truncate pr-4"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('purchaseDetails') }} - {{ selectedPurchase.purchase_id }}
                        </h3>
                        <button
                            :aria-label="t('closeModal')"
                            class="hover:opacity-80 transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded p-1 flex-shrink-0"
                            :style="{
                                color: derivedColors.textMuted,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                            @click="closeModal"
                        >
                            <svg
                                class="w-5 h-5 sm:w-6 sm:h-6"
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

                <div class="p-3 sm:p-4 lg:p-6 space-y-4 sm:space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('purchaseId') }}</label>
                            <div class="font-mono text-xs sm:text-sm break-all" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedPurchase.purchase_id }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('token') }}</label>
                            <div class="flex items-center space-x-2 sm:space-x-3">
                                <div class="h-6 w-6 sm:h-8 sm:w-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-white font-bold text-xs">{{ selectedPurchase.token.symbol?.slice(0, 2) || '??' }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold truncate text-xs sm:text-sm" :style="{ color: derivedColors.textPrimary }">
                                        {{ selectedPurchase.token.name }}
                                    </div>
                                    <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                        {{ selectedPurchase.token.symbol }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('amountInvested') }}</label>
                            <div class="text-sm sm:text-base lg:text-lg font-semibold break-all" :style="{ color: derivedColors.success }">
                                {{ currencySymbol }}{{ formatNumber(selectedPurchase.amount_usd) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('tokensPurchased') }}</label>
                            <div class="text-sm sm:text-base lg:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ formatNumber(selectedPurchase.tokens_purchased) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('tokenPrice') }}</label>
                            <div class="font-semibold text-xs sm:text-sm" :style="{ color: derivedColors.textSecondary }">
                                {{ currencySymbol }}{{ selectedPurchase.token_price }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                            <span
                                :class="getStatusBadgeClasses(selectedPurchase.status)"
                                :style="{
                                    backgroundColor: selectedPurchase.status === 'completed' ? derivedColors.success + '20' :
                                                   selectedPurchase.status === 'pending' ? derivedColors.warning + '20' :
                                                   selectedPurchase.status === 'failed' ? '#ef444420' :
                                                   derivedColors.surface + '50',
                                    borderColor: selectedPurchase.status === 'completed' ? derivedColors.success + '30' :
                                               selectedPurchase.status === 'pending' ? derivedColors.warning + '30' :
                                               selectedPurchase.status === 'failed' ? '#ef444450' :
                                               derivedColors.border + '30'
                                }"
                                class="text-xs px-2 py-1 rounded-full"
                            >
                                {{ formatStatus(selectedPurchase.status) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('purchaseDate') }}</label>
                            <div :style="{ color: derivedColors.textPrimary }">
                                <div class="text-xs sm:text-sm">{{ formatDate(selectedPurchase.purchased_at || selectedPurchase.created_at) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(selectedPurchase.purchased_at || selectedPurchase.created_at) }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('transactionHash') }}</label>
                            <div class="font-mono text-xs break-all" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedPurchase.transaction_hash || t('notAvailable') }}
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs sm:text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('notes') }}</label>
                            <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedPurchase.notes || t('noNotesAvailable') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 border-t space-x-3" :style="{ borderColor: derivedColors.border + '20' }">
                        <button
                            class="px-3 sm:px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 text-xs sm:text-sm"
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
    </UserLayout>
</template>
