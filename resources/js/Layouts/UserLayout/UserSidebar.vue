<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useSettings } from "@/composables/useSettings.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: true
    },
    isMobile: {
        type: Boolean,
        default: false
    },
    auth: {
        type: Object,
        default: () => ({}),
    }
});

const userInfo = computed(() => {
    const user = page.props.auth.user
    return {
        name: user?.name || 'Guest',
        role: user?.role || 'User',
        initials: user?.initials || 'GU'
    }
})

const page = usePage()

const emit = defineEmits(['close-sidebar']);
const { siteName } = useSettings();
const { t } = useTranslation();
const expandedMenu = ref(null);
const sidebarRef = ref(null);
const floatingMenuStyle = ref({});
const isKYCEnabled = page.props.kyc_status || false

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

const sidebarStyle = computed(() => ({
    backgroundColor: derivedColors.value.background,
    borderColor: derivedColors.value.border + '30'
}));

const headerStyle = computed(() => ({
    backgroundColor: derivedColors.value.background,
    borderColor: derivedColors.value.border + '30'
}));

const activeMenuStyle = computed(() => ({
    backgroundColor: derivedColors.value.accent,
    color: derivedColors.value.textPrimary,
    boxShadow: `0 4px 15px ${derivedColors.value.accent}50`
}));

const submenuBgStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '80'
}));

const floatingMenuBgStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface,
    borderColor: derivedColors.value.border,
    boxShadow: `0 25px 50px -12px ${derivedColors.value.primary}30`
}));

const tooltipStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface,
    borderColor: derivedColors.value.border,
    boxShadow: `0 10px 25px ${derivedColors.value.primary}30`
}));

const userProfileBgStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`
}));

const menuHoverStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    color: derivedColors.value.textSecondary
}));

const menuItems = [
    {
        title: t('dashboard'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
        route: '/user/dashboard'
    },
    {
        title: t('trading'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('marketData'), route: '/user/trading/market' },
            { title: t('liveTrading'), route: '/user/trading/live' },
            { title: t('tradingHistory'), route: '/user/trading/history' },
        ]
    },
    {
        title: t('mining'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        route: '/user/mining'
    },
    {
        title: t('investment'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('icoTokens'), route: '/user/investment/ico-tokens' },
            { title: t('myPurchases'), route: '/user/investment/purchases' },
            { title: t('portfolio'), route: '/user/investment/portfolio' }
        ]
    },
    {
        title: t('wallet'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('myWallets'), route: '/user/wallets' },
            { title: t('depositFunds'), route: '/user/wallet/deposit' },
            { title: t('withdrawFunds'), route: '/user/wallet/withdraw' },
            { title: t('transactionHistory'), route: '/user/wallet/transactions' }
        ]
    },
    {
        title: t('subscription'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
        route: '/user/subscriptions'
    },
    {
        title: t('referral'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('referralDashboard'), route: '/user/referral/dashboard' },
            { title: t('commissionHistory'), route: '/user/referral/commissions' },
        ]
    },
    {
        title: t('security'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('twoFactorAuth'), route: '/user/security/2fa' },
            { title: t('changePassword'), route: '/user/security/password' },
            { title: t('loginSessions'), route: '/user/security/sessions' },
        ]
    },
    {
        title: t('settings'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('profileSettings'), route: '/user/settings/profile' },
            ...(isKYCEnabled ? [{ title: t('kycVerification'), route: '/user/settings/kyc' }] : []),
        ]
    },
    {
        title: t('support'),
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: t('contactSupport'), route: '/user/support-tickets/create' },
            { title: t('myTickets'), route: '/user/support-tickets/list' },
        ]
    }
];

const hoveredMenuItem = ref(null);
const initializeExpandedMenu = () => {
    if (!props.isOpen || props.isMobile) return;

    const currentPath = page.url;
    for (const item of menuItems) {
        if (item.hasSubmenu && item.submenu) {
            const hasActiveSubmenuItem = item.submenu.some(sub =>
                currentPath === sub.route || currentPath.startsWith(sub.route + '/')
            );
            if (hasActiveSubmenuItem) {
                expandedMenu.value = item.title;
                break;
            }
        }
    }
};

watch([() => props.isOpen, () => props.isMobile], ([newIsOpen, newIsMobile]) => {
    if (!newIsOpen || newIsMobile) {
        expandedMenu.value = null;
    } else {
        nextTick(() => {
            initializeExpandedMenu();
        });
    }
});

const handleClickOutside = (event) => {
    if (!props.isOpen && expandedMenu.value && !props.isMobile) {
        const floatingMenu = document.querySelector('[data-floating-menu]');
        const sidebar = sidebarRef.value;

        if (floatingMenu && sidebar &&
            !floatingMenu.contains(event.target) &&
            !sidebar.contains(event.target)) {
            expandedMenu.value = null;
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    if (props.isOpen && !props.isMobile) {
        initializeExpandedMenu();
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const toggleSubmenu = (menuTitle, event) => {
    if (props.isMobile && props.isOpen) {
        expandedMenu.value = expandedMenu.value === menuTitle ? null : menuTitle;
    }
    else if (!props.isMobile && !props.isOpen) {
        const newState = expandedMenu.value === menuTitle ? null : menuTitle;
        expandedMenu.value = newState;

        if (newState && event) {
            nextTick(() => {
                const rect = event.currentTarget.getBoundingClientRect();
                floatingMenuStyle.value = {
                    top: `${rect.top}px`,
                    left: `${rect.right + 8}px`
                };
            });
        }
    }
    else if (!props.isMobile && props.isOpen) {
        expandedMenu.value = expandedMenu.value === menuTitle ? null : menuTitle;
    }
};

const closeFloatingSubmenu = () => {
    expandedMenu.value = null;
};

const handleMenuClick = () => {
    if (props.isMobile && props.isOpen) {
        emit('close-sidebar');
    }
};

const handleSubmenuClick = () => {
    if (props.isMobile && props.isOpen) {
        emit('close-sidebar');
    }
};

const getCurrentSubmenu = () => {
    if (!expandedMenu.value) return [];
    const item = menuItems.find(item => item.title === expandedMenu.value);
    return item?.submenu || [];
};

const activePath = computed(() => page.url);
watch(activePath, () => {
    if (props.isOpen && !props.isMobile) {
        initializeExpandedMenu();
    }
});

const isActiveRoute = (route) => {
    return activePath.value === route || activePath.value.startsWith(route + '/');
};

const hasActiveSubmenu = (submenu) => {
    if (!submenu) return false;
    return submenu.some(sub => isActiveRoute(sub.route));
};

const handleMenuItemMouseEnter = (itemTitle) => {
    hoveredMenuItem.value = itemTitle;
};

const handleMenuItemMouseLeave = () => {
    hoveredMenuItem.value = null;
};

const getMenuItemStyle = (item) => {
    const isActive = item.hasSubmenu
        ? hasActiveSubmenu(item.submenu) || expandedMenu.value === item.title
        : isActiveRoute(item.route);

    const isHovered = hoveredMenuItem.value === item.title;

    if (isActive) {
        return activeMenuStyle.value;
    } else if (isHovered) {
        return menuHoverStyle.value;
    } else {
        return { color: derivedColors.value.textMuted };
    }
};
</script>

<template>
    <aside
        ref="sidebarRef"
        :class="[
            'sidebar fixed h-full z-40 transition-all duration-300 ease-in-out border-r shadow-2xl',
            !isMobile && isOpen ? 'w-64' : '',
            !isMobile && !isOpen ? 'w-20' : '',
            isMobile && isOpen ? 'w-64 translate-x-0' : '',
            isMobile && !isOpen ? 'w-64 -translate-x-full' : ''
        ]"
        :style="sidebarStyle"
    >
        <!-- Header Section -->
        <div class="flex items-center justify-center h-14 sm:h-16 border-b" :style="headerStyle">
            <div class="flex items-center px-2">
                <Link
                    href="/"
                    class="flex items-center hover:opacity-80 transition-opacity duration-200"
                >
                    <!-- Collapsed state - show only first letters or icon -->
                    <div v-if="!isOpen && !isMobile" class="text-xl sm:text-2xl font-bold text-center" :style="{ color: derivedColors.accent }">
                        {{ siteName.split(' ').map(word => word.charAt(0)).join('') }}
                    </div>
                    <!-- Expanded state - show full name -->
                    <div v-else class="text-lg sm:text-xl lg:text-2xl font-bold truncate" :style="{ color: derivedColors.accent }">
                        {{ siteName }}
                    </div>
                </Link>
            </div>
        </div>

        <div class="flex flex-col h-[calc(100%-3.5rem)] sm:h-[calc(100%-4rem)] justify-between">
            <!-- Navigation -->
            <nav class="mt-4 sm:mt-6 px-2 sm:px-3 overflow-y-auto hide-scrollbar">
                <div
                    v-for="(item, index) in menuItems"
                    :key="index"
                    class="mb-1 sm:mb-2 relative"
                >
                    <!-- Submenu Items -->
                    <template v-if="item.hasSubmenu">
                        <div
                            class="flex items-center px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg sm:rounded-xl transition-all duration-200 ease-in-out group cursor-pointer backdrop-blur-sm border border-transparent"
                            :class="{
                                'justify-center': !isOpen && !isMobile
                            }"
                            :style="getMenuItemStyle(item)"
                            @click="toggleSubmenu(item.title, $event)"
                            @mouseenter="handleMenuItemMouseEnter(item.title)"
                            @mouseleave="handleMenuItemMouseLeave"
                        >
                            <span
                                class="text-lg flex-shrink-0 transition-all duration-200 ease-in-out"
                                :class="{
                                    'group-hover:scale-110': !isOpen && !isMobile
                                }"
                                v-html="item.icon"
                            />
                            <template v-if="isOpen || isMobile">
                                <span class="ml-2 sm:ml-3 font-medium truncate text-sm sm:text-base">{{ item.title }}</span>
                                <span
                                    class="ml-auto transform transition-transform duration-200 flex-shrink-0"
                                    :class="{ 'rotate-180': expandedMenu === item.title }"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-3 w-3 sm:h-4 sm:w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </span>
                            </template>

                            <!-- Tooltip for collapsed state -->
                            <div
                                v-if="!isOpen && !isMobile"
                                class="absolute left-full ml-4 sm:ml-6 px-2 sm:px-3 py-1.5 sm:py-2 rounded-md sm:rounded-lg text-xs font-medium opacity-0 -translate-x-3 pointer-events-none transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 z-50 whitespace-nowrap border shadow-lg"
                                :style="{ ...tooltipStyle, color: derivedColors.textPrimary }"
                            >
                                {{ item.title }}
                                <div class="absolute top-1/2 -left-1.5 sm:-left-2 transform -translate-y-1/2 border-4 border-transparent" :style="{ borderRightColor: derivedColors.surface }" />
                            </div>
                        </div>

                        <!-- Submenu Items -->
                        <Transition name="submenu-slide">
                            <div
                                v-if="item.hasSubmenu && (isOpen || isMobile) && expandedMenu === item.title"
                                class="mt-1 space-y-1 overflow-hidden rounded-md sm:rounded-lg mx-1 sm:mx-2 py-1 sm:py-2"
                                :style="submenuBgStyle"
                            >
                                <Link
                                    v-for="(subItem, subIndex) in item.submenu"
                                    :key="subIndex"
                                    :href="subItem.route"
                                    class="flex items-center px-3 sm:px-4 py-1.5 sm:py-2 mx-1 sm:mx-2 rounded-md sm:rounded-lg transition-all duration-200 ease-in-out group"
                                    :style="isActiveRoute(subItem.route) ? { backgroundColor: derivedColors.surface + '50', color: derivedColors.textSecondary } : { color: derivedColors.textMuted }"
                                    @click="handleSubmenuClick"
                                >
                                    <span
                                        class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full mr-2 sm:mr-3 flex-shrink-0 transition-colors duration-200"
                                        :style="{ backgroundColor: isActiveRoute(subItem.route) ? derivedColors.textSecondary : derivedColors.textMuted }"
                                    />
                                    <span class="font-medium text-xs sm:text-sm truncate">{{ subItem.title }}</span>
                                </Link>
                            </div>
                        </Transition>
                    </template>

                    <!-- Regular Menu Items -->
                    <template v-else>
                        <Link
                            :href="item.route"
                            class="flex items-center px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg sm:rounded-xl transition-all duration-200 ease-in-out group backdrop-blur-sm"
                            :class="{
                                'justify-center': !isOpen && !isMobile
                            }"
                            :style="getMenuItemStyle(item)"
                            @click="handleMenuClick"
                            @mouseenter="handleMenuItemMouseEnter(item.title)"
                            @mouseleave="handleMenuItemMouseLeave"
                        >
                            <span
                                class="text-lg flex-shrink-0 transition-all duration-200 ease-in-out"
                                :class="{
                                    'group-hover:scale-110': !isOpen && !isMobile
                                }"
                                v-html="item.icon"
                            />
                            <span
                                v-if="isOpen || isMobile"
                                class="ml-2 sm:ml-3 font-medium truncate text-sm sm:text-base"
                            >{{ item.title }}</span>

                            <!-- Tooltip for collapsed state -->
                            <div
                                v-if="!isOpen && !isMobile"
                                class="absolute left-full ml-4 sm:ml-6 px-2 sm:px-3 py-1.5 sm:py-2 rounded-md sm:rounded-lg text-xs font-medium opacity-0 -translate-x-3 pointer-events-none transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 z-50 whitespace-nowrap border shadow-lg"
                                :style="{ ...tooltipStyle, color: derivedColors.textPrimary }"
                            >
                                {{ item.title }}
                                <div class="absolute top-1/2 -left-1.5 sm:-left-2 transform -translate-y-1/2 border-4 border-transparent" :style="{ borderRightColor: derivedColors.surface }" />
                            </div>
                        </Link>
                    </template>
                </div>
            </nav>

            <!-- Bottom Section -->
            <div class="border-t mt-auto" :style="{ backgroundColor: derivedColors.background, borderColor: derivedColors.border + '30' }">
                <!-- User Profile (when expanded) -->
                <div
                    v-if="isOpen || isMobile"
                    class="p-3 sm:p-4 border-b"
                    :style="{ borderColor: derivedColors.border + '30' }"
                >
                    <div class="flex items-center">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full flex items-center justify-center text-white text-xs sm:text-sm font-medium shadow-lg flex-shrink-0" :style="userProfileBgStyle">
                            {{ userInfo.initials }}
                        </div>
                        <div class="ml-2 sm:ml-3 min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ userInfo.name }}
                            </p>
                            <p class="text-xs truncate" :style="{ color: derivedColors.textSecondary }">
                                {{ userInfo.role }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Logout Button -->
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="w-full flex items-center p-3 sm:p-4 transition-all duration-200 ease-in-out group"
                    :class="{ 'justify-center': !isOpen && !isMobile }"
                    :style="{ color: derivedColors.textMuted }"
                >
                    <span class="text-lg transition-colors duration-200 flex-shrink-0">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 sm:h-5 sm:w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                            />
                        </svg>
                    </span>
                    <span
                        v-if="isOpen || isMobile"
                        class="ml-2 sm:ml-3 font-medium text-sm sm:text-base"
                    >{{ t('logout') }}</span>

                    <!-- Logout Tooltip -->
                    <div
                        v-if="!isOpen && !isMobile"
                        class="absolute left-full ml-4 sm:ml-6 px-2 sm:px-3 py-1.5 sm:py-2 rounded-md sm:rounded-lg text-xs font-medium opacity-0 -translate-x-3 pointer-events-none transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 z-50 whitespace-nowrap border shadow-lg"
                        :style="{ ...tooltipStyle, color: derivedColors.textPrimary }"
                    >
                        {{ t('logout') }}
                        <div class="absolute top-1/2 -left-1.5 sm:-left-2 transform -translate-y-1/2 border-4 border-transparent" :style="{ borderRightColor: derivedColors.surface }" />
                    </div>
                </Link>
            </div>
        </div>
    </aside>

    <!-- Floating Submenu for Collapsed State -->
    <Teleport to="body">
        <Transition name="submenu-fade">
            <div
                v-if="!isOpen && expandedMenu && !isMobile"
                data-floating-menu
                class="fixed rounded-lg sm:rounded-xl backdrop-blur-sm py-2 sm:py-3 min-w-[200px] sm:min-w-[220px] z-50"
                :style="{ ...floatingMenuBgStyle, ...floatingMenuStyle }"
                @click.stop
            >
                <!-- Floating Menu Header -->
                <div class="px-3 sm:px-4 py-1.5 sm:py-2 border-b mb-1 sm:mb-2" :style="{ borderColor: derivedColors.border }">
                    <div class="flex items-center">
                        <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full mr-2 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                        <span class="text-xs sm:text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">{{ expandedMenu }}</span>
                    </div>
                </div>

                <!-- Floating Menu Items -->
                <div class="space-y-1 px-1.5 sm:px-2">
                    <template
                        v-for="(subItem, subIndex) in getCurrentSubmenu()"
                        :key="subIndex"
                    >
                        <Link
                            :href="subItem.route"
                            class="flex items-center px-2.5 sm:px-3 py-2 sm:py-2.5 rounded-md sm:rounded-lg transition-all duration-200 group"
                            :style="isActiveRoute(subItem.route) ?
                                { backgroundColor: derivedColors.surface + '70', color: derivedColors.textPrimary } :
                                { color: derivedColors.textSecondary }"
                            @click="closeFloatingSubmenu"
                        >
                            <span
                                class="w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full mr-2 sm:mr-3 transition-colors duration-200 flex-shrink-0"
                                :style="{ backgroundColor: isActiveRoute(subItem.route) ? derivedColors.textPrimary : derivedColors.textMuted }"
                            />
                            <span class="text-xs sm:text-sm font-medium truncate">{{ subItem.title }}</span>

                            <svg
                                v-if="isActiveRoute(subItem.route)"
                                class="w-2.5 h-2.5 sm:w-3 sm:h-3 ml-auto flex-shrink-0"
                                :style="{ color: derivedColors.textPrimary }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </Link>
                    </template>
                </div>

                <!-- Arrow pointer -->
                <div class="absolute top-4 sm:top-5 -left-1.5 sm:-left-2 w-3 h-3 sm:w-4 sm:h-4">
                    <div class="w-full h-full border-l border-t transform rotate-45" :style="{ backgroundColor: derivedColors.surface, borderColor: derivedColors.border }"></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.hide-scrollbar::-webkit-scrollbar {
    display: none;
}

.submenu-slide-enter-active,
.submenu-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: top;
}

.submenu-slide-enter-from {
    opacity: 0;
    transform: scaleY(0);
    max-height: 0;
}

.submenu-slide-enter-to {
    opacity: 1;
    transform: scaleY(1);
    max-height: 500px;
}

.submenu-slide-leave-from {
    opacity: 1;
    transform: scaleY(1);
    max-height: 500px;
}

.submenu-slide-leave-to {
    opacity: 0;
    transform: scaleY(0);
    max-height: 0;
}

.submenu-fade-enter-active,
.submenu-fade-leave-active {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.submenu-fade-enter-from,
.submenu-fade-leave-to {
    opacity: 0;
    transform: translateX(-10px) scale(0.95);
}

@media (max-width: 767px) {
    .tooltip {
        display: none;
    }
}

.sidebar {
    touch-action: manipulation;
}
</style>
