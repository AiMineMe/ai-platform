<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';
import Chart from 'chart.js/auto';

const props = defineProps({
    portfolioStats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    tradingStats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    quickStats: {
        type: Object,
        required: true,
        validator: (value) => value && typeof value === 'object'
    },
    recentActivities: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    portfolioChartData: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    tradingActivityData: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    marketOverview: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    currentPeriod: {
        type: String,
        default: '7d'
    }
});

const chartPeriod = ref(props.currentPeriod === '7d' ? '7D' : props.currentPeriod === '1m' ? '1M' : '3M');
const refreshing = ref(false);
const portfolioChart = ref(null);
const tradingChart = ref(null);
let portfolioChartInstance = null;
let tradingChartInstance = null;

const { currencySymbol } = useSettings();
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

const quickActionStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '50'
}));

const buttonActiveStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary})`,
    color: derivedColors.value.textPrimary
}));

const buttonInactiveStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface,
    color: derivedColors.value.textMuted
}));

const hasPortfolioData = computed(() => {
    return props.portfolioChartData && props.portfolioChartData.length > 0;
});

const hasTradingData = computed(() => {
    return props.tradingActivityData && props.tradingActivityData.length > 0;
});

watch([() => props.portfolioChartData, () => props.tradingActivityData], () => {
    nextTick(() => {
        initCharts();
    });
}, { deep: true });

const initCharts = async () => {
    await nextTick();
    if (portfolioChart.value) {
        const ctx = portfolioChart.value.getContext('2d');
        if (ctx) {
            if (portfolioChartInstance) {
                portfolioChartInstance.destroy();
            }

            const chartData = hasPortfolioData.value ? props.portfolioChartData : [];

            portfolioChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(item => item.label),
                    datasets: [{
                        label: t('portfolioValue'),
                        data: chartData.map(item => item.value),
                        backgroundColor: derivedColors.value.accent + '20',
                        borderColor: derivedColors.value.accent,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: derivedColors.value.accent,
                        pointBorderColor: derivedColors.value.textPrimary,
                        pointBorderWidth: 2,
                        pointRadius: chartPeriod.value === '7D' ? 4 : 2,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: derivedColors.value.surface + 'f0',
                            titleColor: derivedColors.value.textPrimary,
                            bodyColor: derivedColors.value.textSecondary,
                            borderColor: derivedColors.value.accent,
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const pnlData = chartData[context.dataIndex];
                                    const pnl = pnlData?.pnl || 0;
                                    return [
                                        `${t('value')}: ${currencySymbol.value}${context.parsed.y}`,
                                        `P&L: ${pnl >= 0 ? '+' : ''}${currencySymbol.value}${pnl}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: {
                                color: derivedColors.value.border + '20',
                                drawBorder: false
                            },
                            ticks: {
                                color: derivedColors.value.textMuted,
                                maxTicksLimit: 6,
                                callback: function(value) {
                                    return currencySymbol.value + formatNumber(value);
                                }
                            },
                            border: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: derivedColors.value.textMuted,
                                maxTicksLimit: chartPeriod.value === '7D' ? 7 : 8,
                                maxRotation: 0,
                                minRotation: 0
                            },
                            border: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }

    if (tradingChart.value) {
        const ctx = tradingChart.value.getContext('2d');
        if (ctx) {
            if (tradingChartInstance) {
                tradingChartInstance.destroy();
            }

            const chartData = hasTradingData.value ? props.tradingActivityData : [];

            tradingChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.map(item => item.label),
                    datasets: [{
                        label: t('trades'),
                        data: chartData.map(item => item.trades),
                        backgroundColor: derivedColors.value.accent + '80',
                        borderColor: derivedColors.value.accent,
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: derivedColors.value.surface + 'f0',
                            titleColor: derivedColors.value.textPrimary,
                            bodyColor: derivedColors.value.textSecondary,
                            borderColor: derivedColors.value.accent,
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const item = chartData[context.dataIndex];
                                    return [
                                        `${context.parsed.y} ${t('trade')}${context.parsed.y !== 1 ? 's' : ''}`,
                                        `${t('winRate')}: ${item.winRate}%`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: derivedColors.value.border + '20',
                                drawBorder: false
                            },
                            ticks: {
                                color: derivedColors.value.textMuted,
                                stepSize: 1
                            },
                            border: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: derivedColors.value.textMuted
                            },
                            border: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }
};

const setChartPeriod = (period) => {
    if (refreshing.value) return;

    chartPeriod.value = period;
    refreshing.value = true;

    const periodMap = {
        '7D': '7d',
        '1M': '1m',
        '3M': '3m'
    };

    const backendPeriod = periodMap[period] || '7d';

    router.visit(window.location.pathname, {
        method: 'get',
        data: { period: backendPeriod },
        only: ['portfolioChartData', 'tradingActivityData', 'currentPeriod'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast(`${t('chartUpdated')} ${period} ${t('view')}`, 'success');
            nextTick(() => {
                initCharts();
            });
        },
        onError: () => {
            showToast(t('failedToUpdateChart'), 'error');
        },
        onFinish: () => {
            refreshing.value = false;
        }
    });
};

const refreshTradingData = () => {
    if (refreshing.value) return;

    refreshing.value = true;

    router.reload({
        only: ['tradingActivityData', 'tradingStats'],
        onFinish: () => {
            refreshing.value = false;
            showToast(t('tradingDataRefreshed'), 'success');
            initCharts();
        },
        onError: () => {
            refreshing.value = false;
            showToast(t('failedToRefreshTradingData'), 'error');
        }
    });
};

const refreshMarketData = () => {
    if (refreshing.value) return;

    refreshing.value = true;

    router.reload({
        only: ['marketOverview'],
        onFinish: () => {
            refreshing.value = false;
            showToast(t('marketDataRefreshed'), 'success');
        },
        onError: () => {
            refreshing.value = false;
            showToast(t('failedToRefreshMarketData'), 'error');
        }
    });
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';

    const num = parseFloat(value) || 0;

    if (num >= 1000000000) {
        return (num / 1000000000).toFixed(2) + 'B';
    } else if (num >= 1000000) {
        return (num / 1000000).toFixed(2) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(2) + 'K';
    } else if (Number.isInteger(num)) {
        return num.toString();
    } else {
        return num.toFixed(2);
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';

    try {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    } catch (error) {
        console.error('Error formatting date:', error);
        return 'Invalid Date';
    }
};

const formatStatus = (status) => {
    const statusMap = {
        'completed': t('completed'),
        'pending': t('pending'),
        'failed': t('failed'),
        'cancelled': t('cancelled'),
        'processing': t('processing')
    };
    return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
};

const getStatusClass = (status) => {
    const classes = {
        'completed': 'text-green-400',
        'pending': 'text-yellow-400',
        'failed': 'text-red-400',
        'cancelled': derivedColors.value.textMuted,
        'processing': 'text-blue-400'
    };
    return classes[status] || derivedColors.value.textMuted;
};

onMounted(() => {
    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
    initCharts();
});

onUnmounted(() => {
    if (portfolioChartInstance) {
        portfolioChartInstance.destroy();
    }
    if (tradingChartInstance) {
        tradingChartInstance.destroy();
    }
});
</script>

<template>
    <UserLayout
        :page-title="t('overview')"
        :page-section="t('dashboard')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                role="region"
                :aria-label="t('portfolioOverview')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200 group" :style="cardStyle">
                    <div class="flex items-center justify-between mb-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('portfolioValue') }}
                            </p>
                            <p
                                class="text-lg sm:text-2xl font-bold truncate"
                                :style="{ color: derivedColors.textPrimary }"
                                :aria-label="t('totalPortfolioValue')"
                            >
                                {{ currencySymbol }}{{ formatNumber(portfolioStats.totalValue) }}
                            </p>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 sm:h-6 sm:w-6"
                                :style="{ color: derivedColors.accent }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-center text-sm">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            :class="`h-4 w-4 mr-1 flex-shrink-0 ${portfolioStats.portfolioChange >= 0 ? 'text-green-400' : 'text-red-400'}`"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                v-if="portfolioStats.portfolioChange >= 0"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 10l7-7m0 0l7 7m-7-7v18"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 14l-7 7m0 0l-7-7m7 7V3"
                            />
                        </svg>
                        <span :class="`font-medium ${portfolioStats.portfolioChange >= 0 ? 'text-green-400' : 'text-red-400'} truncate`">
                          {{ portfolioStats.portfolioChange >= 0 ? '+' : '' }}{{ portfolioStats.portfolioChange }}%
                        </span>
                        <span class="ml-1 truncate" :style="{ color: derivedColors.textMuted }">{{ t('24hChange') }}</span>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200 group" :style="cardStyle">
                    <div class="flex items-center justify-between mb-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('availableBalance') }}
                            </p>
                            <p
                                class="text-lg sm:text-2xl font-bold truncate"
                                :style="{ color: derivedColors.textPrimary }"
                                :aria-label="t('availableTradingBalance')"
                            >
                                {{ currencySymbol }}{{ formatNumber(portfolioStats.availableBalance) }}
                            </p>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" :style="{ backgroundColor: derivedColors.success + '20' }">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 sm:h-6 sm:w-6 text-green-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="font-medium truncate" :style="{ color: derivedColors.accent }">{{ t('readyToTrade') }}</span>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200 group" :style="cardStyle">
                    <div class="flex items-center justify-between mb-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('todaysPnL') }}
                            </p>
                            <p
                                :class="`text-lg sm:text-2xl font-bold truncate ${portfolioStats.todayPnL >= 0 ? 'text-green-400' : 'text-red-400'}`"
                                :aria-label="t('todaysProfitAndLoss')"
                            >
                                {{ portfolioStats.todayPnL >= 0 ? '+' : '' }}{{ currencySymbol }}{{ formatNumber(Math.abs(portfolioStats.todayPnL)) }}
                            </p>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" :style="{ backgroundColor: derivedColors.secondary + '20' }">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 sm:h-6 sm:w-6"
                                :style="{ color: derivedColors.secondary }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-center text-sm">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            :class="`h-4 w-4 mr-1 flex-shrink-0 ${portfolioStats.todayPnLPercent >= 0 ? 'text-green-400' : 'text-red-400'}`"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                v-if="portfolioStats.todayPnLPercent >= 0"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 10l7-7m0 0l7 7m-7-7v18"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 14l-7 7m0 0l-7-7m7 7V3"
                            />
                        </svg>
                        <span :class="`font-medium ${portfolioStats.todayPnLPercent >= 0 ? 'text-green-400' : 'text-red-400'} truncate`">
                          {{ portfolioStats.todayPnLPercent >= 0 ? '+' : '' }}{{ portfolioStats.todayPnLPercent }}%
                        </span>
                        <span class="ml-1 truncate" :style="{ color: derivedColors.textMuted }">{{ t('sinceOpen') }}</span>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200 group" :style="cardStyle">
                    <div class="flex items-center justify-between mb-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('activeTrades') }}
                            </p>
                            <p
                                class="text-lg sm:text-2xl font-bold truncate"
                                :style="{ color: derivedColors.textPrimary }"
                                :aria-label="t('numberOfActiveTrades')"
                            >
                                {{ portfolioStats.activeTrades }}
                            </p>
                        </div>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" :style="{ backgroundColor: derivedColors.warning + '20' }">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 sm:h-6 sm:w-6"
                                :style="{ color: derivedColors.warning }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                />
                            </svg>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="truncate" :style="{ color: derivedColors.textMuted }">{{ t('openPositions') }}</span>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <h3 class="text-lg font-semibold mb-6" :style="{ color: derivedColors.textPrimary }">
                    {{ t('quickActions') }}
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                    <Link
                        href="/user/wallet/deposit"
                        class="border rounded-xl p-4 sm:p-6 transition-all duration-200 group focus:outline-none focus:ring-2 focus:ring-opacity-50 hover:scale-105"
                        :style="{ ...quickActionStyle, ':hover': { borderColor: derivedColors.accent } }"
                        role="button"
                        :aria-label="t('goToDepositsPage')"
                    >
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center mb-3 transition-colors" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 sm:h-6 sm:w-6"
                                    :style="{ color: derivedColors.accent }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                    />
                                </svg>
                            </div>
                            <span class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('deposit') }}</span>
                        </div>
                    </Link>

                    <Link
                        href="/user/wallet/withdraw"
                        class="border rounded-xl p-4 sm:p-6 transition-all duration-200 group focus:outline-none focus:ring-2 focus:ring-opacity-50 hover:scale-105"
                        :style="{ ...quickActionStyle, ':hover': { borderColor: derivedColors.success } }"
                        role="button"
                        :aria-label="t('goToWithdrawalsPage')"
                    >
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center mb-3 transition-colors" :style="{ backgroundColor: derivedColors.success + '20' }">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 sm:h-6 sm:w-6 text-green-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                                    />
                                </svg>
                            </div>
                            <span class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('withdraw') }}</span>
                        </div>
                    </Link>

                    <Link
                        href="/user/trading/live"
                        class="border rounded-xl p-4 sm:p-6 transition-all duration-200 group focus:outline-none focus:ring-2 focus:ring-opacity-50 hover:scale-105"
                        :style="{ ...quickActionStyle, ':hover': { borderColor: derivedColors.secondary } }"
                        role="button"
                        :aria-label="t('goToTradingPage')"
                    >
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center mb-3 transition-colors" :style="{ backgroundColor: derivedColors.secondary + '20' }">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 sm:h-6 sm:w-6"
                                    :style="{ color: derivedColors.secondary }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                    />
                                </svg>
                            </div>
                            <span class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('trade') }}</span>
                        </div>
                    </Link>

                    <Link
                        href="/user/mining"
                        class="border rounded-xl p-4 sm:p-6 transition-all duration-200 group focus:outline-none focus:ring-2 focus:ring-opacity-50 hover:scale-105"
                        :style="{ ...quickActionStyle, ':hover': { borderColor: derivedColors.warning } }"
                        role="button"
                        :aria-label="t('goToMiningPage')"
                    >
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center mb-3 transition-colors" :style="{ backgroundColor: derivedColors.warning + '20' }">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 sm:h-6 sm:w-6"
                                    :style="{ color: derivedColors.warning }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <span class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('mining') }}</span>
                        </div>
                    </Link>
                </div>
            </div>

            <div
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                role="region"
                :aria-label="t('quickStatistics')"
            >
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-medium truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('winRate') }}
                            </h4>
                            <p class="text-lg sm:text-xl font-bold mt-1 truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ tradingStats.winRate }}%
                            </p>
                        </div>
                        <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-medium truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('totalTrades') }}
                            </h4>
                            <p class="text-lg sm:text-xl font-bold mt-1 truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ tradingStats.totalTrades }}
                            </p>
                        </div>
                        <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: derivedColors.secondary }"></div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-medium truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('monthlyProfit') }}
                            </h4>
                            <p :class="`text-lg sm:text-xl font-bold mt-1 truncate ${quickStats.monthlyProfit >= 0 ? 'text-green-400' : 'text-red-400'}`">
                                {{ quickStats.monthlyProfit >= 0 ? '+' : '' }}{{ currencySymbol }}{{ formatNumber(Math.abs(quickStats.monthlyProfit)) }}
                            </p>
                        </div>
                        <div class="w-3 h-3 rounded-full bg-purple-500 flex-shrink-0"></div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-medium truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('miningBalance') }}
                            </h4>
                            <p class="text-lg sm:text-xl font-bold mt-1 truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ currencySymbol }}{{ formatNumber(quickStats.miningBalance || 0) }}
                            </p>
                        </div>
                        <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: derivedColors.warning }"></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6 shadow-lg" :style="cardStyle">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('marketOverview') }}
                        </h3>
                        <button
                            :disabled="refreshing"
                            class="transition-colors duration-200 p-2 rounded focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50"
                            :style="{ color: derivedColors.accent }"
                            :aria-label="t('refreshMarketData')"
                            @click="refreshMarketData"
                        >
                            <svg
                                :class="refreshing ? 'animate-spin' : ''"
                                class="w-4 h-4"
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
                    <div
                        v-if="marketOverview.length > 0"
                        class="space-y-4 max-h-80 overflow-y-auto"
                    >
                        <div
                            v-for="market in marketOverview"
                            :key="market.symbol"
                            class="flex items-center justify-between p-3 sm:p-4 border rounded-lg hover:scale-105 transition-all duration-200"
                            :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border + '10' }"
                        >
                            <div class="flex items-center min-w-0 flex-1">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-xs font-bold mr-3 overflow-hidden flex-shrink-0" :style="{ backgroundColor: derivedColors.accent, color: derivedColors.background }">
                                    <img
                                        v-if="market.image_url"
                                        :src="market.image_url"
                                        :alt="market.name + ' logo'"
                                        class="w-full h-full object-cover rounded-full"
                                        @error="$event.target.style.display = 'none'; $event.target.nextElementSibling.style.display = 'block'"
                                    />
                                    <span
                                        v-else
                                        :style="{ display: market.image_url ? 'none' : 'block' }"
                                    >
                                        {{ market.symbol.substring(0, 3) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                        {{ market.name }}
                                    </p>
                                    <p class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                                        ${{ formatNumber(market.price) }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p :class="`font-medium text-sm sm:text-base ${market.change >= 0 ? 'text-green-400' : 'text-red-400'}`">
                                    {{ market.change >= 0 ? '+' : '' }}{{ market.change }}%
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-center py-12"
                    >
                        <div class="flex flex-col items-center">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-12 w-12 mb-4"
                                :style="{ color: derivedColors.textMuted }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                />
                            </svg>
                            <p class="text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                {{ t('noMarketDataAvailable') }}
                            </p>
                            <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                {{ t('marketDataWillAppearHere') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6 shadow-lg" :style="cardStyle">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('recentActivity') }}
                        </h3>
                        <Link
                            href="/user/wallet/transactions"
                            class="text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50 rounded px-2 py-1"
                            :style="{ color: derivedColors.accent }"
                            :aria-label="t('viewAllActivities')"
                        >
                            {{ t('viewAll') }}
                        </Link>
                    </div>
                    <div
                        v-if="recentActivities.length > 0"
                        class="space-y-4 max-h-80 overflow-y-auto"
                    >
                        <div
                            v-for="activity in recentActivities"
                            :key="activity.id"
                            class="flex items-center justify-between p-3 sm:p-4 border rounded-lg hover:scale-105 transition-all duration-200"
                            :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border + '10' }"
                        >
                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20', color: derivedColors.accent }">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4 sm:h-5 sm:w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            v-if="activity.type === 'deposit'"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                        />
                                        <path
                                            v-else-if="activity.type === 'withdrawal'"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M20 12H4"
                                        />
                                        <path
                                            v-else-if="activity.type === 'trade'"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                                        />
                                        <path
                                            v-else
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                        />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                        {{ activity.description }}
                                    </p>
                                    <p class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(activity.timestamp) }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p
                                    v-if="activity.amount !== 0"
                                    :class="`text-sm font-medium ${activity.amount > 0 ? 'text-green-400' : 'text-red-400'}`"
                                >
                                    {{ activity.amount > 0 ? '+' : '' }}{{ currencySymbol }}{{ formatNumber(Math.abs(activity.amount)) }}
                                </p>
                                <p
                                    class="text-xs"
                                    :class="getStatusClass(activity.status)"
                                >
                                    {{ formatStatus(activity.status) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-center py-12"
                    >
                        <div class="flex flex-col items-center">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-12 w-12 mb-4"
                                :style="{ color: derivedColors.textMuted }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                            <p class="text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                {{ t('noRecentActivities') }}
                            </p>
                            <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                {{ t('recentActivitiesWillAppearHere') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6 shadow-lg" :style="cardStyle">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 space-y-4 sm:space-y-0">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('tradingPortfolioPerformance') }}
                        </h3>
                        <div
                            class="flex space-x-2"
                            role="group"
                            :aria-label="t('chartTimePeriodSelection')"
                        >
                            <button
                                :disabled="refreshing"
                                class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50"
                                :style="chartPeriod === '7D' ? buttonActiveStyle : buttonInactiveStyle"
                                :aria-pressed="chartPeriod === '7D'"
                                @click="setChartPeriod('7D')"
                            >
                                {{ refreshing && chartPeriod === '7D' ? t('loading') : '7D' }}
                            </button>
                            <button
                                :disabled="refreshing"
                                class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50"
                                :style="chartPeriod === '1M' ? buttonActiveStyle : buttonInactiveStyle"
                                :aria-pressed="chartPeriod === '1M'"
                                @click="setChartPeriod('1M')"
                            >
                                {{ refreshing && chartPeriod === '1M' ? t('loading') : '1M' }}
                            </button>
                            <button
                                :disabled="refreshing"
                                class="px-3 py-2 sm:px-4 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50"
                                :style="chartPeriod === '3M' ? buttonActiveStyle : buttonInactiveStyle"
                                :aria-pressed="chartPeriod === '3M'"
                                @click="setChartPeriod('3M')"
                            >
                                {{ refreshing && chartPeriod === '3M' ? t('loading') : '3M' }}
                            </button>
                        </div>
                    </div>
                    <div class="relative">
                        <canvas
                            ref="portfolioChart"
                            class="w-full h-48 sm:h-64"
                            :aria-label="t('portfolioPerformanceChart')"
                        />
                        <div
                            v-if="!hasPortfolioData"
                            class="absolute inset-0 flex items-center justify-center rounded-lg"
                            :style="{ backgroundColor: derivedColors.surface + '80' }"
                        >
                            <div class="text-center" :style="{ color: derivedColors.textMuted }">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-12 w-12 mx-auto mb-2"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                </svg>
                                <p>{{ t('noPortfolioDataAvailable') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6 shadow-lg" :style="cardStyle">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('tradingActivity') }}
                        </h3>
                        <button
                            :disabled="refreshing"
                            :aria-label="refreshing ? t('refreshingTradingData') : t('refreshTradingData')"
                            class="transition-colors duration-200 p-2 rounded focus:outline-none focus:ring-2 focus:ring-opacity-50 disabled:opacity-50"
                            :style="{ color: derivedColors.accent }"
                            @click="refreshTradingData"
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
                    <div class="relative">
                        <canvas
                            ref="tradingChart"
                            class="w-full h-48 sm:h-64"
                            :aria-label="t('tradingActivityChart')"
                        />
                        <div
                            v-if="!hasTradingData"
                            class="absolute inset-0 flex items-center justify-center rounded-lg"
                            :style="{ backgroundColor: derivedColors.surface + '80' }"
                        >
                            <div class="text-center" :style="{ color: derivedColors.textMuted }">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-12 w-12 mx-auto mb-2"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                <p>{{ t('noTradingDataAvailable') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
