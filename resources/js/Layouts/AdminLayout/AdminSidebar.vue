<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useSettings } from "@/composables/useSettings.js"

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: true
    },
    isMobile: {
        type: Boolean,
        default: false
    },
    menuItems: {
        type: Array,
        default: () => []
    },
    config: {
        type: Object,
        default: () => ({
            brandName: 'Token',
            brandSuffix: 'Hive',
            shortName: 'CX',
            tagline: 'Admin Panel',
            logoutRoute: '/logout'
        })
    },
    userInfo: {
        type: Object,
        default: () => ({
            name: 'John Doe',
            role: 'Admin',
            initials: 'JD'
        })
    }
})

const userInfo = computed(() => {
    const user = page.props.auth?.user  // ✅ Safe access with optional chaining
    return {
        name: user?.name || 'Guest',
        role: user?.role || 'User',
        initials: user?.initials || 'GU'
    }
})

const emit = defineEmits(['close-sidebar'])
const expandedMenu = ref(null)
const sidebarRef = ref(null)
const floatingMenuRef = ref(null)
const page = usePage()
const floatingMenuStyle = ref({})
const { siteName } = useSettings()

const defaultMenuItems = [
    {
        title: 'Dashboard',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
        route: '/admin/dashboard'
    },
    {
        title: 'Users',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'All Users', route: '/admin/users' },
            { title: 'KYC Verification', route: '/admin/kyc-verifications' },
            { title: 'User Wallets', route: '/admin/wallets' },
            { title: 'Referrals', route: '/admin/referrals' },
            { title: 'Login History', route: '/admin/login-attempts' }
        ]
    },
    {
        title: 'Trading',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'Market Data', route: '/admin/market-data' },
            { title: 'Trade Settings', route: '/admin/trade-settings' },
            { title: 'Trade History', route: '/admin/trades' }
        ]
    },
    {
        title: 'Mining System',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        hasSubmenu: true,
        submenu: [
            {
                title: 'Sessions',
                route: '/admin/mining-sessions',
                description: 'Manage user mining sessions and performance'
            },
            {
                title: 'Competitions',
                route: '/admin/mining-competitions',
                description: 'Create and manage mining competitions'
            },
            {
                title: 'Achievements',
                route: '/admin/mining-achievements',
                description: 'Configure achievements and rewards'
            },
            {
                title: 'Leaderboards',
                route: '/admin/mining-leaderboards',
                description: 'View mining performance rankings'
            },
            {
                title: 'Analytics',
                route: '/admin/mining-analytics',
                description: 'Mining system analytics and insights'
            }
        ]
    },
    {
        title: 'Investment',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'ICO Tokens', route: '/admin/ico-tokens' },
            { title: 'Token Sales', route: '/admin/ico-sales' },
            { title: 'Purchase History', route: '/admin/ico-purchases' }
        ]
    },
    {
        title: 'Finance',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'All Transactions', route: '/admin/transactions' },
            { title: 'Deposits', route: '/admin/deposits' },
            { title: 'Withdrawals', route: '/admin/withdrawals' },
        ]
    },
    {
        title: 'MineCash',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
        hasSubmenu: true,
        submenu: [
            {
                title: 'Subscription Plans',
                route: '/admin/subscription-plans',
                description: 'Manage subscription plans and pricing'
            },
            {
                title: 'Revenue Analytics',
                route: '/admin/revenue',
                description: 'Track revenue from all sources'
            },
            {
                title: 'User Subscriptions',
                route: '/admin/user-subscriptions',
                description: 'Monitor active subscriptions'
            }
        ]
    },
    {
        title: 'Gateways',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'Deposit', route: '/admin/payment-gateways' },
            { title: 'Withdrawal', route: '/admin/withdrawal-gateways' }
        ]
    },
    {
        title: 'Communication',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'Support Tickets', route: '/admin/support-tickets' },
            { title: 'Contact Messages', route: '/admin/contacts' },
            { title: 'Newsletter', route: '/admin/newsletter' },
        ]
    },
    {
        title: 'Content',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'Blog Posts', route: '/admin/blogs' },
            { title: 'Menu Builder', route: '/admin/menus' },
            { title: 'Languages', route: '/admin/languages' }
        ]
    },
    {
        title: 'Settings',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        hasSubmenu: true,
        submenu: [
            { title: 'General Settings', route: '/admin/settings' },
            { title: 'Security (2FA)', route: '/admin/security/2fa' },
            { title: 'Clear Cache', route: '/admin/cache-clear' },
            { title: 'System Info', route: '/admin/system-info' },
            { title: 'Automation', route: '/admin/system/cron' },
        ]
    },
    {
        title: 'System Update',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>',
        route: '/admin/system/update'
    }
];

