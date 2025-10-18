<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    deposits: {
        type: Object,
        required: true,
        default: () => ({ data: [], total: 0, links: [] }),
        validator: (value) => value && typeof value === 'object' && Array.isArray(value.data)
    },
    paymentGateways: {
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

const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ALLOWED_FILE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
const STRIPE_SCRIPT_URL = 'https://js.stripe.com/v3/';

const selectedGateway = ref(null);
const isProcessing = ref(false);
const paymentDetails = ref({});
const showModal = ref(false);
const selectedDeposit = ref(null);
const stripe = ref(null);
const stripeCard = ref(null);

const depositForm = ref({
    amount: null,
    payment_gateway_id: null
});
const selectedCryptoCurrency = ref('btc');
const availableCryptos = ref([
    { code: 'btc', name: 'Bitcoin', symbol: '₿' },
    { code: 'eth', name: 'Ethereum', symbol: 'Ξ' },
    { code: 'ltc', name: 'Litecoin', symbol: 'Ł' },
    { code: 'usdt', name: 'Tether (USDT)', symbol: '₮' },
    { code: 'usdc', name: 'USD Coin', symbol: '$' },
    { code: 'bnb', name: 'Binance Coin', symbol: 'BNB' },
    { code: 'trx', name: 'Tron', symbol: 'TRX' },
    { code: 'doge', name: 'Dogecoin', symbol: 'Ð' },
    { code: 'ada', name: 'Cardano', symbol: '₳' },
    { code: 'xrp', name: 'Ripple', symbol: 'XRP' }
]);

const {showToast} = useToast();
const {t} = useTranslation();
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
        warning: '#f59e0b'
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
const currencyName = computed(() => page.props.currencyName || '$');
const detailsModalTitleId = computed(() => 'details-modal-title-' + Math.random().toString(36).substr(2, 9));
const isSecureConnection = computed(() => {
    if (typeof window === 'undefined') return false;
    return (
        window.location.protocol === 'https:' ||
        window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1' ||
        window.location.hostname.startsWith('192.168.') ||
        window.location.hostname.endsWith('.local')
    );
});

const isValidAmount = computed(() => {
    try {
        if (!selectedGateway.value) return false;

        const amount = parseFloat(depositForm.value.amount);
        if (isNaN(amount) || amount <= 0) return false;

        const minAmount = parseFloat(selectedGateway.value.min_amount || 0);
        const maxAmount = parseFloat(selectedGateway.value.max_amount || Number.MAX_SAFE_INTEGER);

        if (isNaN(minAmount) || isNaN(maxAmount)) return false;

        return amount >= minAmount && amount <= maxAmount;
    } catch (error) {
        console.error('Amount validation error:', error);
        return false;
    }
});

const calculatedCharge = computed(() => {
    try {
        if (!selectedGateway.value || !isValidAmount.value) return 0;

        const amount = parseFloat(depositForm.value.amount);
        if (isNaN(amount)) return 0;

        const fixedCharge = parseFloat(selectedGateway.value.fixed_charge || 0);
        const percentCharge = parseFloat(selectedGateway.value.percent_charge || 0);

        if (isNaN(fixedCharge) || isNaN(percentCharge)) return 0;

        return fixedCharge + (amount * percentCharge / 100);
    } catch (error) {
        return 0;
    }
});


const totalAmount = computed(() => {
    try {
        if (!isValidAmount.value) return 0;
        const amount = parseFloat(depositForm.value.amount);
        if (isNaN(amount)) return 0;

        return amount + calculatedCharge.value;
    } catch (error) {
        return 0;
    }
});

const canSubmit = computed(() => {
    try {
        if (!isValidAmount.value || isProcessing.value) {
            return false;
        }

        if (selectedGateway.value?.type === 'manual' && selectedGateway.value?.parameters) {
            const requiredFields = selectedGateway.value.parameters.filter(param => param.field_required);

            for (const field of requiredFields) {
                const value = paymentDetails.value[field.field_name];
                if (!value || (typeof value === 'string' && value.trim() === '')) {
                    return false;
                }
            }
        }

        if (selectedGateway.value?.type === 'automatic' && selectedGateway.value?.slug === 'stripe') {
            return isSecureConnection.value && stripeCard.value;
        }

        return true;
    } catch (error) {
        console.error('Submit validation error:', error);
        return false;
    }
});

const successfulDeposits = computed(() => {
    try {
        if (!props.deposits?.data || !Array.isArray(props.deposits.data)) return 0;

        return props.deposits.data.filter(d => {
            const status = d.status?.toLowerCase();
            return ['approved', 'completed', 'success'].includes(status);
        }).length;
    } catch (error) {
        console.error('Successful deposits calculation error:', error);
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
        'BDT': '৳',
        'BTC': '₿',
        'ETH': 'Ξ',
        'INR': '₹',
        'CAD': 'C$',
        'AUD': 'A$',
        'CHF': 'CHF ',
        'CNY': '¥',
        'KRW': '₩',
        'RUB': '₽'
    };

    return currencySymbols[gateway.currency] || gateway.currency + ' ';
};

const getDepositCurrencySymbol = (deposit) => {
    if (!deposit || typeof deposit !== 'object') return '$';

    try {
        const currency = deposit.currency || deposit.payment_gateway?.currency || 'USD';
        return getGatewayCurrencySymbol({currency});
    } catch (error) {
        console.error('Deposit currency symbol error:', error);
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
            showToast(t('invalidPaymentGateway'), 'error');
            return;
        }

        cleanupStripe();

        selectedGateway.value = gateway;
        depositForm.value.payment_gateway_id = gateway.id;
        depositForm.value.amount = null;
        paymentDetails.value = {};

        if (gateway.slug === 'stripe' && gateway.type === 'automatic') {
            if (!isSecureConnection.value) {
                showToast(t('stripeRequiresHTTPS'), 'warning');
                return;
            }

            nextTick(() => {
                initializeStripe(gateway);
            }).catch(error => {
                showToast(t('failedToInitializePayment'), 'error');
            });
        }

        showToast(t('gatewaySelected', {name: gateway.name}), 'info');
    } catch (error) {
        console.error('Gateway selection error:', error);
        showToast(t('failedToSelectGateway'), 'error');
    }
};

const initializeStripe = async (gateway) => {
    try {
        if (!isSecureConnection.value) {
            showToast(t('stripeRequiresHTTPS'), 'error');
            return;
        }

        const cardElement = document.getElementById('stripe-card-element');
        if (!cardElement) {
            showToast(t('paymentFormNotFound'), 'error');
            return;
        }

        if (!window.Stripe) {
            await loadStripeScript();
        }

        await initStripeElements(gateway);
    } catch (error) {
        console.error('Stripe initialization error:', error);
        showToast(t('failedToInitializeStripe'), 'error');
    }
};

const loadStripeScript = () => {
    return new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = STRIPE_SCRIPT_URL;
        script.onload = resolve;
        script.onerror = () => reject(new Error('Failed to load Stripe script'));

        document.head.appendChild(script);
    });
};

