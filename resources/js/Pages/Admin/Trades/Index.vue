<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";
import {useSettings} from "@/composables/useSettings.js";

const props = defineProps({
    trades: {
        type: Array,
        required: true,
        default: () => []
    },
    meta: {
        type: Object,
        default: () => ({
            total: 0,
            current_page: 1,
            per_page: 20,
            last_page: 1
        })
    },
    stats: {
        type: Object,
        default: () => ({
            totalTrades: 0,
            activeTrades: 0,
            wonTrades: 0,
            lostTrades: 0,
            totalProfitLoss: 0
        })
    },
    symbols: {
        type: Array,
        default: () => []
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'created_at',
            sort_direction: 'desc'
        })
    },
    tradeResultSetting: {
        type: String,
        default: ''
    }
});

const { showToast } = useToast();
const isLoading = ref(false);
const error = ref(null);
const selectedTrades = ref([]);
const showSettleModal = ref(false);
const showCancelModal = ref(false);
const showBulkSettleModal = ref(false);
const showBulkCancelModal = ref(false);
const selectedTrade = ref(null);
const closePrice = ref('');
const bulkClosePrice = ref('');
const showDetailsModal = ref(false);
const selectedTradeForDetails = ref(null);

const searchTerm = ref(props.filters?.search || '');
const selectedSymbol = ref(props.filters?.symbol || '');
const selectedDirection = ref(props.filters?.direction || '');
const selectedStatus = ref(props.filters?.status || '');

const symbolOptions = computed(() => {
    return props.symbols.map(symbol => ({
        value: symbol,
        label: symbol
    }));
});

const directionOptions = [
    { value: 'up', label: 'UP' },
    { value: 'down', label: 'DOWN' }
];

const statusFilterOptions = [
    { value: 'active', label: 'Active' },
    { value: 'won', label: 'Won' },
    { value: 'lost', label: 'Lost' },
    { value: 'expired', label: 'Expired' },
    { value: 'cancelled', label: 'Cancelled' }
];

const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedSymbol.value || selectedDirection.value || selectedStatus.value);
});

const isAllSelected = computed(() => {
    const actionableTrades = props.trades.filter(trade => canTradeBeActioned(trade.status));
    return actionableTrades.length > 0 && selectedTrades.value.length === actionableTrades.length;
});

const activeTradesCount = computed(() => props.stats?.activeTrades || 0);
const wonTradesCount = computed(() => props.stats?.wonTrades || 0);
const lostTradesCount = computed(() => props.stats?.lostTrades || 0);
const totalProfitLoss = computed(() => props.stats?.totalProfitLoss || 0);

const showTradeDetails = (trade) => {
    selectedTradeForDetails.value = trade;
    showDetailsModal.value = true;
};

const closeDetailsModal = () => {
    showDetailsModal.value = false;
    selectedTradeForDetails.value = null;
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return 'Invalid Date';
    }
};

const getTradeOutcome = (trade) => {
    if (trade.status === 'won') return 'Won';
    if (trade.status === 'lost') return 'Lost';
    if (trade.status === 'draw') return 'Draw';
    if (trade.status === 'cancelled') return 'Cancelled';
    if (trade.status === 'expired') return 'Expired';
    return 'Active';
};

const canTradeBeActioned = (status) => {
    return ['active', 'expired'].includes(status);
};

const getActionableTrades = () => {
    return selectedTrades.value.filter(tradeId => {
        const trade = props.trades.find(t => t.id === tradeId);
        return trade && canTradeBeActioned(trade.status);
    });
};

const openSettleModal = (trade) => {
    selectedTrade.value = trade;
    closePrice.value = '';
    showSettleModal.value = true;
};

const closeSettleModal = () => {
    showSettleModal.value = false;
    selectedTrade.value = null;
    closePrice.value = '';
};

const openCancelModal = (trade) => {
    selectedTrade.value = trade;
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    selectedTrade.value = null;
};

const closeBulkSettleModal = () => {
    showBulkSettleModal.value = false;
    bulkClosePrice.value = '';
};

