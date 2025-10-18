<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    currencies: {
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
            totalCurrencies: 0,
            priceGainers: 0,
            priceDecliners: 0
        })
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'created_at',
            sort_direction: 'desc'
        })
    }
});

const { showToast } = useToast();
const isLoading = ref(false);
const error = ref(null);
const searchTerm = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || '');
const selectedStatus = ref(props.filters?.status || '');

const typeOptions = [
    { value: 'crypto', label: 'Cryptocurrency' },
    { value: 'forex', label: 'Forex' },
    { value: 'stock', label: 'Stock' },
    { value: 'commodity', label: 'Commodity' }
];

const statusFilterOptions = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' }
];

const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedType.value || selectedStatus.value);
});

const totalCurrenciesCount = computed(() => props.stats?.totalCurrencies || 0);
const priceGainersCount = computed(() => props.stats?.priceGainers || 0);
const priceDeclinersCount = computed(() => props.stats?.priceDecliners || 0);

const lastUpdatedTime = computed(() => {
    if (!props.currencies.length) return 'Never';
    const latest = props.currencies.reduce((latest, currency) => {
        const updateTime = new Date(currency.last_updated || currency.updated_at);
        return !latest || updateTime > latest ? updateTime : latest;
    }, null);
    return latest ? formatDate(latest) : 'Never';
});

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const applyFilters = () => {
    if (isLoading.value) return;

    isLoading.value = true;
    error.value = null;

    const params = {
        page: 1,
        sort_field: sortField.value,
        sort_direction: sortDirection.value
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedType.value) params.type = selectedType.value;
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/admin/market-data', params, {
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
const clearTypeFilter = () => {
    selectedType.value = '';
    applyFilters();
};
const clearStatusFilter = () => {
    selectedStatus.value = '';
    applyFilters();
};
const getTypeLabel = (type) => {
    const option = typeOptions.find(t => t.value === type);
    return option ? option.label : type;
};

const getStatusLabel = (status) => {
    const option = statusFilterOptions.find(s => s.value === status);
    return option ? option.label : status;
};
const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US').format(num);
};
const getCurrencyInitials = (symbol) => {
    if (!symbol || typeof symbol !== 'string') return '??';
    return symbol.substring(0, 2).toUpperCase();
};
const formatType = (type) => {
    if (!type) return 'Unknown';
    return type.charAt(0).toUpperCase() + type.slice(1);
};
const formatPrice = (price) => {
    const num = parseFloat(price) || 0;
    if (num >= 1000000000) {
        return '$' + (num / 1000000000).toFixed(2) + 'B';
    } else if (num >= 1000000) {
        return '$' + (num / 1000000).toFixed(2) + 'M';
    } else if (num >= 1000) {
        return '$' + (num / 1000).toFixed(2) + 'K';
    } else if (num >= 1) {
        return '$' + num.toFixed(2);
    } else if (num == 0) {
        return '$' + num.toFixed(2);
    } else {
        return '$' + num.toFixed(8);
    }
};

const formatPercentage = (percentage) => {
    const num = parseFloat(percentage) || 0;
    return num.toFixed(2);
};

const getTypeColor = (type) => {
    const colors = {
        crypto: 'bg-gradient-to-br from-orange-500 to-yellow-600',
        forex: 'bg-gradient-to-br from-green-500 to-emerald-600',
        stock: 'bg-gradient-to-br from-blue-500 to-indigo-600',
        commodity: 'bg-gradient-to-br from-purple-500 to-pink-600'
    };
    return colors[type?.toLowerCase()] || 'bg-gradient-to-br from-gray-500 to-gray-600';
};

const getTypeClass = (type) => {
    const classes = {
        crypto: 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700',
        forex: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        stock: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
        commodity: 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700'
    };
    return classes[type?.toLowerCase()] || 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700';
};
const getChangeClass = (changePercent) => {
    const num = parseFloat(changePercent) || 0;
    if (num === 0) return 'text-gray-500 dark:text-gray-400';
    return num > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        console.error('Date formatting error:', error);
        return 'Invalid Date';
    }
};

const formatLastUpdated = (dateString) => {
    if (!dateString) return 'Never';
    return formatDate(dateString);
};

