<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";

const props = defineProps({
    language: {
        type: Object,
        required: true
    },
    translations: {
        type: Object,
        required: true
    },
    stats: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

const { showToast } = useToast();
const isLoading = ref(false);
const searchTerm = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || 'all');
const selectedComponent = ref(props.filters.component || 'hero');
const showAddModal = ref(false);
const showDeleteModal = ref(false);
const deleteTarget = ref(null);
const editingTranslation = ref(null);

const newTranslationForm = ref({
    key: '',
    value: ''
});

// Computed
const statusOptions = [
    { value: 'all', label: 'All Translations' },
    { value: 'translated', label: 'Translated' },
    { value: 'untranslated', label: 'Missing Translation' }
];

const componentOptions = [
    { value: 'hero', label: 'Hero Component' },
    { value: 'crypto_prices', label: 'Crypto Prices Component' },
    { value: 'services', label: 'Services Component' },
    { value: 'sidebar', label: 'Sidebar Component' },
    { value: 'advanced_features', label: 'Advanced Features Component' },
    { value: 'blog', label: 'Blog Component' },
    { value: 'blog_detail', label: 'Blog Detail Page' },
    { value: 'footer', label: 'Footer Component' },
    { value: 'gaming_mining', label: 'Gaming Mining Component' },
    { value: 'navigation', label: 'Navigation Component' },
    { value: 'networks', label: 'Networks Component' },
    { value: 'contact', label: 'Contact Page' },
    { value: 'cookies', label: 'Cookies Page' },
    { value: 'privacy', label: 'Privacy Page' },
    { value: 'terms', label: 'Terms Page' },
    { value: 'auth_login', label: 'Auth Login Page' },
    { value: 'auth_2fa', label: 'Auth 2FA Page' },
    { value: 'user_sidebar', label: 'Sidebar Component' },
    { value: 'user_topbar', label: 'Topbar Component' },
    { value: 'user_dashboard', label: 'Dashboard Page' },
    { value: 'user_mining', label: 'Mining' },
    { value: 'user_market', label: 'Market Data' },
    { value: 'user_trading', label: 'Trading' },
    { value: 'trading_history', label: 'Trading History' },
    { value: 'ico_tokens', label: 'ICO Tokens' },
    { value: 'ico_purchase_history', label: 'ICO Purchase History' },
    { value: 'ico_portfolio', label: 'ICO Portfolio' },
    { value: 'wallet_overview', label: 'Wallet Overview' },
    { value: 'deposits', label: 'Deposits' },
    { value: 'withdrawals', label: 'Withdrawals' },
    { value: 'transactions', label: 'Transactions' },
    { value: 'commissions', label: 'Commissions' },
    { value: 'referral_dashboard', label: 'Referral Dashboard' },
    { value: 'login_history', label: 'Login History' },
    { value: 'password_change', label: 'Password Change' },
    { value: 'two_factor_auth', label: 'Two Factor Authentication' },
    { value: 'kyc_verification', label: 'KYC Verification' },
    { value: 'profile_settings', label: 'Profile Settings' },
    { value: 'create_ticket', label: 'Create Support Ticket' },
    { value: 'ticket_list', label: 'Support Tickets List' },
    { value: 'ticket_details', label: 'Ticket Details' },
    { value: 'dynamic', label: 'Dynamic' },
];

const formatKey = (key) => {
    return key.split('.').pop().replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const getStatusColor = (translation) => {
    if (translation.value && translation.value.trim() !== '') {
        return 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-300';
};

const getStatusText = (translation) => {
    return (translation.value && translation.value.trim() !== '') ? 'Translated' : 'Missing';
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const applyFilters = () => {
    if (isLoading.value) return;
    isLoading.value = true;
    const params = {
        page: 1,
        search: searchTerm.value.trim() || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        component: selectedComponent.value !== 'hero' ? selectedComponent.value : undefined
    };

    router.get(`/admin/languages/${props.language.id}/translations`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: () => {
            isLoading.value = false;
            showToast('Failed to apply filters', 'error');
        }
    });
};

const clearFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = 'all';
    selectedComponent.value = 'hero';

    router.get(`/admin/languages/${props.language.id}/translations`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            showToast('Filters cleared', 'success');
        }
    });
};

const changePage = (page) => {
    if (isLoading.value) return;

    isLoading.value = true;

    const params = {
        page,
        search: searchTerm.value.trim() || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        component: selectedComponent.value !== 'hero' ? selectedComponent.value : undefined
    };

    router.get(`/admin/languages/${props.language.id}/translations`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const updateTranslation = (translation, newValue) => {
    router.put(`/admin/languages/${props.language.id}/translations/${translation.id}`, {
        value: newValue
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingTranslation.value = null;
            showToast('Translation updated successfully', 'success');
        },
        onError: () => {
            showToast('Failed to update translation', 'error');
        }
    });
};

const openAddModal = () => {
    showAddModal.value = true;
    newTranslationForm.value = { key: '', value: '' };
};

const closeAddModal = () => {
    showAddModal.value = false;
    newTranslationForm.value = { key: '', value: '' };
};

const addTranslation = () => {
    if (!newTranslationForm.value.key.trim()) {
        showToast('Please enter a key', 'error');
        return;
    }

    router.post(`/admin/languages/${props.language.id}/translations`, {
        key: newTranslationForm.value.key.trim(),
        value: newTranslationForm.value.value.trim()
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeAddModal();
            showToast('Translation added successfully', 'success');
        },
        onError: (errors) => {
            const message = errors.message || 'Failed to add translation';
            showToast(message, 'error');
        }
    });
};

