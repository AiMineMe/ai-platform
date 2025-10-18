<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    gateway: {
        type: Object,
        default: null
    },
});

const pageProps = usePage();
const { currencySymbol } = useSettings();
const { success, error } = useToast();
const processing = ref(false);
const parameterKey = ref(0);
const errors = computed(() => pageProps.props.errors || {});
const flash = computed(() => pageProps.props.flash);
const isEditing = computed(() => !!props.gateway);

const form = ref({
    name: '',
    currency: '',
    rate: 0,
    min_amount: 0,
    max_amount: 0,
    fixed_charge: 0,
    percent_charge: 0,
    description: '',
    status: true,
    parameters: []
});

const formatNumber = (num) => {
    if (!num || isNaN(num)) return '0.00';
    return parseFloat(num).toFixed(2);
};

const deepClone = (obj) => {
    if (obj === null || typeof obj !== 'object') return obj;
    if (obj instanceof Date) return new Date(obj.getTime());
    if (obj instanceof Array) return obj.map(item => deepClone(item));
    if (typeof obj === 'object') {
        const clonedObj = {};
        for (let key in obj) {
            if (obj.hasOwnProperty(key)) {
                clonedObj[key] = deepClone(obj[key]);
            }
        }
        return clonedObj;
    }
};

const initializeForm = () => {
    if (props.gateway) {
        const gatewayData = deepClone(props.gateway);

        form.value = {
            name: gatewayData.name || '',
            currency: gatewayData.currency || '',
            rate: parseFloat(gatewayData.rate) || 0,
            min_amount: parseFloat(gatewayData.min_amount) || 0,
            max_amount: parseFloat(gatewayData.max_amount) || 0,
            fixed_charge: parseFloat(gatewayData.fixed_charge) || 0,
            percent_charge: parseFloat(gatewayData.percent_charge) || 0,
            description: gatewayData.description || '',
            status: gatewayData.status !== false && gatewayData.status !== 0,
            parameters: []
        };

        let parameters = [];
        if (gatewayData.parameters) {
            if (typeof gatewayData.parameters === 'string') {
                try {
                    parameters = JSON.parse(gatewayData.parameters);
                } catch (e) {
                    parameters = [];
                }
            } else if (Array.isArray(gatewayData.parameters)) {
                parameters = gatewayData.parameters;
            } else if (typeof gatewayData.parameters === 'object') {
                parameters = Object.entries(gatewayData.parameters).map(([key, config]) => ({
                    field_name: key,
                    field_label: config.label || key,
                    field_type: config.type || 'text',
                    field_required: config.required !== false,
                    field_placeholder: config.placeholder || '',
                    field_options: config.options || {},
                    field_options_text: config.options ?
                        (Array.isArray(config.options) ?
                                config.options.map((opt, idx) => `${opt}:${opt}`).join('\n') :
                                Object.entries(config.options).map(([k, v]) => `${k}:${v}`).join('\n')
                        ) : ''
                }));
            }
        }

        form.value.parameters = parameters.map(param => {
            const processedParam = {
                field_name: param.field_name || '',
                field_label: param.field_label || '',
                field_type: param.field_type || 'text',
                field_required: param.field_required !== false,
                field_placeholder: param.field_placeholder || '',
                field_options: param.field_options || {},
                field_options_text: ''
            };

            if (processedParam.field_type === 'select' && processedParam.field_options) {
                if (Array.isArray(processedParam.field_options)) {
                    processedParam.field_options_text = processedParam.field_options
                        .map(opt => `${opt}:${opt}`)
                        .join('\n');
                } else if (typeof processedParam.field_options === 'object') {
                    processedParam.field_options_text = Object.entries(processedParam.field_options)
                        .map(([key, value]) => `${key}:${value}`)
                        .join('\n');
                }
            }

            return processedParam;
        });

        parameterKey.value++;
    } else {
        form.value = {
            name: '',
            currency: '',
            rate: 0,
            min_amount: 0,
            max_amount: 0,
            fixed_charge: 0,
            percent_charge: 0,
            description: '',
            status: true,
            parameters: []
        };
        addParameter();
    }
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
    parameterKey.value++;
};

