<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    gateway: {
        type: Object,
        default: null
    },
    gateway_types: {
        type: Array,
        default: () => [
            { value: 'automatic', label: 'Automatic' },
            { value: 'manual', label: 'Manual' }
        ]
    }
});

const pageProps = usePage();
const { currencySymbol } = useSettings();
const { success, error } = useToast();

const processing = ref(false);
const errors = computed(() => pageProps.props.errors || {});
const flash = computed(() => pageProps.props.flash);
const isEditing = computed(() => !!props.gateway);

const form = ref({
    name: '',
    type: '',
    currency: '',
    rate: 0,
    min_amount: 0,
    max_amount: 0,
    fixed_charge: 0,
    percent_charge: 0,
    description: '',
    status: true,
    credentials: [],
    parameters: [],
    file: null
});

const fileInput = ref(null);
const currentFile = ref(null);
const filePreview = ref(null);

const formatNumber = (num) => {
    if (!num || isNaN(num)) return '0.00';
    return parseFloat(num).toFixed(2);
};

const initializeForm = () => {
    if (props.gateway) {
        form.value = {
            name: props.gateway.name || '',
            type: props.gateway.type || '',
            currency: props.gateway.currency || '',
            rate: parseFloat(props.gateway.rate) || 0,
            min_amount: parseFloat(props.gateway.min_amount) || 0,
            max_amount: parseFloat(props.gateway.max_amount) || 0,
            fixed_charge: parseFloat(props.gateway.fixed_charge) || 0,
            percent_charge: parseFloat(props.gateway.percent_charge) || 0,
            description: props.gateway.description || '',
            status: props.gateway.status !== false,
            credentials: [],
            parameters: props.gateway.parameters || [],
            file: null
        };

        if (props.gateway.file) {
            currentFile.value = props.gateway.file;
        }

        if (props.gateway.credentials) {
            try {
                const credentials = typeof props.gateway.credentials === 'string'
                    ? JSON.parse(props.gateway.credentials)
                    : props.gateway.credentials;

                form.value.credentials = Object.entries(credentials).map(([key, value]) => ({ key, value }));
            } catch (e) {
                form.value.credentials = [];
            }
        }

        if (form.value.parameters) {
            form.value.parameters.forEach(param => {
                if (param.field_type === 'select' && param.field_options) {
                    param.field_options_text = Object.entries(param.field_options)
                        .map(([key, value]) => `${key}:${value}`)
                        .join('\n');
                }
            });
        }
    }
};


