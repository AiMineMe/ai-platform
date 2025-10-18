<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    mainWallet: {
        type: Object,
        default: null,
        validator: (value) => {
            return value === null || (typeof value === 'object' && value.hasOwnProperty('balance'));
        }
    },
    tradeWallet: {
        type: Object,
        default: null,
        validator: (value) => {
            return value === null || (typeof value === 'object' && value.hasOwnProperty('balance'));
        }
    },
    statistics: {
        type: Object,
        default: () => ({
            total_balance: 0,
            main_balance: 0,
            trade_balance: 0,
            wallet_count: 0
        }),
        validator: (value) => {
            const requiredKeys = ['total_balance', 'main_balance', 'trade_balance', 'wallet_count'];
            return requiredKeys.every(key => value.hasOwnProperty(key));
        }
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const MIN_TRANSFER_AMOUNT = 0.01;
const SEARCH_DEBOUNCE_DELAY = 300;
const MIN_SEARCH_LENGTH = 2;
const REFRESH_INTERVAL = 120000;

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
const defaultCurrency = computed(() => page.props.defaultCurrency || 'USD');
const transferModalTitleId = computed(() => 'transfer-modal-title-' + Math.random().toString(36).substr(2, 9));
const userTransferModalTitleId = computed(() => 'user-transfer-modal-title-' + Math.random().toString(36).substr(2, 9));
const isProcessing = ref(false);
const isRefreshing = ref(false);

const transferModal = ref({
    isOpen: false
});

const userTransferModal = ref({
    isOpen: false
});

const transferForm = ref({
    from_type: '',
    to_type: '',
    amount: 0
});

const userTransferForm = ref({
    from_wallet_type: '',
    to_user_id: null,
    to_wallet_type: '',
    amount: 0,
    note: ''
});

const userSearch = ref('');
const searchResults = ref([]);
const selectedUser = ref(null);
const searchTimeout = ref(null);

let refreshInterval = null;
const canTransfer = computed(() => {
    const form = transferForm.value;
    const balance = getWalletBalance(form.from_type);

    return form.amount >= MIN_TRANSFER_AMOUNT &&
        form.amount <= balance &&
        form.from_type !== form.to_type &&
        form.from_type &&
        form.to_type &&
        !isProcessing.value &&
        balance > 0;
});
const canUserTransfer = computed(() => {
    const form = userTransferForm.value;
    const balance = getWalletBalance(form.from_wallet_type);

    return selectedUser.value &&
        form.amount >= MIN_TRANSFER_AMOUNT &&
        form.amount <= balance &&
        form.to_wallet_type &&
        form.from_wallet_type &&
        !isProcessing.value &&
        balance > 0;
});
const formatNumber = (num) => {
    try {
        const number = parseFloat(num) || 0;

        if (!isFinite(number)) return '0';

        if (number >= 1000000000) {
            return (number / 1000000000).toFixed(2) + 'B';
        } else if (number >= 1000000) {
            return (number / 1000000).toFixed(2) + 'M';
        } else if (number >= 1000) {
            return (number / 1000).toFixed(2) + 'K';
        } else if (Number.isInteger(number)) {
            return number.toString();
        } else {
            return number.toFixed(2);
        }
    } catch (err) {
        console.error('Error formatting number:', err);
        return '0';
    }
};


const formatDateTime = (dateString) => {
    if (!dateString) return t('notAvailable');

    try {
        const date = new Date(dateString);

        if (isNaN(date.getTime())) {
            return t('invalidDate');
        }

        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        };

        return date.toLocaleDateString(undefined, options);
    } catch (err) {
        console.error('Error formatting date:', err);
        return t('invalidDate');
    }
};

const getWalletBalance = (type) => {
    try {
        if (type === 'main') {
            return props.mainWallet?.balance || 0;
        } else if (type === 'trade') {
            return props.tradeWallet?.balance || 0;
        }
        return 0;
    } catch (err) {
        console.error('Error getting wallet balance:', err);
        return 0;
    }
};

