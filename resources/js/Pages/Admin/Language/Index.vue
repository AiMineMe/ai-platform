<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    languages: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    filters: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    },
    stats: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const { showToast } = useToast();

const isLoading = ref(false);
const showModal = ref(false);
const editingLanguage = ref(null);
const showDeleteModal = ref(false);
const selectedLanguage = ref(null);
const isProcessing = ref(false);
const error = ref(null);
const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

const form = useForm({
    code: '',
    name: '',
    native_name: '',
    flag: '',
    is_active: true,
    is_default: false,
});

const isEditing = computed(() => !!editingLanguage.value);
const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedStatus.value);
});

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const applyFilters = () => {
    if (isLoading.value) return;
    isLoading.value = true;
    const params = {};

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/admin/languages', params, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { isLoading.value = false; }
    });
};

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const openCreateModal = () => {
    editingLanguage.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const editLanguage = (language) => {
    editingLanguage.value = language;
    form.code = language.code;
    form.name = language.name;
    form.native_name = language.native_name;
    form.flag = language.flag;
    form.is_active = language.is_active;
    form.is_default = language.is_default;
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingLanguage.value = null;
    form.reset();
    form.clearErrors();
};

const saveLanguage = () => {
    if (editingLanguage.value) {
        form.put('/admin/languages/'+ editingLanguage.value.id, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeModal();
                showToast('Language updated successfully', 'success');
            },
            onError: (errors) => {
                console.error('Update language error:', errors);
                const errorMessage = errors.message || 'Failed to update language';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
        });
    } else {
        form.post('/admin/languages', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeModal();
                showToast('Language created successfully', 'success');
            },
            onError: (errors) => {
                console.error('Create language error:', errors);
                const errorMessage = errors.message || 'Failed to create language';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
        });
    }
};

const openDeleteModal = (language) => {
    selectedLanguage.value = language;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedLanguage.value = null;
};

const confirmDelete = () => {
    if (!selectedLanguage.value || isProcessing.value) return;

    isProcessing.value = true;
    error.value = null;

    router.delete('/admin/languages/' + selectedLanguage.value.id, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast('Language deleted successfully', 'success');
        },
        onError: (errors) => {
            console.error('Delete language error:', errors);
            const errorMessage = errors.message || 'Failed to delete language';
            error.value = errorMessage;
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};

const setDefault = (language) => {
    isLoading.value = true;
    error.value = null;

    router.put('/admin/languages/'+ language.id +'/set-default', {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast('Default language set successfully', 'success');
        },
        onError: (errors) => {
            console.error('Set default language error:', errors);
            const errorMessage = errors.message || 'Failed to set default language';
            error.value = errorMessage;
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('en-US').format(value || 0);
};

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2);
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
    <AdminLayout title="Language Management" page-section="Content & Localization">
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Language Management</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage languages and localization for your application
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        @click="openCreateModal"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Language
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12l-7 7-7-7z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Languages</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.total_languages || languages.length) }}</p>
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
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Languages</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(languages.filter(l => l.is_active).length) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Default Language</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ languages.find(l => l.is_default)?.name || 'None' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Translation Keys</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.total_keys || 0) }}</p>
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
                                placeholder="Search languages by name or code..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                                @input="debouncedSearch"
                            >
                            <button
                                v-if="searchTerm"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                @click="searchTerm = ''; applyFilters();"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="default">Default</option>
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

                    <span
                        v-if="searchTerm"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                    >
                        Search: "{{ searchTerm }}"
                        <button
                            class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
                            @click="searchTerm = ''; applyFilters();"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Languages</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Language</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="isLoading">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex items-center justify-center">
                                <svg class="animate-spin h-5 w-5 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">Loading languages...</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-else-if="languages.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 5h12l-7 7-7-7z"/>
                            </svg>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">No languages found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first language.</p>
                            <div class="mt-6">
                                <button
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                                    @click="openCreateModal"
                                >
                                    Add Language
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-for="language in languages" :key="language.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm">
                                    {{ language.flag || getInitials(language.name) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ language.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ language.native_name }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ language.code }}</span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                    <span
                                        v-if="language.is_default"
                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
                                    >
                                        Default
                                    </span>
                                <span
                                    :class="language.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'"
                                    class="px-2 py-1 text-xs font-semibold rounded-full"
                                >
                                        {{ language.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button
                                    @click="editLanguage(language)"
                                    class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                >
                                    Edit
                                </button>
                                <Link
                                    :href="`/admin/languages/${language.id}/translations`"
                                    class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50"
                                >
                                    Translations
                                </Link>
                                <button
                                    v-if="!language.is_default"
                                    @click="setDefault(language)"
                                    class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:hover:bg-yellow-900/50"
                                >
                                    Set Default
                                </button>
                                <button
                                    v-if="!language.is_default"
                                    @click="openDeleteModal(language)"
                                    class="text-xs px-2 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
            @click.self="closeModal"
        >
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-md shadow-lg rounded-md bg-white dark:bg-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ isEditing ? 'Edit Language' : 'Add Language' }}
                    </h3>
                    <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="closeModal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveLanguage" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Language Code *</label>
                        <input
                            v-model="form.code"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                            placeholder="e.g., en, ar, fr"
                            required
                        />
                        <div v-if="form.errors.code" class="text-red-600 dark:text-red-400 text-sm mt-1">
                            {{ form.errors.code }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Language Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                            placeholder="e.g., English, Arabic, French"
                            required
                        />
                        <div v-if="form.errors.name" class="text-red-600 dark:text-red-400 text-sm mt-1">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Native Name *</label>
                        <input
                            v-model="form.native_name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                            placeholder="e.g., English, العربية, Français"
                            required
                        />
                        <div v-if="form.errors.native_name" class="text-red-600 dark:text-red-400 text-sm mt-1">
                            {{ form.errors.native_name }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flag *</label>
                        <input
                            v-model="form.flag"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                            placeholder="e.g"
                            required
                        />
                        <div v-if="form.errors.flag" class="text-red-600 dark:text-red-400 text-sm mt-1">
                            {{ form.errors.flag }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="flex items-center">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Set as Default</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-lg flex items-center"
                        >
                            <svg
                                v-if="form.processing"
                                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            {{ form.processing ? 'Saving...' : 'Save Language' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
            @click.self="closeDeleteModal"
        >
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-md shadow-lg rounded-md bg-white dark:bg-slate-800">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white text-center mb-2">Delete Language</h3>
                <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-6">
                    Are you sure you want to delete <strong>{{ selectedLanguage?.name }}</strong>? This action cannot be undone.
                </p>
                <div class="flex justify-end gap-3">
                    <button
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>
                    <button
                        :disabled="isProcessing"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-400 rounded-lg flex items-center"
                        @click="confirmDelete"
                    >
                        <svg
                            v-if="isProcessing"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        {{ isProcessing ? 'Deleting...' : 'Delete Language' }}
                    </button>
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
</style>