const initStripeElements = async (gateway) => {
    try {
        if (!gateway.credentials?.publishable_key) {
            throw new Error('Missing Stripe publishable key');
        }

        stripe.value = window.Stripe(gateway.credentials.publishable_key);
        const elements = stripe.value.elements();

        stripeCard.value = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: derivedColors.value.textPrimary,
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    '::placeholder': {
                        color: derivedColors.value.textMuted
                    }
                },
                invalid: {
                    color: '#ef4444',
                    iconColor: '#ef4444'
                }
            }
        });

        stripeCard.value.mount('#stripe-card-element');

        stripeCard.value.on('change', (event) => {
            const displayError = document.getElementById('stripe-card-errors');
            if (displayError) {
                displayError.textContent = event.error ? event.error.message : '';
            }
        });

    } catch (error) {
        console.error('Stripe elements error:', error);
        throw error;
    }
};

const cleanupStripe = () => {
    try {
        const errorElement = document.getElementById('stripe-card-errors');
        if (errorElement) {
            errorElement.textContent = '';
        }

        if (stripeCard.value) {
            try {
                stripeCard.value.unmount();
            } catch (unmountError) {
                console.log('Stripe card already unmounted:', unmountError.message);
            }
            stripeCard.value = null;
        }

        stripe.value = null;
    } catch (error) {
        console.error('Stripe cleanup error:', error);
    }
};

const handleFileUpload = (event, fieldName) => {
    try {
        const file = event.target.files[0];
        if (!file) return;

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

        if (file.name.length > 255) {
            showToast(t('fileNameTooLong'), 'error');
            event.target.value = '';
            return;
        }

        const suspiciousPatterns = /\.(exe|bat|cmd|scr|vbs|js)$/i;
        if (suspiciousPatterns.test(file.name)) {
            showToast(t('fileTypeNotAllowed'), 'error');
            event.target.value = '';
            return;
        }

        paymentDetails.value[fieldName] = file;
        showToast(t('fileSelectedSuccessfully', {name: file.name}), 'success');
    } catch (error) {
        console.error('File upload error:', error);
        showToast(t('fileUploadFailed'), 'error');
        event.target.value = '';
    }
};

const validateDepositAmount = (amount) => {
    try {
        if (!amount || amount <= 0) return t('amountMustBeGreaterThanZero');
        if (!selectedGateway.value) return t('selectPaymentGateway');

        const numAmount = parseFloat(amount);
        if (isNaN(numAmount)) return t('invalidAmountFormat');

        const minAmount = parseFloat(selectedGateway.value.min_amount || 0);
        const maxAmount = parseFloat(selectedGateway.value.max_amount || Number.MAX_SAFE_INTEGER);

        if (isNaN(minAmount) || isNaN(maxAmount)) {
            return t('invalidGatewayConfiguration');
        }

        if (numAmount < minAmount) {
            return t('minimumDepositAmount', {
                amount: getGatewayCurrencySymbol(selectedGateway.value) + formatNumber(minAmount)
            });
        }
        if (numAmount > maxAmount) {
            return t('maximumDepositAmount', {
                amount: getGatewayCurrencySymbol(selectedGateway.value) + formatNumber(maxAmount)
            });
        }

        return null;
    } catch (error) {
        console.error('Amount validation error:', error);
        return t('validationErrorOccurred');
    }
};

