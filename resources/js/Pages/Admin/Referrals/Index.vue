<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";
import { useSettings } from "@/composables/useSettings.js";

const props = defineProps({
    referrals: {
        type: Object,
        required: true,
        default: () => ({ data: [] })
    },
    statuses: {
        type: Array,
        default: () => ['pending', 'paid']
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({})
    }
});

const { showToast } = useToast();
const { currencySymbol } = useSettings();
const isLoading = ref(false);
const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const selectedStartDate = ref(props.filters?.start_date || '');
const selectedEndDate = ref(props.filters?.end_date || '');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedStatus.value || selectedStartDate.value || selectedEndDate.value);
});


const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const applyFilters = () => {
    if (isLoading.value) return;

    isLoading.value = true;
    const params = { page: 1 };
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedStartDate.value) params.start_date = selectedStartDate.value;
    if (selectedEndDate.value) params.end_date = selectedEndDate.value;

    router.get('/admin/referrals', params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { isLoading.value = false; }
    });
};

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';
    selectedStartDate.value = '';
    selectedEndDate.value = '';
    applyFilters();
};

const changePage = (page) => {
    if (isLoading.value) return;

    isLoading.value = true;
    const params = { page };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedStartDate.value) params.start_date = selectedStartDate.value;
    if (selectedEndDate.value) params.end_date = selectedEndDate.value;

    router.get('/admin/referrals', params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { isLoading.value = false; }
    });
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('en-US').format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch {
        return 'Invalid Date';
    }
};

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2);
};

const getStatusClass = (status) => {
    return status === 'paid'
        ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300';
};

onMounted(() => {
    const flash = usePage().props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
});
</script>

<template>
    <AdminLayout title="Referral Management" page-section="Finance & Referrals">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Referral Management</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Monitor and manage user referrals and commissions</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Referrals</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.total_referrals) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Paid Commissions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ currencySymbol }}{{ formatNumber(stats.total_commission_paid) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Commissions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ currencySymbol }}{{ formatNumber(stats.total_commission_pending) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Referrers</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.total_users_with_referrals) }}</p>
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
                                v-model="searchTerm"
                                type="text"
                                placeholder="Search by referrer or referred user..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                                @input="debouncedSearch"
                            >
                            <button
                                v-if="searchTerm"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                @click="searchTerm = ''; applyFilters();"
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
                                <option value="">All Statuses</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                </option>
                            </select>
                        </div>

                        <div class="min-w-[140px]">
                            <input
                                v-model="selectedStartDate"
                                type="date"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="applyFilters"
                            >
                        </div>

                        <div class="min-w-[140px]">
                            <input
                                v-model="selectedEndDate"
                                type="date"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="applyFilters"
                            >
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
                            @click="searchTerm = ''; applyFilters();"
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
                        Status: {{ selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1) }}
                        <button
                            class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200"
                            @click="selectedStatus = ''; applyFilters();"
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
                        v-if="selectedStartDate || selectedEndDate"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300"
                    >
                        Date: {{ selectedStartDate || 'Start' }} - {{ selectedEndDate || 'End' }}
                        <button
                            class="ml-2 text-orange-600 hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-200"
                            @click="selectedStartDate = ''; selectedEndDate = ''; applyFilters();"
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Referrer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Referred User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Commission</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="isLoading">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex items-center justify-center">
                                <svg class="animate-spin h-5 w-5 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">Loading referrals...</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-else-if="!referrals.data || referrals.data.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857"/>
                            </svg>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">No referrals found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No referral data available.</p>
                        </td>
                    </tr>

                    <tr v-for="referral in referrals.data" :key="referral.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm">
                                    {{ getInitials(referral.referrer_name) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ referral.referrer_name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ referral.referrer_email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-semibold text-sm">
                                    {{ getInitials(referral.referred_name) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ referral.referred_name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ referral.referred_email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-green-600 dark:text-green-400">
                                {{ currencySymbol }}{{ referral.commission }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border"
                                :class="getStatusClass(referral.status)"
                            >
                                {{ referral.status.charAt(0).toUpperCase() + referral.status.slice(1) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600 dark:text-gray-300">{{ formatDate(referral.created_at) }}</div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination
            v-if="referrals.total > 0"
            :current-page="referrals.current_page"
            :last-page="referrals.last_page"
            :total="referrals.total"
            :per-page="referrals.per_page"
            item-name="referrals"
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
</style>