const getWalletName = (type) => {
    const walletNames = {
        main: t('mainWallet'),
        trade: t('tradeWallet')
    };
    return walletNames[type] || t('unknownWallet');
};

const resetTransferForm = () => {
    transferForm.value = {
        from_type: '',
        to_type: '',
        amount: 0
    };
};

const resetUserTransferForm = () => {
    userTransferForm.value = {
        from_wallet_type: '',
        to_user_id: null,
        to_wallet_type: '',
        amount: 0,
        note: ''
    };
    userSearch.value = '';
    searchResults.value = [];
    selectedUser.value = null;
};

const openTransferModal = (fromType, toType) => {
    try {
        if (!fromType || !toType) {
            return;
        }

        const fromBalance = getWalletBalance(fromType);
        if (!fromBalance || fromBalance <= 0) {
            return;
        }

        if (fromType === toType) {
            return;
        }

        transferForm.value.from_type = fromType;
        transferForm.value.to_type = toType;
        transferForm.value.amount = 0;
        transferModal.value.isOpen = true;
    } catch (err) {
    }
};

const closeTransferModal = () => {
    try {
        transferModal.value.isOpen = false;
        resetTransferForm();
    } catch (err) {
        console.error('Error closing transfer modal:', err);
    }
};

const openUserTransferModal = (fromType) => {
    try {
        if (!fromType) {
            return;
        }
        const fromBalance = getWalletBalance(fromType);
        if (!fromBalance || fromBalance <= 0) {
            return;
        }

        userTransferForm.value.from_wallet_type = fromType;
        userTransferForm.value.to_user_id = null;
        userTransferForm.value.to_wallet_type = '';
        userTransferForm.value.amount = 0;
        userTransferForm.value.note = '';
        userSearch.value = '';
        searchResults.value = [];
        selectedUser.value = null;
        userTransferModal.value.isOpen = true;
    } catch (err) {
        console.error('Error opening user transfer modal:', err);
    }
};


const closeUserTransferModal = () => {
    try {
        userTransferModal.value.isOpen = false;
        resetUserTransferForm();

        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value);
            searchTimeout.value = null;
        }
    } catch (err) {
        console.error('Error closing user transfer modal:', err);
    }
};

const searchUsers = async () => {
    try {
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value);
        }

        if (userSearch.value.length < MIN_SEARCH_LENGTH) {
            searchResults.value = [];
            return;
        }

        searchTimeout.value = setTimeout(async () => {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                if (!csrfToken) {
                    throw new Error('CSRF token not found');
                }

                const response = await fetch('/user/wallet/search-users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        search: userSearch.value.trim()
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                searchResults.value = Array.isArray(data) ? data : [];
            } catch (err) {
                searchResults.value = [];
            }
        }, SEARCH_DEBOUNCE_DELAY);
    } catch (err) {
        console.error('Error in searchUsers:', err);
        searchResults.value = [];
    }
};

const selectUser = (user) => {
    try {
        if (!user || !user.id || !user.name) {
            showToast(t('invalidUserSelected'), 'warning');
            return;
        }

        selectedUser.value = user;
        userTransferForm.value.to_user_id = user.id;
        userSearch.value = user.name;
        searchResults.value = [];
    } catch (err) {
        console.error('Error selecting user:', err);
    }
};

const validateTransferAmount = (amount, fromType) => {
    try {
        if (amount < 0) {
            return t('amountCannotBeNegative');
        }

        if (!amount || amount <= 0) {
            return t('amountMustBeGreaterThanZero');
        }

        if (amount < MIN_TRANSFER_AMOUNT) {
            return t('minimumTransferAmount', {
                amount: currencySymbol.value + MIN_TRANSFER_AMOUNT
            });
        }

        const balance = getWalletBalance(fromType);
        if (amount > balance) {
            return t('insufficientBalance', { wallet: getWalletName(fromType).toLowerCase() });
        }

        return null;
    } catch (err) {
        return t('validationErrorOccurred');
    }
};