const copyAllCredentials = async (credentials) => {
    try {
        const text = Object.entries(credentials)
            .map(([key, value]) => `${formatFieldName(key)}: ${value}`)
            .join('\n');

        await copyToClipboard(text, 'All credentials');
    } catch (error) {
        console.error('Copy all credentials error:', error);
        showToast(t('failedToCopy'), 'error');
    }
};

const submitDeposit = async () => {
    if (!canSubmit.value) {
        showToast(t('ensureRequiredFieldsFilled'), 'error');
        return;
    }

    try {
        const validationError = validateDepositAmount(depositForm.value.amount);
        if (validationError) {
            showToast(validationError, 'error');
            return;
        }

        if (totalAmount.value <= 0) {
            showToast(t('totalAmountMustBeGreaterThanZero'), 'error');
            return;
        }

        isProcessing.value = true;

        if (selectedGateway.value.type === 'automatic' && selectedGateway.value.slug === 'stripe') {
            await submitStripePayment();
        } else if (selectedGateway.value.type === 'automatic' && selectedGateway.value.slug === 'nowpayments') {
            await submitNowPaymentsPayment();
        } else {
            await submitManualPayment();
        }
    } catch (error) {
        console.error('Deposit submission error:', error);
        showToast(t('depositSubmissionFailed'), 'error');
    } finally {
        isProcessing.value = false;
    }
};

const submitNowPaymentsPayment = async () => {
    try {
        const submitData = {
            payment_gateway_id: depositForm.value.payment_gateway_id,
            amount: depositForm.value.amount,
            selected_currency: selectedCryptoCurrency.value,
            payment_details: paymentDetails.value || {}
        };

        router.post('/user/wallet/deposit', submitData, {
            onSuccess: (page) => {
                resetForm();
                showToast(t('depositRequestSubmitted'), 'success');
            },
            onError: (errors) => {
                console.error('NowPayments failed:', errors);
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('paymentFailed');
                showToast(errorMessage, 'error');
            },
            preserveScroll: true
        });
    } catch (error) {
        console.error('NowPayments error:', error);
        showToast(t('paymentProcessingFailed', {message: error.message}), 'error');
    }
};

const submitStripePayment = async () => {
    try {
        if (!stripe.value || !stripeCard.value) {
            throw new Error('Stripe not properly initialized');
        }

        const {error: stripeError, paymentMethod} = await stripe.value.createPaymentMethod({
            type: 'card',
            card: stripeCard.value,
        });

        if (stripeError) {
            throw new Error(stripeError.message);
        }

        const submitData = {
            payment_gateway_id: depositForm.value.payment_gateway_id,
            amount: depositForm.value.amount,
            payment_method_id: paymentMethod.id,
            payment_details: paymentDetails.value || {}
        };

        router.post('/user/wallet/deposit', submitData, {
            onSuccess: () => {
                resetForm();
                showToast(t('paymentProcessedSuccessfully'), 'success');
            },
            onError: (errors) => {
                console.error('Stripe payment failed:', errors);
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('paymentFailed');
                showToast(errorMessage, 'error');
            },
            preserveScroll: true
        });
    } catch (error) {
        console.error('Stripe payment error:', error);
        showToast(t('paymentProcessingFailed', {message: error.message}), 'error');
    }
};

const submitManualPayment = async () => {
    try {
        const formData = new FormData();
        formData.append('payment_gateway_id', depositForm.value.payment_gateway_id);
        formData.append('amount', depositForm.value.amount);
        Object.keys(paymentDetails.value).forEach(key => {
            const value = paymentDetails.value[key];
            if (value instanceof File) {
                if (value.size > MAX_FILE_SIZE) {
                    throw new Error('File size exceeds limit');
                }
                if (!ALLOWED_FILE_TYPES.includes(value.type)) {
                    throw new Error('Invalid file type');
                }
                formData.append(`payment_details[${key}]`, value);
            } else if (value !== null && value !== undefined && value !== '') {
                const sanitizedValue = String(value).trim();
                if (sanitizedValue) {
                    formData.append(`payment_details[${key}]`, sanitizedValue);
                }
            }
        });

        router.post('/user/wallet/deposit', formData, {
            onSuccess: () => {
                resetForm();
                showToast(t('depositRequestSubmitted'), 'success');
            },
            onError: (errors) => {
                console.error('Manual payment failed:', errors);
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('depositSubmissionFailed');
                showToast(errorMessage, 'error');
            },
            preserveScroll: true,
            forceFormData: true
        });
    } catch (error) {
        console.error('Manual payment error:', error);
        showToast(t('depositSubmissionFailed', {message: error.message}), 'error');
    }
};

const showDepositDetails = (deposit) => {
    try {
        if (!deposit?.id) {
            showToast(t('invalidDepositSelected'), 'error');
            return;
        }
        selectedDeposit.value = deposit;
        showModal.value = true;
    } catch (error) {
        console.error('Show deposit details error:', error);
        showToast(t('failedToShowDepositDetails'), 'error');
    }
};