const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            error('Please select a valid image file (JPG, PNG, GIF, SVG, WEBP)');
            return;
        }

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            error('File size must be less than 2MB');
            return;
        }

        form.value.file = file;

        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            filePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeFile = () => {
    form.value.file = null;
    filePreview.value = null;
    currentFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const getFileUrl = (filename) => {
    return `/assets/files/${filename}`;
};

const handleTypeChange = () => {
    if (form.value.type === 'automatic') {
        form.value.parameters = [];
    } else if (form.value.type === 'manual') {
        if (form.value.parameters.length === 0) {
            addParameter();
        }
    }
    if (form.value.credentials.length === 0) {
        addCredential();
    }
};

const addCredential = () => {
    form.value.credentials.push({ key: '', value: '' });
};

const removeCredential = (index) => {
    form.value.credentials.splice(index, 1);
};

const addParameter = () => {
    form.value.parameters.push({
        field_name: '',
        field_label: '',
        field_type: 'text',
        field_required: true,
        field_placeholder: '',
        field_options: {},
        field_options_text: ''
    });
};

const removeParameter = (index) => {
    form.value.parameters.splice(index, 1);
};

const updateSelectOptions = (parameter) => {
    if (!parameter.field_options_text) {
        parameter.field_options = {};
        return;
    }

    const options = {};
    parameter.field_options_text.split('\n').forEach(line => {
        const [key, value] = line.split(':');
        if (key && value) {
            options[key.trim()] = value.trim();
        }
    });
    parameter.field_options = options;
};

const validateForm = () => {
    const errors = [];

    if (!form.value.name.trim()) {
        errors.push('Gateway name is required');
    }

    if (!form.value.type) {
        errors.push('Gateway type is required');
    }

    if (!form.value.currency) {
        errors.push('Currency is required');
    }

    const rate = parseFloat(form.value.rate);
    if (isNaN(rate) || rate <= 0) {
        errors.push('Exchange rate must be a valid number greater than 0');
    }

    if (parseFloat(form.value.min_amount) < 0) {
        errors.push('Minimum amount cannot be negative');
    }

    if (parseFloat(form.value.max_amount) <= 0) {
        errors.push('Maximum amount must be greater than 0');
    }

    if (parseFloat(form.value.min_amount) >= parseFloat(form.value.max_amount)) {
        errors.push('Minimum amount must be less than maximum amount');
    }

    if (form.value.type === 'manual' && form.value.parameters.length === 0) {
        errors.push('At least one form field is required for manual gateways');
    }

    if (form.value.type === 'manual') {
        for (const param of form.value.parameters) {
            if (!param.field_name.trim()) {
                errors.push('All parameter field names are required');
                break;
            }
            if (!param.field_label.trim()) {
                errors.push('All parameter field labels are required');
                break;
            }
            if (!param.field_type) {
                errors.push('All parameter field types are required');
                break;
            }
        }
    }

    if (form.value.type === 'automatic') {
        for (const cred of form.value.credentials) {
            if (!cred.key.trim() || !cred.value.trim()) {
                errors.push('All credential keys and values are required');
                break;
            }
        }
    }

    return errors;
};

const submitForm = () => {
    const validationErrors = validateForm();
    if (validationErrors.length > 0) {
        error(validationErrors[0]);
        return;
    }

    processing.value = true;
    const formData = new FormData();

    // Add all form fields except credentials, parameters, and file
    Object.keys(form.value).forEach(key => {
        if (key === 'credentials' || key === 'parameters' || key === 'file') return;
        formData.append(key, form.value[key]);
    });

    // Add file if selected
    if (form.value.file) {
        formData.append('file', form.value.file);
    }

    if (form.value.credentials.length > 0) {
        const credentialsObj = {};
        form.value.credentials.forEach(cred => {
            if (cred.key && cred.value) {
                credentialsObj[cred.key] = cred.value;
            }
        });
        formData.append('credentials', JSON.stringify(credentialsObj));
    }

    if (form.value.type === 'manual' && form.value.parameters.length > 0) {
        const cleanedParameters = form.value.parameters.map(param => {
            const cleanParam = { ...param };
            delete cleanParam.field_options_text;
            return cleanParam;
        });
        formData.append('parameters', JSON.stringify(cleanedParameters));
    }

    if (isEditing.value) {
        formData.append('_method', 'PUT');
    }

    const url = isEditing.value
        ? `/admin/payment-gateways/${props.gateway.id}`
        : '/admin/payment-gateways';

    router.post(url, formData, {
        forceFormData: true,
        onSuccess: () => {
            setTimeout(() => {
                router.visit('/admin/payment-gateways');
            }, 1000);
        },
        onError: (errors) => {
            processing.value = false;
            const firstError = Object.values(errors)[0];
            if (Array.isArray(firstError)) {
                error(firstError[0]);
            } else {
                error(firstError || 'An error occurred');
            }
        }
    });
};

watch(flash, (newFlash) => {
    if (newFlash?.success) {
        success(newFlash.success);
    } else if (newFlash?.error) {
        error(newFlash.error);
    }
}, { immediate: true });

onMounted(() => {
    initializeForm();
    if (flash.value?.success) {
        success(flash.value.success);
    } else if (flash.value?.error) {
        error(flash.value.error);
    }
});
</script>

<template>
    <AdminLayout
        :title="`${isEditing ? 'Edit' : 'Create'} Payment Gateway`"
        page-section="Payments"
    >
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <Link
                        href="/admin/payment-gateways"
                        class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <svg
                            class="w-4 h-4 mr-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                        Back to Gateways
                    </Link>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ isEditing ? 'Edit Payment Gateway' : 'Create Payment Gateway' }}
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ isEditing ? 'Update gateway configuration and settings' : 'Set up a new payment gateway for deposits' }}
                </p>
            </div>

            <form
                class="space-y-6"
                @submit.prevent="submitForm"
            >
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Basic Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Gateway Name *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                placeholder="e.g., Stripe, PayPal, Bank Transfer"
                            >
                            <p
                                v-if="errors.name"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Gateway Type *
                            </label>
                            <select
                                v-model="form.type"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="handleTypeChange"
                            >
                                <option value="">
                                    Select Type
                                </option>
                                <option
                                    v-for="type in gateway_types"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                            <p
                                v-if="errors.type"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.type }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Currency *
                            </label>
                            <input
                                v-model="form.currency"
                                type="text"
                                required
                                maxlength="10"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 uppercase"
                                placeholder="e.g., USD, EUR, BTC, ETH"
                                @input="form.currency = $event.target.value.toUpperCase()"
                            >
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Enter currency code (e.g., USD, EUR, GBP, BTC, ETH, USDT)
                            </p>
                            <p
                                v-if="errors.currency"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.currency }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Exchange Rate *
                            </label>
                            <input
                                v-model="form.rate"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 uppercase"
                                placeholder="0.00"
                            >
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                1 {{ currencySymbol }} = {{ formatNumber(form.rate) }} {{ form.currency || 'Currency' }}
                            </p>
                            <p
                                v-if="errors.rate"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.rate }}
                            </p>
                        </div>

                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Status
                            </label>
                            <div class="flex items-center h-8">
                                <button
                                    type="button"
                                    class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    :class="form.status ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'"
                                    @click="form.status = !form.status"
                                >
                  <span
                      class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform shadow-lg"
                      :class="form.status ? 'translate-x-6' : 'translate-x-1'"
                  />
                                </button>
                                <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ form.status ? 'Active' : 'Inactive' }}
                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Description
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                            placeholder="Brief description of the payment gateway"
                        />
                        <p
                            v-if="errors.description"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.description }}
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Gateway Logo/Image
                    </h3>

                    <div class="space-y-4">
                        <!-- Current File Display -->
                        <div v-if="currentFile && !filePreview" class="flex items-center space-x-4">
                            <img
                                :src="getFileUrl(currentFile)"
                                :alt="form.name + ' logo'"
                                class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-slate-600"
                            >
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Current Image</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ currentFile }}</p>
                            </div>
                            <button
                                type="button"
                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                @click="removeFile"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- File Preview -->
                        <div v-if="filePreview" class="flex items-center space-x-4">
                            <img
                                :src="filePreview"
                                :alt="form.name + ' logo preview'"
                                class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-slate-600"
                            >
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Selected Image</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ form.file?.name }}</p>
                            </div>
                            <button
                                type="button"
                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                @click="removeFile"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- File Input -->
                        <div v-if="!filePreview && !currentFile">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Upload Logo/Image
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-slate-600 border-dashed rounded-lg hover:border-gray-400 dark:hover:border-slate-500 transition-colors duration-200">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                        <label for="file-upload" class="relative cursor-pointer bg-white dark:bg-slate-800 rounded-md font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Upload a file</span>
                                            <input
                                                id="file-upload"
                                                ref="fileInput"
                                                name="file"
                                                type="file"
                                                class="sr-only"
                                                accept="image/*"
                                                @change="handleFileSelect"
                                            >
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        PNG, JPG, GIF, SVG, WEBP up to 2MB
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Change File Button -->
                        <div v-if="filePreview || currentFile">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                                @click="$refs.fileInput?.click()"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Change Image
                            </button>
                            <input
                                ref="fileInput"
                                name="file"
                                type="file"
                                class="sr-only"
                                accept="image/*"
                                @change="handleFileSelect"
                            >
                        </div>

                        <p v-if="errors.file" class="text-sm text-red-600 dark:text-red-400">
                            {{ errors.file }}
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Limits & Charges
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Minimum Amount *
                            </label>
                            <input
                                v-model="form.min_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                placeholder="0.00"
                            >
                            <p
                                v-if="errors.min_amount"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.min_amount }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Maximum Amount *
                            </label>
                            <input
                                v-model="form.max_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                placeholder="0.00"
                            >
                            <p
                                v-if="errors.max_amount"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.max_amount }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Fixed Charge
                            </label>
                            <input
                                v-model="form.fixed_charge"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                placeholder="0.00"
                            >
                            <p
                                v-if="errors.fixed_charge"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.fixed_charge }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Percent Charge (%)
                            </label>
                            <input
                                v-model="form.percent_charge"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                placeholder="0.00"
                            >
                            <p
                                v-if="errors.percent_charge"
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ errors.percent_charge }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6"
                >
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Credentials
                    </h3>

                    <div class="space-y-4">
                        <div
                            v-for="(credential, index) in form.credentials"
                            :key="index"
                        >
                            <div class="flex gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Key Name
                                    </label>
                                    <input
                                        v-model="credential.key"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                        placeholder="e.g., api_key, secret_key"
                                    >
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Value
                                    </label>
                                    <input
                                        v-model="credential.value"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                        placeholder="Enter credential value"
                                    >
                                </div>
                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        class="px-3 py-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                        @click="removeCredential(index)"
                                    >
                                        <svg
                                            class="w-5 h-5"
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
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors duration-200"
                            @click="addCredential"
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
                            Add Credential
                        </button>
                    </div>
                </div>

                <div
                    v-if="form.type === 'manual'"
                    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6"
                >
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Manual Form Fields
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Define the form fields that users will fill when making deposits with this gateway.
                    </p>

                    <div class="space-y-6">
                        <div
                            v-for="(parameter, index) in form.parameters"
                            :key="index"
                            class="border border-gray-200 dark:border-slate-700 rounded-lg p-4"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Field Name *
                                    </label>
                                    <input
                                        v-model="parameter.field_name"
                                        type="text"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                        placeholder="e.g., transaction_id"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Field Label *
                                    </label>
                                    <input
                                        v-model="parameter.field_label"
                                        type="text"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                        placeholder="e.g., Transaction ID"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Field Type *
                                    </label>
                                    <select
                                        v-model="parameter.field_type"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                    >
                                        <option value="">
                                            Select Type
                                        </option>
                                        <option value="text">
                                            Text
                                        </option>
                                        <option value="email">
                                            Email
                                        </option>
                                        <option value="tel">
                                            Phone
                                        </option>
                                        <option value="number">
                                            Number
                                        </option>
                                        <option value="date">
                                            Date
                                        </option>
                                        <option value="file">
                                            File Upload
                                        </option>
                                        <option value="select">
                                            Select Dropdown
                                        </option>
                                    </select>
                                </div>

                                <div class="flex flex-col">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Required
                                    </label>
                                    <div class="flex items-center h-8">
                                        <button
                                            type="button"
                                            class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            :class="parameter.field_required ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'"
                                            @click="parameter.field_required = !parameter.field_required"
                                        >
                      <span
                          class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform shadow-lg"
                          :class="parameter.field_required ? 'translate-x-6' : 'translate-x-1'"
                      />
                                        </button>
                                        <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ parameter.field_required ? 'Required' : 'Optional' }}
                    </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Placeholder Text
                                </label>
                                <input
                                    v-model="parameter.field_placeholder"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                    placeholder="Enter placeholder text"
                                >
                            </div>

                            <div
                                v-if="parameter.field_type === 'select'"
                                class="mt-4"
                            >
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Select Options (key:value pairs, one per line)
                                </label>
                                <textarea
                                    v-model="parameter.field_options_text"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                    placeholder="option1:Option 1&#10;option2:Option 2"
                                    @input="updateSelectOptions(parameter)"
                                />
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                    @click="removeParameter(index)"
                                >
                                    <svg
                                        class="w-4 h-4 mr-1"
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
                                    Remove Field
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors duration-200"
                            @click="addParameter"
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
                            Add Form Field
                        </button>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <Link
                        href="/admin/payment-gateways"
                        class="px-6 py-3 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="processing"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center"
                    >
                        <svg
                            v-if="processing"
                            class="animate-spin -ml-1 mr-3 h-4 w-4 text-white"
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
                        {{ processing ? 'Saving...' : (isEditing ? 'Update Gateway' : 'Create Gateway') }}
                    </button>
                </div>
            </form>
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

button:not(:disabled):hover {
    transform: translateY(-1px);
    transition: all 0.2s ease-in-out;
}

button:focus,
input:focus,
select:focus,
textarea:focus {
    outline: none;
}

::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.5);
}

@media (max-width: 768px) {
    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