const closeBulkCancelModal = () => {
    showBulkCancelModal.value = false;
};

const confirmSettle = () => {
    if (!closePrice.value || !selectedTrade.value) {
        showToast('Please enter a valid close price', 'error');
        return;
    }

    isLoading.value = true;

    router.post(`/admin/trades/${selectedTrade.value.id}/settle`, {
        close_price: parseFloat(closePrice.value)
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast('Trade settled successfully', 'success');
            closeSettleModal();
        },
        onError: (errors) => {
            console.error('Settle error:', errors);
            showToast('Failed to settle trade', 'error');
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const confirmCancel = () => {
    if (!selectedTrade.value) return;

    isLoading.value = true;

    router.post(`/admin/trades/${selectedTrade.value.id}/cancel`, {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast('Trade cancelled successfully', 'success');
            closeCancelModal();
        },
        onError: (errors) => {
            console.error('Cancel error:', errors);
            showToast('Failed to cancel trade', 'error');
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const confirmBulkSettle = () => {
    if (!bulkClosePrice.value) {
        showToast('Please enter a valid close price', 'error');
        return;
    }

    const actionableTrades = getActionableTrades();
    if (actionableTrades.length === 0) {
        showToast('No actionable trades selected', 'error');
        return;
    }

    isLoading.value = true;

    router.post('/admin/trades/bulk-action', {
        action: 'settle',
        trade_ids: actionableTrades,
        close_price: parseFloat(bulkClosePrice.value)
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast(`${actionableTrades.length} trades settled successfully`, 'success');
            selectedTrades.value = [];
            closeBulkSettleModal();
        },
        onError: (errors) => {
            console.error('Bulk settle error:', errors);
            showToast('Failed to settle trades', 'error');
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const confirmBulkCancel = () => {
    const actionableTrades = getActionableTrades();
    if (actionableTrades.length === 0) {
        showToast('No actionable trades selected', 'error');
        return;
    }

    isLoading.value = true;

    router.post('/admin/trades/bulk-action', {
        action: 'cancel',
        trade_ids: actionableTrades
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast(`${actionableTrades.length} trades cancelled successfully`, 'success');
            selectedTrades.value = [];
            closeBulkCancelModal();
        },
        onError: (errors) => {
            console.error('Bulk cancel error:', errors);
            showToast('Failed to cancel trades', 'error');
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const applyFilters = () => {
    if (isLoading.value) return;

    isLoading.value = true;
    error.value = null;
    selectedTrades.value = [];

    const params = {
        page: 1,
        sort_field: sortField.value,
        sort_direction: sortDirection.value
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedSymbol.value) params.symbol = selectedSymbol.value;
    if (selectedDirection.value) params.direction = selectedDirection.value;
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/admin/trades', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Filter error:', errors);
            error.value = 'Failed to apply filters. Please try again.';
            showToast('Failed to apply filters', 'error');
        }
    });
};

const clearSearch = () => {
    searchTerm.value = '';
    applyFilters();
};

const clearSymbolFilter = () => {
    selectedSymbol.value = '';
    applyFilters();
};

const clearDirectionFilter = () => {
    selectedDirection.value = '';
    applyFilters();
};

const clearStatusFilter = () => {
    selectedStatus.value = '';
    applyFilters();
};

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedSymbol.value = '';
    selectedDirection.value = '';
    selectedStatus.value = '';
    selectedTrades.value = [];

    isLoading.value = true;
    error.value = null;

    router.get('/admin/trades', {
        sort_field: 'created_at',
        sort_direction: 'desc'
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
            showToast('Filters cleared successfully', 'success');
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Clear filters error:', errors);
            error.value = 'Failed to clear filters. Please try again.';
            showToast('Failed to clear filters', 'error');
        }
    });
};

const getSymbolLabel = (symbol) => {
    const option = symbolOptions.value.find(s => s.value === symbol);
    return option ? option.label : symbol;
};

const getDirectionLabel = (direction) => {
    const option = directionOptions.find(d => d.value === direction);
    return option ? option.label : direction;
};

const getStatusLabel = (status) => {
    const option = statusFilterOptions.find(s => s.value === status);
    return option ? option.label : status;
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
    }).format(num);
};

const formatMoney = (value) => {
    const num = parseFloat(value) || 0;
    const absValue = Math.abs(num);
    if (absValue >= 1000000000) {
        return (num / 1000000000).toFixed(2) + 'B';
    } else if (absValue >= 1000000) {
        return (num / 1000000).toFixed(2) + 'M';
    } else if (absValue >= 1000) {
        return (num / 1000).toFixed(2) + 'K';
    } else {
        return num.toFixed(2);
    }
};

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2);
};

const getDirectionClass = (direction) => {
    return direction === 'up'
        ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        : 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700';
};

const getDirectionDotClass = (direction) => {
    return direction === 'up' ? 'bg-green-500' : 'bg-red-500';
};

const getStatusClass = (status) => {
    const classes = {
        active: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
        won: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        lost: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700',
        expired: 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700',
        cancelled: 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700'
    };
    return classes[status?.toLowerCase()] || classes.cancelled;
};

const getStatusDotClass = (status) => {
    const classes = {
        active: 'bg-blue-500',
        won: 'bg-green-500',
        lost: 'bg-red-500',
        expired: 'bg-orange-500',
        cancelled: 'bg-gray-500'
    };
    return classes[status?.toLowerCase()] || 'bg-gray-500';
};

const getProfitLossClass = (profitLoss) => {
    const amount = parseFloat(profitLoss) || 0;
    if (amount > 0) return 'text-green-600 dark:text-green-400';
    if (amount < 0) return 'text-red-600 dark:text-red-400';
    return 'text-gray-500 dark:text-gray-400';
};

const toggleSelectAll = () => {
    const actionableTrades = props.trades.filter(trade => canTradeBeActioned(trade.status));

    if (isAllSelected.value) {
        selectedTrades.value = [];
    } else {
        selectedTrades.value = actionableTrades.map(trade => trade.id);
    }
};

const clearSelection = () => {
    selectedTrades.value = [];
};

const handleSort = (field) => {
    if (isLoading.value) return;

    const currentSortField = props.filters.sort_field;
    const currentSortDirection = props.filters.sort_direction;

    let newDirection = 'asc';
    if (currentSortField === field && currentSortDirection === 'asc') {
        newDirection = 'desc';
    }

    isLoading.value = true;
    error.value = null;
    selectedTrades.value = [];

    const params = {
        sort_field: field,
        sort_direction: newDirection,
        page: 1
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedSymbol.value) params.symbol = selectedSymbol.value;
    if (selectedDirection.value) params.direction = selectedDirection.value;
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/admin/trades', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort trades. Please try again.';
            showToast('Failed to sort trades', 'error');
        }
    });
};

