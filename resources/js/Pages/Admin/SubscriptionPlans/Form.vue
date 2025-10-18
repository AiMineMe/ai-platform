<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    plan: {
        type: Object,
        default: null
    },
    errors: {
        type: Object,
        default: () => ({})
    }
});

const pageProps = usePage();
const { currencySymbol } = useSettings();
const { success, error } = useToast();
const isProcessing = ref(false);
const errors = ref({});

const isEditing = computed(() => !!props.plan);
const flash = computed(() => pageProps.props.flash);

const form = ref({
    name: props.plan?.name || '',
    price: Number(props.plan?.price) || '',
    mining_fee_percentage: Number(props.plan?.mining_fee_percentage) || '',
    mining_rate_multiplier: Number(props.plan?.mining_rate_multiplier) || 1.0,
    features: props.plan?.features || [''],
    is_active: Boolean(props.plan?.is_active) ?? true
});

const goBack = () => {
    router.get('/admin/subscription-plans');
};

const addFeature = () => {
    form.value.features.push('');
};

const removeFeature = (index) => {
    if (form.value.features.length > 1) {
        form.value.features.splice(index, 1);
    }
};

const submitForm = () => {
    isProcessing.value = true;
    errors.value = {};

    // Filter out empty features
    const filteredFeatures = form.value.features.filter(feature => feature.trim() !== '');

    const formData = {
        name: form.value.name,
        price: parseFloat(form.value.price),
        mining_fee_percentage: parseFloat(form.value.mining_fee_percentage),
        mining_rate_multiplier: parseFloat(form.value.mining_rate_multiplier),
        features: filteredFeatures,
        is_active: form.value.is_active
    };

    const url = isEditing.value
        ? `/admin/subscription-plans/${props.plan.id}`
        : '/admin/subscription-plans';

    const method = isEditing.value ? 'patch' : 'post';

    router[method](url, formData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (formErrors) => {
            errors.value = formErrors;
            const errorMessages = Object.values(formErrors).flat();
            const errorMessage = errorMessages.length > 0
                ? errorMessages[0]
                : `Failed to ${isEditing.value ? 'update' : 'create'} plan. Please check the form for errors.`;
            error(errorMessage);
        },
        onFinish: () => isProcessing.value = false
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'Never';

    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return `Today at ${date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    } else if (diffDays === 1) {
        return `Yesterday at ${date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

watch(() => props.errors, (newErrors) => {
    errors.value = { ...errors.value, ...newErrors };
}, { immediate: true });

watch(flash, (newFlash) => {
    if (newFlash?.success) {
        success(newFlash.success);
    } else if (newFlash?.error) {
        error(newFlash.error);
    }
}, { immediate: true });
</script>

<template>
    <AdminLayout
        :title="isEditing ? 'Edit Subscription Plan' : 'Create Subscription Plan'"
        page-section="Monetization System"
    >
        <div class="py-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ isEditing ? 'Edit Subscription Plan' : 'Create New Subscription Plan' }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ isEditing ? `Update ${plan?.name} plan settings and pricing` : 'Set up a new subscription plan with pricing and benefits' }}
                            </p>
                        </div>
                        <button
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            @click="goBack"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to Plans
                        </button>
                    </div>
                </div>

                <div
                    v-if="isEditing"
                    class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 mb-8 border border-blue-200 dark:border-blue-700"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                            Current Plan Status
                        </h3>
                        <span
                            class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="plan.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'"
                        >
                          {{ plan.is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Price:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ currencySymbol }}{{ plan.price }}/month
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Mining Fee:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ plan.mining_fee_percentage }}%
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Rate Multiplier:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ plan.mining_rate_multiplier }}x
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Subscribers:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ plan.users_count || 0 }} users
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md overflow-hidden border border-gray-200 dark:border-slate-700">
                    <form class="p-6 space-y-6" @submit.prevent="submitForm">
                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Basic Information
                            </h3>
                            <div class="grid gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Plan Name *
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="e.g., Basic, Premium, Pro"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.name }"
                                    >
                                    <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Monthly Price *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">{{ currencySymbol }}</span>
                                        </div>
                                        <input
                                            id="price"
                                            v-model="form.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            class="w-full pl-8 rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                            placeholder="9.99"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.price }"
                                        >
                                    </div>
                                    <p v-if="errors.price" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.price) ? errors.price[0] : errors.price }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Amount charged per month for this plan
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Mining Benefits
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="mining_fee_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Mining Fee Percentage *
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="mining_fee_percentage"
                                            v-model="form.mining_fee_percentage"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="100"
                                            required
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                            placeholder="5.0"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.mining_fee_percentage }"
                                        >
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">%</span>
                                        </div>
                                    </div>
                                    <p v-if="errors.mining_fee_percentage" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.mining_fee_percentage) ? errors.mining_fee_percentage[0] : errors.mining_fee_percentage }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Fee charged on token claims (0% = no fee)
                                    </p>
                                </div>

                                <div>
                                    <label for="mining_rate_multiplier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Mining Rate Multiplier *
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="mining_rate_multiplier"
                                            v-model="form.mining_rate_multiplier"
                                            type="number"
                                            step="0.1"
                                            min="1"
                                            max="10"
                                            required
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                            placeholder="1.5"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.mining_rate_multiplier }"
                                        >
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">x</span>
                                        </div>
                                    </div>
                                    <p v-if="errors.mining_rate_multiplier" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.mining_rate_multiplier) ? errors.mining_rate_multiplier[0] : errors.mining_rate_multiplier }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Mining speed multiplier (1.0 = normal speed)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Plan Features
                            </h3>
                            <div class="space-y-3">
                                <div v-for="(feature, index) in form.features" :key="index" class="flex items-center space-x-3">
                                    <div class="flex-1">
                                        <input
                                            v-model="form.features[index]"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                            :placeholder="`Feature ${index + 1}`"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.features }"
                                        >
                                    </div>
                                    <button
                                        v-if="form.features.length > 1"
                                        type="button"
                                        class="p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        @click="removeFeature(index)"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                    @click="addFeature"
                                >
                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Add Feature
                                </button>
                                <p v-if="errors.features" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ Array.isArray(errors.features) ? errors.features[0] : errors.features }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    List the benefits and features included in this plan
                                </p>
                            </div>
                        </div>

                        <div class="pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Status
                            </h3>
                            <div class="flex items-center justify-start">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-700"
                                    >
                                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active Plan</span>
                                </label>
                                <p class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                                    Users can subscribe to this plan when active
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="form.name && form.price && form.mining_fee_percentage !== '' && form.mining_rate_multiplier"
                            class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6 border border-blue-200 dark:border-blue-700"
                        >
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                                Plan Preview
                            </h4>
                            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                                <div class="flex justify-between">
                                    <span>Plan Name:</span>
                                    <span class="font-medium">{{ form.name || 'Not set' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Monthly Price:</span>
                                    <span class="font-medium">{{ currencySymbol }}{{ form.price || '0' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Mining Fee:</span>
                                    <span class="font-medium">{{ form.mining_fee_percentage || '0' }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Rate Multiplier:</span>
                                    <span class="font-medium">{{ form.mining_rate_multiplier || '1' }}x</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Features:</span>
                                    <span class="font-medium">{{ form.features.filter(f => f.trim()).length }} features</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Status:</span>
                                    <span class="font-medium">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
                            <div v-if="isEditing" class="text-sm text-gray-500 dark:text-gray-400">
                                Last updated: {{ formatDate(plan.updated_at) }}
                            </div>
                            <div v-else />

                            <div class="flex space-x-4">
                                <button
                                    type="button"
                                    class="px-6 py-3 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                                    @click="goBack"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isProcessing"
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center"
                                >
                                    <svg
                                        v-if="isProcessing"
                                        class="animate-spin -ml-1 mr-3 h-4 w-4 text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                    </svg>
                                    {{ isProcessing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update Plan' : 'Create Plan') }}
                                </button>
                            </div>
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
</style>