const activePath = computed(() => page.url)
const initializeExpandedMenu = () => {
    if (!props.isOpen || props.isMobile) return

    const currentPath = activePath.value
    for (const item of getMenuItems()) {
        if (item.hasSubmenu && item.submenu) {
            const hasActiveSubmenuItem = item.submenu.some(sub =>
                currentPath === sub.route || currentPath.startsWith(sub.route + '/')
            )
            if (hasActiveSubmenuItem) {
                expandedMenu.value = item.title
                break
            }
        }
    }
}

const getMenuItems = () => {
    return props.menuItems.length > 0 ? props.menuItems : defaultMenuItems
}
const getSidebarClasses = () => {
    return [
        'sidebar fixed h-full z-30 transition-all duration-300 ease-in-out bg-gradient-to-br from-purple-950 via-indigo-950 to-violet-950 border-r border-purple-700/30 shadow-2xl text-gray-100',
        !props.isMobile && props.isOpen ? 'w-64' : '',
        !props.isMobile && !props.isOpen ? 'w-20' : '',
        props.isMobile && props.isOpen ? 'w-64 translate-x-0' : '',
        props.isMobile && !props.isOpen ? 'w-64 -translate-x-full' : ''
    ]
}

const getMenuItemClasses = (item) => {
    return [
        'flex items-center px-4 py-3 text-gray-300 hover:bg-gradient-to-r hover:from-purple-600/20 hover:to-indigo-600/20 hover:text-purple-300 rounded-xl transition-all duration-200 ease-in-out group cursor-pointer backdrop-blur-sm border border-transparent hover:border-purple-500/20',
        !props.isOpen ? 'justify-center' : '',
        (hasActiveSubmenu(item.submenu) || expandedMenu.value === item.title) ? 'bg-gradient-to-r from-purple-700/30 to-indigo-700/30 text-purple-300 border-l-4 border-purple-400 shadow-lg shadow-purple-500/10' : ''
    ]
}

const getIconClasses = (item) => {
    return [
        'text-lg flex-shrink-0 transition-all duration-200 ease-in-out',
        !props.isOpen ? 'group-hover:scale-110 group-hover:text-purple-300' : '',
        ((item.hasSubmenu && hasActiveSubmenu(item.submenu)) ||
            (!item.hasSubmenu && isActiveRoute(item.route)) ||
            expandedMenu.value === item.title) ? 'text-purple-300' : ''
    ]
}

const getSubmenuItemClasses = (subItem) => {
    return [
        'flex items-center px-4 py-2 mx-2 text-gray-300 hover:bg-gradient-to-r hover:from-indigo-700/20 hover:to-violet-700/20 hover:text-indigo-300 rounded-lg transition-all duration-200 ease-in-out group border border-transparent hover:border-indigo-500/20',
        isActiveRoute(subItem.route) ? 'bg-gradient-to-r from-indigo-700/30 to-violet-700/30 text-indigo-300 border-indigo-400/30' : ''
    ]
}

const getSubmenuDotClasses = (subItem) => {
    return [
        'w-2 h-2 rounded-full bg-gray-400 group-hover:bg-indigo-300 mr-3 flex-shrink-0 transition-colors duration-200',
        isActiveRoute(subItem.route) ? 'bg-indigo-300' : ''
    ]
}

const getRegularMenuItemClasses = (item) => {
    return [
        'flex items-center px-4 py-3 text-gray-300 hover:bg-gradient-to-r hover:from-purple-600/20 hover:to-indigo-600/20 hover:text-purple-300 rounded-xl transition-all duration-200 ease-in-out group backdrop-blur-sm border border-transparent hover:border-purple-500/20',
        !props.isOpen ? 'justify-center' : '',
        isActiveRoute(item.route) ? 'bg-gradient-to-r from-purple-700/30 to-indigo-700/30 text-purple-300 border-l-4 border-purple-400' : ''
    ]
}

