<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    subscribers: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            current_page: 1,
            per_page: 20,
            last_page: 1,
            total: 0,
            from: 0,
            to: 0
        })
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            unsubscribed: 0,
            today: 0
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
const isProcessing = ref(false);
const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

const showDeleteModal = ref(false);
const showSendModal = ref(false);
const selectedSubscriber = ref(null);
const sendForm = ref({
    subject: '',
    content: '',
    recipient_type: 'active'
});

const statusFilterOptions = [
    { value: 'active', label: 'Active' },
    { value: 'unsubscribed', label: 'Unsubscribed' }
];

const recipientOptions = [
    { value: 'all', label: 'All Subscribers' },
    { value: 'active', label: 'Active Subscribers Only' }
];

const currentPage = computed(() => props.subscribers.current_page || 1);
const lastPage = computed(() => props.subscribers.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');
const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedStatus.value);
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
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/admin/newsletter', params, {
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

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';

    isLoading.value = true;
    error.value = null;

    router.get('/admin/newsletter', {
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

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const getInitials = (email) => {
    if (!email || typeof email !== 'string') return '?';
    return email.split('@')[0].charAt(0).toUpperCase();
};

const formatStatus = (status) => {
    return status === 'active' ? 'Active' : 'Unsubscribed';
};

const getStatusClass = (status) => {
    return status === 'active'
        ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        : 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700';
};

const getStatusDotClass = (status) => {
    return status === 'active' ? 'bg-green-500' : 'bg-red-500';
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

    router.get('/admin/newsletter', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort subscribers. Please try again.';
            showToast('Failed to sort subscribers', 'error');
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
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/newsletter', params, {
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

const openDeleteModal = (subscriber) => {
    selectedSubscriber.value = subscriber;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedSubscriber.value = null;
};

const openSendModal = () => {
    sendForm.value = {
        subject: '',
        content: '',
        recipient_type: 'active'
    };
    showSendModal.value = true;
};

const closeSendModal = () => {
    showSendModal.value = false;
    sendForm.value = {
        subject: '',
        content: '',
        recipient_type: 'active'
    };
};

const confirmDelete = () => {
    if (!selectedSubscriber.value || isProcessing.value) return;

    isProcessing.value = true;

    router.delete(`/admin/newsletter/subscribers/${selectedSubscriber.value.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast('Subscriber deleted successfully', 'success');
        },
        onError: () => {
            showToast('Failed to delete subscriber', 'error');
        },
        onFinish: () => isProcessing.value = false
    });
};

const sendNewsletter = async () => {
    if (!sendForm.value.subject || !sendForm.value.content || isProcessing.value) return;

    isProcessing.value = true;

    try {
        const response = await fetch('/admin/newsletter/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify(sendForm.value)
        });

        const data = await response.json();

        if (data.success) {
            closeSendModal();
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Send newsletter error:', error);
        showToast('Failed to send newsletter', 'error');
    } finally {
        isProcessing.value = false;
    }
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
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <AdminLayout
        title="Newsletter Subscribers"
        page-section="Marketing"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Newsletter Subscribers
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage your newsletter subscribers and send campaigns
                    </p>
                </div>
                <div class="mt-4 sm:mt-0 flex gap-3">
                    <button
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        @click="openSendModal"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        Send Newsletter
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Subscribers</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.active }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unsubscribed</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.unsubscribed }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today</p>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.today }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 mb-6">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex-1 max-w-md">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                id="search-subscribers"
                                v-model="searchTerm"
                                type="text"
                                placeholder="Search by email..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                                @input="debouncedSearch"
                            >
                            <button
                                v-if="searchTerm"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                @click="clearSearch"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                                <option value="">All Status</option>
                                <option v-for="status in statusFilterOptions" :key="status.value" :value="status.value">
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

                <div v-if="hasActiveFilters" class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active filters:</span>

                    <span v-if="searchTerm" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                        Search: "{{ searchTerm }}"
                        <button class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200" @click="clearSearch">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>

                    <span v-if="selectedStatus" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                        Status: {{ getStatusLabel(selectedStatus) }}
                        <button class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200" @click="clearStatusFilter">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" @click="handleSort('email')">
                            <div class="flex items-center space-x-1">
                                <span>EMAIL</span>
                                <svg v-if="sortField === 'email'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" @click="handleSort('status')">
                            <div class="flex items-center space-x-1">
                                <span>STATUS</span>
                                <svg v-if="sortField === 'status'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" @click="handleSort('created_at')">
                            <div class="flex items-center space-x-1">
                                <span>SUBSCRIBED</span>
                                <svg v-if="sortField === 'created_at'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ACTIONS</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="!isLoading && subscribers.data.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No subscribers found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No subscribers match your current filters. Try adjusting the search criteria.</p>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="isLoading">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">Loading subscribers...</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-for="subscriber in subscribers.data" :key="subscriber.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div class="h-full w-full rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                        {{ getInitials(subscriber.email) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ subscriber.email }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        ID: {{ subscriber.id }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border" :class="getStatusClass(subscriber.status)">
                                <span class="w-2 h-2 rounded-full mr-1" :class="getStatusDotClass(subscriber.status)" />
                                {{ formatStatus(subscriber.status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ formatDate(subscriber.subscribed_at || subscriber.created_at) }}
                                </div>
                                <div v-if="subscriber.unsubscribed_at" class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                                    Unsubscribed: {{ formatDate(subscriber.unsubscribed_at) }}
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                                    @click="openDeleteModal(subscriber)"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
            v-if="subscribers.total > 0"
            :current-page="currentPage"
            :last-page="lastPage"
            :total="subscribers.total"
            :per-page="20"
            item-name="subscribers"
            @page-change="changePage"
        />

        <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeDeleteModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 ease-out" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">Delete Subscriber</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-4">Are you sure you want to delete this subscriber? This action cannot be undone.</p>

                    <div v-if="selectedSubscriber" class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 mb-4">
                        <div class="text-sm space-y-2">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ selectedSubscriber.email }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Status: {{ formatStatus(selectedSubscriber.status) }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Subscribed: {{ formatDate(selectedSubscriber.subscribed_at || selectedSubscriber.created_at) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
                        <button
                            :disabled="isProcessing"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            @click="confirmDelete"
                        >
                            <svg v-if="isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {{ isProcessing ? 'Deleting...' : 'Delete Subscriber' }}
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

        <div v-if="showSendModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeSendModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 transform transition-all duration-300 ease-out max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Send Newsletter</h3>
                        <button @click="closeSendModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="sendNewsletter">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recipients</label>
                                <select v-model="sendForm.recipient_type" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" required>
                                    <option v-for="option in recipientOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject *</label>
                                <input v-model="sendForm.subject" type="text" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" placeholder="Newsletter subject..." required />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content *</label>
                                <textarea v-model="sendForm.content" rows="8" class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" placeholder="Newsletter content..." required></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">HTML content is supported</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeSendModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200">
                                Cancel
                            </button>
                            <button type="submit" :disabled="isProcessing || !sendForm.subject || !sendForm.content" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-lg transition-colors duration-200 flex items-center">
                                <svg v-if="isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ isProcessing ? 'Sending...' : 'Send Newsletter' }}
                            </button>
                        </div>
                    </form>
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
