<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    availableSymbols: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    activeTrades: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    recentTrades: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    userBalance: {
        type: Number,
        default: 0,
        validator: (value) => typeof value === 'number' && value >= 0
    },
    statistics: {
        type: Object,
        default: () => ({
            win_rate: 0,
            total_profit: 0,
            total_trades: 0
        }),
        validator: (value) => value && typeof value === 'object'
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => value && typeof value === 'object'
    },
    serverTimezone: {
        type: String,
        default: 'Asia/Dhaka'
    },
    serverTime: {
        type: String,
        default: () => new Date().toISOString()
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
const selectedSymbol = ref(null);
const isPlacingTrade = ref(false);
const isRefreshing = ref(false);
const showCancelModal = ref(false);
const selectedTrade = ref(null);
const isCancelling = ref(false);
const searchQuery = ref('');
const tradeForm = reactive({
    direction: 'up',
    amount: 10,
    duration: null
});

let refreshInterval = null;
let tradingViewWidget = null;
const filteredSymbols = computed(() => {
    if (!props.availableSymbols) return [];
    let symbols = [...props.availableSymbols];
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim();
        symbols = symbols.filter(symbol =>
            symbol.symbol.toLowerCase().includes(query)
        );
    } else {
        symbols = symbols.slice(0, 10);
    }

    return symbols;
});

const clearSearch = () => {
    searchQuery.value = '';
};

const getServerTime = () => {
    try {
        const now = new Date();
        const formatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: props.serverTimezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });

        const parts = formatter.formatToParts(now);
        const year = parts.find(p => p.type === 'year').value;
        const month = parts.find(p => p.type === 'month').value;
        const day = parts.find(p => p.type === 'day').value;
        const hour = parts.find(p => p.type === 'hour').value;
        const minute = parts.find(p => p.type === 'minute').value;
        const second = parts.find(p => p.type === 'second').value;

        return new Date(`${year}-${month}-${day}T${hour}:${minute}:${second}`);
    } catch (error) {
        console.error('Error getting server time with Intl API:', error);

        if (props.serverTimezone === 'Asia/Dhaka') {
            const now = new Date();
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            return new Date(utc + (6 * 3600000)); // UTC+6
        }

        return new Date();
    }
};

const getCurrentDayName = () => {
    const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const serverTime = getServerTime();
    return days[serverTime.getDay()];
};

const getCurrentTime = () => {
    const serverTime = getServerTime();
    return serverTime.getHours().toString().padStart(2, '0') + ':' +
        serverTime.getMinutes().toString().padStart(2, '0');
};

const timeToMinutes = (timeStr) => {
    if (!timeStr || typeof timeStr !== 'string') {
        console.error('Invalid time string:', timeStr);
        return 0;
    }

    const parts = timeStr.split(':');
    if (parts.length !== 2) {
        console.error('Invalid time format:', timeStr);
        return 0;
    }

    const hours = parseInt(parts[0], 10);
    const minutes = parseInt(parts[1], 10);

    if (isNaN(hours) || isNaN(minutes)) {
        console.error('Invalid time values:', timeStr);
        return 0;
    }

    return hours * 60 + minutes;
};

const isMarketOpen = (symbol) => {
    if (!symbol || !symbol.trading_hours) {
        console.log('No trading hours for symbol:', symbol?.symbol);
        return false;
    }

    let tradingHours = symbol.trading_hours;
    if (typeof tradingHours === 'string') {
        try {
            tradingHours = JSON.parse(tradingHours);
        } catch (e) {
            console.error('Failed to parse trading hours:', e);
            return false;
        }
    }

    const currentDay = getCurrentDayName();
    const currentTime = getCurrentTime();
    const daySchedule = tradingHours[currentDay];

    if (!daySchedule || daySchedule.enabled !== true) {
        return false;
    }

    const currentMinutes = timeToMinutes(currentTime);
    const startMinutes = timeToMinutes(daySchedule.start);
    const endMinutes = timeToMinutes(daySchedule.end);

    if (startMinutes <= endMinutes) {
        const isOpen = currentMinutes >= startMinutes && currentMinutes <= endMinutes;
        return isOpen;
    }

    const isOpen = currentMinutes >= startMinutes || currentMinutes <= endMinutes;
    return isOpen;
};

