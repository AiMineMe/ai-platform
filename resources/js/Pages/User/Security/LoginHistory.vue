<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    history: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    stats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    }
});

const selectedAttempt = ref(null);
const refreshing = ref(false);
const blockingIp = ref(false);
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
        warning: '#f59e0b'
    };
});

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));

const modalTitleId = computed(() => 'modal-title-' + Math.random().toString(36).substr(2, 9));
const hasRecentFailedAttempts = computed(() => {
    if (!props.history.data) return false;

    const recentFailures = props.history.data.filter(attempt => {
        if (attempt.successful) return false;
        const attemptDate = new Date(attempt.attempted_at);
        const oneDayAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);
        return attemptDate > oneDayAgo;
    });

    return recentFailures.length > 2;
});

const changePage = (url) => {
    if (!url) return;

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
        },
        onFinish: () => {
        }
    });
};

const viewAttempt = async (attempt) => {
    selectedAttempt.value = attempt;
    await nextTick();
    const modal = document.querySelector('[role="dialog"]');
    if (modal) {
        modal.focus();
    }
};

const closeModal = () => {
    selectedAttempt.value = null;
};


const refreshHistory = () => {
    if (refreshing.value) return;
    refreshing.value = true;
    router.reload({
        onFinish: () => {
            refreshing.value = false;
            showToast(t('loginHistoryRefreshed'), 'success');
        },
        onError: () => {
            refreshing.value = false;
            showToast(t('failedToRefreshLoginHistory'), 'error');
        }
    });
};


const blockIp = async (ipAddress) => {
    if (blockingIp.value) return;
    if (!confirm(t('confirmBlockIp').replace('{ip}', ipAddress))) {
        return;
    }

    blockingIp.value = true;
    router.post('/user/security/block-ip', { ip_address: ipAddress }, {
        onSuccess: () => {
            showToast(t('ipAddressBlocked').replace('{ip}', ipAddress), 'success');
            closeModal();
        },
        onError: () => {
            showToast(t('failedToBlockIp'), 'error');
        },
        onFinish: () => {
            blockingIp.value = false;
        }
    });
};

const formatDateTime = (dateString) => {
    if (!dateString) return t('unknown');

    try {
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        return new Date(dateString).toLocaleDateString(undefined, options);
    } catch (error) {
        console.error('Date formatting error:', error);
        return t('invalidDate');
    }
};