const getRelativeTime = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
        return `${Math.floor(diffInSeconds / 31536000)}y ago`;
    } catch (error) {
        console.error('Relative time error:', error);
        return '';
    }
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

    const params = {
        sort_field: field,
        sort_direction: newDirection,
        page: 1
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedType.value) params.type = selectedType.value;
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/admin/market-data', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort currencies. Please try again.';
            showToast('Failed to sort currencies', 'error');
        }
    });
};

const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return;

    isLoading.value = true;
    error.value = null;

    const params = { page };
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedType.value) params.type = selectedType.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/market-data', params, {
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

const handleImageError = (event) => {
    event.target.style.display = 'none';
    event.target.nextElementSibling.style.display = 'flex';
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
</script>

<template>
    <AdminLayout
        title="Currency Management"
        page-section="Financial Data"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Crypto Currency Management
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Monitor and track cryptocurrency prices in real time.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg
                            class="h-6 w-6 text-blue-600 dark:text-blue-400"
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
                            Total Currencies
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(totalCurrenciesCount) }}
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
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Price Gainers
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(priceGainersCount) }}
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
                                d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Price Decliners
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(priceDeclinersCount) }}
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
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Last Updated
                        </p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ lastUpdatedTime }}
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
                                id="search-currencies"
                                v-model="searchTerm"
                                type="text"
                                placeholder="Search by name or symbol..."
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
                        v-if="selectedType"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300"
                    >
            Type: {{ getTypeLabel(selectedType) }}
            <button
                class="ml-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-200"
                @click="clearTypeFilter"
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
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                    >
            Status: {{ getStatusLabel(selectedStatus) }}
            <button
                class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200"
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

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                            @click="handleSort('symbol')"
                        >
                            <div class="flex items-center space-x-1">
                                <span>CURRENCY</span>
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
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                            @click="handleSort('type')"
                        >
                            <div class="flex items-center space-x-1">
                                <span>TYPE</span>
                                <svg
                                    v-if="sortField === 'type'"
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
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                            @click="handleSort('current_price')"
                        >
                            <div class="flex items-center space-x-1">
                                <span>CURRENT PRICE</span>
                                <svg
                                    v-if="sortField === 'current_price'"
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
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                            @click="handleSort('change_percent')"
                        >
                            <div class="flex items-center space-x-1">
                                <span>24H CHANGE</span>
                                <svg
                                    v-if="sortField === 'change_percent'"
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
                            PREVIOUS PRICE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            LAST UPDATED
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="!isLoading && currencies.length === 0">
                        <td
                            colspan="7"
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
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    No currencies found
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    No currencies match your current filters. Try adjusting the search criteria.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="isLoading">
                        <td
                            colspan="7"
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
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">Loading currencies...</span>
                            </div>
                        </td>
                    </tr>

                    <tr
                        v-for="currency in currencies"
                        :key="currency.id"
                        class="hover:bg-gray-50 dark:hover:bg-slate-700"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0 relative">
                                    <img
                                        v-if="currency.image_url"
                                        :src="currency.image_url"
                                        :alt="currency.name"
                                        class="h-full w-full rounded-full object-cover shadow-md"
                                        @error="handleImageError"
                                    >
                                    <div
                                        class="h-full w-full rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md"
                                        :class="getTypeColor(currency.type)"
                                        :style="{ display: currency.image_url ? 'none' : 'flex' }"
                                    >
                                        {{ getCurrencyInitials(currency.symbol) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ currency.name || 'Unknown' }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                                        {{ currency.symbol || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                        <span
                                class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                                :class="getTypeClass(currency.type)"
                            >
                            {{ formatType(currency.type) }}
                        </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatPrice(currency.current_price) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ currency.base_currency }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div
                                v-if="currency.change_percent !== null && currency.change_percent !== undefined"
                                class="flex items-center text-sm font-semibold"
                                :class="getChangeClass(currency.change_percent)"
                            >
                                <svg
                                    v-if="currency.change_percent > 0"
                                    class="w-4 h-4 mr-1"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
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
                                        d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                {{ formatPercentage(Math.abs(currency.change_percent)) }}%
                            </div>
                            <div
                                v-else
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                No data
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ formatPrice(currency.previous_price) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ currency.base_currency }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ formatLastUpdated(currency.last_updated || currency.updated_at) }}
                            </div>
                            <div
                                v-if="currency.last_updated || currency.updated_at"
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ getRelativeTime(currency.last_updated || currency.updated_at) }}
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
            item-name="currencies"
            @page-change="changePage"
        />
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
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}
</style>
