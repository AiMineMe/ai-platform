<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";
import { useSettings } from "@/composables/useSettings.js";

const props = defineProps({
    wallets: {
        type: Object,
        required: true,
        default: () => ({ data: [] }),
        validator: (value) => value && typeof value === 'object' && Array.isArray(value.data)
    },
    wallet_types: {
        type: Array,
        default: () => [
            { value: 'main', label: 'Main Wallet' },
            { value: 'tradey', label: 'trade Wallet' }
        ],
        validator: (value) => Array.isArray(value)
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'created_at',
            sort_direction: 'desc'
        }),
        validator: (value) => value && typeof value === 'object'
    },
    meta: {
        type: Object,
        default: () => ({
            total: 0,
            current_page: 1,
            per_page: 10,
            last_page: 1
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                typeof value.total === 'number' &&
                typeof value.current_page === 'number' &&
                typeof value.per_page === 'number' &&
                typeof value.last_page === 'number';
        }
    },
    stats: {
        type: Object,
        default: () => ({
            total_wallets: 0,
            total_balance_usd: 0,
            main_wallets: 0,
            trade_wallets: 0
        }),
        validator: (value) => value && typeof value === 'object'
    },
    currentUser: {
        type: Object,
        default: () => ({}),
        validator: (value) => value && typeof value === 'object'
    }
});

const { showToast } = useToast();
const { currencySymbol } = useSettings();
const isLoading = ref(false);
const processing = ref({});
const updatingWallet = ref(null);
const error = ref(null);
const balanceModal = ref({
    visible: false,
    wallet: null,
    action: 'add',
    amount: '',
    reason: ''
});

const searchTerm = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || '');
const selectedStatus = ref(props.filters?.status || '');
const walletTypes = props.wallet_types;

const statusOptions = [
    { value: 0, label: 'Inactive' },
    { value: 1, label: 'Active' },
    { value: 2, label: 'Locked' }
];

const isAdmin = computed(() => props.currentUser?.role === 'admin');
const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');
const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedType.value || selectedStatus.value);
});

const isProcessing = (walletId, action) => {
    return processing.value[`${walletId}-${action}`] === true;
};

const setProcessing = (walletId, action, value) => {
    if (value) {
        processing.value[`${walletId}-${action}`] = true;
    } else {
        delete processing.value[`${walletId}-${action}`];
    }
};

