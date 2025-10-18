<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useSettings } from '@/composables/useSettings';
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    darkMode: {
        type: Boolean,
        default: true
    },
    pageTitle: {
        type: String,
        default: 'Overview'
    },
    pageSection: {
        type: String,
        default: 'Dashboard'
    },
    sidebarOpen: {
        type: Boolean,
        default: true
    },
    isMobile: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle-sidebar', 'toggle-dark-mode']);
const page = usePage();
const { defaultCurrency, currencySymbol } = useSettings();
const { t } = useTranslation();

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

const topbarStyle = computed(() => ({
    backgroundColor: derivedColors.value.background + 'f2',
    borderColor: derivedColors.value.border + '30'
}));

const walletBalanceStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));

const buttonHoverStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30'
}));

const dropdownStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface,
    borderColor: derivedColors.value.border + '30',
    boxShadow: `0 25px 50px -12px ${derivedColors.value.primary}30`
}));

const walletItemStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '10'
}));

const currentUser = computed(() => {
    return page.props.auth?.user || {
        name: 'Guest User',
        email: 'guest@example.com',
        avatar: null
    };
});

const walletBalances = computed(() => {
    const wallets = page.props.auth?.user?.wallets;
    return {
        main_balance: wallets?.main_balance || '0.00',
        trade_balance: wallets?.trade_balance || '0.00'
    };
});

