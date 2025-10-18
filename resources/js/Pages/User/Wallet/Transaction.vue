<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    transactions: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    filters: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    },
    stats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    statuses: {
        type: Array,
        default: () => ['completed', 'pending', 'failed', 'cancelled'],
        validator: (value) => Array.isArray(value)
    }
});

const selectedTransaction = ref(null);
const refreshing = ref(false);
const filterForm = reactive({
    search: props.filters.search || '',
    type: props.filters.type || '',
    status: props.filters.status || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    per_page: props.filters.per_page || '10'
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
const modalTitleId = computed(() => 'modal-title-' + Math.random().toString(36).substr(2, 9));

const applyFilters = () => {
    const cleanFilters = Object.fromEntries(
        Object.entries(filterForm).filter(([key, value]) => value !== '')
    );

    router.get('/user/wallet/transactions', cleanFilters, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    Object.assign(filterForm, {
        search: '',
        type: '',
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

const viewTransaction = async (transaction) => {
    selectedTransaction.value = transaction;

    await nextTick();
    const modal = document.querySelector('[role="dialog"]');
    if (modal) {
        modal.focus();
    }
};

const closeModal = () => {
    selectedTransaction.value = null;
};
const refreshTransactions = () => {
    if (refreshing.value) return;

    refreshing.value = true;

    router.reload({
        onFinish: () => {
            refreshing.value = false;
            showToast(t('transactionHistoryRefreshed'), 'success');
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
        'cancelled': t('cancelled'),
        'deposit': t('deposit'),
        'withdrawal': t('withdrawal'),
        'transfer': t('transfer'),
        'payment': t('payment'),
        'refund': t('refund'),
        'debit': t('debit'),
        'credit': t('credit')
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

const getTypeBadgeClasses = (type) => {
    const baseClasses = 'inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full border';

    switch (type?.toLowerCase()) {
        case 'deposit':
        case 'credit':
            return `${baseClasses} text-white border-opacity-30`;
        case 'withdrawal':
        case 'debit':
            return `${baseClasses} text-red-300 border-red-500/30`;
        case 'transfer':
            return `${baseClasses} text-blue-300 border-blue-500/30`;
        case 'payment':
            return `${baseClasses} text-purple-300 border-purple-500/30`;
        case 'refund':
            return `${baseClasses} text-white border-opacity-30`;
        default:
            return `${baseClasses} text-gray-300 border-gray-500/30`;
    }
};

const getAmountClass = (type) => {
    switch (type?.toLowerCase()) {
        case 'deposit':
        case 'refund':
        case 'credit':
            return derivedColors.value.success;
        case 'withdrawal':
        case 'debit':
        case 'payment':
        case 'transfer':
            return '#ef4444';
        default:
            return derivedColors.value.textSecondary;
    }
};

const getAmountPrefix = (type) => {
    switch (type?.toLowerCase()) {
        case 'deposit':
        case 'refund':
        case 'credit':
            return '+';
        case 'withdrawal':
        case 'payment':
        case 'transfer':
        case 'debit':
            return '-';
        default:
            return '';
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

const formatWalletType = (walletType) => {
    return walletType?.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) || t('unknown');
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

    document.addEventListener('keydown', handleModalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleModalKeydown);
});

watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        Object.assign(filterForm, {
            search: newFilters.search || '',
            type: newFilters.type || '',
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
        :page-title="t('transactions')"
        :page-section="t('wallet')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4">
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                role="region"
                :aria-label="t('transactionStatistics')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-xl sm:text-2xl font-bold"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalTransactions')"
                    >
                        {{ stats?.total_transactions || 0 }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalTransactions') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-xl sm:text-2xl font-bold"
                        :style="{ color: derivedColors.success }"
                        :aria-label="t('completedTransactions')"
                    >
                        {{ stats?.completed_transactions || 0 }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('completed') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-xl sm:text-2xl font-bold"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalDeposits')"
                    >
                        {{ currencySymbol }}{{ formatNumber(stats?.total_deposits || 0) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalDeposits') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-xl sm:text-2xl font-bold"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalWithdrawals')"
                    >
                        {{ currencySymbol }}{{ formatNumber(stats?.total_withdrawals || 0) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalWithdrawals') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <h2 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('filters') }}
                </h2>
                <form class="space-y-4" @submit.prevent="applyFilters">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div class="sm:col-span-2 md:col-span-1">
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('search') }}</label>
                            <input
                                v-model="filterForm.search"
                                type="text"
                                :placeholder="t('transactionIdPlaceholder')"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-sm sm:text-base"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('type') }}</label>
                            <select
                                v-model="filterForm.type"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                                <option value="">{{ t('allTypes') }}</option>
                                <option value="deposit">{{ t('deposit') }}</option>
                                <option value="withdrawal">{{ t('withdrawal') }}</option>
                                <option value="debit">{{ t('debit') }}</option>
                                <option value="credit">{{ t('credit') }}</option>
                                <option value="transfer">{{ t('transfer') }}</option>
                                <option value="payment">{{ t('payment') }}</option>
                                <option value="refund">{{ t('refund') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('status') }}</label>
                            <select
                                v-model="filterForm.status"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
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
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('endDate') }}</label>
                            <input
                                v-model="filterForm.end_date"
                                type="date"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                            >
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                        <button
                            type="submit"
                            class="w-full sm:w-auto py-2 px-4 rounded-lg font-medium transition-colors duration-200 text-sm sm:text-base"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.background
                            }"
                        >
                            {{ t('applyFilters') }}
                        </button>
                        <button
                            type="button"
                            class="w-full sm:w-auto py-2 px-4 rounded-lg transition-colors duration-200 text-sm sm:text-base"
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
                            :aria-label="refreshing ? t('refreshingTransactions') : t('refreshTransactions')"
                            class="w-full sm:w-auto hover:opacity-80 disabled:opacity-50 transition-colors duration-200 px-4 py-2 text-sm sm:text-base"
                            :style="{ color: derivedColors.accent }"
                            @click="refreshTransactions"
                        >
                            <svg
                                :class="refreshing ? 'animate-spin' : ''"
                                class="w-4 h-4 sm:w-5 sm:h-5 inline mr-1"
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
                            {{ t('refresh') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-2 sm:space-y-0">
                        <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('transactionHistory') }}
                        </h2>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ transactions.total || 0 }} {{ t('totalTransactionsCount') }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="transactions.data && transactions.data.length > 0"
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full hidden md:table"
                        role="table"
                        :aria-label="t('transactionHistoryTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('transactionId') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('type') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('walletType') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('amount') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('postBalance') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('date') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 lg:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
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
                            v-for="(transaction, index) in transactions.data"
                            :key="transaction.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:opacity-80 transition-colors duration-200 focus-within:opacity-80"
                        >
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm font-medium font-mono" :style="{ color: derivedColors.textPrimary }">
                                    {{ transaction.transaction_id }}
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <span
                                    :class="getTypeBadgeClasses(transaction.type)"
                                    :style="{
                                        backgroundColor: transaction.type === 'deposit' || transaction.type === 'credit' ? derivedColors.success + '20' :
                                                       transaction.type === 'withdrawal' || transaction.type === 'debit' ? '#ef444420' :
                                                       derivedColors.surface + '50',
                                        borderColor: transaction.type === 'deposit' || transaction.type === 'credit' ? derivedColors.success + '30' :
                                                   transaction.type === 'withdrawal' || transaction.type === 'debit' ? '#ef444450' :
                                                   derivedColors.border + '30'
                                    }"
                                >
                                    {{ formatStatus(transaction.type) }}
                                </span>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ formatWalletType(transaction.wallet_type) }}
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <div
                                    class="text-sm font-semibold"
                                    :style="{ color: getAmountClass(transaction.type) }"
                                >
                                    {{ getAmountPrefix(transaction.type) }}{{ currencySymbol }}{{ formatNumber(transaction.amount) }}
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ currencySymbol }}{{ formatNumber(transaction.post_balance) }}
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap" role="cell">
                                <span
                                    :class="getStatusBadgeClasses(transaction.status)"
                                    :style="{
                                        backgroundColor: transaction.status === 'completed' ? derivedColors.success + '20' :
                                                       transaction.status === 'pending' ? derivedColors.warning + '20' :
                                                       transaction.status === 'failed' ? '#ef444420' :
                                                       derivedColors.surface + '50',
                                        borderColor: transaction.status === 'completed' ? derivedColors.success + '30' :
                                                   transaction.status === 'pending' ? derivedColors.warning + '30' :
                                                   transaction.status === 'failed' ? '#ef444450' :
                                                   derivedColors.border + '30'
                                    }"
                                >
                                    {{ formatStatus(transaction.status) }}
                                </span>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(transaction.created_at) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(transaction.created_at) }}
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <button
                                    :aria-label="t('viewDetailsFor', { id: transaction.transaction_id })"
                                    class="font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1 text-sm"
                                    :style="{
                                            color: derivedColors.accent,
                                            ':hover': { opacity: '0.8' },
                                            ':focus': { ringColor: derivedColors.accent }
                                        }"
                                    @click="viewTransaction(transaction)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <div class="md:hidden">
                        <div
                            v-for="(transaction, index) in transactions.data"
                            :key="transaction.id"
                            class="border-b p-4 hover:opacity-80 transition-colors duration-200"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-mono font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ transaction.transaction_id }}
                                    </div>
                                    <span
                                        :class="getStatusBadgeClasses(transaction.status)"
                                        :style="{
                                            backgroundColor: transaction.status === 'completed' ? derivedColors.success + '20' :
                                                           transaction.status === 'pending' ? derivedColors.warning + '20' :
                                                           transaction.status === 'failed' ? '#ef444420' :
                                                           derivedColors.surface + '50',
                                            borderColor: transaction.status === 'completed' ? derivedColors.success + '30' :
                                                       transaction.status === 'pending' ? derivedColors.warning + '30' :
                                                       transaction.status === 'failed' ? '#ef444450' :
                                                       derivedColors.border + '30'
                                        }"
                                    >
                                        {{ formatStatus(transaction.status) }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span
                                        :class="getTypeBadgeClasses(transaction.type)"
                                        :style="{
                                            backgroundColor: transaction.type === 'deposit' || transaction.type === 'credit' ? derivedColors.success + '20' :
                                                           transaction.type === 'withdrawal' || transaction.type === 'debit' ? '#ef444420' :
                                                           derivedColors.surface + '50',
                                            borderColor: transaction.type === 'deposit' || transaction.type === 'credit' ? derivedColors.success + '30' :
                                                       transaction.type === 'withdrawal' || transaction.type === 'debit' ? '#ef444450' :
                                                       derivedColors.border + '30'
                                        }"
                                    >
                                        {{ formatStatus(transaction.type) }}
                                    </span>
                                    <div
                                        class="text-lg font-semibold"
                                        :style="{ color: getAmountClass(transaction.type) }"
                                    >
                                        {{ getAmountPrefix(transaction.type) }}{{ currencySymbol }}{{ formatNumber(transaction.amount) }}
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <div :style="{ color: derivedColors.textSecondary }">
                                        {{ formatWalletType(transaction.wallet_type) }}
                                    </div>
                                    <div :style="{ color: derivedColors.textSecondary }">
                                        {{ t('balance') }}: {{ currencySymbol }}{{ formatNumber(transaction.post_balance) }}
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(transaction.created_at) }} {{ formatTime(transaction.created_at) }}
                                    </div>
                                    <button
                                        :aria-label="t('viewDetailsFor', { id: transaction.transaction_id })"
                                        class="text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1"
                                        :style="{
                                                color: derivedColors.accent,
                                                ':hover': { opacity: '0.8' },
                                                ':focus': { ringColor: derivedColors.accent }
                                            }"
                                        @click="viewTransaction(transaction)"
                                    >
                                        {{ t('viewDetails') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12">
                    <div class="flex flex-col items-center px-4">
                        <svg
                            class="w-12 h-12 mb-4"
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
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                        <p class="text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                            {{ t('noTransactionsFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('noTransactionsYet') }}
                        </p>
                        <Link
                            href="/user/dashboard"
                            class="mt-4 py-2 px-4 rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.background,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                        >
                            {{ t('goToDashboard') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-if="transactions.data && transactions.data.length > 0 && transactions.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                        <div class="text-sm text-center sm:text-left" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: transactions.from || 0,
                            to: transactions.to || 0,
                            total: transactions.total || 0
                        }) }}
                        </div>
                        <nav
                            class="flex flex-wrap justify-center sm:justify-end space-x-1"
                            role="navigation"
                            :aria-label="t('pagination')"
                        >
                            <button
                                v-for="(link, index) in transactions.links"
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
            v-if="selectedTransaction"
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
                <div class="px-4 sm:px-6 py-4 border-b sticky top-0 backdrop-blur-md" :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.background + '95' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="modalTitleId"
                            class="text-lg sm:text-xl font-semibold pr-4"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            <span class="hidden sm:inline">{{ t('transactionDetails') }} - </span>
                            <span class="font-mono text-sm sm:text-base">{{ selectedTransaction.transaction_id }}</span>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('transactionId') }}</label>
                            <div class="font-mono text-sm sm:text-base break-all" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedTransaction.transaction_id }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('type') }}</label>
                            <span
                                :class="getTypeBadgeClasses(selectedTransaction.type)"
                                :style="{
                                    backgroundColor: selectedTransaction.type === 'deposit' || selectedTransaction.type === 'credit' ? derivedColors.success + '20' :
                                                   selectedTransaction.type === 'withdrawal' || selectedTransaction.type === 'debit' ? '#ef444420' :
                                                   derivedColors.surface + '50',
                                    borderColor: selectedTransaction.type === 'deposit' || selectedTransaction.type === 'credit' ? derivedColors.success + '30' :
                                               selectedTransaction.type === 'withdrawal' || selectedTransaction.type === 'debit' ? '#ef444450' :
                                               derivedColors.border + '30'
                                }"
                            >
                                {{ formatStatus(selectedTransaction.type) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('amount') }}</label>
                            <div
                                class="text-base sm:text-lg font-semibold"
                                :style="{ color: getAmountClass(selectedTransaction.type) }"
                            >
                                {{ getAmountPrefix(selectedTransaction.type) }}{{ currencySymbol }}{{ formatNumber(selectedTransaction.amount) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('postBalance') }}</label>
                            <div class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ currencySymbol }}{{ formatNumber(selectedTransaction.post_balance) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('walletType') }}</label>
                            <div class="text-sm sm:text-base" :style="{ color: derivedColors.textSecondary }">
                                {{ formatWalletType(selectedTransaction.wallet_type) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                            <span
                                :class="getStatusBadgeClasses(selectedTransaction.status)"
                                :style="{
                                    backgroundColor: selectedTransaction.status === 'completed' ? derivedColors.success + '20' :
                                                   selectedTransaction.status === 'pending' ? derivedColors.warning + '20' :
                                                   selectedTransaction.status === 'failed' ? '#ef444420' :
                                                   derivedColors.surface + '50',
                                    borderColor: selectedTransaction.status === 'completed' ? derivedColors.success + '30' :
                                               selectedTransaction.status === 'pending' ? derivedColors.warning + '30' :
                                               selectedTransaction.status === 'failed' ? '#ef444450' :
                                               derivedColors.border + '30'
                                }"
                            >
                                {{ formatStatus(selectedTransaction.status) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('date') }}</label>
                            <div :style="{ color: derivedColors.textPrimary }">
                                <div class="text-sm sm:text-base">{{ formatDate(selectedTransaction.created_at) }}</div>
                                <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(selectedTransaction.created_at) }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('details') }}</label>
                            <div class="text-sm sm:text-base break-words" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedTransaction.details || t('notAvailable') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 border-t space-x-3" :style="{ borderColor: derivedColors.border + '20' }">
                        <button
                            class="w-full sm:w-auto px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
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