const isActiveRoute = (route) => {
    if (!route) return false
    return activePath.value === route || activePath.value.startsWith(route + '/')
}

const hasActiveSubmenu = (submenu) => {
    if (!submenu || !Array.isArray(submenu)) return false
    return submenu.some(sub => isActiveRoute(sub.route))
}

const getCurrentSubmenu = () => {
    if (!expandedMenu.value) return []
    const item = getMenuItems().find(item => item.title === expandedMenu.value)
    return item?.submenu || []
}

const toggleSubmenu = (menuTitle, event) => {
    if (props.isMobile && props.isOpen) {
        expandedMenu.value = expandedMenu.value === menuTitle ? null : menuTitle
    }
    else if (!props.isMobile && !props.isOpen) {
        const newState = expandedMenu.value === menuTitle ? null : menuTitle
        expandedMenu.value = newState

        if (newState && event?.currentTarget) {
            nextTick(() => {
                const rect = event.currentTarget.getBoundingClientRect()
                floatingMenuStyle.value = {
                    top: `${rect.top}px`,
                    left: `${rect.right + 8}px`
                }
            })
        }
    }
    else if (!props.isMobile && props.isOpen) {
        expandedMenu.value = expandedMenu.value === menuTitle ? null : menuTitle
    }
}

const handleMenuClick = () => {
    if (props.isMobile && props.isOpen) {
        emit('close-sidebar')
    }
}

const handleSubmenuClick = () => {
    if (props.isMobile && props.isOpen) {
        emit('close-sidebar')
    }
    if (!props.isMobile && !props.isOpen) {
        expandedMenu.value = null
    }
}

const handleClickOutside = (event) => {
    if (!props.isOpen && expandedMenu.value && !props.isMobile) {
        if (floatingMenuRef.value && !floatingMenuRef.value.contains(event.target)) {
            expandedMenu.value = null
        }
    }
}

watch([() => props.isOpen, () => props.isMobile], ([newIsOpen, newIsMobile]) => {
    if (!newIsOpen || newIsMobile) {
        expandedMenu.value = null
    } else {
        nextTick(() => {
            initializeExpandedMenu()
        })
    }
})