const showUserMenu = ref(false);
const formatCurrency = (amount) => {
    const numAmount = parseFloat(amount.toString().replace(/[^0-9.-]+/g, ''));
    if (currencySymbol.value && currencySymbol.value !== '$') {
        return `${currencySymbol.value}${numAmount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    }

    const currency = defaultCurrency.value || 'USD';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
    }).format(numAmount);
};

const toggleUserMenu = () => {
    showUserMenu.value = !showUserMenu.value;
};

const toggleSidebar = () => {
    emit('toggle-sidebar');
};

const handleLogout = () => {
    showUserMenu.value = false;
};

const handleClickOutside = (event) => {
    const target = event.target;

    if (showUserMenu.value) {
        const userButton = document.querySelector('[data-dropdown="user"]');
        const userDropdown = document.querySelector('[data-dropdown-content="user"]');

        if (userButton && userDropdown &&
            !userButton.contains(target) &&
            !userDropdown.contains(target)) {
            showUserMenu.value = false;
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <header
        class="fixed top-0 right-0 backdrop-blur-sm border-b h-14 sm:h-16 transition-all duration-300 z-30"
        :style="{
            ...topbarStyle,
            left: isMobile ? '0' : (sidebarOpen ? '256px' : '80px')
        }"
    >
        <div class="flex items-center justify-between h-full px-3 sm:px-4 lg:px-6">
            <!-- Left Section -->
            <div class="flex items-center space-x-2 sm:space-x-4 flex-1 min-w-0">
                <button
                    class="focus:outline-none transition-colors duration-200 p-1.5 sm:p-2 rounded-lg flex-shrink-0"
                    :style="{
                        color: derivedColors.textMuted,
                        ':hover': buttonHoverStyle
                    }"
                    @click="toggleSidebar"
                >
                    <svg
                        class="h-4 w-4 sm:h-5 sm:w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M4 6H20M4 12H20M4 18H20"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>

                <!-- Page Section (Desktop) -->
                <div class="hidden lg:flex items-center text-sm flex-shrink-0">
                    <span :style="{ color: derivedColors.textMuted }">{{ pageSection }}</span>
                </div>

                <!-- Page Title (Mobile) -->
                <div class="md:hidden min-w-0 flex-1">
                    <h1 class="text-base sm:text-lg font-semibold truncate" :style="{ color: derivedColors.textPrimary }">
                        {{ pageTitle }}
                    </h1>
                </div>

                <!-- Page Title (Tablet) -->
                <div class="hidden md:block lg:hidden min-w-0 flex-1">
                    <h1 class="text-lg font-semibold truncate" :style="{ color: derivedColors.textPrimary }">
                        {{ pageTitle }}
                    </h1>
                </div>
            </div>

            <!-- Right Section -->
            <div class="flex items-center space-x-2 sm:space-x-3 lg:space-x-4 flex-shrink-0">
                <!-- Desktop Wallet Balance -->
                <div
                    class="hidden xl:flex items-center space-x-4 px-3 py-2 rounded-lg border"
                    :style="walletBalanceStyle"
                >
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                        <span class="text-sm font-medium whitespace-nowrap" :style="{ color: derivedColors.accent }">
                            {{ t('mainBalance') }}:
                        </span>
                        <span class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ formatCurrency(walletBalances.main_balance) }}
                        </span>
                    </div>

                    <div class="w-px h-4" :style="{ backgroundColor: derivedColors.border }"></div>

                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: derivedColors.secondary }"></div>
                        <span class="text-sm font-medium whitespace-nowrap" :style="{ color: derivedColors.secondary }">
                            {{ t('tradeBalance') }}:
                        </span>
                        <span class="text-sm font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ formatCurrency(walletBalances.trade_balance) }}
                        </span>
                    </div>
                </div>

                <!-- Tablet Wallet Balance -->
                <div
                    class="hidden lg:flex xl:hidden items-center space-x-3 px-2 py-1.5 rounded-lg border"
                    :style="walletBalanceStyle"
                >
                    <div class="text-xs font-medium" :style="{ color: derivedColors.accent }">
                        {{ formatCurrency(walletBalances.main_balance) }}
                    </div>
                    <div class="w-px h-3" :style="{ backgroundColor: derivedColors.border }"></div>
                    <div class="text-xs font-medium" :style="{ color: derivedColors.secondary }">
                        {{ formatCurrency(walletBalances.trade_balance) }}
                    </div>
                </div>

                <!-- Mobile Wallet Balance - Simplified -->
                <div
                    class="lg:hidden flex items-center space-x-2 px-2 py-1 rounded-md border"
                    :style="walletBalanceStyle"
                >
                    <div class="text-xs font-medium" :style="{ color: derivedColors.accent }">
                        {{ formatCurrency(walletBalances.main_balance) }}
                    </div>
                    <div class="w-px h-2.5" :style="{ backgroundColor: derivedColors.border }"></div>
                    <div class="text-xs font-medium" :style="{ color: derivedColors.secondary }">
                        {{ formatCurrency(walletBalances.trade_balance) }}
                    </div>
                </div>

                <!-- User Menu -->
                <div class="relative">
                    <button
                        data-dropdown="user"
                        class="flex items-center focus:outline-none rounded-lg p-1 sm:p-1.5 transition-colors duration-200 min-w-0"
                        :style="{
                            ':hover': buttonHoverStyle
                        }"
                        @click="toggleUserMenu"
                    >
                        <!-- Show avatar when sidebar is collapsed or on mobile -->
                        <div
                            v-if="isMobile || !sidebarOpen"
                            class="w-6 h-6 sm:w-8 sm:h-8 rounded-full flex items-center justify-center flex-shrink-0"
                            :style="{ backgroundColor: derivedColors.accent }"
                        >
                            <span class="text-xs sm:text-sm font-bold text-white">
                                {{ currentUser.name.charAt(0).toUpperCase() }}
                            </span>
                        </div>

                        <!-- Show name when sidebar is expanded on desktop -->
                        <template v-else>
                            <span class="text-xs sm:text-sm font-medium max-w-16 sm:max-w-24 lg:max-w-32 truncate"
                                  :style="{ color: derivedColors.textSecondary }">
                                {{ currentUser.name }}
                            </span>
                            <svg
                                class="h-3 w-3 sm:h-4 sm:w-4 ml-1 flex-shrink-0"
                                :style="{ color: derivedColors.textMuted }"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M19 9L12 16L5 9"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </template>
                    </button>

                    <Transition name="dropdown">
                        <div
                            v-if="showUserMenu"
                            data-dropdown-content="user"
                            class="absolute right-0 mt-2 w-56 sm:w-64 rounded-xl shadow-2xl border py-2 z-50"
                            :style="dropdownStyle"
                        >
                            <!-- User Info Section -->
                            <div class="px-3 sm:px-4 py-3 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium truncate text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                            {{ currentUser.name }}
                                        </div>
                                        <div class="text-xs sm:text-sm truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ currentUser.email }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Mobile Wallet Balances in Dropdown -->
                                <div class="xl:hidden mt-3 pt-3 border-t"
                                     :style="{ borderColor: derivedColors.border + '20' }">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 text-xs">
                                        <div class="rounded-lg p-2 border" :style="walletItemStyle">
                                            <div class="font-medium" :style="{ color: derivedColors.accent }">
                                                {{ t('main') }}
                                            </div>
                                            <div class="font-semibold text-sm" :style="{ color: derivedColors.textPrimary }">
                                                {{ formatCurrency(walletBalances.main_balance) }}
                                            </div>
                                        </div>
                                        <div class="rounded-lg p-2 border" :style="walletItemStyle">
                                            <div class="font-medium" :style="{ color: derivedColors.secondary }">
                                                {{ t('trade') }}
                                            </div>
                                            <div class="font-semibold text-sm" :style="{ color: derivedColors.textPrimary }">
                                                {{ formatCurrency(walletBalances.trade_balance) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Menu Items -->
                            <div class="py-1">
                                <Link
                                    href="/user/settings/profile"
                                    class="flex items-center px-3 sm:px-4 py-2 transition-colors duration-200 text-sm"
                                    :style="{
                                        color: derivedColors.textSecondary,
                                        ':hover': { backgroundColor: derivedColors.surface + '50', color: derivedColors.accent }
                                    }"
                                    @click="showUserMenu = false"
                                >
                                    <svg
                                        class="h-4 w-4 mr-2 sm:mr-3 flex-shrink-0"
                                        :style="{ color: derivedColors.textMuted }"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path
                                            d="M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M12 14C8.13401 14 5 17.134 5 21H19C19 17.134 15.866 14 12 14Z"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <span class="truncate">{{ t('profileSettings') }}</span>
                                </Link>

                                <Link
                                    href="/user/wallets"
                                    class="flex items-center px-3 sm:px-4 py-2 transition-colors duration-200 text-sm"
                                    :style="{
                                        color: derivedColors.textSecondary,
                                        ':hover': { backgroundColor: derivedColors.surface + '50', color: derivedColors.accent }
                                    }"
                                    @click="showUserMenu = false"
                                >
                                    <svg
                                        class="h-4 w-4 mr-2 sm:mr-3 flex-shrink-0"
                                        :style="{ color: derivedColors.textMuted }"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path
                                            d="M21 12C21 13.1046 20.1046 14 19 14H15L13 16H5C3.89543 16 3 15.1046 3 14V6C3 4.89543 3.89543 4 5 4H19C20.1046 4 21 4.89543 21 6V12Z"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        />
                                        <path
                                            d="M7 10H7.01M11 10H11.01M15 10H15.01"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                        />
                                    </svg>
                                    <span class="truncate">{{ t('walletManagement') }}</span>
                                </Link>

                                <Link
                                    href="/user/security/2fa"
                                    class="flex items-center px-3 sm:px-4 py-2 transition-colors duration-200 text-sm"
                                    :style="{
                                        color: derivedColors.textSecondary,
                                        ':hover': { backgroundColor: derivedColors.surface + '50', color: derivedColors.accent }
                                    }"
                                    @click="showUserMenu = false"
                                >
                                    <svg
                                        class="h-4 w-4 mr-2 sm:mr-3 flex-shrink-0"
                                        :style="{ color: derivedColors.textMuted }"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        />
                                        <path
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        />
                                    </svg>
                                    <span class="truncate">{{ t('securitySettings') }}</span>
                                </Link>

                                <hr class="my-1" :style="{ borderColor: derivedColors.border + '20' }">

                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    class="w-full flex items-center px-3 sm:px-4 py-2 text-red-400 hover:bg-red-900/20 hover:text-red-300 transition-colors duration-200 text-sm"
                                    @click="handleLogout"
                                >
                                    <svg
                                        class="h-4 w-4 mr-2 sm:mr-3 flex-shrink-0"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                        />
                                    </svg>
                                    <span class="font-medium truncate">{{ t('logout') }}</span>
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </header>
</template>
