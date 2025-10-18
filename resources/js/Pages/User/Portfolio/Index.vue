<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    holdings: {
        type: Array,
        default: () => []
    },
    portfolioStats: {
        type: Object,
        default: () => ({
            total_invested: 0,
            current_value: 0,
            total_profit_loss: 0,
            total_profit_loss_percentage: 0,
            total_tokens: 0,
            profitable_holdings: 0,
            losing_holdings: 0,
            total_holdings: 0
        })
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

const currencySymbol = computed(() => page.props.currencySymbol || '$');
const isProcessing = ref(false);
const sellModal = ref({
    isOpen: false,
    holding: null
});
const sellForm = ref({
    tokens_to_sell: 0
});

const modalTitleId = computed(() => 'modal-title-' + Math.random().toString(36).substr(2, 9));
const canSell = computed(() => {
    return sellModal.value.holding &&
        sellForm.value.tokens_to_sell >= 1 &&
        sellForm.value.tokens_to_sell <= sellModal.value.holding.available_tokens &&
        !isProcessing.value;
});

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

const getProfitLossColor = (value) => {
    if (value > 0) return derivedColors.value.success;
    if (value < 0) return derivedColors.value.danger;
    return derivedColors.value.textSecondary;
};

const openSellModal = (holding) => {
    sellModal.value.holding = holding;
    sellModal.value.isOpen = true;
    sellForm.value.tokens_to_sell = 0;
};

const closeSellModal = () => {
    sellModal.value.isOpen = false;
    sellModal.value.holding = null;
    sellForm.value.tokens_to_sell = 0;
};

const calculateSaleAmount = () => {
    if (!sellModal.value.holding || !sellForm.value.tokens_to_sell) return 0;
    return sellForm.value.tokens_to_sell * sellModal.value.holding.token.current_price;
};

const submitSale = () => {
    if (!canSell.value) {
        showToast(t('enterValidTokensToSell'), 'warning');
        return;
    }

    isProcessing.value = true;

    const saleData = {
        ico_token_id: sellModal.value.holding.token.id,
        tokens_to_sell: parseInt(sellForm.value.tokens_to_sell)
    };

    router.post('/user/investment/portfolio/sell', saleData, {
        onSuccess: () => {
            closeSellModal();
            showToast(t('tokensSoldSuccessfully'), 'success');
        },
        onError: (errors) => {
            console.error('Sale errors:', errors);
            const errorMessages = Object.values(errors).flat();
            const errorMessage = errorMessages[0] || t('failedToSellTokens');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};
</script>

<template>
    <UserLayout
        :page-title="t('myPortfolio')"
        :page-section="t('portfolio')"
    >
        <div class="max-w-none mx-auto space-y-4 sm:space-y-6 px-3 sm:px-4 lg:px-6">
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4"
                role="region"
                :aria-label="t('portfolioStatistics')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold break-words"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalInvested')"
                    >
                        {{ currencySymbol }}{{ formatNumber(portfolioStats?.total_invested || 0) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalInvested') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold break-words"
                        :style="{ color: derivedColors.success }"
                        :aria-label="t('currentValue')"
                    >
                        {{ currencySymbol }}{{ formatNumber(portfolioStats?.current_value || 0) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('currentValue') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold break-words"
                        :style="{ color: getProfitLossColor(portfolioStats?.total_profit_loss || 0) }"
                        :aria-label="t('totalProfitLoss')"
                    >
                        {{ portfolioStats?.total_profit_loss >= 0 ? '+' : '' }}{{ currencySymbol }}{{ formatNumber(portfolioStats?.total_profit_loss || 0) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalPL') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-xl lg:text-2xl font-bold break-words"
                        :style="{ color: getProfitLossColor(portfolioStats?.total_profit_loss_percentage || 0) }"
                        :aria-label="t('totalProfitLossPercentage')"
                    >
                        {{ portfolioStats?.total_profit_loss_percentage >= 0 ? '+' : '' }}{{ formatNumber(portfolioStats?.total_profit_loss_percentage || 0) }}%
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalPLPercentage') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-2 sm:space-y-0">
                        <h2 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('tokenHoldings') }}
                        </h2>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ holdings?.length || 0 }} {{ t('differentTokens') }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="holdings && holdings.length > 0"
                    class="overflow-x-auto"
                >
                    <div class="block xl:hidden">
                        <div
                            v-for="(holding, index) in holdings"
                            :key="holding.token.id"
                            class="border-b p-4 space-y-4 hover:opacity-80 transition-colors duration-200"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3 min-w-0 flex-1">
                                    <div class="h-10 w-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-bold text-sm">{{ holding.token.symbol?.slice(0, 2) || '??' }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ holding.token.name }}
                                        </div>
                                        <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ holding.token.symbol }}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    :disabled="holding.available_tokens <= 0"
                                    class="px-3 py-1 rounded-lg text-xs font-medium transition-colors disabled:opacity-50"
                                    :style="{
                                        backgroundColor: holding.available_tokens > 0 ? derivedColors.danger + '20' : derivedColors.surface,
                                        color: holding.available_tokens > 0 ? derivedColors.danger : derivedColors.textMuted
                                    }"
                                    @click="openSellModal(holding)"
                                >
                                    {{ t('sell') }}
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('holdings') }}
                                    </div>
                                    <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ formatNumber(holding.available_tokens) }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ holding.purchase_count }} {{ t('purchase') }}{{ holding.purchase_count > 1 ? 's' : '' }}
                                        <span
                                            v-if="holding.sold_tokens > 0"
                                            :style="{ color: derivedColors.warning }"
                                            class="block"
                                        >
                                            ({{ formatNumber(holding.sold_tokens) }} {{ t('sold') }})
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-medium mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('currentPrice') }}
                                    </div>
                                    <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ currencySymbol }}{{ formatNumber(holding.token.current_price) }}
                                    </div>
                                    <div
                                        v-if="holding.token.price_change_percentage !== 0"
                                        class="text-xs flex items-center"
                                        :style="{ color: getProfitLossColor(holding.token.price_change_percentage) }"
                                    >
                                        <svg
                                            v-if="holding.token.price_change_percentage > 0"
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
                                            v-else
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
                                        {{ Math.abs(holding.token.price_change_percentage) }}%
                                    </div>
                                </div>
                            </div>

                            <div class="bg-opacity-50 rounded-lg p-3 space-y-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                            {{ t('avgPrice') }}
                                        </div>
                                        <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                            {{ currencySymbol }}{{ formatNumber(holding.average_price) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                            {{ t('totalInvested') }}
                                        </div>
                                        <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                            {{ currencySymbol }}{{ formatNumber(holding.total_invested) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                            {{ t('currentValue') }}
                                        </div>
                                        <div class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                            {{ currencySymbol }}{{ formatNumber(holding.current_value) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                            {{ t('profitLoss') }}
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <div
                                                class="text-sm font-medium"
                                                :style="{ color: getProfitLossColor(holding.profit_loss) }"
                                            >
                                                {{ holding.profit_loss >= 0 ? '+' : '' }}{{ currencySymbol }}{{ formatNumber(holding.profit_loss) }}
                                            </div>
                                            <div class="flex items-center">
                                                <span
                                                    class="text-xs font-medium"
                                                    :style="{ color: getProfitLossColor(holding.profit_loss_percentage) }"
                                                >
                                                    ({{ holding.profit_loss_percentage >= 0 ? '+' : '' }}{{ formatNumber(holding.profit_loss_percentage) }}%)
                                                </span>
                                                <svg
                                                    v-if="holding.is_profitable"
                                                    class="w-3 h-3 ml-1"
                                                    :style="{ color: derivedColors.success }"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else
                                                    class="w-3 h-3 ml-1"
                                                    :style="{ color: derivedColors.danger }"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table
                        class="w-full min-w-[1000px] hidden xl:table"
                        role="table"
                        :aria-label="t('tokenHoldingsTable')"
                    >
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr role="row">
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('token') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('holdings') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('avgPrice') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('currentPrice') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('totalInvested') }}
                            </th>
                            <th
                                scope="col"
                                class="px-3 sm:px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('currentValue') }}
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
                                {{ t('profitLossPercentage') }}
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
                            v-for="(holding, index) in holdings"
                            :key="holding.token.id"
                            role="row"
                            :aria-rowindex="index + 2"
                            class="hover:opacity-80 transition-colors duration-200 focus-within:opacity-80"
                        >
                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 sm:h-10 sm:w-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                        <span class="text-white font-bold text-xs sm:text-sm">{{ holding.token.symbol?.slice(0, 2) || '??' }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs sm:text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ holding.token.name }}
                                        </div>
                                        <div class="text-xs font-mono truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ holding.token.symbol }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ formatNumber(holding.available_tokens) }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ holding.purchase_count }} {{ t('purchase') }}{{ holding.purchase_count > 1 ? 's' : '' }}
                                    <span
                                        v-if="holding.sold_tokens > 0"
                                        :style="{ color: derivedColors.warning }"
                                        class="ml-1"
                                    >
                                        ({{ formatNumber(holding.sold_tokens) }} {{ t('sold') }})
                                    </span>
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ currencySymbol }}{{ formatNumber(holding.average_price) }}
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(holding.token.current_price) }}
                                </div>
                                <div
                                    v-if="holding.token.price_change_percentage !== 0"
                                    class="text-xs flex items-center"
                                    :style="{ color: getProfitLossColor(holding.token.price_change_percentage) }"
                                >
                                    <svg
                                        v-if="holding.token.price_change_percentage > 0"
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
                                        v-else
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
                                    {{ Math.abs(holding.token.price_change_percentage) }}%
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ currencySymbol }}{{ formatNumber(holding.total_invested) }}
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(holding.current_value) }}
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div
                                    class="text-xs sm:text-sm font-medium"
                                    :style="{ color: getProfitLossColor(holding.profit_loss) }"
                                >
                                    {{ holding.profit_loss >= 0 ? '+' : '' }}{{ currencySymbol }}{{ formatNumber(holding.profit_loss) }}
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <div class="flex items-center">
                                    <span
                                        class="text-xs sm:text-sm font-medium"
                                        :style="{ color: getProfitLossColor(holding.profit_loss_percentage) }"
                                    >
                                        {{ holding.profit_loss_percentage >= 0 ? '+' : '' }}{{ formatNumber(holding.profit_loss_percentage) }}%
                                    </span>
                                    <div class="ml-1 sm:ml-2">
                                        <svg
                                            v-if="holding.is_profitable"
                                            class="w-3 h-3 sm:w-4 sm:h-4"
                                            :style="{ color: derivedColors.success }"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="w-3 h-3 sm:w-4 sm:h-4"
                                            :style="{ color: derivedColors.danger }"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 sm:px-6 py-4 whitespace-nowrap" role="cell">
                                <button
                                    :disabled="holding.available_tokens <= 0"
                                    :aria-label="t('sellTokens', { symbol: holding.token.symbol })"
                                    class="py-1 px-2 sm:px-3 rounded-lg text-xs sm:text-sm font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :style="{
                                        backgroundColor: holding.available_tokens > 0 ? derivedColors.danger : derivedColors.surface,
                                        color: holding.available_tokens > 0 ? derivedColors.textPrimary : derivedColors.textMuted,
                                        ':focus': { ringColor: derivedColors.danger }
                                    }"
                                    @click="openSellModal(holding)"
                                >
                                    {{ t('sell') }}
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
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                        <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                            {{ t('noTokenHoldingsFound') }}
                        </p>
                        <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                            {{ t('startInvestingToSeePortfolio') }}
                        </p>
                        <Link
                            href="/user/investment/ico-tokens"
                            class="mt-4 py-3 px-6 rounded-lg font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
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
            </div>
        </div>

        <div
            v-if="sellModal.isOpen"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="modalTitleId"
            @click.self="closeSellModal"
            @keydown.escape="closeSellModal"
        >
            <div
                class="rounded-xl shadow-2xl border max-w-md w-full mx-4 focus:outline-none"
                :style="{ backgroundColor: derivedColors.background, borderColor: derivedColors.border + '20' }"
                tabindex="-1"
            >
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="modalTitleId"
                            class="text-lg sm:text-xl font-semibold pr-2"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('sellToken', { symbol: sellModal.holding?.token?.symbol }) }}
                        </h3>
                        <button
                            :aria-label="t('closeModal')"
                            class="hover:opacity-80 transition-colors p-1 rounded focus:outline-none focus:ring-2 focus:ring-opacity-50 flex-shrink-0"
                            :style="{
                                color: derivedColors.textMuted,
                                ':focus': { ringColor: derivedColors.accent }
                            }"
                            @click="closeSellModal"
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

                <div class="p-4 sm:p-6">
                    <form class="space-y-6" @submit.prevent="submitSale">
                        <div
                            v-if="sellModal.holding"
                            class="rounded-xl p-4 sm:p-5 border"
                            :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                        >
                            <div class="flex items-center space-x-3 sm:space-x-4 mb-4">
                                <div class="h-10 w-10 sm:h-12 sm:w-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-2xl flex items-center justify-center flex-shrink-0">
                                    <span class="text-white font-bold text-base sm:text-lg">{{ sellModal.holding.token.symbol?.slice(0, 2) || '??' }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-lg sm:text-xl break-words" :style="{ color: derivedColors.textPrimary }">
                                        {{ sellModal.holding.token.name }}
                                    </div>
                                    <div class="text-sm font-mono break-words" :style="{ color: derivedColors.textMuted }">
                                        {{ sellModal.holding.token.symbol }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('available') }}:</span>
                                    <div class="font-bold" :style="{ color: derivedColors.textPrimary }">
                                        {{ formatNumber(sellModal.holding.available_tokens) }}
                                    </div>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('currentPrice') }}:</span>
                                    <div class="font-bold" :style="{ color: derivedColors.success }">
                                        {{ currencySymbol }}{{ sellModal.holding.token.current_price }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-3" :style="{ color: derivedColors.textPrimary }">{{ t('tokensToSell') }}</label>
                            <input
                                v-model.number="sellForm.tokens_to_sell"
                                type="number"
                                step="1"
                                min="1"
                                :max="sellModal.holding?.available_tokens || 0"
                                class="w-full px-3 sm:px-4 py-3 sm:py-4 border rounded-xl text-base sm:text-lg font-medium placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                :style="{
                                    backgroundColor: derivedColors.surface + '50',
                                    borderColor: derivedColors.border + '50',
                                    color: derivedColors.textPrimary,
                                    ':focus': { ringColor: derivedColors.accent, borderColor: derivedColors.accent }
                                }"
                                :placeholder="t('enterTokenAmount')"
                                required
                            >
                            <div class="text-sm mt-2" :style="{ color: derivedColors.textMuted }">
                                {{ t('maxTokens', { amount: formatNumber(sellModal.holding?.available_tokens || 0) }) }}
                            </div>
                        </div>

                        <div
                            v-if="sellForm.tokens_to_sell && sellModal.holding"
                            class="rounded-xl p-4 sm:p-5 border"
                            :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                        >
                            <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                                <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.danger }"></div>
                                {{ t('saleSummary') }}
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span :style="{ color: derivedColors.textMuted }">{{ t('tokensToSell') }}:</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ formatNumber(sellForm.tokens_to_sell) }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span :style="{ color: derivedColors.textMuted }">{{ t('salePrice') }}:</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ currencySymbol }}{{ sellModal.holding.token.current_price }}</span>
                                </div>
                                <div class="flex justify-between items-center border-t pt-3" :style="{ borderColor: derivedColors.border }">
                                    <span class="font-medium" :style="{ color: derivedColors.textMuted }">{{ t('totalAmount') }}:</span>
                                    <span class="font-bold text-base sm:text-lg" :style="{ color: derivedColors.success }">{{ currencySymbol }}{{ formatNumber(calculateSaleAmount()) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3 pt-2">
                            <button
                                type="button"
                                class="flex-1 py-3 px-4 rounded-xl font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                :style="{
                                    backgroundColor: derivedColors.surface,
                                    color: derivedColors.textPrimary,
                                    ':focus': { ringColor: derivedColors.accent }
                                }"
                                @click="closeSellModal"
                            >
                                {{ t('cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="!canSell || isProcessing"
                                class="flex-1 py-3 px-4 rounded-xl font-bold disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                :style="{
                                    backgroundColor: derivedColors.danger,
                                    color: derivedColors.textPrimary,
                                    ':focus': { ringColor: derivedColors.danger }
                                }"
                            >
                                <span
                                    v-if="isProcessing"
                                    class="flex items-center justify-center"
                                >
                                    <svg
                                        class="animate-spin -ml-1 mr-2 h-5 w-5"
                                        xmlns="http://www.w3.org/2000/svg"
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
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{ t('processing') }}...
                                </span>
                                <span v-else>{{ t('sellTokens', { symbol: sellModal.holding.token.symbol }) }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