const formatRelativeTime = (dateString) => {
    if (!dateString) return t('unknown');

    try {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return t('justNow');
        if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
        }
        if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
        }
        if (diffInSeconds < 2592000) {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} ${days === 1 ? 'day' : 'days'} ago`;
        }

        return formatDateTime(dateString);
    } catch (error) {
        console.error('Relative time formatting error:', error);
        return t('unknown');
    }
};

const isCurrentSession = (attempt) => {
    if (!attempt.successful) return false;

    try {
        const attemptTime = new Date(attempt.attempted_at);
        const thirtyMinutesAgo = new Date(Date.now() - 30 * 60 * 1000);
        return attemptTime > thirtyMinutesAgo;
    } catch (error) {
        console.error('Current session check error:', error);
        return false;
    }
};

const getStatusBadgeClasses = (status) => {
    const baseClasses = 'inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full border';
    switch (status?.toLowerCase()) {
        case 'success':
            return `${baseClasses} text-green-300 border-green-500/30`;
        case 'failed':
            return `${baseClasses} text-red-300 border-red-500/30`;
        default:
            return `${baseClasses} border-gray-500/30`;
    }
};

const getStatusBadgeStyle = (status) => {
    switch (status?.toLowerCase()) {
        case 'success':
            return { backgroundColor: derivedColors.value.success + '40' };
        case 'failed':
            return { backgroundColor: '#ef444440' };
        default:
            return { backgroundColor: derivedColors.value.surface + '40' };
    }
};

const getDeviceIconClass = (deviceType) => {
    const baseClasses = 'border';
    switch (deviceType?.toLowerCase()) {
        case 'mobile':
            return `${baseClasses} text-green-300 border-green-500/30`;
        case 'tablet':
            return `${baseClasses} text-blue-300 border-blue-500/30`;
        case 'desktop':
        default:
            return `${baseClasses} border-purple-500/30`;
    }
};

const getDeviceIconStyle = (deviceType) => {
    switch (deviceType?.toLowerCase()) {
        case 'mobile':
            return { backgroundColor: derivedColors.value.success + '40', color: derivedColors.value.success };
        case 'tablet':
            return { backgroundColor: '#3b82f640', color: '#3b82f6' };
        case 'desktop':
        default:
            return { backgroundColor: derivedColors.value.secondary + '40', color: derivedColors.value.secondary };
    }
};

const getBrowserIconClass = (browser) => {
    if (!browser) return 'bg-gray-500';

    switch (browser.toLowerCase()) {
        case 'chrome':
        case 'google chrome':
            return 'bg-yellow-500';
        case 'firefox':
        case 'mozilla firefox':
            return 'bg-orange-500';
        case 'safari':
            return 'bg-blue-500';
        case 'edge':
        case 'microsoft edge':
            return 'bg-blue-600';
        case 'opera':
            return 'bg-red-500';
        case 'brave':
            return 'bg-orange-600';
        default:
            return 'bg-gray-500';
    }
};

const getBrowserInitial = (browser) => {
    if (!browser) return '?';

    switch (browser.toLowerCase()) {
        case 'chrome':
        case 'google chrome':
            return 'C';
        case 'firefox':
        case 'mozilla firefox':
            return 'F';
        case 'safari':
            return 'S';
        case 'edge':
        case 'microsoft edge':
            return 'E';
        case 'opera':
            return 'O';
        case 'brave':
            return 'B';
        default:
            return browser.charAt(0).toUpperCase();
    }
};

const getPaginationLabel = (link, index) => {
    if (link.label.includes('Previous')) {
        return 'Go to previous page';
    }
    if (link.label.includes('Next')) {
        return 'Go to next page';
    }
    if (link.active) {
        return `Current page, page ${link.label}`;
    }
    if (link.url) {
        return `Go to page ${link.label}`;
    }
    return link.label;
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && selectedAttempt.value) {
        closeModal();
        return;
    }

    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        refreshHistory();

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
    if (flash?.info) showToast(flash.info, 'info');
    if (flash?.warning) showToast(flash.warning, 'warning');
    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('keydown', handleModalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('keydown', handleModalKeydown);
});
</script>

<template>
    <UserLayout
        :page-title="t('loginHistory')"
        :page-section="t('security')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4"
                role="region"
                :aria-label="t('loginStatistics')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-3 sm:p-4 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalLoginAttempts')"
                    >
                        {{ stats.total_attempts }}
                    </div>
                    <div class="text-xs sm:text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalAttempts') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-3 sm:p-4 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold"
                        :style="{ color: derivedColors.accent }"
                        :aria-label="t('successfulLogins')"
                    >
                        {{ stats.successful_logins }}
                    </div>
                    <div class="text-xs sm:text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('successfulLogins') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-3 sm:p-4 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold text-red-400"
                        :aria-label="t('failedLoginAttempts')"
                    >
                        {{ stats.failed_attempts }}
                    </div>
                    <div class="text-xs sm:text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('failedAttempts') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-3 sm:p-4 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('uniqueIpAddresses')"
                    >
                        {{ stats.unique_ips }}
                    </div>
                    <div class="text-xs sm:text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('uniqueIpAddresses') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('recentLoginActivity') }}
                        </h2>
                        <div class="flex items-center justify-end sm:justify-start space-x-2">
                            <button
                                :disabled="refreshing"
                                :aria-label="refreshing ? t('refreshingLoginHistory') : t('refreshLoginHistory')"
                                class="transition-colors duration-200 disabled:opacity-50 p-2"
                                :style="{ color: derivedColors.accent }"
                                @click="refreshHistory"
                            >
                                <svg
                                    :class="refreshing ? 'animate-spin' : ''"
                                    class="w-5 h-5"
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
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="history.data && history.data.length > 0"
                    class="block lg:hidden"
                >
                    <div class="divide-y" :style="{ '--tw-divide-opacity': 0.5, borderColor: derivedColors.border }">
                        <div
                            v-for="(attempt, index) in history.data"
                            :key="attempt.id"
                            class="p-4 sm:p-6 space-y-3"
                            :aria-rowindex="index + 1"
                        >
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <span
                                        v-if="attempt.successful"
                                        :class="getStatusBadgeClasses('success')"
                                        :style="getStatusBadgeStyle('success')"
                                        :aria-label="`${t('loginSuccessfulAt')} ${formatDateTime(attempt.attempted_at)}`"
                                    >
                                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ t('success') }}
                                    </span>
                                    <span
                                        v-else
                                        :class="getStatusBadgeClasses('failed')"
                                        :style="getStatusBadgeStyle('failed')"
                                        :aria-label="`${t('loginFailedAt')} ${formatDateTime(attempt.attempted_at)}`"
                                    >
                                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        {{ t('failed') }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium text-sm" :style="{ color: derivedColors.textPrimary }">
                                        {{ formatDateTime(attempt.attempted_at) }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ formatRelativeTime(attempt.attempted_at) }}
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium" :style="{ color: derivedColors.textMuted }">{{ t('ipAddress') }}</span>
                                    <div class="text-right">
                                        <div class="font-mono text-sm" :style="{ color: derivedColors.textSecondary }">
                                            {{ attempt.ip_address }}
                                        </div>
                                        <div
                                            v-if="isCurrentSession(attempt)"
                                            class="text-xs font-medium flex items-center justify-end"
                                            :style="{ color: derivedColors.accent }"
                                        >
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            {{ t('currentSession') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium" :style="{ color: derivedColors.textMuted }">{{ t('location') }}</span>
                                    <div class="text-right">
                                        <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                            {{ attempt.location || t('unknown') }}
                                        </div>
                                        <div v-if="attempt.country" class="text-xs" :style="{ color: derivedColors.textMuted }">
                                            {{ attempt.country }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div
                                        class="h-8 w-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                        :class="getDeviceIconClass(attempt.device_type)"
                                        :style="getDeviceIconStyle(attempt.device_type)"
                                        :aria-label="`${t('deviceType')}: ${attempt.device_type || t('unknown')}`"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                v-if="attempt.device_type === 'Mobile'"
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"
                                            />
                                            <path
                                                v-else-if="attempt.device_type === 'Tablet'"
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                            />
                                            <path
                                                v-else
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ attempt.device_type || t('unknown') }}
                                        </div>
                                        <div class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ attempt.platform || t('unknownOs') }}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    :aria-label="`${t('viewDetailsFor')} ${attempt.ip_address} ${formatDateTime(attempt.attempted_at)}`"
                                    class="transition-colors font-medium focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-3 py-2 text-sm flex-shrink-0"
                                    :style="{ color: derivedColors.accent, backgroundColor: derivedColors.surface }"
                                    @click="viewAttempt(attempt)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="history.data && history.data.length > 0"
                    class="hidden lg:block overflow-x-auto"
                >
                    <table
                        class="w-full min-w-full"
                        role="table"
                        :aria-label="t('loginHistoryTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('status') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('dateTime') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('ipAddress') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('location') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('device') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('browser') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap" :style="{ color: derivedColors.textSecondary }">
                                {{ t('actions') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody class="divide-y" :style="{ '--tw-divide-opacity': 0.5, borderColor: derivedColors.border }" role="rowgroup">
                        <tr
                            v-for="(attempt, index) in history.data"
                            :key="attempt.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:scale-105 transition-all duration-200 focus-within:scale-105"
                            :style="{ ':hover': { backgroundColor: derivedColors.surface + '30' } }"
                        >
                            <td class="px-6 py-4 whitespace-nowrap" role="cell">
                                <span
                                    v-if="attempt.successful"
                                    :class="getStatusBadgeClasses('success')"
                                    :style="getStatusBadgeStyle('success')"
                                    :aria-label="`${t('loginSuccessfulAt')} ${formatDateTime(attempt.attempted_at)}`"
                                >
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ t('success') }}
                                </span>
                                <span
                                    v-else
                                    :class="getStatusBadgeClasses('failed')"
                                    :style="getStatusBadgeStyle('failed')"
                                    :aria-label="`${t('loginFailedAt')} ${formatDateTime(attempt.attempted_at)}`"
                                >
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    {{ t('failed') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ formatDateTime(attempt.attempted_at) }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatRelativeTime(attempt.attempted_at) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="font-mono text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ attempt.ip_address }}
                                </div>
                                <div
                                    v-if="isCurrentSession(attempt)"
                                    class="text-xs font-medium flex items-center"
                                    :style="{ color: derivedColors.accent }"
                                >
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ t('currentSession') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ attempt.location || t('unknown') }}
                                </div>
                                <div v-if="attempt.country" class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ attempt.country }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="flex items-center space-x-2">
                                    <div
                                        class="h-8 w-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                        :class="getDeviceIconClass(attempt.device_type)"
                                        :style="getDeviceIconStyle(attempt.device_type)"
                                        :aria-label="`${t('deviceType')}: ${attempt.device_type || t('unknown')}`"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                v-if="attempt.device_type === 'Mobile'"
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"
                                            />
                                            <path
                                                v-else-if="attempt.device_type === 'Tablet'"
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                            />
                                            <path
                                                v-else
                                                stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                            {{ attempt.device_type || t('unknown') }}
                                        </div>
                                        <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                            {{ attempt.platform || t('unknownOs') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="flex items-center space-x-2">
                                    <div
                                        class="h-6 w-6 rounded flex items-center justify-center flex-shrink-0"
                                        :class="getBrowserIconClass(attempt.browser)"
                                        :aria-label="`${t('browser')}: ${attempt.browser || t('unknown')}`"
                                    >
                                        <span class="text-xs font-bold text-white">{{ getBrowserInitial(attempt.browser) }}</span>
                                    </div>
                                    <span class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ attempt.browser || t('unknown') }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <button
                                    :aria-label="`${t('viewDetailsFor')} ${attempt.ip_address} ${formatDateTime(attempt.attempted_at)}`"
                                    class="transition-colors font-medium focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1"
                                    :style="{ color: derivedColors.accent }"
                                    @click="viewAttempt(attempt)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-12">
                    <div class="flex flex-col items-center">
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
                            {{ t('noLoginHistoryFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('loginAttemptsWillAppearHere') }}
                        </p>
                        <Link
                            href="/user/dashboard"
                            class="mt-4 px-4 py-2 rounded-lg transition-colors duration-200 font-medium focus:outline-none focus:ring-2 focus:ring-opacity-50"
                            :style="{ backgroundColor: derivedColors.accent, color: derivedColors.background }"
                        >
                            {{ t('goToDashboard') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-if="history.data && history.data.length > 0 && history.links"
                    class="px-3 sm:px-6 py-3 sm:py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col space-y-3 sm:space-y-0 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs sm:text-sm text-center sm:text-left" :style="{ color: derivedColors.textMuted }">
                            Showing {{ history.from || 0 }} to {{ history.to || 0 }} of {{ history.total || 0 }} results
                        </div>
                        <nav class="flex justify-center sm:justify-end" role="navigation" aria-label="Pagination">
                            <div class="flex space-x-1 overflow-x-auto scrollbar-hide max-w-full">
                                <template v-if="isMobile">
                                    <button
                                        v-for="(link, index) in getMobileLinks(history.links)"
                                        :key="index"
                                        :disabled="!link.url"
                                        :aria-current="link.active ? 'page' : null"
                                        :aria-label="getPaginationLabel(link, index)"
                                        class="px-2 py-2 text-xs font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 min-w-[32px] flex-shrink-0"
                                        :style="link.active
                                            ? { backgroundColor: derivedColors.accent, color: derivedColors.background }
                                            : link.url
                                            ? { backgroundColor: derivedColors.surface, color: derivedColors.textSecondary }
                                            : { backgroundColor: derivedColors.surface + '50', color: derivedColors.textMuted, cursor: 'not-allowed' }"
                                        @click="changePage(link.url)"
                                        v-html="link.label"
                                    />
                                </template>

                                <template v-else>
                                    <button
                                        v-for="(link, index) in history.links"
                                        :key="index"
                                        :disabled="!link.url"
                                        :aria-current="link.active ? 'page' : null"
                                        :aria-label="getPaginationLabel(link, index)"
                                        class="px-2 sm:px-3 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 min-w-[32px] sm:min-w-[auto] flex-shrink-0"
                                        :style="link.active
                                            ? { backgroundColor: derivedColors.accent, color: derivedColors.background }
                                            : link.url
                                            ? { backgroundColor: derivedColors.surface, color: derivedColors.textSecondary }
                                            : { backgroundColor: derivedColors.surface + '50', color: derivedColors.textMuted, cursor: 'not-allowed' }"
                                        @click="changePage(link.url)"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>

            <div
                v-if="hasRecentFailedAttempts"
                class="border rounded-xl p-4 sm:p-6 shadow-lg"
                :style="{ backgroundColor: '#ef444420', borderColor: '#ef444430' }"
                role="alert"
                aria-live="polite"
            >
                <div class="flex flex-col sm:flex-row sm:items-start space-y-3 sm:space-y-0 sm:space-x-3">
                    <svg
                        class="w-6 h-6 text-red-400 flex-shrink-0 mt-0.5 mx-auto sm:mx-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                        />
                    </svg>
                    <div class="text-center sm:text-left w-full">
                        <h4 class="text-red-300 font-medium mb-2">
                            {{ t('securityAlert') }}
                        </h4>
                        <p class="text-red-200 text-sm mb-3">
                            {{ t('recentFailedAttemptsDetected') }}
                        </p>
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                            <Link
                                href="/user/security/password"
                                class="text-xs bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 text-center"
                            >
                                {{ t('changePassword') }}
                            </Link>
                            <Link
                                href="/user/security/2fa"
                                class="text-xs bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 text-center"
                            >
                                {{ t('enable2FA') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="selectedAttempt"
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
                            {{ t('loginAttemptDetails') }}
                        </h3>
                        <button
                            :aria-label="t('closeModal')"
                            class="transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded p-1"
                            :style="{ color: derivedColors.textMuted }"
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
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                            <span
                                v-if="selectedAttempt.successful"
                                :class="getStatusBadgeClasses('success')"
                                :style="getStatusBadgeStyle('success')"
                            >
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ t('success') }}
                            </span>
                            <span
                                v-else
                                :class="getStatusBadgeClasses('failed')"
                                :style="getStatusBadgeStyle('failed')"
                            >
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                {{ t('failed') }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('dateTime') }}</label>
                            <div class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                <div>{{ formatDateTime(selectedAttempt.attempted_at) }}</div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ formatRelativeTime(selectedAttempt.attempted_at) }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('ipAddress') }}</label>
                            <div class="font-mono text-base sm:text-lg break-all" :style="{ color: derivedColors.textPrimary }">
                                {{ selectedAttempt.ip_address }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('location') }}</label>
                            <div :style="{ color: derivedColors.textSecondary }">
                                {{ selectedAttempt.location || t('unknown') }}
                            </div>
                            <div
                                v-if="selectedAttempt.country"
                                class="text-sm"
                                :style="{ color: derivedColors.textMuted }"
                            >
                                {{ selectedAttempt.country }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('device') }}</label>
                            <div :style="{ color: derivedColors.textPrimary }">
                                {{ selectedAttempt.device_type || t('unknown') }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('platform') }}</label>
                            <div :style="{ color: derivedColors.textSecondary }">
                                {{ selectedAttempt.platform || t('unknownOs') }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('browser') }}</label>
                            <div :style="{ color: derivedColors.textPrimary }">
                                {{ selectedAttempt.browser || t('unknown') }}
                            </div>
                        </div>
                        <div v-if="selectedAttempt.failure_reason">
                            <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('failureReason') }}</label>
                            <div class="text-red-300">
                                {{ selectedAttempt.failure_reason }}
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedAttempt.user_agent">
                        <label class="block text-sm font-medium mb-1" :style="{ color: derivedColors.textMuted }">{{ t('userAgent') }}</label>
                        <div class="text-sm break-all p-3 rounded-lg border" :style="{ color: derivedColors.textPrimary, backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border }">
                            {{ selectedAttempt.user_agent }}
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end pt-4 border-t space-y-3 sm:space-y-0 sm:space-x-3" :style="{ borderColor: derivedColors.border + '20' }">
                        <button
                            v-if="!selectedAttempt.successful"
                            :disabled="blockingIp"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50 order-2 sm:order-1"
                            @click="blockIp(selectedAttempt.ip_address)"
                        >
                            <span v-if="blockingIp">{{ t('blocking') }}</span>
                            <span v-else>{{ t('blockIp') }}</span>
                        </button>
                        <button
                            class="px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 order-1 sm:order-2"
                            :style="{ backgroundColor: derivedColors.surface, color: derivedColors.textPrimary }"
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
