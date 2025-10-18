<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    withdrawals: {
        type: Array,
        required: true,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    meta: {
        type: Object,
        default: () => ({
            total: 0,
            current_page: 1,
            per_page: 20,
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
            totalWithdrawals: 0,
            pendingWithdrawals: 0,
            totalAmount: 0,
            pendingAmount: 0
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                typeof value.totalWithdrawals === 'number' &&
                typeof value.pendingWithdrawals === 'number' &&
                typeof value.totalAmount === 'number' &&
                typeof value.pendingAmount === 'number';
        }
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'created_at',
            sort_direction: 'desc'
        }),
        validator: (value) => value && typeof value === 'object'
    },
    gateways: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    currentUser: {
        type: Object,
        default: () => ({}),
        validator: (value) => value && typeof value === 'object'
    }
});

const { currencySymbol } = useSettings();
const { showToast } = useToast();
const isLoading = ref(false);
const updatingWithdrawal = ref(null);
const error = ref(null);
const isProcessing = ref(false);
const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const selectedGateway = ref(props.filters?.gateway || '');
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const selectedWithdrawal = ref(null);
const approveResponse = ref('');
const rejectResponse = ref('');

const statusFilterOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' }
];

const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');
const gatewayOptions = computed(() => {
    if (!props.gateways || !Array.isArray(props.gateways)) return [];
    return props.gateways
        .filter(gateway => gateway && gateway.id && gateway.name)
        .map(gateway => ({
            value: gateway.id,
            label: gateway.name
        }));
});

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedStatus.value || selectedGateway.value);
});

const totalWithdrawalsCount = computed(() => props.stats?.total_withdrawals || 0);
const pendingWithdrawalsCount = computed(() => props.stats?.pending_withdrawals || 0);
const totalAmountCount = computed(() => props.stats?.total_amount || 0);
const pendingAmountCount = computed(() => props.stats?.pending_amount || 0);

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
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedGateway.value) params.gateway = selectedGateway.value;

    router.get('/admin/withdrawals', params, {
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

const clearStatusFilter = () => {
    selectedStatus.value = '';
    applyFilters();
};

const clearGatewayFilter = () => {
    selectedGateway.value = '';
    applyFilters();
};

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';
    selectedGateway.value = '';

    isLoading.value = true;
    error.value = null;

    router.get('/admin/withdrawals', {
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

const getStatusLabel = (status) => {
    const option = statusFilterOptions.find(s => s.value === status);
    return option ? option.label : status;
};

const getGatewayLabel = (gatewayId) => {
    const option = gatewayOptions.value.find(g => g.value === gatewayId);
    return option ? option.label : gatewayId;
};

const getUserName = (withdrawal) => {
    return (withdrawal.user && withdrawal.user.name) ? withdrawal.user.name : 'N/A';
};

const getUserEmail = (withdrawal) => {
    return (withdrawal.user && withdrawal.user.email) ? withdrawal.user.email : 'N/A';
};

const getGatewayName = (withdrawal) => {
    const gateway = withdrawal.withdrawal_gateway || withdrawal.gateway;
    return (gateway && gateway.name) ? gateway.name : 'N/A';
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0.00';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
};

const formatMoney = (value) => {
    if (!value || isNaN(value)) return '0.00';
    const num = Math.abs(parseFloat(value));

    if (num >= 1e9) return (num / 1e9).toFixed(2) + 'B';
    if (num >= 1e6) return (num / 1e6).toFixed(2) + 'M';
    if (num >= 1e3) return (num / 1e3).toFixed(2) + 'K';
    return num.toFixed(2);
};

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2);
};

const formatStatus = (status) => {
    const statusMap = {
        'pending': 'Pending',
        'approved': 'Approved',
        'rejected': 'Rejected'
    };
    return statusMap[status] || 'Unknown';
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700',
        'approved': 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        'rejected': 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
    };
    return classes[status] || classes['pending'];
};

const getStatusDotClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-500',
        'approved': 'bg-green-500',
        'rejected': 'bg-red-500'
    };
    return classes[status] || 'bg-gray-500';
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
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedGateway.value) params.gateway = selectedGateway.value;

    router.get('/admin/withdrawals', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort withdrawals. Please try again.';
            showToast('Failed to sort withdrawals', 'error');
        }
    });
};

const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return;

    isLoading.value = true;
    error.value = null;

    const params = { page };
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedGateway.value) params.gateway = selectedGateway.value;
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/withdrawals', params, {
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

const openApproveModal = (withdrawal) => {
    selectedWithdrawal.value = withdrawal;
    approveResponse.value = '';
    showApproveModal.value = true;
};

const closeApproveModal = () => {
    showApproveModal.value = false;
    selectedWithdrawal.value = null;
    approveResponse.value = '';
};

