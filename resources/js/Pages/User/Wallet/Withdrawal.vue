<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    withdrawals: {
        type: Object,
        required: true,
        default: () => ({ data: [], total: 0, links: [] }),
        validator: (value) => value && typeof value === 'object' && Array.isArray(value.data)
    },
    withdrawalGateways: {
        type: Array,
        required: true,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    walletBalance: {
        type: [Number, String],
        default: 0,
        validator: (value) => {
            const num = parseFloat(value);
            return !isNaN(num) && num >= 0;
        }
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const selectedGateway = ref(null);
const isProcessing = ref(false);
const withdrawalDetails = ref({});
const showModal = ref(false);
const selectedWithdrawal = ref(null);
const showCancelModal = ref(false);
const withdrawalToCancel = ref(null);
const isCancelling = ref(false);

const withdrawalForm = ref({
    amount: null,
    withdrawal_gateway_id: null
});

const { showToast } = useToast();
const { t } = useTranslation();
const page = usePage();

const primaryColor = computed(() => page.props.primaryColor || '#1f2937');
const derivedColors = computed(() => {
    const primary = primaryColor.value;

    const adjustColor = (color, amount) => {
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);

        const newR = Math.min(255, Math.max(0, r + amount));
        const newG = Math.min(255, Math.max(0, g + amount));
        const newB = Math.min(255, Math.max(0, b + amount));

        return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
    };

    return {
        primary: primary,
        secondary: adjustColor(primary, 40),
        background: adjustColor(primary, -30),
        surface: adjustColor(primary, -10),
        textPrimary: '#ffffff',
        textSecondary: '#e2e8f0',
        textMuted: '#94a3b8',
        border: adjustColor(primary, 60),
        accent: adjustColor(primary, 120),
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
    };
});

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));

const inputStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border,
    color: derivedColors.value.textPrimary
}));

const currencySymbol = computed(() => page.props.currencySymbol || '$');
const cancelModalTitleId = computed(() => 'cancel-modal-title-' + Math.random().toString(36).substr(2, 9));
const detailsModalTitleId = computed(() => 'details-modal-title-' + Math.random().toString(36).substr(2, 9));
const availableBalance = computed(() => {
    try {
        const balance = parseFloat(props.walletBalance);
        return isNaN(balance) ? 0 : Math.max(0, balance);
    } catch (error) {
        return 0;
    }
});

const isValidAmount = computed(() => {
    try {
        if (!selectedGateway.value) return false;

        const amount = parseFloat(withdrawalForm.value.amount);
        if (isNaN(amount) || amount <= 0) return false;

        const minAmount = parseFloat(selectedGateway.value.min_amount || 0);
        const maxAmount = parseFloat(selectedGateway.value.max_amount || Number.MAX_SAFE_INTEGER);

        if (isNaN(minAmount) || isNaN(maxAmount)) return false;

        return amount >= minAmount && amount <= maxAmount && amount <= availableBalance.value;
    } catch (error) {
        return false;
    }
});

const calculatedCharge = computed(() => {
    try {
        if (!selectedGateway.value || !isValidAmount.value) return 0;

        const amount = parseFloat(withdrawalForm.value.amount);
        if (isNaN(amount)) return 0;

        const fixedCharge = parseFloat(selectedGateway.value.fixed_charge || 0);
        const percentCharge = parseFloat(selectedGateway.value.percent_charge || 0);

        if (isNaN(fixedCharge) || isNaN(percentCharge)) return 0;

        return fixedCharge + (amount * percentCharge / 100);
    } catch (error) {
        return 0;
    }
});

const finalAmount = computed(() => {
    try {
        if (!isValidAmount.value) return 0;
        const amount = parseFloat(withdrawalForm.value.amount);
        if (isNaN(amount)) return 0;

        return Math.max(0, amount - calculatedCharge.value);
    } catch (error) {
        return 0;
    }
});

const totalDeduction = computed(() => {
    try {
        if (!isValidAmount.value) return 0;
        const amount = parseFloat(withdrawalForm.value.amount);
        if (isNaN(amount)) return 0;

        return amount;
    } catch (error) {
        return 0;
    }
});

const canSubmit = computed(() => {
    try {
        if (!isValidAmount.value || isProcessing.value) {
            return false;
        }

        if (selectedGateway.value?.parameters) {
            const requiredFields = selectedGateway.value.parameters.filter(param => param.field_required);

            for (const field of requiredFields) {
                const value = withdrawalDetails.value[field.field_name];
                if (!value || (typeof value === 'string' && value.trim() === '')) {
                    return false;
                }
            }
        }

        return true;
    } catch (error) {
        return false;
    }
});

const successfulWithdrawals = computed(() => {
    try {
        if (!props.withdrawals?.data || !Array.isArray(props.withdrawals.data)) return 0;
        return props.withdrawals.data.filter(w => {
            const status = w.status?.toLowerCase();
            return ['approved', 'completed', 'success'].includes(status);
        }).length;
    } catch (error) {
        return 0;
    }
});

