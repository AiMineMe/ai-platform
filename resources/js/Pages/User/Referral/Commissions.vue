<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    commissions: {
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
        default: () => ['pending', 'paid', 'cancelled'],
        validator: (value) => Array.isArray(value)
    }
});

const selectedCommission = ref(null);
const refreshing = ref(false);
const filterForm = reactive({
    search: props.filters.search || '',
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

const currencySymbol = computed(() => page.props.currencySymbol || '$');
const modalTitleId = computed(() => 'modal-title-' + Math.random().toString(36).substr(2, 9));

const applyFilters = () => {
    const cleanFilters = Object.fromEntries(
        Object.entries(filterForm).filter(([key, value]) => value !== '')
    );

    router.get('/user/referral/commissions', cleanFilters, {
        preserveState: true,
        preserveScroll: true
    });
};

const clearFilters = () => {
    Object.assign(filterForm, {
        search: '',
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

const viewCommission = async (commission) => {
    selectedCommission.value = commission;

    await nextTick();
    const modal = document.querySelector('[role="dialog"]');
    if (modal) {
        modal.focus();
    }
};

const closeModal = () => {
    selectedCommission.value = null;
};

const refreshCommissions = () => {
    if (refreshing.value) return;

    refreshing.value = true;

    router.reload({
        onFinish: () => {
            refreshing.value = false;
            showToast(t('commissionHistoryRefreshed'), 'success');
        },
        onError: () => {
            refreshing.value = false;
            showToast(t('failedToRefreshHistory'), 'error');
        }
    });
};

const formatStatus = (status) => {
    const statusMap = {
        'pending': t('pending'),
        'paid': t('paid'),
        'cancelled': t('cancelled')
    };
    return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
};

const getStatusBadgeClasses = (status) => {
    const baseClasses = 'inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full border';

    switch (status?.toLowerCase()) {
        case 'paid':
            return `${baseClasses} text-white border-opacity-30`;
        case 'pending':
            return `${baseClasses} text-white border-opacity-30`;
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
    if (event.key === 'Escape' && selectedCommission.value) {
        closeModal();
        return;
    }

    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        refreshCommissions();
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
        :page-title="t('commissionHistory')"
        :page-section="t('referral')"
    >
        <div class="max-w-none mx-auto space-y-4 sm:space-y-6 px-3 sm:px-4">
            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-2 sm:space-y-0">
                        <h2 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('commissionHistory') }}
                        </h2>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ commissions.total || 0 }} {{ t('totalCommissionsCount') }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="commissions.data && commissions.data.length > 0"
                    class="overflow-x-auto"
                >
                    <div class="block lg:hidden">
                        <div
                            v-for="(commission, index) in commissions.data"
                            :key="commission.id"
                            class="border-b p-4 space-y-3 hover:opacity-80 transition-colors duration-200"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium font-mono" :style="{ color: derivedColors.textPrimary }">
                                        #{{ commission.id }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(commission.created_at) }}
                                    </div>
                                </div>
                                <span
                                    :class="getStatusBadgeClasses(commission.status)"
                                    :style="{
                                        backgroundColor: commission.status === 'paid' ? derivedColors.success + '20' :
                                                       commission.status === 'pending' ? derivedColors.warning + '20' :
                                                       derivedColors.surface + '50',
                                        borderColor: commission.status === 'paid' ? derivedColors.success + '30' :
                                                   commission.status === 'pending' ? derivedColors.warning + '30' :
                                                   derivedColors.border + '30'
                                    }"
                                >
                                    {{ formatStatus(commission.status) }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="text-lg font-semibold" :style="{ color: derivedColors.success }">
                                        +{{ currencySymbol }}{{ formatNumber(commission.commission) }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ commission.type || t('referral') }}
                                    </div>
                                </div>
                                <button
                                    :aria-label="t('viewDetailsFor', { id: commission.id })"
                                    class="text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-3 py-2"
                                    :style="{
                                        color: derivedColors.accent,
                                        backgroundColor: derivedColors.accent + '10',
                                        ':focus': { ringColor: derivedColors.accent }
                                    }"
                                    @click="viewCommission(commission)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </div>

                            <div class="bg-opacity-50 rounded-lg p-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ commission.referred_user }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ commission.referred_email }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <table
                        class="w-full hidden lg:table"
                        role="table"
                        :aria-label="t('commissionHistoryTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('commissionId') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('referredUser') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('commission') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('commissionType') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('status') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('date') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
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
                            v-for="(commission, index) in commissions.data"
                            :key="commission.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:opacity-80 transition-colors duration-200 focus-within:opacity-80"
                        >
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm font-medium font-mono" :style="{ color: derivedColors.textPrimary }">
                                    #{{ commission.id }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ commission.referred_user }}
                                </div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ commission.referred_email }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <div
                                    class="text-sm font-semibold"
                                    :style="{ color: derivedColors.success }"
                                >
                                    +{{ currencySymbol }}{{ formatNumber(commission.commission) }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ commission.type || t('referral') }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <span
                                    :class="getStatusBadgeClasses(commission.status)"
                                    :style="{
                                        backgroundColor: commission.status === 'paid' ? derivedColors.success + '20' :
                                                       commission.status === 'pending' ? derivedColors.warning + '20' :
                                                       derivedColors.surface + '50',
                                        borderColor: commission.status === 'paid' ? derivedColors.success + '30' :
                                                   commission.status === 'pending' ? derivedColors.warning + '30' :
                                                   derivedColors.border + '30'
                                    }"
                                >
                                    {{ formatStatus(commission.status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(commission.created_at) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(commission.created_at) }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <button
                                    :aria-label="t('viewDetailsFor', { id: commission.id })"
                                    class="font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1"
                                    :style="{
                                        color: derivedColors.accent,
                                        ':hover': { opacity: '0.8' },
                                        ':focus': { ringColor: derivedColors.accent }
                                    }"
                                    @click="viewCommission(commission)"
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
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                            {{ t('noCommissionsFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('startReferringToEarnCommissions') }}
                        </p>
                        <Link
                            href="/user/referral/dashboard"
                            class="mt-4 py-3 px-6 rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.background,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                        >
                            {{ t('viewReferralProgram') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-if="commissions.data && commissions.data.length > 0 && commissions.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: commissions.from || 0,
                            to: commissions.to || 0,
                            total: commissions.total || 0
                        }) }}
                        </div>
                        <nav
                            class="flex flex-wrap justify-center sm:justify-end space-x-1"
                            role="navigation"
                            :aria-label="t('pagination')"
                        >
                            <button
                                v-for="(link, index) in commissions.links"
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
            v-if="selectedCommission"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4"
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
                            class="text-lg sm:text-xl font-semibold pr-4"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('commissionDetails') }} - #{{ selectedCommission.id }}
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

                <div class="p-4 sm:p-6 space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('commissionId') }}</label>
                            <div class="font-mono text-base sm:text-lg" :style="{ color: derivedColors.textPrimary }">
                                #{{ selectedCommission.id }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                            <span
                                :class="getStatusBadgeClasses(selectedCommission.status)"
                                :style="{
                                    backgroundColor: selectedCommission.status === 'paid' ? derivedColors.success + '20' :
                                                   selectedCommission.status === 'pending' ? derivedColors.warning + '20' :
                                                   derivedColors.surface + '50',
                                    borderColor: selectedCommission.status === 'paid' ? derivedColors.success + '30' :
                                               selectedCommission.status === 'pending' ? derivedColors.warning + '30' :
                                               derivedColors.border + '30'
                                }"
                            >
                                {{ formatStatus(selectedCommission.status) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('commissionAmount') }}</label>
                            <div
                                class="text-base sm:text-lg font-semibold"
                                :style="{ color: derivedColors.success }"
                            >
                                +{{ currencySymbol }}{{ formatNumber(selectedCommission.commission) }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('commissionType') }}</label>
                            <div class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedCommission.type || t('referral') }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('referredUser') }}</label>
                            <div class="break-words" :style="{ color: derivedColors.textSecondary }">
                                {{ selectedCommission.referred_user }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('referredEmail') }}</label>
                            <div class="break-words" :style="{ color: derivedColors.textSecondary }">
                                {{ selectedCommission.referred_email }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('earnedDate') }}</label>
                            <div :style="{ color: derivedColors.textPrimary }">
                                <div>{{ formatDate(selectedCommission.created_at) }}</div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(selectedCommission.created_at) }}
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedCommission.paid_at">
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('paidDate') }}</label>
                            <div :style="{ color: derivedColors.textPrimary }">
                                <div>{{ formatDate(selectedCommission.paid_at) }}</div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(selectedCommission.paid_at) }}
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedCommission.level">
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('referralLevel') }}</label>
                            <div class="text-sm" :style="{ color: derivedColors.textPrimary }">
                                {{ t('level') }} {{ selectedCommission.level }}
                            </div>
                        </div>
                        <div v-if="selectedCommission.percentage">
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('commissionRate') }}</label>
                            <div class="text-sm" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedCommission.percentage }}%
                            </div>
                        </div>
                        <div v-if="selectedCommission.source_amount">
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('sourceAmount') }}</label>
                            <div class="text-sm break-words" :style="{ color: derivedColors.textPrimary }">
                                {{ currencySymbol }}{{ formatNumber(selectedCommission.source_amount) }}
                            </div>
                        </div>
                        <div v-if="selectedCommission.notes" class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('notes') }}</label>
                            <div class="text-sm break-words" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedCommission.notes }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 border-t space-x-3" :style="{ borderColor: derivedColors.border + '20' }">
                        <button
                            class="px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
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
