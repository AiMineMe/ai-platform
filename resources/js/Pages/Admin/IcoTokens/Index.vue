<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    icoTokens: {
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
            totalTokens: 0,
            activeTokens: 0,
            totalRaised: 0,
            featuredTokens: 0
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                typeof value.totalTokens === 'number' &&
                typeof value.activeTokens === 'number' &&
                typeof value.totalRaised === 'number' &&
                typeof value.featuredTokens === 'number';
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
    currentUser: {
        type: Object,
        default: () => ({}),
        validator: (value) => value && typeof value === 'object'
    }
});

const { currencySymbol } = useSettings();
const { showToast } = useToast();
const isLoading = ref(false);
const updatingToken = ref(null);
const processingToken = ref(null);
const error = ref(null);
const isProcessing = ref(false);

const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const selectedFeatured = ref(props.filters?.featured || '');
const showDeleteModal = ref(false);
const selectedToken = ref(null);

const statusFilterOptions = [
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
];

const featuredFilterOptions = [
    { value: 'true', label: 'Featured Only' },
    { value: 'false', label: 'Not Featured' }
];

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
];

const isAdmin = computed(() => props.currentUser?.role === 'admin');
const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedStatus.value || selectedFeatured.value);
});

const totalTokensCount = computed(() => props.stats?.totalTokens || 0);
const activeTokensCount = computed(() => props.stats?.activeTokens || 0);
const totalRaisedCount = computed(() => props.stats?.totalRaised || 0);
const featuredTokensCount = computed(() => props.stats?.featuredTokens || 0);

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
    if (selectedFeatured.value) params.featured = selectedFeatured.value;

    router.get('/admin/ico-tokens', params, {
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

const clearFeaturedFilter = () => {
    selectedFeatured.value = '';
    applyFilters();
};

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';
    selectedFeatured.value = '';

    isLoading.value = true;
    error.value = null;

    router.get('/admin/ico-tokens', {
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

const getFeaturedLabel = (featured) => {
    const option = featuredFilterOptions.find(f => f.value === featured);
    return option ? option.label : featured;
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '' || isNaN(value)) {
        return '0.00';
    }

    const numValue = parseFloat(value);
    if (isNaN(numValue)) {
        return '0.00';
    }

    const absValue = Math.abs(numValue);

    if (absValue >= 1000000000) {
        return (numValue / 1000000000).toFixed(2) + 'B';
    } else if (absValue >= 1000000) {
        return (numValue / 1000000).toFixed(2) + 'M';
    } else if (absValue >= 1000) {
        return (numValue / 1000).toFixed(2) + 'K';
    } else {
        return numValue.toFixed(2);
    }
};

const getTokenInitials = (symbol) => {
    if (!symbol || typeof symbol !== 'string') return '??';
    return symbol.substring(0, 2).toUpperCase();
};

const getTokenLogo = (logo) => {
    if (!logo) return null;
    return logo.startsWith('http') ? logo : `/storage/${logo}`;
};

const handleLogoError = (event) => {
    event.target.style.display = 'none';
    const fallback = document.createElement('div');
    fallback.className = 'h-full w-full rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm';
    fallback.textContent = '??';
    event.target.parentElement.appendChild(fallback);
};

const getProgressPercentage = (token) => {
    const tokensSold = parseFloat(token.tokens_sold || 0);
    const totalSupply = parseFloat(token.total_supply || 0);

    if (totalSupply <= 0) return 0;
    return Math.min((tokensSold / totalSupply) * 100, 100);
};

const formatStatus = (status) => {
    if (!status) return 'Unknown';
    return status.charAt(0).toUpperCase() + status.slice(1);
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

const getDaysRemainingText = (token) => {
    if (!token.sale_start_date || !token.sale_end_date) return 'N/A';

    try {
        const now = new Date();
        const endDate = new Date(token.sale_end_date);
        const startDate = new Date(token.sale_start_date);

        if (now < startDate) {
            const daysToStart = Math.ceil((startDate - now) / (1000 * 60 * 60 * 24));
            return `Starts in ${daysToStart} days`;
        } else if (now > endDate) {
            return 'Sale ended';
        } else {
            const daysRemaining = Math.ceil((endDate - now) / (1000 * 60 * 60 * 24));
            return `${daysRemaining} days left`;
        }
    } catch (error) {
        console.error('Date calculation error:', error);
        return 'Invalid dates';
    }
};

const getDaysRemainingClass = (token) => {
    if (!token.sale_start_date || !token.sale_end_date) return 'text-gray-500 dark:text-gray-400';

    try {
        const now = new Date();
        const endDate = new Date(token.sale_end_date);
        const startDate = new Date(token.sale_start_date);

        if (now < startDate) {
            return 'text-blue-600 dark:text-blue-400';
        } else if (now > endDate) {
            return 'text-gray-500 dark:text-gray-400';
        } else {
            const daysRemaining = Math.ceil((endDate - now) / (1000 * 60 * 60 * 24));
            return daysRemaining <= 7 ? 'text-red-600 font-semibold dark:text-red-400' : 'text-green-600 dark:text-green-400';
        }
    } catch (error) {
        return 'text-gray-500 dark:text-gray-400';
    }
};

const getStatusClass = (status) => {
    const statusClasses = {
        'active': 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        'paused': 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700',
        'completed': 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
        'cancelled': 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
    };
    return statusClasses[status?.toLowerCase()] || 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700';
};

const getStatusIcon = (status) => {
    const icons = {
        'active': `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`,
        'paused': `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6"></path></svg>`,
        'completed': `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`,
        'cancelled': `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`
    };
    return icons[status?.toLowerCase()] || '';
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
    if (selectedFeatured.value) params.featured = selectedFeatured.value;

    router.get('/admin/ico-tokens', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort ICO tokens. Please try again.';
            showToast('Failed to sort ICO tokens', 'error');
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
    if (selectedFeatured.value) params.featured = selectedFeatured.value;
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/ico-tokens', params, {
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

const canUpdateStatus = (token) => {
    return isAdmin.value;

};

const updateTokenStatus = async (tokenId, newStatus) => {
    if (!isAdmin.value || updatingToken.value === tokenId) return;

    const token = props.icoTokens.find(t => t.id === tokenId);
    if (!token || !canUpdateStatus(token)) {
        showToast('You do not have permission to update this token\'s status', 'error');
        return;
    }

    updatingToken.value = tokenId;
    error.value = null;

    try {
        router.put(`/admin/ico-tokens/${tokenId}/status`, {
            status: newStatus
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast(`Token status updated to ${formatStatus(newStatus)}`, 'success');
            },
            onError: (errors) => {
                console.error('Status update error:', errors);
                const errorMessage = errors.message || 'Failed to update token status';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                updatingToken.value = null;
            }
        });
    } catch (error) {
        console.error('Error updating token status:', error);
        showToast('Failed to update token status', 'error');
        updatingToken.value = null;
    }
};

const createToken = () => {
    router.get('/admin/ico-tokens/create');
};

const editToken = (token) => {
    router.get(`/admin/ico-tokens/${token.id}/edit`);
};

const toggleFeatured = async (token) => {
    if (processingToken.value === token.id) return;

    processingToken.value = token.id;
    error.value = null;

    try {
        router.patch(`/admin/ico-tokens/${token.id}/toggle-featured`, {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                const message = token.is_featured ? 'Token removed from featured' : 'Token added to featured';
                showToast(message, 'success');
            },
            onError: (errors) => {
                console.error('Toggle featured error:', errors);
                const errorMessage = errors.message || 'Failed to update featured status';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                processingToken.value = null;
            }
        });
    } catch (error) {
        console.error('Error toggling featured:', error);
        showToast('Failed to update featured status', 'error');
        processingToken.value = null;
    }
};

const openDeleteModal = (token) => {
    selectedToken.value = token;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedToken.value = null;
};

const confirmDelete = () => {
    if (!selectedToken.value || isProcessing.value) return;

    isProcessing.value = true;

    router.delete(`/admin/ico-tokens/${selectedToken.value.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast('ICO token deleted successfully', 'success');
        },
        onError: () => {
            showToast('Failed to delete ICO token', 'error');
        },
        onFinish: () => isProcessing.value = false
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
</script>

<template>
  <AdminLayout
    title="ICO Token Management"
    page-section="Token Sales & ICO"
  >
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            ICO Token Management
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage and monitor all ICO token sales, progress, and performance metrics
          </p>
        </div>
        <div class="mt-4 sm:mt-0">
          <button
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
            @click="createToken"
          >
            <svg
              class="w-4 h-4 mr-2"
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
            Add New Token
          </button>
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
              Total Tokens
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ totalTokensCount }}
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
              Active Sales
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ activeTokensCount }}
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
                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Raised
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ currencySymbol }}{{ formatMoney(totalRaisedCount) }}
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
                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Featured Tokens
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ featuredTokensCount }}
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
                id="search-ico-tokens"
                v-model="searchTerm"
                type="text"
                placeholder="Search by name, symbol, or ID..."
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
                v-model="selectedFeatured"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Featured
                </option>
                <option
                  v-for="featured in featuredFilterOptions"
                  :key="featured.value"
                  :value="featured.value"
                >
                  {{ featured.label }}
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
            v-if="selectedFeatured"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
          >
            Featured: {{ getFeaturedLabel(selectedFeatured) }}
            <button
              class="ml-2 text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200"
              @click="clearFeaturedFilter"
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
                  <span>TOKEN</span>
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
                @click="handleSort('price')"
              >
                <div class="flex items-center space-x-1">
                  <span>PRICE</span>
                  <svg
                    v-if="sortField === 'price'"
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
                PROGRESS
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
                SALE PERIOD
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                ACTIONS
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-if="!isLoading && icoTokens.length === 0">
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
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No ICO tokens found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No ICO tokens match your current filters. Try adjusting the search criteria.
                  </p>
                  <div class="mt-6">
                      <button
                          class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                          @click="createToken"
                      >
                          <svg
                              class="w-4 h-4 mr-2"
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
                          Add New Token
                      </button>
                  </div>
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
                  <span class="text-gray-600 dark:text-gray-400">Loading ICO tokens...</span>
                </div>
              </td>
            </tr>

            <tr
              v-for="token in icoTokens"
              :key="token.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-3">
                  <div class="h-10 w-10 flex-shrink-0">
                    <div
                      v-if="token.logo"
                      class="h-full w-full rounded-full overflow-hidden border-2 border-gray-200 dark:border-slate-600 shadow-sm"
                    >
                      <img
                        :src="getTokenLogo(token.logo)"
                        :alt="`${token.name || 'Token'} logo`"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        @error="handleLogoError"
                      >
                    </div>
                    <div
                      v-else
                      class="h-full w-full rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-md"
                    >
                      {{ getTokenInitials(token.symbol) }}
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center space-x-2">
                      <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                        {{ token.name || 'Unnamed Token' }}
                      </div>
                      <span
                        v-if="token.is_featured"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
                      >
                        <svg
                          class="w-3 h-3 mr-1"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                        >
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                        Featured
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                      {{ token.symbol || 'N/A' }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                  {{ currencySymbol }}{{ formatNumber(parseFloat(token.price || 0)) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  per token
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-1">
                  <div
                    class="bg-gradient-to-r from-blue-500 to-purple-600 h-2.5 rounded-full transition-all duration-300"
                    :style="{ width: Math.min(getProgressPercentage(token), 100) + '%' }"
                  />
                </div>
                <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                  <span>{{ formatNumber(parseFloat(token.tokens_sold || 0)) }} sold</span>
                  <span>{{ getProgressPercentage(token).toFixed(1) }}%</span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  of {{ formatNumber(parseFloat(token.total_supply || 0)) }} total
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div
                  v-if="isAdmin && canUpdateStatus(token)"
                  class="min-w-[120px]"
                >
                  <select
                    :value="token.status"
                    :disabled="updatingToken === token.id"
                    class="w-full text-xs rounded-lg border-gray-300 dark:border-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 py-2 px-3 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-colors duration-200"
                    @change="updateTokenStatus(token.id, $event.target.value)"
                  >
                    <option
                      v-for="status in statusOptions"
                      :key="status.value"
                      :value="status.value"
                    >
                      {{ status.label }}
                    </option>
                  </select>
                </div>
                <span
                  v-else
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border items-center"
                  :class="getStatusClass(token.status)"
                >
                  <span
                    class="mr-1"
                    v-html="getStatusIcon(token.status)"
                  />
                  {{ formatStatus(token.status) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">
                  {{ formatDate(token.sale_start_date) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  to {{ formatDate(token.sale_end_date) }}
                </div>
                <div
                  class="text-xs"
                  :class="getDaysRemainingClass(token)"
                >
                  {{ getDaysRemainingText(token) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <button
                    :disabled="processingToken === token.id"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="token.is_featured ? 'text-yellow-700 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900/30 border-yellow-200 dark:border-yellow-700 hover:bg-yellow-200 dark:hover:bg-yellow-900/50' : 'text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700 hover:bg-yellow-100 dark:hover:bg-yellow-900/30'"
                    @click="toggleFeatured(token)"
                  >
                    <svg
                      v-if="processingToken === token.id"
                      class="animate-spin -ml-1 mr-1 h-3 w-3 text-current"
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
                    <svg
                      v-else
                      class="h-3 w-3 mr-1"
                      :fill="token.is_featured ? 'currentColor' : 'none'"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                      />
                    </svg>
                    {{ processingToken === token.id ? 'Processing...' : (token.is_featured ? 'Featured' : 'Feature') }}
                  </button>

                  <button
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-900/50 focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                    @click="editToken(token)"
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
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                      />
                    </svg>
                    Edit
                  </button>

                <button
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                    @click="openDeleteModal(token)"
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
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                        />
                    </svg>
                    Delete
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
      item-name="ICO tokens"
      @page-change="changePage"
    />

      <div
          v-if="showDeleteModal"
          class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50"
          @click="closeDeleteModal"
      >
          <div
              class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 ease-out"
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
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                          />
                      </svg>
                  </div>
                  <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">
                      Delete ICO Token
                  </h3>
                  <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-4">
                      Are you sure you want to delete this ICO token? This action cannot be undone and will affect all token sales and investor data.
                  </p>
                  <div class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
                      <button
                          :disabled="isProcessing"
                          class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                          @click="confirmDelete"
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
                                  d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                              />
                          </svg>
                          {{ isProcessing ? 'Deleting...' : 'Delete Token' }}
                      </button>
                      <button
                          class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-white hover:bg-gray-50 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-slate-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                          @click="closeDeleteModal"
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