const openDeleteModal = (translation) => {
    deleteTarget.value = translation;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteTarget.value = null;
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;

    router.delete(`/admin/languages/${props.language.id}/translations/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast('Translation deleted successfully', 'success');
        },
        onError: () => {
            closeDeleteModal();
            showToast('Failed to delete translation', 'error');
        }
    });
};

const goBack = () => {
    router.get('/admin/languages');
};

onMounted(() => {
    const page = usePage();
    const flash = page.props.flash;

    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
});
</script>

<template>
    <AdminLayout
        :title="`Language Management - ${language.name} Translations`"
        page-section="Content Management"
    >
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button
                        @click="goBack"
                        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl transition-all duration-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ language.flag }}</span>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ language.name }} Translations
                                </h1>
                                <p class="text-gray-600 dark:text-gray-400">
                                    {{ language.native_name }} • {{ language.code.toUpperCase() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ stats.progress }}% Complete
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ stats.translated }} of {{ stats.total }} translations
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Translated</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.translated }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Missing</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.untranslated }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Progress</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.progress }}%</p>
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
                                v-model="searchTerm"
                                type="text"
                                placeholder="Search translations or keys..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                                @input="debouncedSearch"
                            >
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="min-w-[220px]">
                            <select
                                v-model="selectedComponent"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="applyFilters"
                            >
                                <option v-for="option in componentOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="min-w-[160px]">
                            <select
                                v-model="selectedStatus"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="applyFilters"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <button
                            @click="openAddModal"
                            class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors duration-200 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Translation
                        </button>

                        <button
                            v-if="searchTerm || selectedStatus !== 'all' || selectedComponent !== 'hero'"
                            @click="clearFilters"
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors duration-200"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Translation Key
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ language.name }} Translation
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="isLoading">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">Loading translations...</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-else-if="!translations.data || translations.data.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    No translations found
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    No translations match your current filters.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr v-else v-for="translation in translations.data" :key="translation.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatKey(translation.key) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                    {{ translation.key }}
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <div v-if="editingTranslation === translation.id">
                                    <textarea
                                        :value="translation.value"
                                        @blur="updateTranslation(translation, $event.target.value)"
                                        @keydown.enter.prevent="updateTranslation(translation, $event.target.value)"
                                        @keydown.escape="editingTranslation = null"
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                        rows="2"
                                        autofocus
                                    />
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Press Enter to save, Escape to cancel
                                </div>
                            </div>
                            <div v-else>
                                <div
                                    @click="editingTranslation = translation.id"
                                    class="text-sm text-gray-900 dark:text-white cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600 p-2 rounded transition-colors duration-200"
                                    :class="!translation.value ? 'italic text-gray-500 dark:text-gray-400' : ''"
                                >
                                    {{ translation.value || 'Click to add translation...' }}
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getStatusColor(translation)">
                                {{ getStatusText(translation) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center gap-2">
                                <button
                                    @click="editingTranslation = translation.id"
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    @click="openDeleteModal(translation)"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination
            v-if="translations.data && translations.data.length > 0"
            :current-page="translations.current_page"
            :last-page="translations.last_page"
            :total="translations.total"
            :per-page="translations.per_page"
            item-name="translations"
            @page-change="changePage"
        />

        <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeAddModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 ease-out" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Add Translation</h3>
                        </div>
                        <button @click="closeAddModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="addTranslation">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Translation Key *
                                </label>
                                <input
                                    v-model="newTranslationForm.key"
                                    type="text"
                                    placeholder="e.g., welcome_message"
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    required
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ language.name }} Translation
                                </label>
                                <textarea
                                    v-model="newTranslationForm.value"
                                    placeholder="Enter translation value..."
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    rows="3"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeAddModal"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors duration-200"
                            >
                                Add Translation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeDeleteModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 ease-out" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Delete Translation</h3>
                        </div>
                        <button @click="closeDeleteModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mb-6">
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Are you sure you want to delete this translation? This action cannot be undone.
                        </p>
                        <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white mb-1">
                                {{ formatKey(deleteTarget?.key) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono mb-2">
                                {{ deleteTarget?.key }}
                            </div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                "{{ deleteTarget?.value || 'No translation' }}"
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            @click="closeDeleteModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmDelete"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200"
                        >
                            Delete Translation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
