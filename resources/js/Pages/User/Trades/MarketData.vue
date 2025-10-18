<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    currencies: {
        type: Object,
        default: () => ({})
    }
});

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
        danger: '#ef4444',
        warning: '#f59e0b'
    };
});

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));
const currencySymbol = computed(() => page.props.currencySymbol || '$');
const currencyData = computed(() => props.currencies?.data || []);
const formatPrice = (price) => {
    const number = parseFloat(price) || 0;
    if (number >= 1) {
        return number.toFixed(2);
    } else if (number >= 0.01) {
        return number.toFixed(4);
    } else {
        return number.toFixed(8);
    }
};

const getChangeColor = (changePercent) => {
    if (changePercent > 0) return derivedColors.value.success;
    if (changePercent < 0) return derivedColors.value.danger;
    return derivedColors.value.textSecondary;
};

const formatTimeAgo = (timestamp) => {
    if (!timestamp) return t('notAvailable');

    const now = new Date();
    const updated = new Date(timestamp);
    const diffMs = now - updated;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return t('justNow');
    if (diffMins < 60) return t('minutesAgo', { minutes: diffMins });
    if (diffHours < 24) return t('hoursAgo', { hours: diffHours });
    return t('daysAgo', { days: diffDays });
};

const changePage = (url) => {
    if (!url) return;

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true
    });
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
</script>

