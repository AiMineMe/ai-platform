<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    gateways: {
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
            totalGateways: 0,
            activeGateways: 0,
            automaticGateways: 0,
            manualGateways: 0
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                typeof value.totalGateways === 'number' &&
                typeof value.activeGateways === 'number' &&
                typeof value.automaticGateways === 'number' &&
                typeof value.manualGateways === 'number';
        }
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'name',
            sort_direction: 'asc'
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
const updatingGateway = ref(null);
const error = ref(null);
const isProcessing = ref(false);
const searchTerm = ref(props.filters?.search || '');
const selectedType = ref(props.filters?.type || '');
const selectedStatus = ref(props.filters?.status || '');
const showDeleteModal = ref(false);
const selectedGateway = ref(null);

const typeFilterOptions = [
    { value: 'automatic', label: 'Automatic' },
    { value: 'manual', label: 'Manual' }
];

const statusFilterOptions = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' }
];

const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'name');
const sortDirection = computed(() => props.filters.sort_direction || 'asc');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedType.value || selectedStatus.value);
});

const totalGatewaysCount = computed(() => props.stats?.total_gateways || 0);
const activeGatewaysCount = computed(() => props.stats?.active_gateways || 0);
const automaticGatewaysCount = computed(() => props.stats?.automatic_gateways || 0);
const manualGatewaysCount = computed(() => props.stats?.manual_gateways || 0);

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

    router.get('/admin/payment-gateways', params, {
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

    router.get('/admin/payment-gateways', {
        sort_field: 'name',
        sort_direction: 'asc'
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
    const option = typeFilterOptions.find(t => t.value === type);
    return option ? option.label : type;
};

const getStatusLabel = (status) => {
    const option = statusFilterOptions.find(s => s.value === status);
    return option ? option.label : status;
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
    return status ? 'Active' : 'Inactive';
};

const formatType = (type) => {
    if (!type) return 'Unknown';
    return type.charAt(0).toUpperCase() + type.slice(1);
};

const getStatusClass = (status) => {
    return status
        ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        : 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700';
};

const getStatusDotClass = (status) => {
    return status ? 'bg-green-500' : 'bg-red-500';
};

const getTypeClass = (type) => {
    const classes = {
        'automatic': 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700',
        'manual': 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700'
    };
    return classes[type] || 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700';
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

    router.get('/admin/payment-gateways', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort gateways. Please try again.';
            showToast('Failed to sort gateways', 'error');
        }
    });
};

const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return;

    isLoading.value = true;
    error.value = null;

    const params = {page};
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedType.value) params.type = selectedType.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/payment-gateways', params, {
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


const updateGatewayStatus = async (gatewayId, newStatus) => {
    const gateway = props.gateways.find(g => g && g.id === gatewayId);
    if (!gateway) {
        showToast('You do not have permission to update this gateway\'s status', 'error');
        return;
    }

    updatingGateway.value = gatewayId;
    error.value = null;
    try {
        router.patch(`/admin/payment-gateways/${gatewayId}/toggle-status`, {
            status: newStatus
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast(`Gateway status updated to ${newStatus ? 'Active' : 'Inactive'}`, 'success');
            },
            onError: (errors) => {
                console.error('Status update error:', errors);
                const errorMessage = errors.message || 'Failed to update gateway status';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                updatingGateway.value = null;
            }
        });
    } catch (error) {
        console.error('Error updating gateway status:', error);
        showToast('Failed to update gateway status', 'error');
        updatingGateway.value = null;
    }
};

const createGateway = () => {
    router.get('/admin/payment-gateways/create');
};

const editGateway = (gateway) => {
    router.get(`/admin/payment-gateways/${gateway.id}/edit`);
};

const openDeleteModal = (gateway) => {
    selectedGateway.value = gateway;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedGateway.value = null;
};