const getMarketStatus = (symbol) => {
    if (!symbol || !symbol.trading_hours) {
        return t('tradingHoursNotConfigured');
    }

    let tradingHours = symbol.trading_hours;
    if (typeof tradingHours === 'string') {
        try {
            tradingHours = JSON.parse(tradingHours);
        } catch (e) {
            return t('invalidTradingHoursConfiguration');
        }
    }

    const currentDay = getCurrentDayName();
    const daySchedule = tradingHours[currentDay];

    if (!daySchedule || daySchedule.enabled !== true) {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const currentDayIndex = days.indexOf(currentDay);

        for (let i = 1; i <= 7; i++) {
            const nextDayIndex = (currentDayIndex + i) % 7;
            const nextDay = days[nextDayIndex];
            const nextDaySchedule = tradingHours[nextDay];

            if (nextDaySchedule && nextDaySchedule.enabled === true) {
                const dayName = nextDay.charAt(0).toUpperCase() + nextDay.slice(1);
                return t('opensOnDay', { day: dayName, time: nextDaySchedule.start });
            }
        }
        return t('tradingNotAvailable');
    }

    const currentTime = getCurrentTime();
    const currentMinutes = timeToMinutes(currentTime);
    const startMinutes = timeToMinutes(daySchedule.start);
    const endMinutes = timeToMinutes(daySchedule.end);

    if (startMinutes <= endMinutes) {
        if (currentMinutes < startMinutes) {
            return t('opensToday', { time: daySchedule.start });
        } else if (currentMinutes > endMinutes) {
            return t('closedToday', { time: daySchedule.end });
        } else {
            return t('openUntil', { time: daySchedule.end });
        }
    } else {
        if (currentMinutes > endMinutes && currentMinutes < startMinutes) {
            return t('opensToday', { time: daySchedule.start });
        } else {
            return t('openUntil', { time: daySchedule.end });
        }
    }
};