const openRejectModal = (withdrawal) => {
    selectedWithdrawal.value = withdrawal;
    rejectResponse.value = '';
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    selectedWithdrawal.value = null;
    rejectResponse.value = '';
};

const confirmApprove = () => {
    if (!selectedWithdrawal.value || isProcessing.value) return;

    isProcessing.value = true;

    router.patch(`/admin/withdrawals/${selectedWithdrawal.value.id}/approve`, {
        response: approveResponse.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeApproveModal();
            showToast('Withdrawal approved successfully', 'success');
        },
        onError: () => {
            showToast('Failed to approve withdrawal', 'error');
        },
        onFinish: () => isProcessing.value = false
    });
};

const confirmReject = () => {
    if (!selectedWithdrawal.value || isProcessing.value || !rejectResponse.value.trim()) return;

    isProcessing.value = true;

    router.patch(`/admin/withdrawals/${selectedWithdrawal.value.id}/reject`, {
        response: rejectResponse.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeRejectModal();
            showToast('Withdrawal rejected successfully', 'success');
        },
        onError: () => {
            showToast('Failed to reject withdrawal', 'error');
        },
        onFinish: () => isProcessing.value = false
    });
};


const formatFieldName = (fieldName) => {
    if (!fieldName || typeof fieldName !== 'string') return '';
    return fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const isFileField = (value) => {
    if (!value || typeof value !== 'string') return false;
    return value.includes('/storage/') || value.includes('/uploads/') || value.startsWith('http');
};

const getFileUrl = (value) => {
    if (!value) return '#';
    if (value.startsWith('http')) return value;
    return `/storage/${value}`;
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
    title="Withdraw Requests"
    page-section="Payments"
  >
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Withdraw Requests
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage and process user withdrawal requests
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
                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Withdrawals
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ totalWithdrawalsCount }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-yellow-600 dark:text-yellow-400"
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
              Pending
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ pendingWithdrawalsCount }}
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
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Deducted
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ currencySymbol }}{{ formatMoney(totalAmountCount) }}
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
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Pending Deduction
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ currencySymbol }}{{ formatMoney(pendingAmountCount) }}
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
                id="search-withdrawals"
                v-model="searchTerm"
                type="text"
                placeholder="Search by transaction ID, user, or gateway..."
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

            <div class="min-w-[140px]">
              <select
                v-model="selectedGateway"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Gateways
                </option>
                <option
                  v-for="gateway in gatewayOptions"
                  :key="gateway.value"
                  :value="gateway.value"
                >
                  {{ gateway.label }}
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

          <span
            v-if="selectedGateway"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300"
          >
            Gateway: {{ getGatewayLabel(selectedGateway) }}
            <button
              class="ml-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-200"
              @click="clearGatewayFilter"
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
                @click="handleSort('trx')"
              >
                <div class="flex items-center space-x-1">
                  <span>TRANSACTION</span>
                  <svg
                    v-if="sortField === 'trx'"
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
                @click="handleSort('user_name')"
              >
                <div class="flex items-center space-x-1">
                  <span>USER</span>
                  <svg
                    v-if="sortField === 'user_name'"
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
                GATEWAY
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('amount')"
              >
                <div class="flex items-center space-x-1">
                  <span>AMOUNTS</span>
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
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('created_at')"
              >
                <div class="flex items-center space-x-1">
                  <span>DATE</span>
                  <svg
                    v-if="sortField === 'created_at'"
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
            <tr v-if="!isLoading && withdrawals.length === 0">
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
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No withdrawals found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No withdrawal requests match your current filters. Try adjusting the search criteria.
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
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400">Loading withdrawals...</span>
                </div>
              </td>
            </tr>

            <tr
              v-for="withdrawal in withdrawals"
              :key="withdrawal.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                  {{ withdrawal.trx || 'N/A' }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-3">
                  <div class="h-8 w-8 flex-shrink-0">
                    <div class="h-full w-full rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-xs shadow-md">
                      {{ getInitials(getUserName(withdrawal)) }}
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                      {{ getUserName(withdrawal) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                      {{ getUserEmail(withdrawal) }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">
                  {{ getGatewayName(withdrawal) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ withdrawal.currency || 'N/A' }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100 space-y-1">
                  <div class="bg-blue-50 dark:bg-blue-900/20 p-2 rounded border-l-2 border-blue-500">
                    <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                      Gateway ({{ withdrawal.currency || 'N/A' }})
                    </div>
                    <div class="font-semibold">
                      {{ withdrawal.currency || '' }}{{ formatNumber(withdrawal.amount || 0) }}
                    </div>
                    <div
                      v-if="withdrawal.charge && parseFloat(withdrawal.charge) > 0"
                      class="text-xs text-red-500"
                    >
                      Fee: {{ withdrawal.currency || '' }}{{ formatNumber(withdrawal.charge) }}
                    </div>
                    <div class="text-xs text-green-600">
                      User gets: {{ withdrawal.currency || '' }}{{ formatNumber(withdrawal.final_amount || 0) }}
                    </div>
                  </div>

                  <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded border-l-2 border-red-500">
                    <div class="text-xs text-red-600 dark:text-red-400 font-medium">
                      Deducted
                    </div>
                    <div class="text-red-600 dark:text-red-400 font-semibold">
                      {{ currencySymbol }}{{ formatNumber(withdrawal.withdrawal_amount || 0) }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                  :class="getStatusClass(withdrawal.status)"
                >
                  <span
                    class="w-2 h-2 rounded-full mr-1"
                    :class="getStatusDotClass(withdrawal.status)"
                  />
                  {{ formatStatus(withdrawal.status) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">
                  {{ formatDate(withdrawal.created_at) }}
                </div>
                <div
                  v-if="withdrawal.created_at"
                  class="text-xs text-gray-500 dark:text-gray-400"
                >
                  {{ getRelativeTime(withdrawal.created_at) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <template v-if="withdrawal.status === 'pending'">
                    <button
                      :disabled="updatingWithdrawal === withdrawal.id"
                      class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50 focus:ring-2 focus:ring-green-500 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                      @click="openApproveModal(withdrawal)"
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
                          d="M5 13l4 4L19 7"
                        />
                      </svg>
                      Approve
                    </button>

                    <button
                      :disabled="updatingWithdrawal === withdrawal.id"
                      class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 focus:ring-2 focus:ring-red-500 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                      @click="openRejectModal(withdrawal)"
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
                      Reject
                    </button>
                  </template>

                  <template v-else>
                    <span class="text-sm text-gray-500 dark:text-gray-400">No Action</span>
                  </template>
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
      item-name="withdrawals"
      @page-change="changePage"
    />

    <div
      v-if="showApproveModal"
      class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50"
      @click="closeApproveModal"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 transform transition-all duration-300 ease-out max-h-[90vh] overflow-y-auto"
        @click.stop
      >
        <div class="p-6">
          <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 dark:bg-green-900/30 rounded-full mb-4">
            <svg
              class="w-6 h-6 text-green-600 dark:text-green-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">
            Approve Withdrawal
          </h3>
          <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-4">
            Are you sure you want to approve this withdrawal request?
          </p>

          <div
            v-if="selectedWithdrawal"
            class="space-y-4 mb-6"
          >
            <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">
                Transaction Summary
              </h4>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Transaction ID:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ selectedWithdrawal.trx }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">User:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ getUserName(selectedWithdrawal) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Gateway:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ getGatewayName(selectedWithdrawal) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Currency:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ selectedWithdrawal.currency || 'N/A' }}</span>
                </div>
              </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border-l-4 border-blue-500">
              <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-3">
                Amount Details
              </h4>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-blue-700 dark:text-blue-300">Requested Amount:</span>
                  <span class="font-semibold text-blue-900 dark:text-blue-100">{{ selectedWithdrawal.currency }}{{ formatNumber(selectedWithdrawal.amount) }}</span>
                </div>
                <div
                  v-if="selectedWithdrawal.charge && parseFloat(selectedWithdrawal.charge) > 0"
                  class="flex justify-between"
                >
                  <span class="text-blue-700 dark:text-blue-300">Processing Fee:</span>
                  <span class="font-medium text-orange-600 dark:text-orange-400">{{ selectedWithdrawal.currency }}{{ formatNumber(selectedWithdrawal.charge) }}</span>
                </div>
                <div class="flex justify-between border-t border-blue-200 dark:border-blue-700 pt-2">
                  <span class="text-blue-700 dark:text-blue-300 font-medium">User Receives:</span>
                  <span class="font-bold text-green-600 dark:text-green-400 text-lg">{{ selectedWithdrawal.currency }}{{ formatNumber(selectedWithdrawal.final_amount) }}</span>
                </div>
                <div class="flex justify-between bg-red-50 dark:bg-red-900/20 rounded p-2 mt-2">
                  <span class="text-red-700 dark:text-red-300">Wallet Deduction:</span>
                  <span class="font-bold text-red-600 dark:text-red-400">{{ currencySymbol }}{{ formatNumber(selectedWithdrawal.withdrawal_amount) }}</span>
                </div>
              </div>
            </div>

            <div
              v-if="selectedWithdrawal.user_data && Object.keys(selectedWithdrawal.user_data).length > 0"
              class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4"
            >
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">
                Withdrawal Details Provided by User
              </h4>
              <div class="text-sm space-y-2">
                <div
                  v-for="(value, key) in selectedWithdrawal.user_data"
                  :key="key"
                  class="flex justify-between items-start"
                >
                  <span class="text-gray-600 dark:text-gray-400 capitalize min-w-[140px]">{{ formatFieldName(key) }}:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100 break-all text-right flex-1 ml-2">
                    <template v-if="isFileField(value)">
                      <a
                        :href="getFileUrl(value)"
                        target="_blank"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline"
                      >
                        View File
                      </a>
                    </template>
                    <template v-else>
                      {{ value || 'N/A' }}
                    </template>
                  </span>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">
                Timeline
              </h4>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Created:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(selectedWithdrawal.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Response (Optional)
            </label>
            <textarea
              v-model="approveResponse"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 dark:focus:border-green-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
              placeholder="Optional note for the user..."
            />
          </div>

          <div class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
            <button
              :disabled="isProcessing"
              class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
              @click="confirmApprove"
            >
              <svg
                v-if="isProcessing"
                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
              {{ isProcessing ? 'Approving...' : 'Approve Withdrawal' }}
            </button>
            <button
              class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-white hover:bg-gray-50 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-slate-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              @click="closeApproveModal"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showRejectModal"
      class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50"
      @click="closeRejectModal"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 transform transition-all duration-300 ease-out max-h-[90vh] overflow-y-auto"
        @click.stop
      >
        <div class="p-6">
          <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
            <svg
              class="w-6 h-6 text-red-600 dark:text-red-400"
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
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">
            Reject Withdrawal
          </h3>
          <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-4">
            Please provide a reason for rejecting this withdrawal request.
          </p>

          <div
            v-if="selectedWithdrawal"
            class="space-y-4 mb-6"
          >
            <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">
                Transaction Summary
              </h4>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Transaction ID:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ selectedWithdrawal.trx }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">User:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ getUserName(selectedWithdrawal) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Gateway:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ getGatewayName(selectedWithdrawal) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Currency:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ selectedWithdrawal.currency || 'N/A' }}</span>
                </div>
              </div>
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border-l-4 border-red-500">
              <h4 class="font-medium text-red-900 dark:text-red-100 mb-3">
                Amount Details
              </h4>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-red-700 dark:text-red-300">Requested Amount:</span>
                  <span class="font-semibold text-red-900 dark:text-red-100">{{ selectedWithdrawal.currency }}{{ formatNumber(selectedWithdrawal.amount) }}</span>
                </div>
                <div
                  v-if="selectedWithdrawal.charge && parseFloat(selectedWithdrawal.charge) > 0"
                  class="flex justify-between"
                >
                  <span class="text-red-700 dark:text-red-300">Processing Fee:</span>
                  <span class="font-medium text-orange-600 dark:text-orange-400">{{ selectedWithdrawal.currency }}{{ formatNumber(selectedWithdrawal.charge) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-red-700 dark:text-red-300">User Would Receive:</span>
                  <span class="font-medium text-red-600 dark:text-red-400">{{ selectedWithdrawal.currency }}{{ formatNumber(selectedWithdrawal.final_amount) }}</span>
                </div>
                <div class="flex justify-between bg-gray-100 dark:bg-gray-700 rounded p-2 mt-2">
                  <span class="text-gray-700 dark:text-gray-300">Amount to Refund:</span>
                  <span class="font-bold text-green-600 dark:text-green-400">{{ currencySymbol }}{{ formatNumber(selectedWithdrawal.withdrawal_amount) }}</span>
                </div>
              </div>
            </div>

            <div
              v-if="selectedWithdrawal.user_data && Object.keys(selectedWithdrawal.user_data).length > 0"
              class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4"
            >
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">
                Withdrawal Details Provided by User
              </h4>
              <div class="text-sm space-y-2">
                <div
                  v-for="(value, key) in selectedWithdrawal.user_data"
                  :key="key"
                  class="flex justify-between items-start"
                >
                  <span class="text-gray-600 dark:text-gray-400 capitalize min-w-[140px]">{{ formatFieldName(key) }}:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100 break-all text-right flex-1 ml-2">
                    <template v-if="isFileField(value)">
                      <a
                        :href="getFileUrl(value)"
                        target="_blank"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline"
                      >
                        View File
                      </a>
                    </template>
                    <template v-else>
                      {{ value || 'N/A' }}
                    </template>
                  </span>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
              <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">
                Timeline
              </h4>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Created:</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(selectedWithdrawal.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Rejection Reason *
            </label>
            <textarea
              v-model="rejectResponse"
              rows="3"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:border-red-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
              placeholder="Please explain why this withdrawal is being rejected..."
            />
          </div>

          <div class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
            <button
              :disabled="isProcessing || !rejectResponse.trim()"
              class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
              @click="confirmReject"
            >
              <svg
                v-if="isProcessing"
                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
              {{ isProcessing ? 'Rejecting...' : 'Reject Withdrawal' }}
            </button>
            <button
              class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-white hover:bg-gray-50 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-slate-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              @click="closeRejectModal"
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
}
</style>