const validateUserTransfer = () => {
    const form = userTransferForm.value;

    if (!selectedUser.value) {
        return t('pleaseSelectRecipientUser');
    }

    if (!form.to_wallet_type) {
        return t('pleaseSelectRecipientWalletType');
    }

    if (!form.from_wallet_type) {
        return t('invalidSourceWallet');
    }

    return validateTransferAmount(form.amount, form.from_wallet_type);
};

const submitTransfer = () => {
    try {
        if (!canTransfer.value) {
            return;
        }

        const validationError = validateTransferAmount(transferForm.value.amount, transferForm.value.from_type);
        if (validationError) {
            return;
        }

        if (transferForm.value.from_type === transferForm.value.to_type) {
            return;
        }

        isProcessing.value = true;

        router.post('/user/wallet/transfer', transferForm.value, {
            onSuccess: () => {
                closeTransferModal();
            },
            onError: (errors) => {
                console.error('Transfer errors:', errors);
                const errorMessages = Object.values(errors).flat();
            },
            onFinish: () => {
                isProcessing.value = false;
            },
            preserveScroll: true,
            preserveState: false
        });
    } catch (err) {
        isProcessing.value = false;
    }
};

const submitUserTransfer = () => {
    try {
        if (!canUserTransfer.value) {
            return;
        }

        const validationError = validateUserTransfer();
        if (validationError) {
            return;
        }

        isProcessing.value = true;

        router.post('/user/wallet/transfer-to-user', userTransferForm.value, {
            onSuccess: () => {
                closeUserTransferModal();
            },
            onError: (errors) => {
                console.error('User transfer errors:', errors);
                const errorMessages = Object.values(errors).flat();
                const errorMessage = errorMessages[0] || t('failedToTransferFunds');
            },
            onFinish: () => {
                isProcessing.value = false;
            },
            preserveScroll: true,
            preserveState: false
        });
    } catch (err) {
        isProcessing.value = false;
    }
};

const handleSearchKeydown = (event, index) => {
    const results = searchResults.value;
    if (!results.length) return;

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            const nextIndex = Math.min(index + 1, results.length - 1);
            document.querySelector(`[data-index="${nextIndex}"]`)?.focus();
            break;
        case 'ArrowUp':
            event.preventDefault();
            const prevIndex = Math.max(index - 1, 0);
            document.querySelector(`[data-index="${prevIndex}"]`)?.focus();
            break;
        case 'Escape':
            searchResults.value = [];
            document.getElementById('user-search')?.focus();
            break;
    }
};

const setupAutoRefresh = () => {
    try {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }

        if (process.env.NODE_ENV === 'production' || process.env.VITE_AUTO_REFRESH === 'true') {
            refreshInterval = setInterval(() => {
                if (!isProcessing.value && !isRefreshing.value && document.visibilityState === 'visible') {
                    router.reload({
                        only: ['mainWallet', 'tradeWallet', 'statistics'],
                        preserveScroll: true,
                        preserveState: true
                    });
                }
            }, REFRESH_INTERVAL);
        }
    } catch (err) {
        console.error('Error setting up auto-refresh:', err);
    }
};

const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        if (transferModal.value.isOpen) {
            closeTransferModal();
        } else if (userTransferModal.value.isOpen) {
            closeUserTransferModal();
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
        setupAutoRefresh();
        if (!props.statistics || typeof props.statistics !== 'object') {
            console.error('Invalid statistics prop');
        }
    } catch (err) {
        console.error('Mount error:', err);
    }
});

onUnmounted(() => {
    try {
        document.removeEventListener('keydown', handleKeydown);
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value);
            searchTimeout.value = null;
        }

        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }

        isProcessing.value = false;
    } catch (err) {
        console.error('Unmount cleanup error:', err);
    }
});
</script>

