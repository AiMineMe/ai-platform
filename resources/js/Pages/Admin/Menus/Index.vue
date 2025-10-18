<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    menus: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    availableComponents: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    }
});

const { showToast } = useToast();
const isLoading = ref(false);
const showModal = ref(false);
const editingMenu = ref(null);
const showDeleteModal = ref(false);
const selectedMenu = ref(null);
const isProcessing = ref(false);
const error = ref(null);

const form = useForm({
    identifier: '',
    menu_name: '',
    path: '',
    components: [],
    component_props: {},
    sort_order: 0,
    is_active: true,
});

const isEditing = computed(() => !!editingMenu.value);
const openCreateModal = () => {
    editingMenu.value = null;
    form.reset();
    form.is_active = true;
    form.sort_order = 0;
    form.components = [];
    form.clearErrors();
    showModal.value = true;
};

const editMenu = (menu) => {
    editingMenu.value = menu;
    form.identifier = menu.identifier;
    form.menu_name = menu.menu_name;
    form.path = menu.path;
    form.components = menu.components || [];
    form.component_props = menu.component_props || {};
    form.sort_order = menu.sort_order || 0;
    form.is_active = menu.is_active ?? true;
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingMenu.value = null;
    form.reset();
    form.clearErrors();
};

const onIdentifierChange = () => {
    if (!isEditing.value && form.identifier) {
        form.path = '/' + form.identifier.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }
};

const saveMenu = () => {
    if (editingMenu.value) {
        form.put('/admin/menus/' + editingMenu.value.id, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeModal();
                showToast('Menu item updated successfully', 'success');
            },
            onError: (errors) => {
                console.error('Update menu error:', errors);
                const errorMessage = errors.message || 'Failed to update menu item';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
        });
    } else {
        form.post('/admin/menus', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeModal();
                showToast('Menu item created successfully', 'success');
            },
            onError: (errors) => {
                console.error('Create menu error:', errors);
                const errorMessage = errors.message || 'Failed to create menu item';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
        });
    }
};

const toggleMenuStatus = (menu) => {
    const toggleForm = useForm({
        is_active: !menu.is_active
    });

    toggleForm.patch('/admin/menus/' + menu.id + '/toggle-status', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showToast(`Menu item ${!menu.is_active ? 'activated' : 'deactivated'} successfully`, 'success');
        },
        onError: (errors) => {
            console.error('Toggle menu status error:', errors);
            const errorMessage = errors.message || 'Failed to update menu status';
            showToast(errorMessage, 'error');
        },
    });
};

const openDeleteModal = (menu) => {
    selectedMenu.value = menu;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedMenu.value = null;
};