const confirmDelete = () => {
    if (!selectedGateway.value || isProcessing.value) return;

    isProcessing.value = true;

    router.delete(`/admin/payment-gateways/${selectedGateway.value.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast('Gateway deleted successfully', 'success');
        },
        onError: () => {
            showToast('Failed to delete gateway', 'error');
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
        title="Payment Gateways"
        page-section="Payments"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Payment Gateways
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage payment methods and configurations
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        @click="createGateway"
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
                        Add New Gateway
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
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
                            Total Gateways
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ totalGatewaysCount }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
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
                            Active Gateways
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ activeGatewaysCount }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
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
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Automatic
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ automaticGatewaysCount }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
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
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Manual
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ manualGatewaysCount }}
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
                                id="search-gateways"
                                v-model="searchTerm"
                                type="text"
                                placeholder="Search by name or currency..."
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
                                    v-for="type in typeFilterOptions"
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
                            @click="handleSort('name')"
                        >
                            <div class="flex items-center space-x-1">
                                <span>GATEWAY</span>
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
                            LIMITS
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            RATE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            CHARGES
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            ACTIONS
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="!isLoading && gateways.length === 0">
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
                                    No payment gateways found
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    No payment gateways match your current filters. Try adjusting the search criteria.
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
                                <span class="text-gray-600 dark:text-gray-400">Loading payment gateways...</span>
                            </div>
                        </td>
                    </tr>

                    <tr
                        v-for="gateway in gateways"
                        :key="gateway.id"
                        class="hover:bg-gray-50 dark:hover:bg-slate-700"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div
                                        class="h-full w-full rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                        {{ getInitials(gateway.name) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ gateway.name || 'N/A' }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        {{ gateway.currency || 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                <span
                    class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                    :class="getStatusClass(gateway.status)"
                >
                  <span
                      class="w-2 h-2 rounded-full mr-1"
                      :class="getStatusDotClass(gateway.status)"
                  />
                  {{ formatStatus(gateway.status) }}
                </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ formatNumber(gateway.min_amount || 0) }} {{ gateway.currency || 'N/A' }} -
                                    {{ formatNumber(gateway.max_amount || 0) }} {{ gateway.currency || 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ gateway.currency || 'N/A' }}
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    1 {{ currencySymbol }} = {{ formatNumber(gateway.rate || 0) }}
                                    {{ gateway.currency || 'N/A' }}
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                <div
                                    v-if="gateway.fixed_charge && parseFloat(gateway.fixed_charge) > 0"
                                    class="text-xs"
                                >
                                    Fixed: {{ formatMoney(gateway.fixed_charge) }} {{ gateway.currency || 'N/A' }}
                                </div>
                                <div
                                    v-if="gateway.percent_charge && parseFloat(gateway.percent_charge) > 0"
                                    class="text-xs"
                                >
                                    Percent: {{ gateway.percent_charge }}%
                                </div>
                                <div
                                    v-if="(!gateway.fixed_charge || parseFloat(gateway.fixed_charge) === 0) && (!gateway.percent_charge || parseFloat(gateway.percent_charge) === 0)"
                                    class="text-xs text-green-600 dark:text-green-400"
                                >
                                    Free
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                <span
                    class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                    :class="getTypeClass(gateway.type)"
                >
                  {{ formatType(gateway.type) }}
                </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-900/50 focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                                    @click="editGateway(gateway)"
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
                                    @click="openDeleteModal(gateway)"
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
            item-name="gateways"
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
                    <div
                        class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
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
                        Delete Payment Gateway
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-4">
                        Are you sure you want to delete this payment gateway? This action cannot be undone.
                    </p>

                    <div
                        v-if="selectedGateway"
                        class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 mb-4"
                    >
                        <div class="text-sm space-y-2">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ selectedGateway.name }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Currency: {{ selectedGateway.currency }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Type: {{ formatType(selectedGateway.type) }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Status: {{ formatStatus(selectedGateway.status) }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            {{ isProcessing ? 'Deleting...' : 'Delete Gateway' }}
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