const removeParameter = (index) => {
    if (form.value.parameters.length > index) {
        form.value.parameters.splice(index, 1);
        parameterKey.value++;
    }
};

const updateSelectOptions = (parameter) => {
    if (!parameter.field_options_text) {
        parameter.field_options = {};
        return;
    }

    const options = {};
    const lines = parameter.field_options_text.split('\n');

    lines.forEach(line => {
        const trimmedLine = line.trim();
        if (trimmedLine && trimmedLine.includes(':')) {
            const colonIndex = trimmedLine.indexOf(':');
            const key = trimmedLine.substring(0, colonIndex).trim();
            const value = trimmedLine.substring(colonIndex + 1).trim();
            if (key && value) {
                options[key] = value;
            }
        }
    });

    parameter.field_options = options;
};

const validateForm = () => {
    const errors = [];

    if (!form.value.name?.trim()) {
        errors.push('Gateway name is required');
    }

    if (!form.value.currency?.trim()) {
        errors.push('Currency is required');
    }

    if (form.value.currency && form.value.currency.length !== 3) {
        errors.push('Currency must be 3 characters long');
    }

    const rate = parseFloat(form.value.rate);
    if (isNaN(rate) || rate <= 0) {
        errors.push('Exchange rate must be a valid number greater than 0');
    }

    const minAmount = parseFloat(form.value.min_amount);
    const maxAmount = parseFloat(form.value.max_amount);

    if (isNaN(minAmount) || minAmount < 0) {
        errors.push('Minimum amount must be a valid positive number');
    }

    if (isNaN(maxAmount) || maxAmount <= 0) {
        errors.push('Maximum amount must be a valid number greater than 0');
    }

    if (!isNaN(minAmount) && !isNaN(maxAmount) && minAmount >= maxAmount) {
        errors.push('Minimum amount must be less than maximum amount');
    }

    if (form.value.parameters.length === 0) {
        errors.push('At least one form field is required for withdrawal gateways');
    }

    for (let i = 0; i < form.value.parameters.length; i++) {
        const param = form.value.parameters[i];
        if (!param.field_name?.trim() || !param.field_label?.trim() || !param.field_type) {
            errors.push(`Parameter ${i + 1}: Field name, label, and type are required`);
            break;
        }

        if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(param.field_name)) {
            errors.push(`Parameter ${i + 1}: Field name must contain only letters, numbers, and underscores, and cannot start with a number`);
            break;
        }

        const fieldNames = form.value.parameters.map(p => p.field_name.trim().toLowerCase());
        const duplicates = fieldNames.filter((name, index) => fieldNames.indexOf(name) !== index);
        if (duplicates.length > 0) {
            errors.push('Duplicate field names are not allowed');
            break;
        }

        if (param.field_type === 'select' && (!param.field_options_text?.trim() || Object.keys(param.field_options).length === 0)) {
            errors.push(`Parameter ${i + 1}: Select fields must have at least one option`);
            break;
        }
    }

    return errors;
};