<template>
    <UserLayout
        :page-title="t('walletOverview')"
        :page-section="t('wallet')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="text-xl sm:text-2xl font-bold" :style="{ color: derivedColors.accent }">
                        {{ currencySymbol }}{{ formatNumber(statistics.total_balance) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalBalance') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="text-xl sm:text-2xl font-bold" :style="{ color: derivedColors.success }">
                        {{ currencySymbol }}{{ formatNumber(statistics.main_balance) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('mainWallet') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="text-xl sm:text-2xl font-bold" :style="{ color: derivedColors.secondary }">
                        {{ currencySymbol }}{{ formatNumber(statistics.trade_balance) }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('tradeWallet') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="text-xl sm:text-2xl font-bold" :style="{ color: derivedColors.warning }">
                        {{ statistics.wallet_count }}
                    </div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('activeWallets') }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
                <div class="xl:col-span-3 space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h2 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('mainWallet') }}
                        </h2>

                        <div v-if="mainWallet" class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                                <div class="text-center sm:text-left">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('wallet') }}
                                    </div>
                                    <div class="font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ mainWallet.name }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ t('primaryTradingWallet') }}
                                    </div>
                                </div>
                                <div class="flex-shrink-0 mx-auto sm:mx-4">
                                    <div class="h-12 w-12 rounded-xl flex items-center justify-center shadow-lg" :style="{ backgroundColor: derivedColors.accent }">
                                        <svg class="w-6 h-6" :style="{ color: derivedColors.background }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="text-center sm:text-right">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('balance') }}
                                    </div>
                                    <div class="font-semibold text-xl sm:text-2xl" :style="{ color: derivedColors.accent }">
                                        {{ currencySymbol }}{{ formatNumber(mainWallet.balance || 0) }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ t('available') }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 p-3 rounded-lg" :style="{ backgroundColor: derivedColors.border + '30' }">
                                <div class="text-xs mb-1" :style="{ color: derivedColors.textMuted }">{{ t('walletAddress') }}</div>
                                <div class="font-mono text-xs break-all" :style="{ color: derivedColors.textSecondary }">
                                    {{ mainWallet.address }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                                <button
                                    :disabled="!mainWallet.balance || mainWallet.balance <= 0 || isProcessing"
                                    class="py-3 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base"
                                    :style="{
                                        backgroundColor: (mainWallet.balance > 0 && !isProcessing) ? derivedColors.accent : derivedColors.surface,
                                        color: (mainWallet.balance > 0 && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                    }"
                                    @click="openTransferModal('main', 'trade')"
                                >
                                    {{ t('toTradeWallet') }}
                                </button>
                                <button
                                    :disabled="!mainWallet.balance || mainWallet.balance <= 0 || isProcessing"
                                    class="py-3 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base"
                                    :style="{
                                        backgroundColor: (mainWallet.balance > 0 && !isProcessing) ? derivedColors.secondary : derivedColors.surface,
                                        color: (mainWallet.balance > 0 && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                    }"
                                    @click="openUserTransferModal('main')"
                                >
                                    {{ t('transferToUser') }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-center py-8" :style="{ color: derivedColors.textMuted }">
                            <svg class="w-12 h-12 mx-auto mb-4" :style="{ color: derivedColors.textMuted }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <p>{{ t('mainWalletNotFound') }}</p>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h2 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('tradeWallet') }}
                        </h2>

                        <div v-if="tradeWallet" class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                                <div class="text-center sm:text-left">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('wallet') }}
                                    </div>
                                    <div class="font-semibold" :style="{ color: derivedColors.textPrimary }">
                                        {{ tradeWallet.name }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ t('tradeOptionsTrading') }}
                                    </div>
                                </div>
                                <div class="flex-shrink-0 mx-auto sm:mx-4">
                                    <div class="h-12 w-12 rounded-xl flex items-center justify-center shadow-lg" :style="{ backgroundColor: derivedColors.secondary }">
                                        <svg class="w-6 h-6" :style="{ color: derivedColors.background }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="text-center sm:text-right">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('balance') }}
                                    </div>
                                    <div class="font-semibold text-xl sm:text-2xl" :style="{ color: derivedColors.secondary }">
                                        {{ currencySymbol }}{{ formatNumber(tradeWallet.balance || 0) }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ t('available') }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 p-3 rounded-lg" :style="{ backgroundColor: derivedColors.border + '30' }">
                                <div class="text-xs mb-1" :style="{ color: derivedColors.textMuted }">{{ t('walletAddress') }}</div>
                                <div class="font-mono text-xs break-all" :style="{ color: derivedColors.textSecondary }">
                                    {{ tradeWallet.address }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                                <button
                                    :disabled="!tradeWallet.balance || tradeWallet.balance <= 0 || isProcessing"
                                    class="py-3 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base"
                                    :style="{
                                        backgroundColor: (tradeWallet.balance > 0 && !isProcessing) ? derivedColors.secondary : derivedColors.surface,
                                        color: (tradeWallet.balance > 0 && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                    }"
                                    @click="openTransferModal('trade', 'main')"
                                >
                                    {{ t('toMainWallet') }}
                                </button>
                                <button
                                    :disabled="!tradeWallet.balance || tradeWallet.balance <= 0 || isProcessing"
                                    class="py-3 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base"
                                    :style="{
                                        backgroundColor: (tradeWallet.balance > 0 && !isProcessing) ? derivedColors.accent : derivedColors.surface,
                                        color: (tradeWallet.balance > 0 && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                    }"
                                    @click="openUserTransferModal('trade')"
                                >
                                    {{ t('transferToUser') }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-center py-8" :style="{ color: derivedColors.textMuted }">
                            <svg class="w-12 h-12 mx-auto mb-4" :style="{ color: derivedColors.textMuted }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <p>{{ t('tradeWalletNotFound') }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('quickActions') }}
                        </h3>
                        <div class="space-y-3">
                            <button
                                :disabled="!mainWallet?.balance || mainWallet.balance <= 0 || isProcessing"
                                class="w-full py-2 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                                :style="{
                                    backgroundColor: (mainWallet?.balance > 0 && !isProcessing) ? derivedColors.accent : derivedColors.surface,
                                    color: (mainWallet?.balance > 0 && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                }"
                                @click="openTransferModal('main', 'trade')"
                            >
                                {{ t('mainToTrade') }}
                            </button>
                            <button
                                :disabled="!tradeWallet?.balance || tradeWallet.balance <= 0 || isProcessing"
                                class="w-full py-2 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                                :style="{
                                    backgroundColor: (tradeWallet?.balance > 0 && !isProcessing) ? derivedColors.secondary : derivedColors.surface,
                                    color: (tradeWallet?.balance > 0 && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                }"
                                @click="openTransferModal('trade', 'main')"
                            >
                                {{ t('tradeToMain') }}
                            </button>
                            <button
                                :disabled="(!mainWallet?.balance || mainWallet.balance <= 0) && (!tradeWallet?.balance || tradeWallet.balance <= 0) || isProcessing"
                                class="w-full py-2 px-4 rounded-lg font-medium transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                                :style="{
                                    backgroundColor: ((mainWallet?.balance > 0 || tradeWallet?.balance > 0) && !isProcessing) ? derivedColors.accent : derivedColors.surface,
                                    color: ((mainWallet?.balance > 0 || tradeWallet?.balance > 0) && !isProcessing) ? derivedColors.background : derivedColors.textMuted
                                }"
                                @click="openUserTransferModal('main')"
                            >
                                {{ t('sendToUser') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="transferModal.isOpen"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="transferModalTitleId"
            @click.self="closeTransferModal"
            @keydown.escape="closeTransferModal"
        >
            <div
                class="rounded-2xl shadow-2xl border max-w-md w-full backdrop-blur-md max-h-[90vh] overflow-y-auto"
                :style="{ backgroundColor: derivedColors.background + '95', borderColor: derivedColors.border + '50' }"
            >
                <div class="px-4 sm:px-6 py-4 border-b sticky top-0 backdrop-blur-md" :style="{ borderColor: derivedColors.border + '50', backgroundColor: derivedColors.background + '95' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="transferModalTitleId"
                            class="text-lg sm:text-xl font-semibold"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('transferFunds') }}
                        </h3>
                        <button
                            class="p-1 transition-colors"
                            :style="{ color: derivedColors.textMuted }"
                            @click="closeTransferModal"
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

                <div class="p-4 sm:p-6">
                    <form
                        class="space-y-6"
                        novalidate
                        @submit.prevent="submitTransfer"
                    >
                        <div class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                                <div class="text-center sm:text-left">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('from') }}
                                    </div>
                                    <div class="font-semibold capitalize" :style="{ color: derivedColors.textPrimary }">
                                        {{ getWalletName(transferForm.from_type) }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.accent }">
                                        {{ currencySymbol }}{{ formatNumber(getWalletBalance(transferForm.from_type)) }}
                                    </div>
                                </div>
                                <div class="flex-shrink-0 mx-auto sm:mx-4">
                                    <svg
                                        class="w-6 h-6"
                                        :style="{ color: derivedColors.accent }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 8l4 4m0 0l-4 4m4-4H3"
                                        />
                                    </svg>
                                </div>
                                <div class="text-center sm:text-right">
                                    <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('to') }}
                                    </div>
                                    <div class="font-semibold capitalize" :style="{ color: derivedColors.textPrimary }">
                                        {{ getWalletName(transferForm.to_type) }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.secondary }">
                                        {{ currencySymbol }}{{ formatNumber(getWalletBalance(transferForm.to_type)) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ t('transferAmount', { currency: defaultCurrency }) }}
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 font-bold text-base sm:text-lg" :style="{ color: derivedColors.accent }">{{ currencySymbol }}</span>
                                <input
                                    v-model.number="transferForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :max="getWalletBalance(transferForm.from_type)"
                                    class="w-full pl-10 sm:pl-12 pr-4 py-3 sm:py-4 border rounded-xl text-base sm:text-lg font-medium placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    :placeholder="t('amountPlaceholder')"
                                    required
                                    autocomplete="off"
                                >
                            </div>
                            <div class="text-sm mt-2" :style="{ color: derivedColors.textMuted }">
                                {{ t('available') }}: {{ currencySymbol }}{{ formatNumber(getWalletBalance(transferForm.from_type)) }}
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canTransfer || isProcessing"
                            class="w-full py-3 sm:py-4 px-6 rounded-xl font-bold text-base sm:text-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-lg relative overflow-hidden group"
                            :style="{
                                backgroundColor: canTransfer ? derivedColors.accent : derivedColors.surface,
                                color: canTransfer ? derivedColors.background : derivedColors.textMuted
                            }"
                        >
                            <span
                                v-if="isProcessing"
                                class="flex items-center justify-center relative z-10"
                            >
                                <svg
                                    class="animate-spin -ml-1 mr-3 h-5 w-5 sm:h-6 sm:w-6"
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
                            >{{ t('transferFunds') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="userTransferModal.isOpen"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="userTransferModalTitleId"
            @click.self="closeUserTransferModal"
            @keydown.escape="closeUserTransferModal"
        >
            <div
                class="rounded-2xl shadow-2xl border max-w-md w-full max-h-[90vh] overflow-y-auto backdrop-blur-md"
                :style="{ backgroundColor: derivedColors.background + '95', borderColor: derivedColors.border + '50' }"
            >
                <div class="px-4 sm:px-6 py-4 border-b sticky top-0 backdrop-blur-md" :style="{ borderColor: derivedColors.border + '50', backgroundColor: derivedColors.background + '95' }">
                    <div class="flex items-center justify-between">
                        <h3
                            :id="userTransferModalTitleId"
                            class="text-lg sm:text-xl font-semibold"
                            :style="{ color: derivedColors.textPrimary }"
                        >
                            {{ t('transferToUser') }}
                        </h3>
                        <button
                            class="p-1 transition-colors"
                            :style="{ color: derivedColors.textMuted }"
                            @click="closeUserTransferModal"
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

                <div class="p-4 sm:p-6">
                    <form
                        class="space-y-6"
                        novalidate
                        @submit.prevent="submitUserTransfer"
                    >
                        <div class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                            <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                {{ t('from') }}
                            </div>
                            <div class="font-semibold capitalize" :style="{ color: derivedColors.textPrimary }">
                                {{ getWalletName(userTransferForm.from_wallet_type) }}
                            </div>
                            <div class="text-sm" :style="{ color: derivedColors.accent }">
                                {{ currencySymbol }}{{ formatNumber(getWalletBalance(userTransferForm.from_wallet_type)) }}
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ t('searchUser') }}
                            </label>
                            <div class="relative">
                                <input
                                    v-model="userSearch"
                                    type="text"
                                    class="w-full px-4 py-3 sm:py-4 border rounded-xl text-base sm:text-lg font-medium placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    :placeholder="t('enterNameOrEmail')"
                                    autocomplete="off"
                                    @input="searchUsers"
                                >
                                <div
                                    v-if="searchResults.length > 0"
                                    class="absolute z-10 w-full mt-1 border rounded-lg shadow-lg max-h-48 overflow-y-auto"
                                    :style="{
                                        backgroundColor: derivedColors.surface,
                                        borderColor: derivedColors.border
                                    }"
                                    role="listbox"
                                >
                                    <div
                                        v-for="(user, index) in searchResults"
                                        :key="user.id"
                                        class="p-3 cursor-pointer border-b last:border-b-0 hover:opacity-80 transition-opacity"
                                        :style="{ borderColor: derivedColors.border + '50' }"
                                        role="option"
                                        :tabindex="0"
                                        :data-index="index"
                                        @click="selectUser(user)"
                                        @keydown.enter="selectUser(user)"
                                        @keydown.space.prevent="selectUser(user)"
                                        @keydown="handleSearchKeydown($event, index)"
                                    >
                                        <div class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                            {{ user.name }}
                                        </div>
                                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                            {{ user.email }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedUser">
                            <label class="block font-medium mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('selectedUser') }}</label>
                            <div class="rounded-xl p-4 sm:p-5 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border + '50' }">
                                <div class="flex items-center space-x-3 sm:space-x-4">
                                    <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-2xl flex items-center justify-center" :style="{ backgroundColor: derivedColors.accent }">
                                        <span class="font-bold text-sm sm:text-lg" :style="{ color: derivedColors.background }">{{ selectedUser.name.charAt(0) }}</span>
                                    </div>
                                    <div>
                                        <div class="font-bold text-base sm:text-lg" :style="{ color: derivedColors.textPrimary }">
                                            {{ selectedUser.name }}
                                        </div>
                                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                            {{ selectedUser.email }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedUser">
                            <label class="block font-medium mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">{{ t('recipientWallet') }}</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    class="p-3 sm:p-4 rounded-xl border-2 transition-all duration-300"
                                    :class="[
                                        userTransferForm.to_wallet_type === 'main'
                                            ? ''
                                            : 'hover:opacity-80'
                                    ]"
                                    :style="{
                                        borderColor: userTransferForm.to_wallet_type === 'main' ? derivedColors.accent : derivedColors.border,
                                        backgroundColor: userTransferForm.to_wallet_type === 'main' ? derivedColors.accent + '20' : derivedColors.surface + '50',
                                        color: userTransferForm.to_wallet_type === 'main' ? derivedColors.accent : derivedColors.textSecondary
                                    }"
                                    :aria-pressed="userTransferForm.to_wallet_type === 'main'"
                                    @click="userTransferForm.to_wallet_type = 'main'"
                                >
                                    <div class="font-medium text-sm sm:text-base">
                                        {{ t('mainWallet') }}
                                    </div>
                                    <div class="text-xs opacity-75 mt-1">
                                        {{ t('primaryWallet') }}
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    class="p-3 sm:p-4 rounded-xl border-2 transition-all duration-300"
                                    :class="[
                                        userTransferForm.to_wallet_type === 'trade'
                                            ? ''
                                            : 'hover:opacity-80'
                                    ]"
                                    :style="{
                                        borderColor: userTransferForm.to_wallet_type === 'trade' ? derivedColors.secondary : derivedColors.border,
                                        backgroundColor: userTransferForm.to_wallet_type === 'trade' ? derivedColors.secondary + '20' : derivedColors.surface + '50',
                                        color: userTransferForm.to_wallet_type === 'trade' ? derivedColors.secondary : derivedColors.textSecondary
                                    }"
                                    :aria-pressed="userTransferForm.to_wallet_type === 'trade'"
                                    @click="userTransferForm.to_wallet_type = 'trade'"
                                >
                                    <div class="font-medium text-sm sm:text-base">
                                        {{ t('tradeWallet') }}
                                    </div>
                                    <div class="text-xs opacity-75 mt-1">
                                        {{ t('tradingWallet') }}
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ t('transferAmount', { currency: defaultCurrency }) }}
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 font-bold text-base sm:text-lg" :style="{ color: derivedColors.accent }">{{ currencySymbol }}</span>
                                <input
                                    v-model.number="userTransferForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :max="getWalletBalance(userTransferForm.from_wallet_type)"
                                    class="w-full pl-10 sm:pl-12 pr-4 py-3 sm:py-4 border rounded-xl text-base sm:text-lg font-medium placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    :placeholder="t('amountPlaceholder')"
                                    autocomplete="off"
                                >
                            </div>
                            <div class="text-sm mt-2" :style="{ color: derivedColors.textMuted }">
                                {{ t('available') }}: {{ currencySymbol }}{{ formatNumber(getWalletBalance(userTransferForm.from_wallet_type)) }}
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-3 text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                {{ t('noteOptional') }}
                            </label>
                            <textarea
                                v-model="userTransferForm.note"
                                class="w-full px-4 py-3 sm:py-4 border rounded-xl placeholder-gray-400 focus:ring-2 focus:ring-opacity-50 transition-all duration-200 resize-none text-sm sm:text-base"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                rows="3"
                                :placeholder="t('enterTransferNote')"
                                maxlength="500"
                            />
                        </div>

                        <div class="sticky bottom-0 backdrop-blur-md pb-2 -mx-4 sm:-mx-6 px-4 sm:px-6" :style="{ backgroundColor: derivedColors.background + '95' }">
                            <button
                                type="submit"
                                :disabled="!canUserTransfer || isProcessing"
                                class="w-full py-3 sm:py-4 px-6 rounded-xl font-bold text-base sm:text-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 shadow-lg relative overflow-hidden group"
                                :style="{
                                    backgroundColor: canUserTransfer ? derivedColors.accent : derivedColors.surface,
                                    color: canUserTransfer ? derivedColors.background : derivedColors.textMuted
                                }"
                            >
                                <span
                                    v-if="isProcessing"
                                    class="flex items-center justify-center relative z-10"
                                >
                                    <svg
                                        class="animate-spin -ml-1 mr-3 h-5 w-5 sm:h-6 sm:w-6"
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
                                >{{ t('transferFunds') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