const closeModal = () => {
    try {
        showModal.value = false;
        setTimeout(() => {
            selectedDeposit.value = null;
        }, 300);
    } catch (error) {
        console.error('Close modal error:', error);
    }
};


const resetForm = () => {
    try {
        cleanupStripe();
        selectedGateway.value = null;
        depositForm.value = {amount: null, payment_gateway_id: null};
        paymentDetails.value = {};

        if (stripeCard.value) {
            stripeCard.value.clear();
        }
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
                console.error('Pagination error:', errors);
                showToast(t('failedToLoadPage'), 'error');
            }
        });
    } catch (error) {
        console.error('Navigation error:', error);
        showToast(t('navigationFailed'), 'error');
    }
};


const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        if (showModal.value) {
            closeModal();
        }
    }
};

const copyToClipboard = async (text, label = '') => {
    try {
        const cleanText = text.replace(/<[^>]*>/g, '');

        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(cleanText);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = cleanText;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            const successful = document.execCommand('copy');
            textArea.remove();

            if (!successful) {
                throw new Error('Copy command failed');
            }
        }

        showToast(`Text copied to clipboard successfully!`, 'success');
    } catch (error) {
        console.error('Copy failed:', error);
        showToast('Failed to copy to clipboard', 'error');
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
        if (props.paymentGateways.some(g => g.slug === 'stripe') && !isSecureConnection.value) {
            showToast(t('stripeRequiresHTTPS'), 'warning');
        }

        if (!Array.isArray(props.paymentGateways)) {
            console.error('Invalid paymentGateways prop');
        }

        if (!props.deposits || typeof props.deposits !== 'object') {
            console.error('Invalid deposits prop');
        }
    } catch (error) {
        console.error('Mount error:', error);
        showToast(t('applicationInitializationFailed'), 'error');
    }
});

onUnmounted(() => {
    try {
        document.removeEventListener('keydown', handleKeydown);
        cleanupStripe();
        isProcessing.value = false;
    } catch (error) {
        console.error('Unmount cleanup error:', error);
    }
});
</script>