<template>
    <UserLayout
        :page-title="t('marketData')"
        :page-section="t('market')"
    >
        <div class="max-w-none mx-auto space-y-4 sm:space-y-6 px-3 sm:px-4">
            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('cryptocurrencyPrices') }}
                        </h2>
                    </div>
                </div>

                <div
                    v-if="currencyData && currencyData.length > 0"
                    class="overflow-x-auto"
                >
                    <!-- Mobile Cards View -->
                    <div class="block lg:hidden">
                        <div
                            v-for="(currency, index) in currencyData"
                            :key="currency.id"
                            class="border-b p-4 space-y-3 hover:opacity-80 transition-colors duration-200"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <!-- Currency Header -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3 min-w-0 flex-1">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <img
                                            v-if="currency.image_url"
                                            :src="currency.image_url"
                                            :alt="currency.name"
                                            class="h-10 w-10 rounded-full"
                                            @error="$event.target.style.display='none'"
                                        >
                                        <div
                                            v-else
                                            class="h-10 w-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center"
                                        >
                                            <span class="text-white font-bold text-sm">{{ currency.symbol?.slice(0, 2) || '??' }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ currency.name }}
                                        </div>
                                        <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ currency.symbol }}
                                        </div>
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full border flex-shrink-0"
                                    :style="{
                                        backgroundColor: derivedColors.surface + '50',
                                        borderColor: derivedColors.border + '30',
                                        color: derivedColors.textSecondary
                                    }"
                                >
                                    {{ currency.type.charAt(0).toUpperCase() + currency.type.slice(1) }}
                                </span>
                            </div>

                            <!-- Price & Change Info -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('price') }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ currencySymbol }}{{ formatPrice(currency.current_price) }}
                                    </div>
                                    <div
                                        v-if="currency.previous_price && currency.previous_price !== currency.current_price"
                                        class="text-xs"
                                        :style="{ color: derivedColors.textMuted }"
                                    >
                                        {{ t('prev') }}: {{ currencySymbol }}{{ formatPrice(currency.previous_price) }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('change24h') }}
                                    </div>
                                    <div
                                        v-if="currency.change_percent !== null"
                                        class="flex items-center"
                                    >
                                        <span
                                            class="text-sm font-medium flex items-center"
                                            :style="{ color: getChangeColor(currency.change_percent) }"
                                        >
                                            <svg
                                                v-if="currency.change_percent > 0"
                                                class="w-3 h-3 mr-1 flex-shrink-0"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            <svg
                                                v-else-if="currency.change_percent < 0"
                                                class="w-3 h-3 mr-1 flex-shrink-0"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            {{ currency.change_percent >= 0 ? '+' : '' }}{{ currency.change_percent?.toFixed(2) }}%
                                        </span>
                                    </div>
                                    <div
                                        v-else
                                        class="text-sm"
                                        :style="{ color: derivedColors.textMuted }"
                                    >
                                        {{ t('noData') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Last Updated -->
                            <div class="bg-opacity-50 rounded-lg p-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('lastUpdated') }}
                                </div>
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ formatTimeAgo(currency.last_updated) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <table
                        class="w-full hidden lg:table"
                        role="table"
                        :aria-label="t('marketDataTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('currency') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('price') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('change24h') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('type') }}
                            </th>
                            <th
                                scope="col"
                                class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('lastUpdated') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody
                            class="divide-y"
                            :style="{ borderColor: derivedColors.border + '50' }"
                            role="rowgroup"
                        >
                        <tr
                            v-for="(currency, index) in currencyData"
                            :key="currency.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:opacity-80 transition-colors duration-200"
                        >
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 sm:h-10 sm:w-10 flex-shrink-0 mr-3 sm:mr-4">
                                        <img
                                            v-if="currency.image_url"
                                            :src="currency.image_url"
                                            :alt="currency.name"
                                            class="h-8 w-8 sm:h-10 sm:w-10 rounded-full"
                                            @error="$event.target.style.display='none'"
                                        >
                                        <div
                                            v-else
                                            class="h-8 w-8 sm:h-10 sm:w-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center"
                                        >
                                            <span class="text-white font-bold text-xs sm:text-sm">{{ currency.symbol?.slice(0, 2) || '??' }}</span>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ currency.name }}
                                        </div>
                                        <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ currency.symbol }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatPrice(currency.current_price) }}
                                </div>
                                <div
                                    v-if="currency.previous_price && currency.previous_price !== currency.current_price"
                                    class="text-xs"
                                    :style="{ color: derivedColors.textMuted }"
                                >
                                    {{ t('prev') }}: {{ currencySymbol }}{{ formatPrice(currency.previous_price) }}
                                </div>
                            </td>

                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div
                                    v-if="currency.change_percent !== null"
                                    class="flex items-center"
                                >
                                    <span
                                        class="text-sm font-medium flex items-center"
                                        :style="{ color: getChangeColor(currency.change_percent) }"
                                    >
                                        <svg
                                            v-if="currency.change_percent > 0"
                                            class="w-4 h-4 mr-1"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <svg
                                            v-else-if="currency.change_percent < 0"
                                            class="w-4 h-4 mr-1"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        {{ currency.change_percent >= 0 ? '+' : '' }}{{ currency.change_percent?.toFixed(2) }}%
                                    </span>
                                </div>
                                <div
                                    v-else
                                    class="text-sm"
                                    :style="{ color: derivedColors.textMuted }"
                                >
                                    {{ t('noData') }}
                                </div>
                            </td>

                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full border"
                                    :style="{
                                        backgroundColor: derivedColors.surface + '50',
                                        borderColor: derivedColors.border + '30',
                                        color: derivedColors.textSecondary
                                    }"
                                >
                                    {{ currency.type.charAt(0).toUpperCase() + currency.type.slice(1) }}
                                </span>
                            </td>

                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm" role="cell">
                                <div :style="{ color: derivedColors.textSecondary }">
                                    {{ formatTimeAgo(currency.last_updated) }}
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
                            {{ t('noMarketDataFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('marketDataWillAppearHere') }}
                        </p>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="currencies.data && currencies.data.length > 0 && currencies.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm order-2 sm:order-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: currencies.from || 0,
                            to: currencies.to || 0,
                            total: currencies.total || 0
                        }) }}
                        </div>
                        <nav
                            class="flex flex-wrap gap-1 justify-center sm:justify-end order-1 sm:order-2"
                            role="navigation"
                            :aria-label="t('pagination')"
                        >
                            <button
                                v-for="(link, index) in currencies.links"
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
                                    color: link.active ? derivedColors.textPrimary : link.url ? derivedColors.textSecondary : derivedColors.textMuted,
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
    </UserLayout>
</template>