const getGatewayCurrencySymbol = (gateway) => {
    if (!gateway?.currency) return '$';

    const currencySymbols = {
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'JPY': '¥',
        'BTC': '₿',
        'ETH': 'Ξ'
    };

    return currencySymbols[gateway.currency] || gateway.currency + ' ';
};

const getWithdrawalCurrencySymbol = (withdrawal) => {
    if (!withdrawal || typeof withdrawal !== 'object') return '$';

    try {
        const currency = withdrawal.currency || withdrawal.withdrawal_gateway?.currency || 'USD';
        return getGatewayCurrencySymbol({ currency });
    } catch (error) {
        return '$';
    }
};

const getStatusBadgeClasses = () => {
    return 'px-2 py-1 text-xs font-medium rounded-full border';
};


const getStatusBadgeColor = (status) => {
    if (!status || typeof status !== 'string') {
        return derivedColors.value.textMuted;
    }
    switch (status.toLowerCase()) {
        case 'approved':
        case 'completed':
        case 'success':
            return derivedColors.value.success;
        case 'pending':
        case 'processing':
            return derivedColors.value.warning;
        case 'rejected':
        case 'failed':
        case 'cancelled':
            return '#ef4444';
        default:
            return derivedColors.value.textMuted;
    }
};


const formatNumber = (num) => {
    if (num === null || num === undefined || num === '') return '0.00';
    const number = parseFloat(num);
    if (isNaN(number)) return '0.00';

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
    }).format(number);
};

const formatDate = (dateString) => {
    if (!dateString) return t('notAvailable');
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return t('invalidDate');

        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        return t('invalidDate');
    }
};

const formatTime = (dateString) => {
    if (!dateString) return t('notAvailable');
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return t('invalidTime');

        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return t('invalidTime');
    }
};

const formatDateTime = (dateString) => {
    if (!dateString) return t('notAvailable');
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return t('invalidDate');

        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return t('invalidDate');
    }
};

const formatStatus = (status) => {
    if (!status || typeof status !== 'string') return t('unknown');
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
};