const submitForm = async () => {
    if (processing.value) return;

    const validationErrors = validateForm();
    if (validationErrors.length > 0) {
        error(validationErrors[0]);
        return;
    }

    processing.value = true;

    try {
        const formData = new FormData();

        formData.append('name', form.value.name.trim());
        formData.append('currency', form.value.currency.trim().toUpperCase());
        formData.append('rate', form.value.rate.toString());
        formData.append('min_amount', form.value.min_amount.toString());
        formData.append('max_amount', form.value.max_amount.toString());
        formData.append('fixed_charge', form.value.fixed_charge.toString());
        formData.append('percent_charge', form.value.percent_charge.toString());
        formData.append('description', form.value.description || '');
        formData.append('status', form.value.status ? '1' : '0');

        if (form.value.parameters.length > 0) {
            const cleanedParameters = form.value.parameters.map(param => {
                const cleanParam = {
                    field_name: param.field_name.trim(),
                    field_label: param.field_label.trim(),
                    field_type: param.field_type,
                    field_required: param.field_required,
                    field_placeholder: param.field_placeholder || '',
                    field_options: param.field_options || {}
                };
                return cleanParam;
            });
            formData.append('parameters', JSON.stringify(cleanedParameters));
        }

        if (isEditing.value) {
            formData.append('_method', 'PUT');
        }

        const url = isEditing.value
            ? `/admin/withdrawal-gateways/${props.gateway.id}`
            : '/admin/withdrawal-gateways';

        router.post(url, formData, {
            forceFormData: true,
            preserveState: false,
            preserveScroll: false,
            onSuccess: () => {
                setTimeout(() => {
                    router.visit('/admin/withdrawal-gateways');
                }, 1000);
            },
            onError: (errors) => {
                processing.value = false;

                let errorMessage = 'An error occurred while saving the gateway';
                if (errors) {
                    const firstErrorKey = Object.keys(errors)[0];
                    const firstError = errors[firstErrorKey];

                    if (Array.isArray(firstError)) {
                        errorMessage = firstError[0];
                    } else if (typeof firstError === 'string') {
                        errorMessage = firstError;
                    }
                }

                error(errorMessage);
            }
        });
    } catch (err) {
        processing.value = false;
        error('An unexpected error occurred. Please try again.');
    }
};

watch(() => props.gateway, (newGateway) => {
    if (newGateway) {
        nextTick(() => {
            initializeForm();
        });
    }
}, {immediate: false, deep: true});

watch(flash, (newFlash) => {
    if (newFlash?.success) {
        success(newFlash.success);
    } else if (newFlash?.error) {
        error(newFlash.error);
    }
}, {immediate: true});

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
        :title="`${isEditing ? 'Edit' : 'Create'} Withdrawal Gateway`"
        page-section="Payments"
    >
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <Link
                        href="/admin/withdrawal-gateways"
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
                        Back to Withdrawal Gateways
                    </Link>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ isEditing ? 'Edit Withdrawal Gateway' : 'Create Withdrawal Gateway' }}
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{
                        isEditing ? 'Update gateway configuration and settings' : 'Set up a new withdrawal gateway for withdrawals'
                    }}
                </p>
            </div>

            <form
                class="space-y-6"
                @submit.prevent="submitForm"
            >
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
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
                                placeholder="e.g., Bank Transfer, PayPal, Crypto Wallet"
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
                                Currency *
                            </label>
                            <input
                                v-model="form.currency"
                                type="text"
                                placeholder="Enter currency code (e.g., USD, EUR, GBP)"
                                required
                                maxlength="3"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 uppercase"
                                @input="form.currency = $event.target.value.toUpperCase()"
                            >
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
                            <div class="relative">
                                <input
                                    v-model="form.rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                    placeholder="0.00"
                                >
                            </div>
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
                            placeholder="Brief description of the withdrawal gateway"
                        />
                        <p
                            v-if="errors.description"
                            class="mt-1 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ errors.description }}
                        </p>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
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

                <div
                    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Withdrawal Form Fields
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Define the form fields that users will fill when making withdrawals with this gateway.
                    </p>

                    <div class="space-y-6">
                        <div
                            v-for="(parameter, index) in form.parameters"
                            :key="`param-${index}-${parameterKey}`"
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
                                        placeholder="e.g., account_number"
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
                                        placeholder="e.g., Account Number"
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
                                    placeholder="bank1:Bank 1&#10;bank2:Bank 2"
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
                        href="/admin/withdrawal-gateways"
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
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
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