watch(activePath, () => {
    if (props.isOpen && !props.isMobile) {
        initializeExpandedMenu()
    }
})

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    if (props.isOpen && !props.isMobile) {
        initializeExpandedMenu()
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

</script>

<template>
    <aside
        ref="sidebarRef"
        :class="getSidebarClasses()"
        role="navigation"
        :aria-label="isOpen ? 'Main navigation' : 'Collapsed navigation'"
        :aria-expanded="isOpen"
    >
        <!-- Header -->
        <div class="flex items-center justify-center h-16 border-b border-purple-700/30 bg-gradient-to-r from-purple-900/70 to-indigo-900/70 backdrop-blur-sm">
            <div class="flex items-center">
                <Link
                    href="/admin/dashboard"
                    class="flex items-center hover:opacity-80 transition-opacity duration-200"
                >
                    <div
                        v-if="isOpen"
                        class="text-2xl font-bold bg-gradient-to-r from-purple-400 via-pink-400 to-indigo-400 bg-clip-text text-transparent drop-shadow-lg"
                    >{{ siteName }}</div>
                    <div
                        v-else
                        class="text-2xl font-bold bg-gradient-to-r from-purple-400 via-pink-400 to-indigo-400 bg-clip-text text-transparent drop-shadow-lg"
                    >
                        {{ config.shortName }}
                    </div>
                </Link>
            </div>
        </div>

        <div class="flex flex-col h-[calc(100%-4rem)] justify-between">
            <nav
                class="mt-6 px-3 overflow-y-auto hide-scrollbar"
                role="menu"
                aria-label="Primary navigation"
            >
                <div
                    v-for="(item, index) in getMenuItems()"
                    :key="`menu-${index}-${item.title}`"
                    class="mb-2 relative"
                >
                    <template v-if="item.hasSubmenu">
                        <div
                            :class="getMenuItemClasses(item)"
                            role="menuitem"
                            :aria-expanded="expandedMenu === item.title"
                            :aria-haspopup="true"
                            :tabindex="0"
                            @click="toggleSubmenu(item.title, $event)"
                            @keydown.enter="toggleSubmenu(item.title, $event)"
                            @keydown.space.prevent="toggleSubmenu(item.title, $event)"
                        >
              <span
                  :class="getIconClasses(item)"
                  aria-hidden="true"
                  v-html="item.icon"
              />

                            <template v-if="isOpen">
                                <span class="ml-3 font-medium truncate">{{ item.title }}</span>
                                <span
                                    class="ml-auto transform transition-transform duration-200 text-purple-400"
                                    :class="{ 'rotate-180': expandedMenu === item.title }"
                                    aria-hidden="true"
                                >
                  <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-4 w-4"
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

                            <div
                                v-if="!isOpen && !isMobile"
                                class="absolute left-full ml-6 px-3 py-2 bg-gradient-to-r from-purple-800 to-indigo-800 rounded-lg text-xs font-medium text-purple-100 opacity-0 -translate-x-3 pointer-events-none transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 z-50 whitespace-nowrap border border-purple-600/30 shadow-lg"
                            >
                                {{ item.title }}
                                <div class="absolute top-1/2 -left-2 transform -translate-y-1/2 border-4 border-transparent border-r-purple-800" />
                            </div>
                        </div>

                        <Transition name="submenu-slide">
                            <div
                                v-if="item.hasSubmenu && isOpen && expandedMenu === item.title"
                                class="mt-1 space-y-1 overflow-hidden bg-gradient-to-r from-indigo-950/40 to-violet-950/40 rounded-lg mx-2 py-2 border border-indigo-700/20"
                                role="menu"
                                :aria-label="`${item.title} submenu`"
                            >
                                <Link
                                    v-for="(subItem, subIndex) in item.submenu"
                                    :key="`sub-${index}-${subIndex}-${subItem.title}`"
                                    :href="subItem.route"
                                    :class="getSubmenuItemClasses(subItem)"
                                    role="menuitem"
                                    @click="handleSubmenuClick"
                                >
                  <span
                      :class="getSubmenuDotClasses(subItem)"
                      aria-hidden="true"
                  />
                                    <span class="font-medium text-sm truncate">{{ subItem.title }}</span>
                                </Link>
                            </div>
                        </Transition>
                    </template>

                    <template v-else>
                        <Link
                            :href="item.route"
                            :class="getRegularMenuItemClasses(item)"
                            role="menuitem"
                            @click="handleMenuClick"
                        >
                      <span
                          :class="getIconClasses(item)"
                          aria-hidden="true"
                          v-html="item.icon"
                      />
                            <span
                                v-if="isOpen"
                                class="ml-3 font-medium truncate"
                            >{{ item.title }}</span>
                            <div
                                v-if="!isOpen && !isMobile"
                                class="absolute left-full ml-6 px-3 py-2 bg-gradient-to-r from-purple-800 to-indigo-800 rounded-lg text-xs font-medium text-purple-100 opacity-0 -translate-x-3 pointer-events-none transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 z-50 whitespace-nowrap border border-purple-600/30 shadow-lg"
                            >
                                {{ item.title }}
                                <div class="absolute top-1/2 -left-2 transform -translate-y-1/2 border-4 border-transparent border-r-purple-800" />
                            </div>
                        </Link>
                    </template>
                </div>
            </nav>

            <div class="border-t border-purple-700/30 bg-gradient-to-r from-purple-900/70 to-indigo-900/70 backdrop-blur-sm mt-auto">
                <div
                    v-if="isOpen && userInfo && page.props.auth?.user"
                    class="p-4 border-b border-purple-700/30"
                >
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-indigo-500 flex items-center justify-center text-white text-sm font-medium shadow-lg ring-2 ring-purple-400/30">
                            {{ userInfo.initials }}
                        </div>
                        <div class="ml-3">
                            <p class="text-purple-100 text-sm font-medium">
                                {{ userInfo.name }}
                            </p>
                            <p class="text-purple-300 text-xs">
                                {{ userInfo.role }}
                            </p>
                        </div>
                    </div>
                </div>

                <Link
                    :href="config.logoutRoute"
                    method="post"
                    as="button"
                    class="w-full flex items-center p-4 text-gray-300 hover:bg-gradient-to-r hover:from-red-900/30 hover:to-orange-900/30 hover:text-red-300 transition-all duration-200 ease-in-out group border border-transparent hover:border-red-500/20"
                    :class="{ 'justify-center': !isOpen }"
                    role="menuitem"
                    :aria-label="isOpen ? 'Logout' : 'Logout from account'"
                >
          <span
              class="text-lg group-hover:text-red-300 transition-colors duration-200"
              aria-hidden="true"
          >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
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
                    v-if="isOpen"
                    class="ml-3 font-medium"
                >Logout</span>

                    <div
                        v-if="!isOpen && !isMobile"
                        class="absolute left-full ml-6 px-3 py-2 bg-gradient-to-r from-red-800 to-orange-800 rounded-lg text-xs font-medium text-red-100 opacity-0 -translate-x-3 pointer-events-none transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 z-50 whitespace-nowrap border border-red-600/30 shadow-lg"
                    >
                        Logout
                        <div class="absolute top-1/2 -left-2 transform -translate-y-1/2 border-4 border-transparent border-r-red-800" />
                    </div>
                </Link>
            </div>
        </div>
    </aside>

    <Teleport
        v-if="!isOpen && expandedMenu && !isMobile"
        to="body"
    >
        <Transition name="submenu-fade">
            <div
                ref="floatingMenuRef"
                class="fixed bg-gradient-to-br from-purple-900 via-indigo-900 to-violet-900 rounded-xl shadow-2xl border border-purple-600/40 backdrop-blur-sm py-3 min-w-[220px] z-[999]"
                :style="floatingMenuStyle"
                role="menu"
                :aria-label="expandedMenu + ' submenu'"
                @click.stop
            >
                <div class="px-4 py-2 border-b border-purple-600/30 mb-2">
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-gradient-to-r from-purple-400 to-indigo-400 rounded-full mr-2" />
                        <span class="text-sm font-medium text-purple-100">{{ expandedMenu }}</span>
                    </div>
                </div>
                <div class="space-y-1 px-2">
                    <Link
                        v-for="(subItem, index) in getCurrentSubmenu()"
                        :key="index"
                        :href="subItem.route"
                        class="flex items-center px-3 py-2.5 text-gray-300 hover:bg-gradient-to-r hover:from-indigo-600/20 hover:to-violet-600/20 hover:text-indigo-300 rounded-lg transition-all duration-200 group border border-transparent hover:border-indigo-500/20"
                        :class="{ 'bg-gradient-to-r from-indigo-600/30 to-violet-600/30 text-indigo-300 border-indigo-400/30': isActiveRoute(subItem.route) }"
                        role="menuitem"
                        @click="handleSubmenuClick"
                    >
            <span
                class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-3 transition-colors duration-200 group-hover:bg-indigo-300"
                :class="{ 'bg-indigo-300': isActiveRoute(subItem.route) }"
            />
                        <span class="text-sm font-medium">{{ subItem.title }}</span>
                        <svg
                            v-if="isActiveRoute(subItem.route)"
                            class="w-3 h-3 ml-auto text-indigo-300"
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
                </div>
                <div class="absolute top-5 -left-2 w-4 h-4">
                    <div class="w-full h-full bg-gradient-to-br from-purple-900 to-indigo-900 border-l border-t border-purple-600/40 transform rotate-45" />
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

@media (hover: hover) and (pointer: fine) {
    .group:hover {
        transform: translateX(2px);
    }
}

.sidebar [role="menuitem"]:focus {
    outline: 2px solid rgb(168 85 247);
    outline-offset: 2px;
    border-radius: 0.75rem;
}

.sidebar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(147, 51, 234, 0.1) 0%, rgba(79, 70, 229, 0.1) 100%);
    pointer-events: none;
    z-index: -1;
}

@keyframes glow-purple {
    0%, 100% {
        box-shadow: 0 0 5px rgba(168, 85, 247, 0.5);
    }
    50% {
        box-shadow: 0 0 20px rgba(168, 85, 247, 0.8), 0 0 30px rgba(168, 85, 247, 0.6);
    }
}

.group:hover .text-lg {
    animation: glow-purple 2s ease-in-out infinite;
}

@keyframes gradient-shift-purple {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

.bg-gradient-to-r.from-purple-400.via-pink-400.to-indigo-400 {
    background-size: 200% 200%;
    animation: gradient-shift-purple 4s ease infinite;
}

.sidebar .group:hover {
    box-shadow: 0 4px 15px rgba(147, 51, 234, 0.2);
}

</style>