const confirmDelete = () => {
    if (!selectedMenu.value || isProcessing.value) return;

    isProcessing.value = true;
    error.value = null;

    try {
        router.delete('/admin/menus/' + selectedMenu.value.id, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
                showToast('Menu item deleted successfully', 'success');
            },
            onError: (errors) => {
                console.error('Delete menu error:', errors);
                const errorMessage = errors.message || 'Failed to delete menu item';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                isProcessing.value = false;
            }
        });
    } catch (error) {
        console.error('Error deleting menu:', error);
        showToast('Failed to delete menu item', 'error');
        isProcessing.value = false;
    }
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && showModal.value) {
        closeModal();
        return;
    }
    if (event.key === 'Escape' && showDeleteModal.value) {
        closeDeleteModal();
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
        title="Manage Navigation Menus"
        page-section="Navigation Management"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Navigation Menus
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage your website navigation menu items with dynamic components
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        @click="openCreateModal"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Menu Item
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Menu Name
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Path
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Components
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Order
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
                    <tr v-if="!isLoading && menus.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    No menu items found
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Get started by adding your first menu item with dynamic components.
                                </p>
                                <div class="mt-6">
                                    <button
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-lime-500 hover:bg-lime-600"
                                        @click="openCreateModal"
                                    >
                                        Add Menu Item
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr
                        v-for="menu in menus"
                        :key="menu.id"
                        class="hover:bg-gray-50 dark:hover:bg-slate-700"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ menu.menu_name }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ menu.identifier }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-mono text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-2 py-1 rounded">
                                {{ menu.path }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div v-if="menu.components && menu.components.length > 0" class="flex flex-wrap gap-1">
                                <span
                                    v-for="component in menu.components"
                                    :key="component"
                                    class="text-xs font-mono text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/30 px-2 py-1 rounded"
                                >
                                    {{ component }}
                                </span>
                            </div>
                            <span v-else class="text-sm text-gray-400">No components</span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ menu.sort_order || 0 }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <button
                                @click="toggleMenuStatus(menu)"
                                class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium transition-colors duration-200"
                                :class="menu.is_active
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/50'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-900/50'"
                            >
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <circle v-if="menu.is_active" cx="10" cy="10" r="8" class="text-green-400" />
                                    <circle v-else cx="10" cy="10" r="8" class="text-gray-400" />
                                </svg>
                                {{ menu.is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-2">
                                <button
                                    @click="editMenu(menu)"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>

                                <button
                                    @click="openDeleteModal(menu)"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 focus:ring-2 focus:ring-red-500 transition-colors duration-200"
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

        <div
            v-if="showModal"
            class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50"
            @click="closeModal"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 transform transition-all duration-300 ease-out max-h-[90vh] overflow-y-auto"
                @click.stop
            >
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ isEditing ? 'Edit Menu Item' : 'Create Menu Item' }}
                        </h3>
                        <button
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="saveMenu">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 space-y-0">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Menu Identifier *
                                </label>
                                <input
                                    v-model="form.identifier"
                                    @input="onIdentifierChange"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    placeholder="e.g., services, about-us"
                                    required
                                />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Unique identifier for this menu item (letters, numbers, hyphens only)
                                </p>
                                <div v-if="form.errors.identifier" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                    {{ form.errors.identifier }}
                                </div>
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Menu Name *
                                </label>
                                <input
                                    v-model="form.menu_name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    placeholder="e.g., Services, Live Prices"
                                    required
                                />
                                <div v-if="form.errors.menu_name" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                    {{ form.errors.menu_name }}
                                </div>
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    URL Path *
                                </label>
                                <input
                                    v-model="form.path"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    placeholder="e.g., /services, /about"
                                    required
                                />
                                <div v-if="form.errors.path" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                    {{ form.errors.path }}
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                    Select Components
                                </label>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <label
                                        v-for="component in availableComponents"
                                        :key="component.value"
                                        class="relative flex items-center cursor-pointer"
                                    >
                                        <input
                                            v-model="form.components"
                                            type="checkbox"
                                            :value="component.value"
                                            class="sr-only"
                                        />
                                        <div
                                            class="w-full p-3 rounded-lg border-2 transition-all duration-200 text-center"
                                            :class="form.components.includes(component.value)
                                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                                : 'border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:border-blue-300 dark:hover:border-blue-400'"
                                        >
                                            <div class="text-sm font-medium">{{ component.label }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ component.value }}</div>
                                        </div>
                                        <div
                                            v-if="form.components.includes(component.value)"
                                            class="absolute top-2 right-2 w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center"
                                        >
                                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    Select one or more components to display on this page
                                </p>
                                <div v-if="form.errors.components" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                    {{ form.errors.components }}
                                </div>
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Sort Order
                                </label>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                    placeholder="0"
                                />
                                <div v-if="form.errors.sort_order" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                    {{ form.errors.sort_order }}
                                </div>
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Status
                                </label>
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            v-model="form.is_active"
                                            type="radio"
                                            :value="true"
                                            class="sr-only"
                                        />
                                        <div class="w-4 h-4 rounded-full border-2 border-green-500 mr-2 flex items-center justify-center transition-colors duration-200"
                                             :class="form.is_active ? 'bg-green-500' : 'bg-transparent'">
                                            <div v-if="form.is_active" class="w-2 h-2 rounded-full bg-white"></div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Active
                                        </span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            v-model="form.is_active"
                                            type="radio"
                                            :value="false"
                                            class="sr-only"
                                        />
                                        <div
                                            class="w-4 h-4 rounded-full border-2 border-gray-400 mr-2 flex items-center justify-center transition-colors duration-200"
                                            :class="!form.is_active ? 'bg-gray-400' : 'bg-transparent'"
                                        >
                                            <div
                                                v-if="!form.is_active"
                                                class="w-2 h-2 rounded-full bg-white"
                                            ></div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Inactive
                                        </span>
                                    </label>
                                </div>
                                <div v-if="form.errors.is_active" class="text-red-600 dark:text-red-400 text-sm mt-1">
                                    {{ form.errors.is_active }}
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-lime-500 hover:bg-lime-600 disabled:bg-lime-400 rounded-lg transition-colors duration-200 flex items-center"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ form.processing ? 'Saving...' : 'Save Menu Item' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">
                        Delete Menu Item
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-6">
                        Are you sure you want to delete <strong>{{ selectedMenu?.menu_name }}</strong>? This action cannot be undone.
                    </p>

                    <div v-if="selectedMenu" class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 mb-6">
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ selectedMenu.menu_name }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-1">
                                {{ selectedMenu.path }}
                            </div>
                            <div v-if="selectedMenu.components && selectedMenu.components.length > 0" class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                Components: {{ selectedMenu.components.join(', ') }}
                            </div>
                            <div class="mt-2">
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                    :class="selectedMenu.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300'"
                                >
                                    {{ selectedMenu.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>

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
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {{ isProcessing ? 'Deleting...' : 'Delete Menu Item' }}
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
</style>