const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return;

    isLoading.value = true;
    error.value = null;
    selectedTrades.value = [];

    const params = { page };
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedSymbol.value) params.symbol = selectedSymbol.value;
    if (selectedDirection.value) params.direction = selectedDirection.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/trades', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Pagination error:', errors);
            error.value = 'Failed to load page. Please try again.';
            showToast('Failed to load page', 'error');
        }
    });
};

const showTradeResultModal = ref(false);
const selectedResult = ref('');

const openTradeResultModal = () => {
    selectedResult.value = props.tradeResultSetting;
    showTradeResultModal.value = true;
};

const closeTradeResultModal = () => {
    showTradeResultModal.value = false;
    selectedResult.value = '';
};

const updateTradeResultSetting = () => {
    if (!selectedResult.value) {
        showToast('Please select a result', 'error');
        return;
    }

    isLoading.value = true;

    router.post('/admin/trades/set-result-setting', {
        result: selectedResult.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast('Trade result setting updated successfully', 'success');
            closeTradeResultModal();
        },
        onError: (errors) => {
            showToast('Failed to update setting', 'error');
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

onMounted(() => {
    const page = usePage();
    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) {
        error.value = flash.error;
        showToast(flash.error, 'error');
    }
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
});
const { currencySymbol } = useSettings();
</script>

<template>
  <AdminLayout
    title="Trades Management"
    page-section="Trading & Markets"
  >
      <div class="mb-6">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                  <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                      Trades
                  </h1>
                  <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                      Manage and monitor all trading activities
                  </p>
              </div>

              <div class="mt-4 sm:mt-0">
                  <button
                      class="inline-flex items-center px-4 py-2 text-sm font-medium text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-700 rounded-lg hover:bg-purple-200 dark:hover:bg-purple-900/50 focus:ring-2 focus:ring-purple-500 transition-colors duration-200"
                      @click="openTradeResultModal(null)"
                  >
                      <svg
                          class="h-4 w-4 mr-2"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                      >
                          <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                          />
                      </svg>
                      Trade Result
                  </button>
              </div>
          </div>
      </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-orange-600 dark:text-orange-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 10V3L4 14h7v7l9-11h-7z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Active
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ activeTradesCount }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-green-600 dark:text-green-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Won
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ wonTradesCount }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-red-600 dark:text-red-400"
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
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Lost
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ lostTradesCount }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-purple-600 dark:text-purple-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total P&L
            </p>
            <p
              class="text-2xl font-bold"
              :class="totalProfitLoss >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
            >
              {{ currencySymbol }}{{ formatMoney(totalProfitLoss) }}
            </p>
          </div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 mb-6">
      <div class="p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div class="flex-1 max-w-md">
            <div class="relative">
              <svg
                class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400 dark:text-gray-500"
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
                id="search-trades"
                v-model="searchTerm"
                type="text"
                placeholder="Search trades by ID or user..."
                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                @input="debouncedSearch"
              >
              <button
                v-if="searchTerm"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="clearSearch"
              >
                <svg
                  class="h-4 w-4"
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
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-[140px]">
              <select
                v-model="selectedSymbol"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Symbols
                </option>
                <option
                  v-for="symbol in symbolOptions"
                  :key="symbol.value"
                  :value="symbol.value"
                >
                  {{ symbol.label }}
                </option>
              </select>
            </div>

            <div class="min-w-[140px]">
              <select
                v-model="selectedDirection"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Directions
                </option>
                <option
                  v-for="direction in directionOptions"
                  :key="direction.value"
                  :value="direction.value"
                >
                  {{ direction.label }}
                </option>
              </select>
            </div>

            <div class="min-w-[140px]">
              <select
                v-model="selectedStatus"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Status
                </option>
                <option
                  v-for="status in statusFilterOptions"
                  :key="status.value"
                  :value="status.value"
                >
                  {{ status.label }}
                </option>
              </select>
            </div>

            <button
              v-if="hasActiveFilters"
              class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
              @click="clearAllFilters"
            >
              Clear Filters
            </button>
          </div>
        </div>

        <div
          v-if="hasActiveFilters"
          class="mt-4 flex flex-wrap items-center gap-2"
        >
          <span class="text-sm text-gray-600 dark:text-gray-400">Active filters:</span>

          <span
            v-if="searchTerm"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
          >
            Search: "{{ searchTerm }}"
            <button
              class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
              @click="clearSearch"
            >
              <svg
                class="h-3 w-3"
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
          </span>

          <span
            v-if="selectedSymbol"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300"
          >
            Symbol: {{ getSymbolLabel(selectedSymbol) }}
            <button
              class="ml-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-200"
              @click="clearSymbolFilter"
            >
              <svg
                class="h-3 w-3"
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
          </span>

          <span
            v-if="selectedDirection"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
          >
            Direction: {{ getDirectionLabel(selectedDirection) }}
            <button
              class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200"
              @click="clearDirectionFilter"
            >
              <svg
                class="h-3 w-3"
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
          </span>

          <span
            v-if="selectedStatus"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
          >
            Status: {{ getStatusLabel(selectedStatus) }}
            <button
              class="ml-2 text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200"
              @click="clearStatusFilter"
            >
              <svg
                class="h-3 w-3"
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
          </span>
        </div>
      </div>
    </div>

    <div
      v-if="selectedTrades.length > 0"
      class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6"
    >
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm text-blue-700 dark:text-blue-300 font-medium">
          {{ selectedTrades.length }} {{ selectedTrades.length === 1 ? 'trade' : 'trades' }} selected
        </span>
        <div class="flex flex-wrap gap-2">
          <button
            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-lime-500 hover:bg-lime-600 rounded-lg focus:ring-2 focus:ring-green-500 transition-colors duration-200"
            @click="showBulkSettleModal = true"
          >
            <svg
              class="w-3 h-3 mr-1"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            Settle Selected
          </button>
          <button
            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600  rounded-lg focus:ring-2 focus:ring-yellow-500 transition-colors duration-200"
            @click="showBulkCancelModal = true"
          >
            <svg
              class="w-3 h-3 mr-1"
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
            Cancel Selected
          </button>
          <button
            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-gray-500 transition-colors duration-200"
            @click="clearSelection"
          >
            Clear Selection
          </button>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
          <thead class="bg-gray-50 dark:bg-slate-900">
            <tr>
              <th class="px-6 py-3">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  @change="toggleSelectAll"
                >
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('trade_id')"
              >
                <div class="flex items-center space-x-1">
                  <span>TRADE</span>
                  <svg
                    v-if="sortField === 'trade_id'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                USER
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('symbol')"
              >
                <div class="flex items-center space-x-1">
                  <span>SYMBOL</span>
                  <svg
                    v-if="sortField === 'symbol'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                DIRECTION
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('amount')"
              >
                <div class="flex items-center space-x-1">
                  <span>AMOUNT</span>
                  <svg
                    v-if="sortField === 'amount'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                DURATION
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('status')"
              >
                <div class="flex items-center space-x-1">
                  <span>STATUS</span>
                  <svg
                    v-if="sortField === 'status'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                ACTIONS
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-if="!isLoading && trades.length === 0">
              <td
                colspan="10"
                class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
              >
                <div class="text-center">
                  <svg
                    class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No trades found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No trades match your current filters. Try adjusting the search criteria.
                  </p>
                </div>
              </td>
            </tr>

            <tr v-if="isLoading">
              <td
                colspan="10"
                class="px-6 py-12 text-center"
              >
                <div class="flex items-center justify-center">
                  <svg
                    class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600"
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
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400">Loading trades...</span>
                </div>
              </td>
            </tr>

            <tr
              v-for="trade in trades"
              :key="trade.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <input
                  v-model="selectedTrades"
                  type="checkbox"
                  :value="trade.id"
                  :disabled="!canTradeBeActioned(trade.status)"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                >
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ trade.trade_id || trade.id }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatDateTime(trade.created_at) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="h-8 w-8 flex-shrink-0">
                    <div class="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-xs font-medium text-gray-700 dark:text-gray-300">
                      {{ getInitials(trade.user?.name || trade.user?.email) }}
                    </div>
                  </div>
                  <div class="ml-3">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ trade.user?.name || 'N/A' }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      {{ trade.user?.email || 'N/A' }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ trade.symbol || 'N/A' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  ${{ formatNumber(trade.open_price) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                  :class="getDirectionClass(trade.direction)"
                >
                  <span
                    class="w-2 h-2 rounded-full mr-1"
                    :class="getDirectionDotClass(trade.direction)"
                  />
                  {{ trade.direction ? trade.direction.toUpperCase() : 'N/A' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ currencySymbol }}{{ formatNumber(trade.amount) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ trade.payout_rate }}% payout
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ trade.duration_formatted || 'N/A' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                  :class="getStatusClass(trade.status)"
                >
                  <span
                    class="w-2 h-2 rounded-full mr-1"
                    :class="getStatusDotClass(trade.status)"
                  />
                  {{ trade.status ? trade.status.toUpperCase() : 'UNKNOWN' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <button
                    v-if="canTradeBeActioned(trade.status)"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50 focus:ring-2 focus:ring-green-500 transition-colors duration-200"
                    @click="openSettleModal(trade)"
                  >
                    <svg
                      class="h-3 w-3 mr-1"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                    Settle
                  </button>
                  <button
                    v-if="canTradeBeActioned(trade.status)"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-yellow-700 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg hover:bg-yellow-200 dark:hover:bg-yellow-900/50 focus:ring-2 focus:ring-yellow-500 transition-colors duration-200"
                    @click="openCancelModal(trade)"
                  >
                    <svg
                      class="h-3 w-3 mr-1"
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
                    Cancel
                  </button>
                    <button
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-slate-600 shadow-sm text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                        @click="showTradeDetails(trade)"
                    >
                        <svg
                            class="h-3 w-3 mr-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>
                        Details
                    </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AdminPagination
      v-if="meta.total > 0"
      :current-page="currentPage"
      :last-page="lastPage"
      :total="meta.total"
      :per-page="20"
      item-name="trades"
      @page-change="changePage"
    />

    <div
      v-if="showSettleModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="closeSettleModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800"
        @click.stop
      >
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              Settle Trade
            </h3>
            <button
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              @click="closeSettleModal"
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

          <div
            v-if="selectedTrade"
            class="mb-4"
          >
            <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-3 mb-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Trade ID: <span class="font-medium text-gray-900 dark:text-white">{{ selectedTrade.trade_id }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Symbol: <span class="font-medium text-gray-900 dark:text-white">{{ selectedTrade.symbol }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Direction: <span class="font-medium text-gray-900 dark:text-white">{{ selectedTrade.direction?.toUpperCase() }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Open Price: <span class="font-medium text-gray-900 dark:text-white">${{ formatNumber(selectedTrade.open_price) }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Amount: <span class="font-medium text-gray-900 dark:text-white">{{ currencySymbol }}{{ formatNumber(selectedTrade.amount) }}</span>
              </p>
            </div>

            <div class="mb-4">
              <label
                for="closePrice"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Close Price
              </label>
              <input
                id="closePrice"
                v-model="closePrice"
                type="number"
                step="0.00000001"
                min="0"
                placeholder="Enter close price"
                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
              >
            </div>
          </div>

          <div class="flex items-center justify-end space-x-3">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-gray-500 transition-colors duration-200"
              @click="closeSettleModal"
            >
              Cancel
            </button>
            <button
              :disabled="!closePrice || isLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-lime-500 hover:bg-lime-600 disabled:bg-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 transition-colors duration-200"
              @click="confirmSettle"
            >
              <span v-if="isLoading">Settling...</span>
              <span v-else>Settle Trade</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showCancelModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="closeCancelModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800"
        @click.stop
      >
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              Cancel Trade
            </h3>
            <button
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
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

          <div
            v-if="selectedTrade"
            class="mb-4"
          >
            <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-3 mb-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Trade ID: <span class="font-medium text-gray-900 dark:text-white">{{ selectedTrade.trade_id }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Symbol: <span class="font-medium text-gray-900 dark:text-white">{{ selectedTrade.symbol }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Direction: <span class="font-medium text-gray-900 dark:text-white">{{ selectedTrade.direction?.toUpperCase() }}</span>
              </p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Amount: <span class="font-medium text-gray-900 dark:text-white">{{ currencySymbol }}{{ formatNumber(selectedTrade.amount) }}</span>
              </p>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3">
              <p class="text-sm text-yellow-800 dark:text-yellow-300">
                Are you sure you want to cancel this trade? This action cannot be undone.
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end space-x-3">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-gray-500 transition-colors duration-200"
              @click="closeCancelModal"
            >
              No, Keep Trade
            </button>
            <button
              :disabled="isLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 disabled:bg-gray-400 rounded-lg focus:ring-2 focus:ring-red-500 transition-colors duration-200"
              @click="confirmCancel"
            >
              <span v-if="isLoading">Cancelling...</span>
              <span v-else>Yes, Cancel Trade</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showBulkSettleModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="closeBulkSettleModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800"
        @click.stop
      >
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              Bulk Settle Trades
            </h3>
            <button
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              @click="closeBulkSettleModal"
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

          <div class="mb-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3 mb-4">
              <p class="text-sm text-blue-800 dark:text-blue-300">
                You are about to settle {{ getActionableTrades().length }} trades with the same close price.
              </p>
            </div>

            <div class="mb-4">
              <label
                for="bulkClosePrice"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Close Price (for all trades)
              </label>
              <input
                id="bulkClosePrice"
                v-model="bulkClosePrice"
                type="number"
                step="0.00000001"
                min="0"
                placeholder="Enter close price"
                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
              >
            </div>
          </div>

          <div class="flex items-center justify-end space-x-3">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-gray-500 transition-colors duration-200"
              @click="closeBulkSettleModal"
            >
              Cancel
            </button>
            <button
              :disabled="!bulkClosePrice || isLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-lime-500 hover:bg-lime-600 disabled:bg-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 transition-colors duration-200"
              @click="confirmBulkSettle"
            >
              <span v-if="isLoading">Settling...</span>
              <span v-else>Settle All Trades</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showBulkCancelModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="closeBulkCancelModal"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800"
        @click.stop
      >
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              Bulk Cancel Trades
            </h3>
            <button
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              @click="closeBulkCancelModal"
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

          <div class="mb-4">
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3">
              <p class="text-sm text-yellow-800 dark:text-yellow-300">
                Are you sure you want to cancel {{ getActionableTrades().length }} selected trades? This action cannot be undone.
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end space-x-3">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-gray-500 transition-colors duration-200"
              @click="closeBulkCancelModal"
            >
              No, Keep Trades
            </button>
            <button
              :disabled="isLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 disabled:bg-gray-400 rounded-lg focus:ring-2 focus:ring-red-500 transition-colors duration-200"
              @click="confirmBulkCancel"
            >
              <span v-if="isLoading">Cancelling...</span>
              <span v-else>Yes, Cancel All Trades</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <div
          v-if="showDetailsModal"
          class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
          role="dialog"
          aria-modal="true"
      >
          <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800">
              <div class="mt-3">
                  <div class="flex items-center justify-between mb-4">
                      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                          Trade Details
                      </h3>
                      <button
                          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                          @click="closeDetailsModal"
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

                  <div
                      v-if="selectedTradeForDetails"
                      class="space-y-4"
                  >
                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Trade ID:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100">#{{ selectedTradeForDetails.trade_id }}</span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">User:</span>
                          <div class="text-right">
                              <div class="text-sm text-gray-900 dark:text-gray-100">
                                  {{ selectedTradeForDetails.user?.name || 'N/A' }}
                              </div>
                              <div class="text-xs text-gray-500 dark:text-gray-400">
                                  {{ selectedTradeForDetails.user?.email || 'N/A' }}
                              </div>
                          </div>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Symbol:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100">{{ selectedTradeForDetails.symbol }}</span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Direction:</span>
                          <span
                              class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                              :class="getDirectionClass(selectedTradeForDetails.direction)"
                          >
                        <span
                            class="w-2 h-2 rounded-full mr-1"
                            :class="getDirectionDotClass(selectedTradeForDetails.direction)"
                        />
                        {{ selectedTradeForDetails.direction ? selectedTradeForDetails.direction.toUpperCase() : 'N/A' }}
                    </span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Investment Amount:</span>
                          <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ currencySymbol }}{{ formatNumber(selectedTradeForDetails.amount) }}
                    </span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Payout Rate:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100">{{ selectedTradeForDetails.payout_rate }}%</span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100">{{ selectedTradeForDetails.duration_formatted || 'N/A' }}</span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Open Price:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100">${{ formatNumber(selectedTradeForDetails.open_price) }}</span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Close Price:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100">
                        {{ selectedTradeForDetails.close_price ? '$' + formatNumber(selectedTradeForDetails.close_price) : 'Not closed yet' }}
                    </span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</span>
                          <span
                              class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                              :class="getStatusClass(selectedTradeForDetails.status)"
                          >
                        <span
                            class="w-2 h-2 rounded-full mr-1"
                            :class="getStatusDotClass(selectedTradeForDetails.status)"
                        />
                        {{ getTradeOutcome(selectedTradeForDetails) }}
                    </span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Profit/Loss:</span>
                          <span
                              class="text-sm font-semibold"
                              :class="getProfitLossClass(selectedTradeForDetails.profit_loss)"
                          >
                        {{ currencySymbol }}{{ formatNumber(selectedTradeForDetails.profit_loss || 0) }}
                    </span>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Open Time:</span>
                          <div class="text-right">
                              <div class="text-sm text-gray-900 dark:text-gray-100">
                                  {{ formatDateTime(selectedTradeForDetails.open_time || selectedTradeForDetails.created_at) }}
                              </div>
                          </div>
                      </div>

                      <div class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Expiry Time:</span>
                          <div class="text-right">
                              <div class="text-sm text-gray-900 dark:text-gray-100">
                                  {{ formatDateTime(selectedTradeForDetails.expiry_time) }}
                              </div>
                          </div>
                      </div>

                      <div v-if="selectedTradeForDetails.close_time" class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Close Time:</span>
                          <div class="text-right">
                              <div class="text-sm text-gray-900 dark:text-gray-100">
                                  {{ formatDateTime(selectedTradeForDetails.close_time) }}
                              </div>
                          </div>
                      </div>

                      <div v-if="selectedTradeForDetails.notes" class="flex justify-between">
                          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes:</span>
                          <span class="text-sm text-gray-900 dark:text-gray-100 text-right max-w-xs">
                        {{ selectedTradeForDetails.notes }}
                    </span>
                      </div>

                      <!-- Additional Trade Information -->
                      <div class="border-t pt-4 mt-4" style="border-color: rgb(229 231 235 / 0.2)">
                          <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Additional Information</h4>

                          <div class="flex justify-between mb-2">
                              <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Potential Payout:</span>
                              <span class="text-sm text-gray-900 dark:text-gray-100">
                            {{ currencySymbol }}{{ formatNumber((selectedTradeForDetails.amount * selectedTradeForDetails.payout_rate) / 100) }}
                        </span>
                          </div>

                          <div v-if="selectedTradeForDetails.status === 'active'" class="flex justify-between mb-2">
                              <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Time Remaining:</span>
                              <span class="text-sm text-gray-900 dark:text-gray-100">
                            {{ selectedTradeForDetails.time_remaining || 'Calculating...' }}
                        </span>
                          </div>

                          <div class="flex justify-between">
                              <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Created:</span>
                              <div class="text-right">
                                  <div class="text-sm text-gray-900 dark:text-gray-100">
                                      {{ formatDateTime(selectedTradeForDetails.created_at) }}
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

  <div
      v-if="showTradeResultModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="closeTradeResultModal"
  >
      <div
          class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800"
          @click.stop
      >
          <div class="mt-3">
              <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                      Trade Result Setting
                  </h3>
                  <button
                      class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                      @click="closeTradeResultModal"
                  >
                      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                  </button>
              </div>

              <div class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Default Trade Result
                  </label>
                  <select
                      v-model="selectedResult"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                  >
                      <option value="">Select result...</option>
                      <option value="won">Won</option>
                      <option value="lost">Lost</option>
                      <option value="automated">Automated</option>
                  </select>
              </div>

              <div class="flex items-center justify-end space-x-3">
                  <button
                      class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-gray-500 transition-colors duration-200"
                      @click="closeTradeResultModal"
                  >
                      Cancel
                  </button>
                  <button
                      :disabled="!selectedResult || isLoading"
                      class="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 rounded-lg focus:ring-2 focus:ring-purple-500 transition-colors duration-200"
                      @click="updateTradeResultSetting"
                  >
                      <span v-if="isLoading">Updating...</span>
                      <span v-else>Save Setting</span>
                  </button>
              </div>
          </div>
      </div>
  </div>
  </AdminLayout>
</template>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-5 {
        grid-template-columns: 1fr;
    }
}
</style>