const canPlaceTrade = computed(() => {
    return selectedSymbol.value &&
        isMarketOpen(selectedSymbol.value) &&
        tradeForm.direction &&
        tradeForm.amount >= (selectedSymbol.value.min_amount || 1) &&
        tradeForm.amount <= (selectedSymbol.value.max_amount || 1000) &&
        tradeForm.amount <= props.userBalance &&
        tradeForm.duration !== null &&
        !isPlacingTrade.value;
});
const initTradingView = (symbol) => {
    const tradingViewSymbol = symbol.currency?.tradingview_symbol || 'BITSTAMP:BTCUSD';
    const container = document.getElementById('tradingview_widget');

    if (!container) {
        console.error('TradingView container not found');
        return;
    }

    // Clear existing content
    container.innerHTML = '';

    // Create widget elements programmatically
    const widgetContainer = document.createElement('div');
    widgetContainer.className = 'tradingview-widget-container';
    widgetContainer.style.height = '100%';
    widgetContainer.style.width = '100%';

    const widgetContent = document.createElement('div');
    widgetContent.className = 'tradingview-widget-container__widget';
    widgetContent.style.height = 'calc(100% - 32px)';
    widgetContent.style.width = '100%';

    const copyright = document.createElement('div');
    copyright.className = 'tradingview-widget-copyright';

    // Create the copyright link based on the symbol
    const symbolForUrl = tradingViewSymbol.replace(':', 'USD/?exchange=');
    copyright.innerHTML = `
        <a href="https://www.tradingview.com/symbols/${symbolForUrl}" rel="noopener nofollow" target="_blank">
            <span class="blue-text">${tradingViewSymbol.replace(':', '').replace('BITSTAMP', '').replace('BINANCE', '')} chart</span>
        </a>
        <span class="trademark"> by TradingView</span>
    `;

    const script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';
    script.async = true;

    const config = {
        "allow_symbol_change": true,
        "calendar": false,
        "details": true,
        "hide_side_toolbar": false,
        "hide_top_toolbar": false,
        "hide_legend": false,
        "hide_volume": false,
        "hotlist": true,
        "interval": "1",
        "locale": "en",
        "save_image": true,
        "style": "1",
        "symbol": tradingViewSymbol,
        "theme": "dark",
        "timezone": props.serverTimezone || "Asia/Dhaka",
        "backgroundColor": "#0F0F0F",
        "gridColor": "rgba(242, 242, 242, 0.06)",
        "watchlist": [],
        "withdateranges": true,
        "range": "1D",
        "compareSymbols": [],
        "show_popup_button": true,
        "popup_height": "650",
        "popup_width": "1000",
        "studies": [
            "STD;24h%Volume",
            "STD;Accumulation_Distribution"
        ],
        "autosize": true
    };

    script.innerHTML = JSON.stringify(config);

    // Assemble the widget
    widgetContainer.appendChild(widgetContent);
    widgetContainer.appendChild(copyright);
    widgetContainer.appendChild(script);
    container.appendChild(widgetContainer);

    console.log('TradingView widget created for:', symbol.symbol, 'with symbol:', tradingViewSymbol);
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

const selectSymbol = (symbol) => {
    selectedSymbol.value = symbol;

    if (symbol.durations && symbol.durations.length > 0) {
        tradeForm.duration = symbol.durations[0];
    } else {
        tradeForm.duration = null;
    }

    const minAmount = symbol.min_amount || 10;
    const maxAmount = symbol.max_amount || 1000;

    if (tradeForm.amount < minAmount) {
        tradeForm.amount = minAmount;
    } else if (tradeForm.amount > maxAmount) {
        tradeForm.amount = maxAmount;
    }

    initTradingView(symbol);
};

const setAmountByPercentage = (percentage) => {
    if (!selectedSymbol.value) return;

    const maxAmount = Math.min(selectedSymbol.value.max_amount || 1000, props.userBalance);
    const calculatedAmount = (maxAmount * percentage) / 100;
    const finalAmount = Math.max(selectedSymbol.value.min_amount || 1, calculatedAmount);

    tradeForm.amount = Math.round(finalAmount * 100) / 100;
};

const formatDuration = (seconds) => {
    if (!seconds) return t('notAvailable');
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
    return `${Math.floor(seconds / 86400)}d`;
};

const getTradeProgress = (trade) => {
    try {
        const now = new Date().getTime();
        const start = new Date(trade.open_time).getTime();
        const end = new Date(trade.expiry_time).getTime();

        if (isNaN(start) || isNaN(end)) return 0;

        const elapsed = now - start;
        const total = end - start;

        if (total <= 0) return 100;

        return Math.min(100, Math.max(0, (elapsed / total) * 100));
    } catch (error) {
        console.error('Error calculating trade progress:', error);
        return 0;
    }
};

const canCancelTrade = (trade) => {
    try {
        const timeSinceOpen = (new Date().getTime() - new Date(trade.open_time).getTime()) / 1000;
        return trade.status === 'active' && timeSinceOpen <= 30;
    } catch (error) {
        console.error('Error checking cancel eligibility:', error);
        return false;
    }
};

const placeTrade = () => {
    if (!canPlaceTrade.value) {
        if (!isMarketOpen(selectedSymbol.value)) {
            showToast(t('marketCurrentlyClosed'), 'warning');
        } else {
            showToast(t('ensureTradeDetailsValid'), 'warning');
        }
        return;
    }

    if (tradeForm.amount > props.userBalance) {
        showToast(t('insufficientBalance'), 'error');
        return;
    }

    isPlacingTrade.value = true;

    const tradeData = {
        symbol: selectedSymbol.value.symbol,
        direction: tradeForm.direction,
        amount: parseFloat(tradeForm.amount),
        duration: tradeForm.duration
    };

    router.post('/user/trading', tradeData, {
        onSuccess: () => {
            const currentSymbol = selectedSymbol.value;
            tradeForm.amount = currentSymbol?.min_amount || 10;
            tradeForm.direction = 'up';
            tradeForm.duration = currentSymbol?.durations?.[0] || null;
            showToast(t('tradePlacedSuccessfully'), 'success');
        },
        onError: (errors) => {
            console.error('Trade placement errors:', errors);
            const errorMessages = Object.values(errors).flat();
            const errorMessage = errorMessages[0] || t('failedToPlaceTrade');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isPlacingTrade.value = false;
        },
        preserveScroll: true
    });
};

const openCancelModal = (trade) => {
    selectedTrade.value = trade;
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    selectedTrade.value = null;
    isCancelling.value = false;
};

const confirmCancel = () => {
    if (!selectedTrade.value) return;

    isCancelling.value = true;
    router.post(`/user/trading/${selectedTrade.value.id}/cancel`, {}, {
        onSuccess: () => {
            showToast(t('tradeCancelledSuccessfully'), 'success');
            closeCancelModal();
        },
        onError: (errors) => {
            console.error('Cancel trade errors:', errors);
            const errorMessages = Object.values(errors).flat();
            const errorMessage = errorMessages[0] || t('failedToCancelTrade');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isCancelling.value = false;
        },
        preserveScroll: true
    });
};

const setupAutoRefresh = () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }

    if (props.activeTrades && props.activeTrades.length > 0) {
        refreshInterval = setInterval(() => {
            if (!isPlacingTrade.value && !isRefreshing.value && props.activeTrades.length > 0) {
                router.reload({
                    only: ['activeTrades', 'userBalance', 'statistics'],
                    preserveScroll: true
                });
            }
        }, 30000);
    }
};