const isAnyProcessing = (walletId) => {
    return isProcessing(walletId, 'add') || isProcessing(walletId, 'subtract') || updatingWallet.value === walletId;
};

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

    router.get('/admin/wallets', params, {
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

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedType.value = '';
    selectedStatus.value = '';

    isLoading.value = true;
    error.value = null;

    router.get('/admin/wallets', {
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

const getTypeLabel = (type) => {
    const option = walletTypes.find(t => t.value === type);
    return option ? option.label : type;
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US').format(num);
};

const formatType = (type) => {
    if (!type) return 'Unknown';
    return type.charAt(0).toUpperCase() + type.slice(1) + ' Wallet';
};

const formatStatus = (status) => {
    const statusMap = {
        0: 'Inactive',
        1: 'Active',
        2: 'Locked'
    };
    return statusMap[status] || 'Unknown';
};

const formatMoney = (value) => {
    if (!value && value !== 0) return '0.00';
    const num = parseFloat(value);
    if (num >= 1000000000) return (num / 1000000000).toFixed(2) + 'B';
    else if (num >= 1000000) return (num / 1000000).toFixed(2) + 'M';
    else if (num >= 1000) return (num / 1000).toFixed(2) + 'K';
    else return num.toFixed(2);
};

const getStatusClass = (status) => {
    const classes = {
        0: 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700',
        1: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        2: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
    };
    return classes[status] || classes[0];
};

const getStatusDotClass = (status) => {
    const classes = {
        0: 'bg-gray-500',
        1: 'bg-green-500',
        2: 'bg-red-500'
    };
    return classes[status] || classes[0];
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

    router.get('/admin/wallets', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort wallets. Please try again.';
            showToast('Failed to sort wallets', 'error');
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

    router.get('/admin/wallets', params, {
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

const updateWalletStatus = async (walletId, newStatus) => {
    if (!isAdmin.value || isAnyProcessing(walletId)) {
        if (isAnyProcessing(walletId)) {
            showToast('Please wait for the current operation to complete', 'warning');
        }
        return;
    }

    updatingWallet.value = walletId;
    error.value = null;

    try {
        router.put(`/admin/wallets/${walletId}/status`, {
            status: parseInt(newStatus)
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast('Wallet status updated successfully', 'success');
            },
            onError: (errors) => {
                console.error('Status update error:', errors);
                const errorMessage = errors.message || 'Failed to update wallet status';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                updatingWallet.value = null;
            }
        });
    } catch (error) {
        console.error('Error updating wallet status:', error);
        showToast('Failed to update wallet status', 'error');
        updatingWallet.value = null;
    }
};

const showBalanceModal = (wallet, action) => {
    if (isAnyProcessing(wallet.id)) {
        showToast('Please wait for the current operation to complete', 'warning');
        return;
    }
    balanceModal.value = {
        visible: true,
        wallet: { ...wallet },
        action: action,
        amount: '',
        reason: ''
    };
};

const closeBalanceModal = () => {
    balanceModal.value = {
        visible: false,
        wallet: null,
        action: 'add',
        amount: '',
        reason: ''
    };
};

const processBalanceAdjustment = () => {
    if (!balanceModal.value.wallet || !balanceModal.value.amount) return;

    const walletId = balanceModal.value.wallet.id;
    const action = balanceModal.value.action;

    if (isAnyProcessing(walletId)) return;
    setProcessing(walletId, action, true);
    router.put(`/admin/wallets/${walletId}/adjust-balance`, {
        action: action,
        amount: parseFloat(balanceModal.value.amount),
        reason: balanceModal.value.reason
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showToast(`Balance ${action === 'add' ? 'added' : 'subtracted'} successfully`, 'success');
            closeBalanceModal();
        },
        onError: (errors) => {
            const errorMessage = errors?.message ||
                (typeof errors === 'object' ? Object.values(errors)[0] : errors) ||
                `Failed to ${action} balance`;
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            setProcessing(walletId, action, false);
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

onUnmounted(() => {
    processing.value = {};
    updatingWallet.value = null;
    balanceModal.value.visible = false;
});
</script>

<template>
  <AdminLayout
    title="Wallet Management"
    page-section="Wallet & Finance"
  >
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Wallet Management
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage user wallets and adjust balances
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
              Total Balance
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ currencySymbol }}{{ formatMoney(stats.total_balance_usd) }}
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
                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Wallets
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(stats.total_wallets) }}
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
                d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Main Wallets
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(stats.main_wallets) }}
            </p>
          </div>
        </div>
      </div>

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
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              trade Wallets
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(stats.trade_wallets) }}
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
                id="search-wallets"
                v-model="searchTerm"
                type="text"
                placeholder="Search by wallet name, user, or currency..."
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
                v-model="selectedType"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Types
                </option>
                <option
                  v-for="type in walletTypes"
                  :key="type.value"
                  :value="type.value"
                >
                  {{ type.label }}
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
                  All Statuses
                </option>
                <option value="0">
                  Inactive
                </option>
                <option value="1">
                  Active
                </option>
                <option value="2">
                  Locked
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
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300"
          >
            Status: {{ formatStatus(selectedStatus) }}
            <button
              class="ml-2 text-orange-600 hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-200"
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
                @click="handleSort('name')"
              >
                <div class="flex items-center space-x-1">
                  <span>WALLET</span>
                  <svg
                    v-if="sortField === 'name'"
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
                @click="handleSort('balance')"
              >
                <div class="flex items-center space-x-1">
                  <span>BALANCE</span>
                  <svg
                    v-if="sortField === 'balance'"
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
                USER
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                ACTIONS
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-if="!isLoading && (!wallets.data || wallets.data.length === 0)">
              <td
                colspan="6"
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
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No wallets found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No wallets match your current filters. Try adjusting the search criteria.
                  </p>
                </div>
              </td>
            </tr>

            <tr v-if="isLoading">
              <td
                colspan="6"
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
                      d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400">Loading wallets...</span>
                </div>
              </td>
            </tr>

            <tr
              v-for="wallet in wallets.data"
              :key="wallet.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-3">
                  <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                      {{ wallet.name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                      {{ wallet.currency }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-medium rounded-full"
                  :class="{
                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300': wallet.type === 'main',
                    'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300': wallet.type === 'trade'
                  }"
                >
                  {{ wallet.type_label || formatType(wallet.type) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  <div class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ currencySymbol }}{{ formatNumber(wallet.balance) }}
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="min-w-[140px]">
                  <select
                    v-if="isAdmin"
                    :value="wallet.status"
                    :disabled="isAnyProcessing(wallet.id)"
                    class="w-full text-xs rounded-lg border-gray-300 dark:border-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 py-2 px-3 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                    @change="updateWalletStatus(wallet.id, $event.target.value)"
                  >
                    <option
                      v-for="status in statusOptions"
                      :key="status.value"
                      :value="status.value"
                    >
                      {{ status.label }}
                    </option>
                  </select>
                  <span
                    v-else
                    class="px-2.5 py-1.5 inline-flex text-xs leading-5 font-semibold rounded-full items-center shadow-sm"
                    :class="getStatusClass(wallet.status)"
                  >
                    <span
                      class="w-2 h-2 rounded-full mr-2"
                      :class="getStatusDotClass(wallet.status)"
                    />
                    {{ wallet.status_label || formatStatus(wallet.status) }}
                  </span>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                  <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ wallet.user.name }}
                  </div>
                  <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                    {{ wallet.user.email }}
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <button
                    :disabled="isAnyProcessing(wallet.id)"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-lime-500 hover:bg-lime-600 disabled:bg-green-400 disabled:cursor-not-allowed border border-transparent rounded-lg focus:ring-2 focus:ring-green-500 transition-colors duration-200"
                    @click="showBalanceModal(wallet, 'add')"
                  >
                    <svg
                      v-if="isProcessing(wallet.id, 'add')"
                      class="animate-spin h-3 w-3 mr-1"
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
                        d="M4 12a8 8 0 718-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      />
                    </svg>
                    <svg
                      v-else
                      class="h-3 w-3 mr-1"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                      />
                    </svg>
                    {{ isProcessing(wallet.id, 'add') ? 'Processing...' : 'Add' }}
                  </button>

                  <button
                    :disabled="isAnyProcessing(wallet.id)"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-400 disabled:cursor-not-allowed border border-transparent rounded-lg focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                    @click="showBalanceModal(wallet, 'subtract')"
                  >
                    <svg
                      v-if="isProcessing(wallet.id, 'subtract')"
                      class="animate-spin h-3 w-3 mr-1"
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
                        d="M4 12a8 8 0 718-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                      />
                    </svg>
                    <svg
                      v-else
                      class="h-3 w-3 mr-1"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 12H4"
                      />
                    </svg>
                    {{ isProcessing(wallet.id, 'subtract') ? 'Processing...' : 'Subtract' }}
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
      item-name="wallets"
      @page-change="changePage"
    />

    <div
      v-show="balanceModal.visible"
      class="fixed inset-0 z-50 overflow-y-auto"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div
          v-show="balanceModal.visible"
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          aria-hidden="true"
          @click="closeBalanceModal"
        />

        <div class="relative inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                {{ balanceModal.action === 'add' ? 'Add Balance' : 'Subtract Balance' }}
              </h3>
              <button
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                @click="closeBalanceModal"
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
              v-if="balanceModal.wallet"
              class="mb-6 bg-gray-50 dark:bg-slate-700 rounded-lg p-4"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="font-medium text-gray-700 dark:text-gray-300">Wallet:</span>
                  <span class="ml-2 text-gray-900 dark:text-gray-100">{{ balanceModal.wallet.name }}</span>
                </div>
                <div>
                  <span class="font-medium text-gray-700 dark:text-gray-300">User:</span>
                  <span class="ml-2 text-gray-900 dark:text-gray-100">{{ balanceModal.wallet.user.name }}</span>
                </div>
                <div class="col-span-1 md:col-span-2">
                  <span class="font-medium text-gray-700 dark:text-gray-300">Current Balance:</span>
                  <span class="ml-2 text-gray-900 dark:text-gray-100 font-mono">{{ currencySymbol }}{{ formatNumber(balanceModal.wallet.balance) }}</span>
                </div>
              </div>
            </div>

            <div class="mb-6">
              <label
                for="balance-amount"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Amount ({{ currencySymbol }})
              </label>
              <input
                id="balance-amount"
                v-model="balanceModal.amount"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                required
              >
            </div>

            <div class="mb-6">
              <label
                for="balance-reason"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Reason (Optional)
              </label>
              <textarea
                id="balance-reason"
                v-model="balanceModal.reason"
                rows="3"
                placeholder="Enter reason for balance adjustment..."
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
              />
            </div>
          </div>

          <div class="bg-gray-50 dark:bg-slate-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              :disabled="!balanceModal.amount"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
              :class="{
                'bg-green-600 hover:bg-green-700 focus:ring-green-500': balanceModal.action === 'add',
                'bg-red-600 hover:bg-red-700 focus:ring-red-500': balanceModal.action === 'subtract'
              }"
              @click="processBalanceAdjustment"
            >
              {{ balanceModal.action === 'add' ? 'Add Balance' : 'Subtract Balance' }}
            </button>
            <button
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm"
              @click="closeBalanceModal"
            >
              Cancel
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
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}</style>