const formatFieldName = (fieldName) => {
    if (!fieldName || typeof fieldName !== 'string') return '';
    return fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const selectGateway = (gateway) => {
    try {
        if (!gateway?.id) {
            showToast(t('invalidWithdrawalGateway'), 'error');
            return;
        }

        selectedGateway.value = gateway;
        withdrawalForm.value.withdrawal_gateway_id = gateway.id;
        withdrawalForm.value.amount = null;
        withdrawalDetails.value = {};

        showToast(t('gatewaySelected', { name: gateway.name }), 'info');
    } catch (error) {
        showToast(t('failedToSelectGateway'), 'error');
    }
};

const handleFileUpload = (event, fieldName) => {
    try {
        const file = event.target.files[0];
        if (!file) return;

        const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_FILE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (file.size > MAX_FILE_SIZE) {
            showToast(t('fileSizeTooLarge'), 'error');
            event.target.value = '';
            return;
        }

        if (!ALLOWED_FILE_TYPES.includes(file.type)) {
            showToast(t('invalidFileType'), 'error');
            event.target.value = '';
            return;
        }

        withdrawalDetails.value[fieldName] = file;
        showToast(t('fileSelectedSuccessfully', { name: file.name }), 'success');
    } catch (error) {
        showToast(t('fileUploadFailed'), 'error');
        event.target.value = '';
    }
};

const validateWithdrawalAmount = (amount) => {
    try {
        if (!amount || amount <= 0) return t('amountMustBeGreaterThanZero');
        if (!selectedGateway.value) return t('selectWithdrawalGateway');

        const numAmount = parseFloat(amount);
        if (isNaN(numAmount)) return t('invalidAmountFormat');

        const minAmount = parseFloat(selectedGateway.value.min_amount || 0);
        const maxAmount = parseFloat(selectedGateway.value.max_amount || Number.MAX_SAFE_INTEGER);

        if (isNaN(minAmount) || isNaN(maxAmount)) {
            return t('invalidGatewayConfiguration');
        }

        if (numAmount < minAmount) {
            return t('minimumWithdrawalAmount', {
                amount: getGatewayCurrencySymbol(selectedGateway.value) + formatNumber(minAmount)
            });
        }
        if (numAmount > maxAmount) {
            return t('maximumWithdrawalAmount', {
                amount: getGatewayCurrencySymbol(selectedGateway.value) + formatNumber(maxAmount)
            });
        }

        if (numAmount > availableBalance.value) {
            return t('insufficientWalletBalance');
        }

        return null;
    } catch (error) {
        return t('validationErrorOccurred');
    }
};

const submitWithdrawal = async () => {
    if (!canSubmit.value) {
        showToast(t('ensureRequiredFieldsFilled'), 'error');
        return;
    }

    try {
        const validationError = validateWithdrawalAmount(withdrawalForm.value.amount);
        if (validationError) {
            showToast(validationError, 'error');
            return;
        }

        if (finalAmount.value <= 0) {
            showToast(t('finalAmountMustBeGreaterThanZero'), 'error');
            return;
        }

        isProcessing.value = true;

        const formData = new FormData();
        formData.append('withdrawal_gateway_id', withdrawalForm.value.withdrawal_gateway_id);
        formData.append('amount', withdrawalForm.value.amount);

        // Append withdrawal details
        Object.keys(withdrawalDetails.value).forEach(key => {
            const value = withdrawalDetails.value[key];
            if (value instanceof File) {
                formData.append(`withdrawal_details[${key}]`, value);
            } else if (value !== null && value !== undefined && value !== '') {
                const sanitizedValue = String(value).trim();
                if (sanitizedValue) {
                    formData.append(`withdrawal_details[${key}]`, sanitizedValue);
                }
            }
        });

        router.post('/user/wallet/withdraw', formData, {
            onSuccess: () => {
                resetForm();
                showToast(t('withdrawalRequestSubmitted'), 'success');
            },
            onError: (errors) => {
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('withdrawalSubmissionFailed');
                showToast(errorMessage, 'error');
            },
            preserveScroll: true,
            forceFormData: true
        });
    } catch (error) {
        showToast(t('withdrawalSubmissionFailed'), 'error');
    } finally {
        isProcessing.value = false;
    }
};

const cancelWithdrawal = async (withdrawal) => {
    try {
        if (!withdrawal?.id) {
            showToast(t('invalidWithdrawalSelected'), 'error');
            return;
        }

        if (withdrawal.status !== 'pending') {
            showToast(t('onlyPendingWithdrawalsCanBeCancelled'), 'error');
            return;
        }

        isCancelling.value = true;

        router.patch(`/user/wallet/withdraw/${withdrawal.id}/cancel`, {}, {
            onSuccess: () => {
                showToast(t('withdrawalCancelledSuccessfully'), 'success');
                closeCancelModal();
            },
            onError: (errors) => {
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('failedToCancelWithdrawal');
                showToast(errorMessage, 'error');
            },
            preserveScroll: true
        });
    } catch (error) {
        showToast(t('failedToCancelWithdrawal'), 'error');
    } finally {
        isCancelling.value = false;
    }
};

const showCancelConfirmation = (withdrawal) => {
    try {
        if (!withdrawal?.id) {
            showToast(t('invalidWithdrawalSelected'), 'error');
            return;
        }
        withdrawalToCancel.value = withdrawal;
        showCancelModal.value = true;
    } catch (error) {
        showToast(t('failedToShowCancellationDialog'), 'error');
    }
};

const closeCancelModal = () => {
    try {
        showCancelModal.value = false;
        setTimeout(() => {
            withdrawalToCancel.value = null;
            isCancelling.value = false;
        }, 300);
    } catch (error) {
    }
};

const confirmCancelWithdrawal = () => {
    try {
        if (withdrawalToCancel.value) {
            cancelWithdrawal(withdrawalToCancel.value);
        }
    } catch (error) {
        showToast(t('failedToCancelWithdrawal'), 'error');
    }
};

const showWithdrawalDetails = (withdrawal) => {
    try {
        if (!withdrawal?.id) {
            showToast(t('invalidWithdrawalSelected'), 'error');
            return;
        }
        selectedWithdrawal.value = withdrawal;
        showModal.value = true;
    } catch (error) {
        showToast(t('failedToShowWithdrawalDetails'), 'error');
    }
};


const closeModal = () => {
    try {
        showModal.value = false;
        setTimeout(() => {
            selectedWithdrawal.value = null;
        }, 300);
    } catch (error) {
    }
};

const resetForm = () => {
    try {
        selectedGateway.value = null;
        withdrawalForm.value = { amount: null, withdrawal_gateway_id: null };
        withdrawalDetails.value = {};
    } catch (error) {
    }
};

const goToPage = (url) => {
    try {
        if (!url || typeof url !== 'string') return;

        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                showToast(t('failedToLoadPage'), 'error');
            }
        });
    } catch (error) {
        showToast(t('navigationFailed'), 'error');
    }
};

const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        if (showModal.value) {
            closeModal();
        } else if (showCancelModal.value) {
            closeCancelModal();
        }
    }
};

onMounted(() => {
    try {
        const flash = page.props.flash;

        if (flash?.success) showToast(flash.success, 'success');
        if (flash?.error) showToast(flash.error, 'error');
        if (flash?.warning) showToast(flash.warning, 'warning');
        if (flash?.info) showToast(flash.info, 'info');
        document.addEventListener('keydown', handleKeydown);
    } catch (error) {
        showToast(t('applicationInitializationFailed'), 'error');
    }
});

onUnmounted(() => {
    try {
        document.removeEventListener('keydown', handleKeydown);
        isProcessing.value = false;
        isCancelling.value = false;
    } catch (error) {
    }
});
</script>