<template>
    <UserLayout
        :page-title="t('deposit')"
        :page-section="t('wallet')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-6" :style="cardStyle">
                        <h2 class="text-lg font-semibold mb-4 flex items-center"
                            :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('selectPaymentMethod') }}
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                            <div
                                v-for="gateway in paymentGateways"
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
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-md"
                                         :style="{ backgroundColor: derivedColors.accent }">
                                        <span class="font-bold text-sm" :style="{ color: derivedColors.background }">{{
                                                gateway.name.substring(0, 2)
                                            }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-bold text-sm" :style="{ color: derivedColors.textPrimary }">
                                            {{ gateway.name }}
                                        </h3>
                                        <div class="text-xs font-medium" :style="{ color: derivedColors.accent }">
                                            {{
                                                gateway.fixed_charge > 0 ? currencySymbol + formatNumber(gateway.fixed_charge) + ' + ' : ''
                                            }}{{ formatNumber(gateway.percent_charge) }}% {{ t('fee') }}
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
                            v-if="selectedGateway && selectedGateway.type === 'manual' && selectedGateway.description"
                            class="rounded-xl p-4 border mb-6"
                            :style="{
        backgroundColor: derivedColors.surface + '30',
        borderColor: derivedColors.border + '30'
    }"
                        >
                            <h4 class="font-medium flex items-center mb-3" :style="{ color: derivedColors.textPrimary }">
                                <div class="w-2 h-2 rounded-full mr-3"
                                     :style="{ backgroundColor: derivedColors.accent }"></div>
                                {{ t('paymentInstructions') }}
                            </h4>

                            <div
                                class="text-sm leading-relaxed prose prose-sm max-w-none mb-4"
                                :style="{ color: derivedColors.textSecondary }"
                                v-html="selectedGateway.description"
                            />

                            <!-- Payment Credentials Section -->
                            <div v-if="selectedGateway.credentials && Object.keys(selectedGateway.credentials).length > 0"
                                 class="rounded-lg p-4 border space-y-3"
                                 :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '40' }">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('paymentCredentials') }}
                                    </h5>
                                    <button
                                        class="inline-flex items-center px-2 py-1 text-xs rounded transition-colors hover:opacity-80"
                                        :style="{ backgroundColor: derivedColors.accent + '20', color: derivedColors.accent }"
                                        @click="copyAllCredentials(selectedGateway.credentials)"
                                    >
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        {{ t('copyAll') }}
                                    </button>
                                </div>

                                <div
                                    v-for="(value, key) in selectedGateway.credentials"
                                    :key="key"
                                    class="flex items-center justify-between p-3 rounded-lg border group hover:shadow-sm transition-all"
                                    :style="{ backgroundColor: derivedColors.background + '30', borderColor: derivedColors.border + '30' }"
                                >
                                    <div class="flex-1 min-w-0 mr-3">
                                        <div class="text-xs font-medium uppercase mb-1" :style="{ color: derivedColors.textMuted }">
                                            {{ formatFieldName(key) }}
                                        </div>
                                        <div class="font-mono text-sm break-all" :style="{ color: derivedColors.textPrimary }">
                                            {{ value }}
                                        </div>
                                    </div>
                                    <button
                                        class="flex-shrink-0 p-2 rounded-lg transition-all opacity-0 group-hover:opacity-100"
                                        :style="{ backgroundColor: derivedColors.accent + '20', color: derivedColors.accent }"
                                        @click="copyToClipboard(value, formatFieldName(key))"
                                        :title="`Copy ${formatFieldName(key)}`"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div v-if="selectedGateway.file" class="mt-4 pt-4 border-t"
                                 :style="{ borderColor: derivedColors.border + '20' }">
                                <img
                                    :src="`/assets/files/${selectedGateway.file}`"
                                    :alt="selectedGateway.name"
                                    class="w-full max-w-md rounded-lg border"
                                    :style="{ borderColor: derivedColors.border + '30' }"
                                />
                            </div>
                        </div>

                        <div
                            v-if="selectedGateway"
                            class="border-t pt-6"
                            :style="{ borderColor: derivedColors.border + '20' }"
                        >
                            <form
                                class="space-y-6"
                                novalidate
                                @submit.prevent="submitDeposit"
                            >
                                <div>
                                    <label class="block font-medium mb-3" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('depositAmount', {currency: selectedGateway.currency}) }}
                                    </label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-4 top-1/2 transform -translate-y-1/2 font-bold text-lg"
                                            :style="{ color: derivedColors.accent }">{{
                                                getGatewayCurrencySymbol(selectedGateway)
                                            }}</span>
                                        <input
                                            v-model.number="depositForm.amount"
                                            type="number"
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
                                        {{
                                            t('minMax', {
                                                min: getGatewayCurrencySymbol(selectedGateway) + formatNumber(selectedGateway.min_amount),
                                                max: getGatewayCurrencySymbol(selectedGateway) + formatNumber(selectedGateway.max_amount)
                                            })
                                        }}
                                    </div>
                                    <div class="text-sm mt-1" :style="{ color: derivedColors.accent }">
                                        {{ t('currentBalance') }}: {{ currencySymbol }}{{ formatNumber(walletBalance) }}
                                    </div>
                                </div>

                                <div v-if="selectedGateway.type === 'automatic' && selectedGateway.slug === 'nowpayments'">
                                    <label class="block font-medium mb-3" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('selectCryptocurrency') }}
                                    </label>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                        <div
                                            v-for="crypto in availableCryptos"
                                            :key="crypto.code"
                                            tabindex="0"
                                            role="button"
                                            :aria-label="`Select ${crypto.name}`"
                                            :class="[
                                                'border rounded-lg p-3 cursor-pointer transition-all duration-200 hover:scale-[1.02] transform focus:outline-none focus:ring-2',
                                                selectedCryptoCurrency === crypto.code
                                                    ? 'shadow-lg'
                                                    : 'hover:shadow-md'
                                            ]"
                                            :style="{
                                                borderColor: selectedCryptoCurrency === crypto.code ? derivedColors.accent + '50' : derivedColors.border + '30',
                                                backgroundColor: selectedCryptoCurrency === crypto.code ? derivedColors.surface + '80' : derivedColors.surface + '50',
                                            }"
                                            @click="selectedCryptoCurrency = crypto.code"
                                            @keydown.enter="selectedCryptoCurrency = crypto.code"
                                            @keydown.space.prevent="selectedCryptoCurrency = crypto.code"
                                        >
                                            <div class="flex flex-col items-center space-y-2">
                                                <div
                                                    class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold"
                                                    :style="{
                                                        backgroundColor: selectedCryptoCurrency === crypto.code ? derivedColors.accent : derivedColors.border + '30',
                                                        color: selectedCryptoCurrency === crypto.code ? derivedColors.background : derivedColors.textPrimary
                                                    }"
                                                >
                                                    {{ crypto.symbol }}
                                                </div>
                                                <div class="text-center">
                                                    <div class="text-xs font-bold uppercase" :style="{ color: derivedColors.textPrimary }">
                                                        {{ crypto.code }}
                                                    </div>
                                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                                        {{ crypto.name }}
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="selectedCryptoCurrency === crypto.code"
                                                    class="w-5 h-5"
                                                    :style="{ color: derivedColors.accent }"
                                                >
                                                    <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
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

                                    <div class="mt-4 p-4 rounded-lg border" :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border + '30' }">
                                        <div class="flex items-start space-x-3">
                                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                                <p class="font-medium mb-1">{{ t('cryptoPaymentInfo') }}</p>
                                                <p>{{ t('cryptoPaymentDescription') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="selectedGateway.type === 'manual' && selectedGateway.parameters && selectedGateway.parameters.length > 0"
                                    class="space-y-4"
                                >
                                    <h4 class="font-medium flex items-center"
                                        :style="{ color: derivedColors.textPrimary }">
                                        <div class="w-2 h-2 rounded-full mr-3"
                                             :style="{ backgroundColor: derivedColors.accent }"></div>
                                        {{ t('paymentInformation') }}
                                    </h4>

                                    <div
                                        v-for="param in selectedGateway.parameters"
                                        :key="param.field_name"
                                        class="space-y-2"
                                    >
                                        <label class="block text-sm font-medium"
                                               :style="{ color: derivedColors.textSecondary }">
                                            {{ param.field_label }}
                                            <span
                                                v-if="param.field_required"
                                                class="text-red-400"
                                            >*</span>
                                        </label>

                                        <template v-if="['text', 'email', 'tel'].includes(param.field_type)">
                                            <input
                                                v-model="paymentDetails[param.field_name]"
                                                :type="param.field_type"
                                                :placeholder="param.field_placeholder"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                        </template>

                                        <template v-else-if="['date', 'datetime-local'].includes(param.field_type)">
                                            <input
                                                v-model="paymentDetails[param.field_name]"
                                                :type="param.field_type"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                        </template>

                                        <template v-else-if="param.field_type === 'number'">
                                            <input
                                                v-model.number="paymentDetails[param.field_name]"
                                                type="number"
                                                :placeholder="param.field_placeholder"
                                                :required="param.field_required"
                                                class="w-full px-4 py-3 border rounded-lg placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            >
                                        </template>

                                        <template v-else-if="param.field_type === 'select'">
                                            <select
                                                v-model="paymentDetails[param.field_name]"
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

                                <div v-if="selectedGateway.type === 'automatic' && selectedGateway.slug === 'stripe'">
                                    <div v-show="isSecureConnection">
                                        <div
                                            id="stripe-card-element"
                                            class="p-4 border rounded-xl"
                                            :style="{ ...inputStyle }"
                                        />
                                        <div
                                            id="stripe-card-errors"
                                            class="text-red-400 text-sm mt-2"
                                        />
                                    </div>
                                    <div
                                        v-if="!isSecureConnection"
                                        class="text-red-400 text-sm"
                                    >
                                        {{ t('stripeRequiresHTTPS') }}
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
                                    >{{ selectedGateway.type === 'manual' ? t('submitRequest') : t('payNow') }}</span>
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
                        <h3 class="text-lg font-semibold mb-4 flex items-center"
                            :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('paymentSummary') }}
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('amount') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{
                                        getGatewayCurrencySymbol(selectedGateway)
                                    }}{{ formatNumber(depositForm.amount) }}</span>
                            </div>
                            <div
                                v-if="calculatedCharge > 0"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('processingFee') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.warning }">{{
                                        getGatewayCurrencySymbol(selectedGateway)
                                    }}{{ formatNumber(calculatedCharge) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-t pt-3"
                                 :style="{ borderColor: derivedColors.border + '20' }">
                                <span class="font-medium"
                                      :style="{ color: derivedColors.textMuted }">{{ t('totalAmount') }}:</span>
                                <span class="font-bold text-xl" :style="{ color: derivedColors.accent }">{{
                                        getGatewayCurrencySymbol(selectedGateway)
                                    }}{{ formatNumber(totalAmount) }}</span>
                            </div>
                            <div class="rounded-lg p-3 mt-3" :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="text-xs mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('youWillReceive') }}:
                                </div>
                                <div class="font-semibold" :style="{ color: derivedColors.accent }">
                                    {{ currencySymbol }}{{ formatNumber(depositForm.amount) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedGateway"
                        class="backdrop-blur-sm rounded-xl shadow-lg border p-6"
                        :style="cardStyle"
                    >
                        <h3 class="text-lg font-semibold mb-4 flex items-center"
                            :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('gatewayInformation') }}
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('gateway') }}:</span>
                                <span class="font-medium"
                                      :style="{ color: derivedColors.textPrimary }">{{ selectedGateway.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('currency') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{
                                        selectedGateway.currency
                                    }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('type') }}:</span>
                                <span class="font-medium capitalize"
                                      :style="{ color: derivedColors.textPrimary }">{{ selectedGateway.type }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('processing') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{
                                        selectedGateway.type === 'automatic' ? t('instant') : t('manualReview')
                                    }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4 flex items-center"
                            :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('depositStats') }}
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold" :style="{ color: derivedColors.accent }">
                                    {{ deposits?.total || 0 }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('totalDeposits') }}
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold" :style="{ color: derivedColors.success }">
                                    {{ successfulDeposits }}
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
                    <div
                        class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-2 sm:space-y-0">
                        <div>
                            <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('depositHistory') }}
                            </h2>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('trackDepositTransactions') }}
                            </p>
                        </div>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ deposits?.total || 0 }} {{ t('totalDepositsCount') }}
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <!-- Mobile Cards View -->
                    <div class="block lg:hidden">
                        <!-- Empty State for Mobile -->
                        <div v-if="!deposits?.data || deposits.data.length === 0" class="text-center py-8 px-4">
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
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                    />
                                </svg>
                                <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                    {{ t('noDepositsFound') }}
                                </p>
                                <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('makeFirstDeposit') }}
                                </p>
                            </div>
                        </div>

                        <!-- Mobile Cards -->
                        <div
                            v-for="deposit in deposits?.data"
                            :key="deposit.id"
                            class="border-b p-4 space-y-3 hover:opacity-80 transition-colors"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <!-- Header Row -->
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium font-mono"
                                         :style="{ color: derivedColors.textPrimary }">
                                        {{ deposit.trx }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(deposit.created_at) }}
                                    </div>
                                </div>
                                <span
                                    :class="getStatusBadgeClasses()"
                                    :style="{
                        backgroundColor: getStatusBadgeColor(deposit.status) + '20',
                        borderColor: getStatusBadgeColor(deposit.status) + '30',
                        color: getStatusBadgeColor(deposit.status)
                    }"
                                >
                    {{ formatStatus(deposit.status) }}
                </span>
                            </div>

                            <!-- Amount Info Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs uppercase font-medium"
                                         :style="{ color: derivedColors.textMuted }">
                                        {{ t('amount') }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ getDepositCurrencySymbol(deposit) }}{{ formatNumber(deposit.amount) }}
                                    </div>
                                    <div
                                        v-if="deposit.charge > 0"
                                        class="text-xs"
                                        :style="{ color: derivedColors.warning }"
                                    >
                                        +{{ getDepositCurrencySymbol(deposit) }}{{ formatNumber(deposit.charge) }}
                                        {{ t('fee') }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-medium"
                                         :style="{ color: derivedColors.textMuted }">
                                        {{ t('depositAmount', {currency: currencyName}) }}
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: derivedColors.accent }">
                                        {{ currencySymbol }}{{ formatNumber(deposit.deposit_amount) }}
                                    </div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('toWallet') }}
                                    </div>
                                </div>
                            </div>

                            <div class="bg-opacity-50 rounded-lg p-3"
                                 :style="{ backgroundColor: derivedColors.surface + '30' }">
                                <div class="text-xs uppercase font-medium mb-1"
                                     :style="{ color: derivedColors.textMuted }">
                                    {{ t('gateway') }}
                                </div>
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ deposit.payment_gateway?.name || t('notAvailable') }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ deposit.currency }}
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button
                                    class="text-sm font-medium transition-colors duration-200 px-3 py-2 rounded-lg"
                                    :style="{
                        color: derivedColors.accent,
                        backgroundColor: derivedColors.accent + '10'
                    }"
                                    @click="showDepositDetails(deposit)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <table class="w-full hidden lg:table">
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('trxId') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('amount') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('depositAmount', {currency: currencyName}) }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('gateway') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('status') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('date') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase"
                                :style="{ color: derivedColors.textSecondary }">
                                {{ t('actions') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody class="divide-y" :style="{ borderColor: derivedColors.border + '50' }">
                        <tr v-if="!deposits?.data || deposits.data.length === 0">
                            <td
                                colspan="7"
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
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                        />
                                    </svg>
                                    <p class="text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                                        {{ t('noDepositsFound') }}
                                    </p>
                                    <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('makeFirstDeposit') }}
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="deposit in deposits?.data"
                            :key="deposit.id"
                            class="hover:opacity-80 transition-colors"
                        >
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium font-mono"
                                     :style="{ color: derivedColors.textPrimary }">
                                    {{ deposit.trx }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ getDepositCurrencySymbol(deposit) }}{{ formatNumber(deposit.amount) }}
                                </div>
                                <div
                                    v-if="deposit.charge > 0"
                                    class="text-xs"
                                    :style="{ color: derivedColors.warning }"
                                >
                                    +{{ getDepositCurrencySymbol(deposit) }}{{ formatNumber(deposit.charge) }}
                                    {{ t('fee') }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold" :style="{ color: derivedColors.accent }">
                                    {{ currencySymbol }}{{ formatNumber(deposit.deposit_amount) }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('toWallet') }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ deposit.payment_gateway?.name || t('notAvailable') }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ deposit.currency }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <span
                    :class="getStatusBadgeClasses()"
                    :style="{
                        backgroundColor: getStatusBadgeColor(deposit.status) + '20',
                        borderColor: getStatusBadgeColor(deposit.status) + '30',
                        color: getStatusBadgeColor(deposit.status)
                    }"
                >
                    {{ formatStatus(deposit.status) }}
                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <div :style="{ color: derivedColors.textSecondary }">{{
                                        formatDate(deposit.created_at)
                                    }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatTime(deposit.created_at) }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                <button
                                    class="font-medium transition-colors duration-200"
                                    :style="{ color: derivedColors.accent }"
                                    @click="showDepositDetails(deposit)"
                                >
                                    {{ t('viewDetails') }}
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="deposits?.data && deposits.data.length > 0 && deposits.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20' }"
                >
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{
                                t('showingResults', {
                                    from: deposits.from || 0,
                                    to: deposits.to || 0,
                                    total: deposits.total || 0
                                })
                            }}
                        </div>
                        <div class="flex flex-wrap justify-center sm:justify-end space-x-1">
                            <button
                                v-for="(link, index) in deposits.links"
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
                            {{ t('depositDetails') }}
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
                    v-if="selectedDeposit"
                    class="p-6 space-y-6"
                >
                    <div class="rounded-xl p-5 border"
                         :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('transactionInformation') }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm block"
                                       :style="{ color: derivedColors.textMuted }">{{ t('transactionId') }}</label>
                                <p class="font-mono text-lg" :style="{ color: derivedColors.accent }">
                                    {{ selectedDeposit.trx }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm block" :style="{ color: derivedColors.textMuted }">{{
                                        t('status')
                                    }}</label>
                                <div class="mt-1">
                                    <span
                                        :class="getStatusBadgeClasses()"
                                        :style="{
                                            backgroundColor: getStatusBadgeColor(selectedDeposit.status) + '20',
                                            borderColor: getStatusBadgeColor(selectedDeposit.status) + '30',
                                            color: getStatusBadgeColor(selectedDeposit.status)
                                        }"
                                    >
                                        {{ formatStatus(selectedDeposit.status) }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm block"
                                       :style="{ color: derivedColors.textMuted }">{{ t('paymentGateway') }}</label>
                                <p class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ selectedDeposit.payment_gateway?.name || t('notAvailable') }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm block"
                                       :style="{ color: derivedColors.textMuted }">{{ t('currency') }}</label>
                                <p class="font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ selectedDeposit.currency || 'USD' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl p-5 border"
                         :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('amountDetails') }}
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('requestedAmount') }}:</span>
                                <span class="font-medium text-lg" :style="{ color: derivedColors.textPrimary }">
                                    {{
                                        getDepositCurrencySymbol(selectedDeposit)
                                    }}{{ formatNumber(selectedDeposit.amount) }}
                                </span>
                            </div>

                            <div
                                v-if="selectedDeposit.charge > 0"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('processingFee') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.warning }">
                                    {{
                                        getDepositCurrencySymbol(selectedDeposit)
                                    }}{{ formatNumber(selectedDeposit.charge) }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center border-t pt-3"
                                 :style="{ borderColor: derivedColors.border }">
                                <span class="font-medium"
                                      :style="{ color: derivedColors.textPrimary }">{{ t('totalPaid') }}:</span>
                                <span class="font-bold text-xl" :style="{ color: derivedColors.accent }">
                                    {{
                                        getDepositCurrencySymbol(selectedDeposit)
                                    }}{{ formatNumber(selectedDeposit.final_amount || selectedDeposit.amount) }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center rounded-lg p-3 mt-4"
                                 :style="{ backgroundColor: derivedColors.border + '30' }">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('addedToWallet') }}:</span>
                                <span class="font-bold" :style="{ color: derivedColors.success }">
                                    {{ currencySymbol }}{{ formatNumber(selectedDeposit.deposit_amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedDeposit.payment_details && Object.keys(selectedDeposit.payment_details).length > 0"
                        class="rounded-xl p-5 border"
                        :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                    >
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('paymentDetails') }}
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="(value, key) in selectedDeposit.payment_details"
                                :key="key"
                                class="flex justify-between items-start"
                            >
                                <span class="capitalize min-w-[140px]"
                                      :style="{ color: derivedColors.textMuted }">{{ formatFieldName(key) }}:</span>
                                <span class="font-medium break-all text-right flex-1 ml-2"
                                      :style="{ color: derivedColors.textPrimary }">
                                    <template v-if="value && key === 'file'">
                                        <div class="flex items-center gap-2 justify-end">
                                            <a
                                                :href="`/user/wallet/deposit/${selectedDeposit.id}/view/${key}`"
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
                                                :href="`/user/wallet/deposit/${selectedDeposit.id}/download/${key}`"
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

                    <div class="rounded-xl p-5 border"
                         :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('timeline') }}
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span :style="{ color: derivedColors.textMuted }">{{ t('created') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{
                                        formatDateTime(selectedDeposit.created_at)
                                    }}</span>
                            </div>
                            <div
                                v-if="selectedDeposit.approved_at"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('approved') }}:</span>
                                <span class="font-medium" :style="{ color: derivedColors.success }">{{
                                        formatDateTime(selectedDeposit.approved_at)
                                    }}</span>
                            </div>
                            <div
                                v-if="selectedDeposit.rejected_at"
                                class="flex justify-between items-center"
                            >
                                <span :style="{ color: derivedColors.textMuted }">{{ t('rejected') }}:</span>
                                <span class="font-medium text-red-400">{{
                                        formatDateTime(selectedDeposit.rejected_at)
                                    }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedDeposit.admin_response"
                        class="rounded-xl p-5 border"
                        :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                    >
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('adminResponse') }}
                        </h4>
                        <p class="leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                            {{ selectedDeposit.admin_response }}
                        </p>
                    </div>

                    <div
                        v-if="selectedDeposit.transaction_id"
                        class="rounded-xl p-5 border"
                        :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }"
                    >
                        <h4 class="font-medium mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3"
                                 :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('paymentTransactionId') }}
                        </h4>
                        <p class="font-mono break-all text-lg" :style="{ color: derivedColors.accent }">
                            {{ selectedDeposit.transaction_id }}
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