onMounted(() => {
    if (props.availableSymbols.length > 0) {
        const firstSymbols = props.availableSymbols.slice(0, 10);
        const openSymbol = firstSymbols.find(symbol => isMarketOpen(symbol));
        if (openSymbol || firstSymbols.length > 0) {
            selectSymbol(openSymbol || firstSymbols[0]);
        }
    }

    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
    setupAutoRefresh();
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    if (tradingViewWidget) {
        tradingViewWidget = null;
    }
});

watch(() => props.availableSymbols, (newSymbols) => {
    if (newSymbols.length > 0 && !selectedSymbol.value) {
        const firstSymbols = newSymbols.slice(0, 10);
        const openSymbol = firstSymbols.find(symbol => isMarketOpen(symbol));
        if (openSymbol || firstSymbols.length > 0) {
            selectSymbol(openSymbol || firstSymbols[0]);
        }
    }
}, { immediate: true });

watch(() => props.activeTrades, () => {
    setupAutoRefresh();
}, { deep: true });

watch(selectedSymbol, (newSymbol) => {
    if (newSymbol) {
        initTradingView(newSymbol);
    }
});

watch(searchQuery, (newQuery) => {
    if (newQuery.trim() && filteredSymbols.value.length > 0) {
        const currentSymbolInResults = filteredSymbols.value.find(s => s.symbol === selectedSymbol.value?.symbol);
        if (!currentSymbolInResults) {
            const firstResult = filteredSymbols.value[0];
            if (isMarketOpen(firstResult)) {
                selectSymbol(firstResult);
            }
        }
    }
});
</script>