<template>
    <UserLayout
        :page-title="t('withdraw')"
        :page-section="t('wallet')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-6" :style="cardStyle">
                        <h2 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('selectWithdrawalMethod') }}
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                            <div
                                v-for="gateway in withdrawalGateways"
                                :key="gateway.id"
                                tabindex="0"
                                role="button"
                                :aria-label="t('selectGateway', { name: gateway.name })"
                                :class="[
                                    'border rounded-xl p-4 cursor-pointer transition-all duration-300 hover:scale-[1.02] transform focus:outline-none focus:ring-2',
                                    selectedGateway?.id === gateway.id
                                        ? 'shadow-xl'
                                        : 'hover:shadow-lg'
                                ]"
                                :style="{
                                    borderColor: selectedGateway?.id === gateway.id ? derivedColors.accent + '50' : derivedColors.border + '30',
                                    backgroundColor: selectedGateway?.id === gateway.id ? derivedColors.surface + '80' : derivedColors.surface + '50',
                                    ':focus': { ringColor: derivedColors.accent }
                                }"
                                @click="selectGateway(gateway)"
                                @keydown.enter="selectGateway(gateway)"
                                @keydown.space.prevent="selectGateway(gateway)"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-md" :style="{ backgroundColor: derivedColors.accent }">
                                        <span class="font-bold text-sm" :style="{ color: derivedColors.background }">{{ gateway.name.substring(0, 2) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-bold text-sm" :style="{ color: derivedColors.textPrimary }">
                                            {{ gateway.name }}
                                        </h3>
                                        <div class="text-xs font-medium" :style="{ color: derivedColors.accent }">
                                            {{ gateway.fixed_charge > 0 ? currencySymbol + formatNumber(gateway.fixed_charge) + ' + ' : '' }}{{ formatNumber(gateway.percent_charge) }}% {{ t('fee') }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="selectedGateway?.id === gateway.id"
                                        :style="{ color: derivedColors.accent }"
                                    >
                                        <svg
                                            class="w-5 h-5"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="selectedGateway && selectedGateway.description"
                            class="rounded-xl p-4 border mb-6"
                            :style="{
        backgroundColor: derivedColors.surface + '30',
        borderColor: derivedColors.border + '30'
    }"
                        >
                            <h4 class="font-medium mb-3 flex items-center" :style="{ color: derivedColors.textPrimary }">
                                <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                                {{ t('withdrawalInstructions') }}
                            </h4>
                            <div
                                class="text-sm leading-relaxed prose prose-sm max-w-none"
                                :style="{ color: derivedColors.textSecondary }"
                                v-html="selectedGateway.description"
                            />
                        </div>

                        <div
                            v-if="selectedGateway"
                            class="border-t pt-6"
                            :style="{ borderColor: derivedColors.border + '20' }"
                        >
                            <form
                                class="space-y-6"
                                novalidate
                                @submit.prevent="submitWithdrawal"
                            >
                                <div>
                                    <label class="block font-medium mb-3" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('withdrawalAmount', { currency: selectedGateway.currency }) }}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 transform -translate-y-1/2 font-bold text-lg" :style="{ color: derivedColors.accent }">{{ getGatewayCurrencySymbol(selectedGateway) }}</span>
                                        <input
                                            v-model.number="withdrawalForm.amount"
                                            type="number"
                                            step="0.01"
                                            :min="selectedGateway.min_amount"
                                            :max="selectedGateway.max_amount"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            :aria-describedby="`amount-help-${selectedGateway.id}`"
                                            class="w-full pl-12 pr-4 py-4 border rounded-xl text-lg font-medium placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                            :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            :placeholder="t('amountPlaceholder')"
                                            required
                                        >
                                    </div>
                                    <div
                                        :id="`amount-help-${selectedGateway.id}`"
                                        class="text-sm mt-2"
                                        :style="{ color: derivedColors.textMuted }"
                                    >
                                        {{ t('minMax', {
                                        min: getGatewayCurrencySymbol(selectedGateway) + formatNumber(selectedGateway.min_amount),
                                        max: getGatewayCurrencySymbol(selectedGateway) + formatNumber(selectedGateway.max_amount)
                                    }) }}
                                    </div>
                                    <div class="text-sm mt-1" :style="{ color: derivedColors.accent }">
                                        {{ t('availableBalance') }}: {{ currencySymbol }}{{ formatNumber(walletBalance) }}
                                    </div>
                                </div>

                                <div
                                    v-if="selectedGateway.parameters && selectedGateway.parameters.length > 0"
                                    class="space-y-4"
                                >
                                    <h4 class="font-medium flex items-center" :style="{ color: derivedColors.textPrimary }">
                                        <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                                        {{ t('withdrawalInformation') }}
                                    </h4>

                                    <div
                                        v-for="param in selectedGateway.parameters"
                                        :key="param.field_name"
                                        class="space-y-2"
                                    >
                                        <label class="block text-sm font-medium" :style="{ color: derivedColors.textSecondary }">
                                            {{ param.field_label }}
                                            <span
                                                v-if="param.field_required"
                                                class="text-red-400"
                                            >*</span>
                                        </label>

                                        <template v-if="['text', 'email', 'tel'].includes(param.field_type)">
                                            <input
                                                v-model="withdrawalDetails[param.field_name]"
                                                :type="param.field_type"
                                                :placeholder="param.field_placeholder"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                        </template>

                                        <template v-else-if="['date', 'datetime-local'].includes(param.field_type)">
                                            <input
                                                v-model="withdrawalDetails[param.field_name]"
                                                :type="param.field_type"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                        </template>

                                        <template v-else-if="param.field_type === 'number'">
                                            <input
                                                v-model.number="withdrawalDetails[param.field_name]"
                                                type="number"
                                                :placeholder="param.field_placeholder"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                        </template>

                                        <template v-else-if="param.field_type === 'select'">
                                            <select
                                                v-model="withdrawalDetails[param.field_name]"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                                <option value="">
                                                    {{ param.field_placeholder || t('selectOption') }}
                                                </option>
                                                <option
                                                    v-for="(label, value) in param.field_options"
                                                    :key="value"
                                                    :value="value"
                                                >
                                                    {{ label }}
                                                </option>
                                            </select>
                                        </template>

                                        <template v-else-if="param.field_type === 'file'">
                                            <input
                                                type="file"
                                                :required="param.field_required"
                                                accept="image/*,.pdf"
                                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 transition-all duration-200 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium hover:file:opacity-80"
                                                :style="{
                                                    ...inputStyle,
                                                    ':focus': { borderColor: derivedColors.accent },
                                                    'file:backgroundColor': derivedColors.accent,
                                                    'file:color': derivedColors.background
                                                }"
                                                @change="handleFileUpload($event, param.field_name)"
                                            >
                                        </template>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="!canSubmit || isProcessing"
                                    class="w-full py-4 px-6 rounded-xl font-bold text-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-lg hover:shadow-opacity-30 relative overflow-hidden group"
                                    :style="{
                                        backgroundColor: canSubmit ? derivedColors.accent : derivedColors.surface,
                                        color: canSubmit ? derivedColors.background : derivedColors.textMuted
                                    }"
                                >
                                    <span
                                        v-if="isProcessing"
                                        class="flex items-center justify-center relative z-10"
                                    >
                                        <svg
                                            class="animate-spin -ml-1 mr-3 h-6 w-6"
                                            :style="{ color: derivedColors.background }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
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
                                        {{ t('processing') }}...
                                    </span>
                                    <span
                                        v-else
                                        class="relative z-10"
                                    >{{ t('submitWithdrawalRequest') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div
                        v-if="selectedGateway && isValidAmount"
                        class="backdrop-blur-sm rounded-xl shadow-lg border p-6"
                        :style="cardStyle"
                    >
                        <h3 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('withdrawalSummary') }}
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('requestedAmount') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ getGatewayCurrencySymbol(selectedGateway) }}{{ formatNumber(withdrawalForm.amount) }}</span>
                            </div>
                            <div
                                v-if="calculatedCharge > 0"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('processingFee') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.warning }">{{ getGatewayCurrencySymbol(selectedGateway) }}{{ formatNumber(calculatedCharge) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-t pt-3" :style="{ borderColor: derivedColors.border + '20' }">
                                <span class="font-medium" :style="{ color: derivedColors.textMuted }">{{ t('youllReceive') }}:</span>
                                <span class="font-bold text-xl" :style="{ color: derivedColors.accent }">{{ getGatewayCurrencySymbol(selectedGateway) }}{{ formatNumber(finalAmount) }}</span>
                            </div>
                            <div class="rounded-lg p-3 mt-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="text-xs mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('totalDeductedFromWallet') }}:
                                </div>
                                <div class="font-semibold" :style="{ color: derivedColors.accent }">
                                    {{ currencySymbol }}{{ formatNumber(totalDeduction) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedGateway"
                        class="backdrop-blur-sm rounded-xl shadow-lg border p-6"
                        :style="cardStyle"
                    >
                        <h3 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('gatewayInformation') }}
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('gateway') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ selectedGateway.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('currency') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ selectedGateway.currency }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('processing') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ t('manualReview') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('withdrawalStats') }}
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold" :style="{ color: derivedColors.accent }">
                                    {{ withdrawals?.total || 0 }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('totalWithdrawals') }}
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold" :style="{ color: derivedColors.success }">
                                    {{ successfulWithdrawals }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('successful') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border overflow-hidden" :style="cardStyle">
                <div class="px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-2 sm:space-y-0">
                        <div>
                            <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('withdrawalHistory') }}
                            </h2>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('trackWithdrawalTransactions') }}
                            </p>
                        </div>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ withdrawals?.total || 0 }} {{ t('totalWithdrawalsCount') }}
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <div class="block lg:hidden">
                        <div v-if="!withdrawals?.data || withdrawals.data.length === 0" class="text-center py-8 px-4">
                            <div class="flex flex-col items-center">
                                <svg
                                    class="w-10 h-10 sm:w-12 sm:h-12 mb-4"
                                    :style="{ color: derivedColors.textMuted }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                    {{ t('noWithdrawalsFound') }}
                                </p>
                                <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('makeFirstWithdrawal') }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-for="withdrawal in withdrawals?.data"
                            :key="withdrawal.id"
                            class="border-b p-4 space-y-3 hover:opacity-80 transition-colors"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium font-mono" :style="{ color: derivedColors.textPrimary }">
                                        {{ withdrawal.trx }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(withdrawal.created_at) }}
                                    </div>
                                </div>
                                <span
                                    :class="getStatusBadgeClasses(withdrawal.status)"
                                    :style="{
                        backgroundColor: getStatusBadgeColor(withdrawal.status) + '20',
                        borderColor: getStatusBadgeColor(withdrawal.status) + '30',
                        color: getStatusBadgeColor(withdrawal.status)
                    }"
                                >
                    {{ formatStatus(withdrawal.status) }}
                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                        {{ t('requested') }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ getWithdrawalCurrencySymbol(withdrawal) }}{{ formatNumber(withdrawal.amount) }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ withdrawal.currency }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                        {{ t('youReceive') }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.accent }">
                                        {{ getWithdrawalCurrencySymbol(withdrawal) }}{{ formatNumber(withdrawal.final_amount) }}
                                    </div>
                                    <div
                                        v-if="withdrawal.charge > 0"
                                        class="text-xs"
                                        :style="{ color: derivedColors.warning }"
                                    >
                                        -{{ getWithdrawalCurrencySymbol(withdrawal) }}{{ formatNumber(withdrawal.charge) }} {{ t('fee') }}
                                    </div>
                                </div>
                            </div>

                            <div class="bg-opacity-50 rounded-lg p-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                            {{ t('deducted') }}
                                        </div>
                                        <div class="text-sm font-semibold text-red-300">
                                            {{ currencySymbol }}{{ formatNumber(withdrawal.withdrawal_amount) }}
                                        </div>
                                        <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                            {{ t('fromWallet') }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase font-medium" :style="{ color: derivedColors.textMuted }">
                                            {{ t('gateway') }}
                                        </div>
                                        <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                            {{ withdrawal.withdrawal_gateway?.name || t('notAvailable') }}
                                        </div>
                                        <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                            {{ withdrawal.currency }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between items-center pt-2">
                                <div>
                                    <button
                                        v-if="withdrawal.status === 'pending'"
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors px-2 py-1 rounded"
                                        @click="showCancelConfirmation(withdrawal)"
                                    >
                                        {{ t('cancel') }}
                                    </button>
                                </div>
                                <button
                                    class="text-sm font-medium transition-colors duration-200 px-3 py-2 rounded-lg"
                                    :style="{
                        color: derivedColors.accent,
                        backgroundColor: derivedColors.accent + '10'
                    }"
                                    @click="showWithdrawalDetails(withdrawal)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <table class="w-full hidden lg:table">
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('trxId') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('requested') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('youReceive') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('deducted') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('gateway') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('status') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('date') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase" :style="{ color: derivedColors.textSecondary }">
                                {{ t('actions') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody class="divide-y" :style="{ borderColor: derivedColors.border + '50' }">
                        <!-- Empty State Row -->
                        <tr v-if="!withdrawals?.data || withdrawals.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 sm:px-6 py-12 text-center"
                            >
                                <div class="flex flex-col items-center">
                                    <svg
                                        class="w-12 h-12 mb-4"
                                        :style="{ color: derivedColors.textMuted }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                        />
                                    </svg>
                                    <p class="text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                        {{ t('noWithdrawalsFound') }}
                                    </p>
                                    <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('makeFirstWithdrawal') }}
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="withdrawal in withdrawals?.data"
                            :key="withdrawal.id"
                            class="hover:opacity-80 transition-colors"
                        >
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium font-mono" :style="{ color: derivedColors.textPrimary }">
                                    {{ withdrawal.trx }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ getWithdrawalCurrencySymbol(withdrawal) }}{{ formatNumber(withdrawal.amount) }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ withdrawal.currency }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.accent }">
                                    {{ getWithdrawalCurrencySymbol(withdrawal) }}{{ formatNumber(withdrawal.final_amount) }}
                                </div>
                                <div
                                    v-if="withdrawal.charge > 0"
                                    class="text-xs"
                                    :style="{ color: derivedColors.warning }"
                                >
                                    -{{ getWithdrawalCurrencySymbol(withdrawal) }}{{ formatNumber(withdrawal.charge) }} {{ t('fee') }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-red-300">
                                    {{ currencySymbol }}{{ formatNumber(withdrawal.withdrawal_amount) }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('fromWallet') }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ withdrawal.withdrawal_gateway?.name || t('notAvailable') }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ withdrawal.currency }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <span
                    :class="getStatusBadgeClasses(withdrawal.status)"
                    :style="{
                        backgroundColor: getStatusBadgeColor(withdrawal.status) + '20',
                        borderColor: getStatusBadgeColor(withdrawal.status) + '30',
                        color: getStatusBadgeColor(withdrawal.status)
                    }"
                >
                    {{ formatStatus(withdrawal.status) }}
                </span>
                                <div
                                    v-if="withdrawal.status === 'pending'"
                                    class="mt-1"
                                >
                                    <button
                                        class="text-xs text-red-400 hover:text-red-300 transition-colors"
                                        @click="showCancelConfirmation(withdrawal)"
                                    >
                                        {{ t('cancel') }}
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <div :style="{ color: derivedColors.textSecondary }">{{ formatDate(withdrawal.created_at) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(withdrawal.created_at) }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <button
                                    class="font-medium transition-colors duration-200"
                                    :style="{ color: derivedColors.accent }"
                                    @click="showWithdrawalDetails(withdrawal)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="withdrawals?.data && withdrawals.data.length > 0 && withdrawals.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20' }"
                >
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: withdrawals.from || 0,
                            to: withdrawals.to || 0,
                            total: withdrawals.total || 0
                        }) }}
                        </div>
                        <div class="flex flex-wrap justify-center sm:justify-end space-x-1">
                            <button
                                v-for="(link, index) in withdrawals.links"
                                :key="index"
                                :disabled="!link.url"
                                :class="[
                    'px-2 sm:px-3 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors',
                    link.active ? 'cursor-default' : link.url ? 'hover:opacity-80' : 'cursor-not-allowed opacity-50'
                ]"
                                :style="{
                    backgroundColor: link.active ? derivedColors.accent : link.url ? derivedColors.surface : derivedColors.surface + '50',
                    color: link.active ? derivedColors.background : link.url ? derivedColors.textSecondary : derivedColors.textMuted
                }"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showCancelModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="cancelModalTitleId"
            @click.self="closeCancelModal"
            @keydown.escape="closeCancelModal"
        >
            <div
                class="rounded-2xl shadow-2xl border max-w-md w-full backdrop-blur-md"
                :style="{ backgroundColor: derivedColors.background + '95', borderColor: derivedColors.border + '50' }"
            >
                <div class="px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="cancelModalTitleId"
                            class="text-xl font-semibold"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('cancelWithdrawal') }}
                        </h3>
                        <button
                            class="p-1 transition-colors"
                            :style="{ color: derivedColors.textMuted }"
                            @click="closeCancelModal"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
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

                <div
                    v-if="withdrawalToCancel"
                    class="p-6"
                >
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <svg
                                class="h-6 w-6 text-red-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.864-.833-2.634 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium mb-2" :style="{ color: derivedColors.textPrimary }">
                            {{ t('cancelWithdrawalRequest') }}
                        </h3>
                        <p class="text-sm mb-4" :style="{ color: derivedColors.textMuted }">
                            {{ t('cancelWithdrawalConfirmation') }}
                        </p>
                    </div>

                    <div class="rounded-lg p-4 mb-6" :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('transactionId') }}:</span>
                                <span class="font-mono text-sm" :style="{ color: derivedColors.textPrimary }">{{ withdrawalToCancel.trx }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('amount') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ getWithdrawalCurrencySymbol(withdrawalToCancel) }}{{ formatNumber(withdrawalToCancel.amount) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('gateway') }}:</span>
                                <span :style="{ color: derivedColors.textPrimary }">{{ withdrawalToCancel.withdrawal_gateway?.name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-lg p-3 mb-6" :style="{ backgroundColor: derivedColors.success + '20', borderColor: derivedColors.success + '30' }">
                        <div class="flex items-center">
                            <svg
                                class="w-5 h-5 mr-2"
                                :style="{ color: derivedColors.success }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p class="text-sm" :style="{ color: derivedColors.success }">
                                <strong>{{ currencySymbol }}{{ formatNumber(withdrawalToCancel.withdrawal_amount) }}</strong> {{ t('willBeRefunded') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 p-6 border-t" :style="{ borderColor: derivedColors.border + '50' }">
                    <button
                        class="py-2 px-4 rounded-lg font-medium transition-colors duration-200"
                        :style="{
                            backgroundColor: derivedColors.surface + '80',
                            color: derivedColors.textPrimary
                        }"
                        @click="closeCancelModal"
                    >
                        {{ t('keepWithdrawal') }}
                    </button>
                    <button
                        :disabled="isCancelling"
                        class="bg-red-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 flex items-center"
                        @click="confirmCancelWithdrawal"
                    >
                        <svg
                            v-if="isCancelling"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
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
                        {{ isCancelling ? t('cancelling') : t('yesCancelWithdrawal') }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="detailsModalTitleId"
            @click.self="closeModal"
            @keydown.escape="closeModal"
        >
            <div
                class="rounded-2xl shadow-2xl border max-w-2xl w-full max-h-[90vh] overflow-y-auto backdrop-blur-md"
                :style="{ backgroundColor: derivedColors.background + '95', borderColor: derivedColors.border + '50' }"
            >
                <div class="px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="detailsModalTitleId"
                            class="text-xl font-semibold"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('withdrawalDetails') }}
                        </h3>
                        <button
                            class="p-1 transition-colors"
                            :style="{ color: derivedColors.textMuted }"
                            @click="closeModal"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
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

                <div
                    v-if="selectedWithdrawal"
                    class="p-6 space-y-6"
                >
                    <div class="rounded-xl p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('transactionInformation') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('transactionId') }}</label>
                                <p class="font-mono text-lg" :style="{ color: derivedColors.accent }">
                                    {{ selectedWithdrawal.trx }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('status') }}</label>
                                <div class="mt-1">
                                    <span
                                        :class="getStatusBadgeClasses(selectedWithdrawal.status)"
                                        :style="{
                                            backgroundColor: getStatusBadgeColor(selectedWithdrawal.status) + '20',
                                            borderColor: getStatusBadgeColor(selectedWithdrawal.status) + '30',
                                            color: getStatusBadgeColor(selectedWithdrawal.status)
                                        }"
                                    >
                                        {{ formatStatus(selectedWithdrawal.status) }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('withdrawalGateway') }}</label>
                                <p class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ selectedWithdrawal.withdrawal_gateway?.name || t('notAvailable') }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{ t('currency') }}</label>
                                <p class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ selectedWithdrawal.currency || 'USD' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('amountDetails') }}
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('requestedAmount') }}:</span>
                                <span class="font-medium text-lg" :style="{ color: derivedColors.textPrimary }">
                                    {{ getWithdrawalCurrencySymbol(selectedWithdrawal) }}{{ formatNumber(selectedWithdrawal.amount) }}
                                </span>
                            </div>

                            <div
                                v-if="selectedWithdrawal.charge > 0"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('processingFee') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.warning }">
                                    {{ getWithdrawalCurrencySymbol(selectedWithdrawal) }}{{ formatNumber(selectedWithdrawal.charge) }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-t pt-3" :style="{ borderColor: derivedColors.border }">
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ t('youReceived') }}:</span>
                                <span class="font-bold text-xl" :style="{ color: derivedColors.accent }">
                                    {{ getWithdrawalCurrencySymbol(selectedWithdrawal) }}{{ formatNumber(selectedWithdrawal.final_amount) }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center rounded-lg p-3 mt-4" :style="{ backgroundColor: derivedColors.border + '30' }">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('deductedFromWallet') }}:</span>
                                <span class="font-bold text-red-300">
                                    {{ currencySymbol }}{{ formatNumber(selectedWithdrawal.withdrawal_amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('timeline') }}
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('created') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ formatDateTime(selectedWithdrawal.created_at) }}</span>
                            </div>
                            <div
                                v-if="selectedWithdrawal.approved_at"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('approved') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.success }">{{ formatDateTime(selectedWithdrawal.approved_at) }}</span>
                            </div>
                            <div
                                v-if="selectedWithdrawal.rejected_at"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('rejected') }}:</span>
                                <span class="font-medium text-red-400">{{ formatDateTime(selectedWithdrawal.rejected_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedWithdrawal.admin_response"
                        class="rounded-xl p-5 border"
                        :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                    >
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('adminResponse') }}
                        </h4>
                        <p class="leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                            {{ selectedWithdrawal.admin_response }}
                        </p>
                    </div>

                    <div
                        v-if="selectedWithdrawal.user_data && Object.keys(selectedWithdrawal.user_data).length > 0"
                        class="rounded-xl p-5 border"
                        :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                    >
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('withdrawalDetails') }}
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="(value, key) in selectedWithdrawal.user_data"
                                :key="key"
                                class="flex justify-between items-start"
                            >
                                <span class="capitalize min-w-[140px]" :style="{ color: derivedColors.textMuted }">{{ formatFieldName(key) }}:</span>
                                <span class="font-medium break-all text-right flex-1 ml-2" :style="{ color: derivedColors.textPrimary }">
                                    <template v-if="value && String(value).includes('withdrawals/')">
                                        <div class="flex items-center gap-2 justify-end">
                                            <a
                                                :href="`/user/wallet/withdraw/${selectedWithdrawal.id}/view/${key}`"
                                                target="_blank"
                                                class="inline-flex items-center px-3 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors"
                                            >
                                                <svg
                                                    class="w-3 h-3 mr-1"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                    />
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                    />
                                                </svg>
                                                {{ t('view') }}
                                            </a>
                                            <a
                                                :href="`/user/wallet/withdraw/${selectedWithdrawal.id}/download/${key}`"
                                                class="inline-flex items-center px-3 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded transition-colors"
                                            >
                                                <svg
                                                    class="w-3 h-3 mr-1"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                    />
                                                </svg>
                                                {{ t('download') }}
                                            </a>
                                        </div>
                                    </template>
                                    <template v-else>
                                        {{ value || t('notAvailable') }}
                                    </template>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedWithdrawal.transaction_id"
                        class="rounded-xl p-5 border"
                        :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                    >
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('paymentTransactionId') }}
                        </h4>
                        <p class="font-mono break-all text-lg" :style="{ color: derivedColors.accent }">
                            {{ selectedWithdrawal.transaction_id }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end p-6 border-t" :style="{ borderColor: derivedColors.border + '50' }">
                    <button
                        class="py-3 px-6 rounded-xl font-medium transition-colors duration-200"
                        :style="{
                            backgroundColor: derivedColors.surface + '80',
                            color: derivedColors.textPrimary
                        }"
                        @click="closeModal"
                    >
                        {{ t('close') }}
                    </button>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