<template>
    <UserLayout
        :page-title="t('trading')"
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
                        :aria-label="t('accountBalance')"
                    >
                        {{ currencySymbol }}{{ formatNumber(userBalance || 0) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('accountBalance') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.success }"
                        :aria-label="t('totalTrades')"
                    >
                        {{ statistics?.total_trades || 0 }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalTrades') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.warning }"
                        :aria-label="t('activeTrades')"
                    >
                        {{ (activeTrades || []).length }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('activeTrades') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: (statistics?.total_profit || 0) >= 0 ? derivedColors.success : derivedColors.danger }"
                        :aria-label="t('totalProfit')"
                    >
                        {{ currencySymbol }}{{ formatNumber(statistics?.total_profit || 0) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalPL') }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 xl:grid-cols-4 gap-6">
                <div class="lg:col-span-1 order-1 lg:order-1">
                    <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                        <div class="flex flex-col sm:flex-row lg:flex-col items-start sm:items-center lg:items-start justify-between mb-4">
                            <h2 class="text-lg font-semibold mb-2 sm:mb-0 lg:mb-2" :style="{ color: derivedColors.textPrimary }">
                                {{ t('selectAsset') }}
                            </h2>
                            <span class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ filteredSymbols.length }} {{ t('available') }}
                            </span>
                        </div>

                        <div class="mb-4">
                            <div class="relative">
                                <svg
                                    class="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2"
                                    :style="{ color: derivedColors.textMuted }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    :placeholder="t('searchSymbol')"
                                    class="w-full pl-10 pr-10 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 transition-all duration-200 text-sm"
                                    :style="{
                                        backgroundColor: derivedColors.surface + '50',
                                        borderColor: derivedColors.border,
                                        color: derivedColors.textPrimary,
                                        ':focus': { ringColor: derivedColors.accent, borderColor: derivedColors.accent }
                                    }"
                                >
                                <button
                                    v-if="searchQuery"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 hover:opacity-80 transition-opacity"
                                    :style="{ color: derivedColors.textMuted }"
                                    @click="clearSearch"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div v-if="!searchQuery" class="text-xs mt-2" :style="{ color: derivedColors.textMuted }">
                                {{ t('showingFirst10Symbols') }}
                            </div>
                        </div>

                        <div v-if="filteredSymbols.length > 0" class="space-y-3 max-h-64 sm:max-h-96 overflow-y-auto">
                            <button
                                v-for="symbol in filteredSymbols"
                                :key="symbol.symbol"
                                :disabled="!isMarketOpen(symbol)"
                                :aria-label="t('selectSymbolFor', { symbol: symbol.symbol })"
                                class="w-full p-3 sm:p-4 rounded-lg border-2 transition-all duration-200 text-left hover:scale-105 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                :style="{
                                    borderColor: selectedSymbol?.symbol === symbol.symbol ? derivedColors.accent : (isMarketOpen(symbol) ? derivedColors.border : derivedColors.danger),
                                    backgroundColor: selectedSymbol?.symbol === symbol.symbol ? derivedColors.accent + '10' : (isMarketOpen(symbol) ? derivedColors.surface + '30' : derivedColors.danger + '20'),
                                    color: selectedSymbol?.symbol === symbol.symbol ? derivedColors.accent : (isMarketOpen(symbol) ? derivedColors.textPrimary : derivedColors.danger),
                                    opacity: isMarketOpen(symbol) ? '1' : '0.6',
                                    cursor: isMarketOpen(symbol) ? 'pointer' : 'not-allowed',
                                    ':focus': { ringColor: derivedColors.accent }
                                }"
                                @click="selectSymbol(symbol)"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0 flex-1">
                                        <div class="w-6 h-6 sm:w-8 sm:h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-white font-bold text-xs">{{ symbol.symbol?.slice(0, 2) || '??' }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold text-sm sm:text-base truncate">{{ symbol.symbol }}</div>
                                            <div class="text-xs opacity-75 truncate">{{ t('payoutRate') }}: {{ symbol.payout_rate }}%</div>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="font-bold text-xs sm:text-sm" :style="{ color: derivedColors.success }">{{ symbol.payout_rate }}%</div>
                                        <div class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ currencySymbol }}{{ formatNumber(symbol.min_amount) }} - {{ currencySymbol }}{{ formatNumber(symbol.max_amount) }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="!isMarketOpen(symbol)"
                                    class="mt-2 text-xs"
                                    :style="{ color: derivedColors.danger }"
                                >
                                    {{ getMarketStatus(symbol) }}
                                </div>
                            </button>
                        </div>

                        <div v-else class="text-center py-6 sm:py-8">
                            <svg
                                class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4"
                                :style="{ color: derivedColors.textMuted }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                            <p class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                                {{ searchQuery ? t('noSymbolsFound') : t('noSymbolsAvailable') }}
                            </p>
                            <p class="text-xs sm:text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                {{ searchQuery ? t('tryDifferentSearch') : t('checkBackLater') }}
                            </p>
                            <button
                                v-if="searchQuery"
                                class="mt-3 text-xs sm:text-sm py-2 px-4 rounded-lg border transition-colors"
                                :style="{
                                    borderColor: derivedColors.border,
                                    color: derivedColors.textSecondary,
                                    ':hover': { backgroundColor: derivedColors.surface + '30' }
                                }"
                                @click="clearSearch"
                            >
                                {{ t('clearSearch') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Chart Section - Full width on mobile, 2 columns on large screens -->
                <div class="lg:col-span-2 order-2 lg:order-2">
                    <div class="backdrop-blur-sm rounded-xl border overflow-hidden" :style="cardStyle">
                        <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-2 sm:space-y-0">
                                <h2 class="text-lg font-semibold truncate" :style="{ color: derivedColors.textPrimary }">
                                    {{ selectedSymbol ? selectedSymbol.symbol : t('selectAssetToViewChart') }}
                                </h2>
                                <div v-if="selectedSymbol" class="flex items-center space-x-4">
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ t('live') }}
                                    </div>
                                    <div
                                        class="w-2 h-2 rounded-full animate-pulse"
                                        :style="{ backgroundColor: isMarketOpen(selectedSymbol) ? derivedColors.success : derivedColors.danger }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <div
                                v-if="selectedSymbol"
                                id="tradingview_widget"
                                class="h-64 sm:h-80 lg:h-[500px] w-full flex items-center justify-center"
                                :style="{ backgroundColor: derivedColors.surface + '30' }"
                            >
                                <div class="text-center p-4">
                                    <div class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 rounded-full flex items-center justify-center" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                        <svg
                                            class="w-6 h-6 sm:w-8 sm:h-8"
                                            :style="{ color: derivedColors.accent }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                            />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg sm:text-xl font-semibold mb-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ selectedSymbol.symbol }} {{ t('chart') }}
                                    </h3>
                                    <p class="text-sm mb-2" :style="{ color: derivedColors.textSecondary }">
                                        {{ t('payoutRate') }}: {{ selectedSymbol.payout_rate }}%
                                    </p>
                                    <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('chartPlaceholder') }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-else
                                class="h-64 sm:h-80 lg:h-[500px] flex items-center justify-center"
                                :style="{ backgroundColor: derivedColors.surface + '30' }"
                            >
                                <div class="text-center p-4">
                                    <svg
                                        class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4"
                                        :style="{ color: derivedColors.textMuted }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                        />
                                    </svg>
                                    <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                        {{ t('selectAssetToViewChart') }}
                                    </p>
                                    <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('chooseAssetFromLeft') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1 order-3 lg:order-3 space-y-6">
                    <div v-if="selectedSymbol && isMarketOpen(selectedSymbol)" class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('placeOrder') }}
                        </h3>

                        <form class="space-y-4" @submit.prevent="placeTrade">
                            <div>
                                <label class="block text-sm font-medium mb-3" :style="{ color: derivedColors.textSecondary }">{{ t('direction') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        :aria-label="t('callUp')"
                                        class="p-3 sm:p-4 rounded-lg border transition-all duration-200 text-center hover:scale-105 focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                        :style="{
                                            borderColor: tradeForm.direction === 'up' ? derivedColors.success : derivedColors.border,
                                            backgroundColor: tradeForm.direction === 'up' ? derivedColors.success + '20' : derivedColors.surface + '30',
                                            color: tradeForm.direction === 'up' ? derivedColors.success : derivedColors.textSecondary,
                                            ':focus': { ringColor: derivedColors.success }
                                        }"
                                        @click="tradeForm.direction = 'up'"
                                    >
                                        <div class="flex items-center justify-center mb-1">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                            <span class="font-bold text-sm">{{ t('call') }}</span>
                                        </div>
                                        <div class="text-xs opacity-80">{{ t('priceWillRise') }}</div>
                                    </button>

                                    <button
                                        type="button"
                                        :aria-label="t('putDown')"
                                        class="p-3 sm:p-4 rounded-lg border transition-all duration-200 text-center hover:scale-105 focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                        :style="{
                                            borderColor: tradeForm.direction === 'down' ? derivedColors.danger : derivedColors.border,
                                            backgroundColor: tradeForm.direction === 'down' ? derivedColors.danger + '20' : derivedColors.surface + '30',
                                            color: tradeForm.direction === 'down' ? derivedColors.danger : derivedColors.textSecondary,
                                            ':focus': { ringColor: derivedColors.danger }
                                        }"
                                        @click="tradeForm.direction = 'down'"
                                    >
                                        <div class="flex items-center justify-center mb-1">
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                            </svg>
                                            <span class="font-bold text-sm">{{ t('put') }}</span>
                                        </div>
                                        <div class="text-xs opacity-80">{{ t('priceWillFall') }}</div>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">{{ t('investmentAmount') }}</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 font-medium" :style="{ color: derivedColors.accent }">{{ currencySymbol }}</span>
                                    <input
                                        v-model.number="tradeForm.amount"
                                        type="number"
                                        step="0.01"
                                        :min="selectedSymbol?.min_amount || 1"
                                        :max="Math.min(selectedSymbol?.max_amount || 1000, userBalance)"
                                        class="w-full pl-8 pr-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 transition-all duration-200 text-sm"
                                        :style="{
                                            backgroundColor: derivedColors.surface + '50',
                                            borderColor: derivedColors.border,
                                            color: derivedColors.textPrimary,
                                            ':focus': { ringColor: derivedColors.accent, borderColor: derivedColors.accent }
                                        }"
                                        :placeholder="t('enterAmount')"
                                        required
                                    >
                                </div>
                                <div class="flex justify-between text-xs mt-2" :style="{ color: derivedColors.textMuted }">
                                    <span>{{ t('min') }}: {{ currencySymbol }}{{ formatNumber(selectedSymbol?.min_amount || 1) }}</span>
                                    <span>{{ t('max') }}: {{ currencySymbol }}{{ formatNumber(selectedSymbol?.max_amount || 1000) }}</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4 gap-2 mt-3">
                                    <button
                                        v-for="percentage in [25, 50, 75, 100]"
                                        :key="percentage"
                                        type="button"
                                        class="text-xs py-2 px-2 sm:px-3 rounded-lg transition-colors duration-200 border"
                                        :style="{
                                            backgroundColor: derivedColors.surface,
                                            borderColor: derivedColors.border,
                                            color: derivedColors.textSecondary,
                                            ':hover': { borderColor: derivedColors.accent + '50' }
                                        }"
                                        @click="setAmountByPercentage(percentage)"
                                    >
                                        {{ percentage }}%
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-3" :style="{ color: derivedColors.textSecondary }">{{ t('duration') }}</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="duration in selectedSymbol?.durations || []"
                                        :key="duration"
                                        type="button"
                                        :aria-label="t('selectDuration', { duration: formatDuration(duration) })"
                                        class="p-2 sm:p-3 rounded-lg border transition-all duration-200 text-center hover:scale-105 focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                        :style="{
                                            borderColor: tradeForm.duration === duration ? derivedColors.accent : derivedColors.border,
                                            backgroundColor: tradeForm.duration === duration ? derivedColors.accent + '10' : derivedColors.surface + '30',
                                            color: tradeForm.duration === duration ? derivedColors.accent : derivedColors.textSecondary,
                                            ':focus': { ringColor: derivedColors.accent }
                                        }"
                                        @click="tradeForm.duration = duration"
                                    >
                                        <div class="font-semibold text-xs sm:text-sm">{{ formatDuration(duration) }}</div>
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="tradeForm.amount && tradeForm.duration && selectedSymbol"
                                class="rounded-xl p-3 sm:p-4 border"
                                :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '20' }"
                            >
                                <h4 class="font-semibold mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('tradeSummary') }}</h4>
                                <div class="space-y-2 text-xs sm:text-sm">
                                    <div class="flex justify-between">
                                        <span :style="{ color: derivedColors.textMuted }">{{ t('investment') }}:</span>
                                        <span :style="{ color: derivedColors.textPrimary }">{{ currencySymbol }}{{ formatNumber(tradeForm.amount) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span :style="{ color: derivedColors.textMuted }">{{ t('payoutRate') }}:</span>
                                        <span :style="{ color: derivedColors.success }">{{ selectedSymbol.payout_rate }}%</span>
                                    </div>
                                    <div class="flex justify-between border-t pt-2" :style="{ borderColor: derivedColors.border }">
                                        <span :style="{ color: derivedColors.textMuted }">{{ t('potentialProfit') }}:</span>
                                        <span class="font-bold" :style="{ color: derivedColors.success }">
                                            {{ currencySymbol }}{{ formatNumber((tradeForm.amount * selectedSymbol.payout_rate) / 100) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                :disabled="!canPlaceTrade || isPlacingTrade"
                                class="w-full py-3 sm:py-4 px-4 sm:px-6 rounded-xl font-bold transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                :style="{
                                    backgroundColor: canPlaceTrade && !isPlacingTrade ? derivedColors.accent : derivedColors.surface,
                                    color: canPlaceTrade && !isPlacingTrade ? derivedColors.background : derivedColors.textMuted,
                                    ':focus': { ringColor: derivedColors.accent }
                                }"
                            >
                                <span v-if="isPlacingTrade" class="flex items-center justify-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    {{ t('placingTrade') }}...
                                </span>
                                <span v-else>{{ t('placeTrade') }}</span>
                            </button>
                        </form>
                    </div>

                    <div v-else-if="selectedSymbol && !isMarketOpen(selectedSymbol)" class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                        <div class="text-center">
                            <svg class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4" :style="{ color: derivedColors.danger }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="text-base sm:text-lg font-semibold mb-2" :style="{ color: derivedColors.danger }">{{ t('marketClosed') }}</h3>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">{{ getMarketStatus(selectedSymbol) }}</p>
                        </div>
                    </div>

                    <div v-else class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                        <div class="text-center">
                            <svg class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4" :style="{ color: derivedColors.textMuted }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                            <h3 class="text-base sm:text-lg font-semibold mb-2" :style="{ color: derivedColors.textPrimary }">{{ t('selectAssetToTrade') }}</h3>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('chooseAssetFromLeft') }}</p>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 space-y-2 sm:space-y-0">
                            <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">{{ t('activeTrades') }}</h3>
                            <span class="text-xs sm:text-sm px-2 sm:px-3 py-1 rounded-full border" :style="{ backgroundColor: derivedColors.accent + '20', borderColor: derivedColors.accent + '30', color: derivedColors.accent }">
                                {{ (activeTrades || []).length }} {{ t('active') }}
                            </span>
                        </div>

                        <div v-if="!activeTrades || activeTrades.length === 0" class="text-center py-6 sm:py-8">
                            <svg class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4" :style="{ color: derivedColors.textMuted }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">{{ t('noActiveTrades') }}</p>
                            <p class="text-xs sm:text-sm mt-1" :style="{ color: derivedColors.textMuted }">{{ t('placeTradeToGetStarted') }}</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="trade in activeTrades"
                                :key="trade.id"
                                class="border rounded-xl p-3 sm:p-4 transition-all duration-200"
                                :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-3 space-y-2 sm:space-y-0">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-semibold text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ trade.symbol }}</span>
                                        <span
                                            class="px-2 py-1 rounded-full text-xs font-medium border"
                                            :style="{
                                                backgroundColor: trade.direction === 'up' ? derivedColors.success + '20' : derivedColors.danger + '20',
                                                borderColor: trade.direction === 'up' ? derivedColors.success + '30' : derivedColors.danger + '30',
                                                color: trade.direction === 'up' ? derivedColors.success : derivedColors.danger
                                            }"
                                        >
                                            {{ trade.direction === 'up' ? t('call') : t('put') }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ currencySymbol }}{{ formatNumber(trade.amount) }}</div>
                                        <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ trade.time_remaining || t('calculating') }}</div>
                                    </div>
                                </div>

                                <div class="w-full rounded-full h-2 overflow-hidden mb-3" :style="{ backgroundColor: derivedColors.surface }">
                                    <div
                                        class="h-2 rounded-full transition-all duration-1000"
                                        :style="{
                                            width: getTradeProgress(trade) + '%',
                                            backgroundColor: derivedColors.accent
                                        }"
                                        role="progressbar"
                                        :aria-valuenow="getTradeProgress(trade)"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    ></div>
                                </div>

                                <div v-if="canCancelTrade(trade)" class="text-right">
                                    <button
                                        class="text-xs py-1 px-2 sm:px-3 rounded-lg border transition-colors duration-200"
                                        :style="{
                                            borderColor: derivedColors.danger + '30',
                                            color: derivedColors.danger,
                                            ':hover': { backgroundColor: derivedColors.danger + '10' }
                                        }"
                                        @click="openCancelModal(trade)"
                                    >
                                        {{ t('cancel') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showCancelModal"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeCancelModal"
        >
            <div
                class="rounded-xl shadow-2xl border w-full max-w-md focus:outline-none"
                :style="{ backgroundColor: derivedColors.background, borderColor: derivedColors.border + '20' }"
                @click.stop
            >
                <div class="flex items-center justify-between p-4 sm:p-6 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <h3 class="text-base sm:text-lg font-semibold flex items-center" :style="{ color: derivedColors.textPrimary }">
                        <svg class="w-5 h-5 mr-3" :style="{ color: derivedColors.danger }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        {{ t('cancelTrade') }}
                    </h3>
                    <button
                        class="hover:opacity-80 transition-colors p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50"
                        :style="{ color: derivedColors.textMuted, ':focus': { ringColor: derivedColors.accent } }"
                        @click="closeCancelModal"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    <div v-if="selectedTrade" class="mb-6">
                        <div class="rounded-xl p-4 mb-4 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('asset') }}</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ selectedTrade.symbol }}</span>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('direction') }}</span>
                                    <span
                                        class="font-medium px-2 py-1 rounded text-xs"
                                        :style="{
                                            backgroundColor: selectedTrade.direction === 'up' ? derivedColors.success + '20' : derivedColors.danger + '20',
                                            color: selectedTrade.direction === 'up' ? derivedColors.success : derivedColors.danger
                                        }"
                                    >
                                        {{ selectedTrade.direction === 'up' ? t('callUp') : t('putDown') }}
                                    </span>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('amount') }}</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ currencySymbol }}{{ formatNumber(selectedTrade.amount) }}</span>
                                </div>
                                <div>
                                    <span class="block" :style="{ color: derivedColors.textMuted }">{{ t('duration') }}</span>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ formatDuration(selectedTrade.duration_seconds) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl p-4 mb-4 border" :style="{ backgroundColor: derivedColors.danger + '20', borderColor: derivedColors.danger + '50' }">
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" :style="{ color: derivedColors.danger }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <div>
                                    <h4 class="font-medium mb-1" :style="{ color: derivedColors.danger }">{{ t('confirmCancellation') }}</h4>
                                    <p class="text-sm" :style="{ color: derivedColors.danger }">{{ t('cancelTradeWarning') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl p-4 border" :style="{ backgroundColor: derivedColors.warning + '20', borderColor: derivedColors.warning + '50' }">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4" :style="{ color: derivedColors.warning }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.warning }">{{ t('cancellationTimeLimit') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-end space-y-3 sm:space-y-0 sm:space-x-3">
                        <button
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg border transition-all duration-200"
                            :style="{
                                backgroundColor: derivedColors.surface,
                                borderColor: derivedColors.border,
                                color: derivedColors.textSecondary
                            }"
                            @click="closeCancelModal"
                        >
                            {{ t('keepTrade') }}
                        </button>
                        <button
                            :disabled="isCancelling"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 disabled:opacity-50"
                            :style="{
                                backgroundColor: derivedColors.danger,
                                color: derivedColors.textPrimary
                            }"
                            @click="confirmCancel"
                        >
                            <svg v-if="isCancelling" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span>{{ isCancelling ? t('cancelling') : t('yesCancelTrade') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
