<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted, computed } from 'vue';


const props = defineProps({
    darkMode: {
        type: Boolean,
        default: false
    },
    pageTitle: {
        type: String,
        default: 'Overview',
        validator: (value) => typeof value === 'string' && value.length > 0
    },
    pageSection: {
        type: String,
        default: 'Dashboard',
        validator: (value) => typeof value === 'string' && value.length > 0
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

const emit = defineEmits(['toggle-sidebar']);

const page = usePage();
const showUserMenu = ref(false);
const avatarError = ref(false);
let clickOutsideHandler = null;

const currentUser = computed(() => {
    try {
        const user = page.props.auth?.user || page.props.currentUser;
        return {
            name: user?.name || 'Admin User',
            email: user?.email || 'admin@example.com',
            avatar: user?.avatar || null
        };
    } catch (error) {
        return {
            name: 'Admin User',
            email: 'admin@example.com',
            avatar: null
        };
    }
});

const getUserInitials = computed(() => {
    try {
        const name = currentUser.value.name || 'User';
        return name.split(' ')
            .map(n => n.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2);
    } catch (error) {
        return 'U';
    }
});

const toggleUserMenu = () => {
    showUserMenu.value = !showUserMenu.value;
};

const toggleSidebar = () => {
    emit('toggle-sidebar');
};

const handleAvatarError = () => {
    avatarError.value = true;
};

const handleMenuItemClick = () => {
    showUserMenu.value = false;
};

const handleLogout = () => {
    showUserMenu.value = false;
};

const handleClickOutside = (event) => {
    try {
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
    } catch (error) {
    }
};

onMounted(() => {
    clickOutsideHandler = handleClickOutside;
    document.addEventListener('click', clickOutsideHandler);
});

onUnmounted(() => {
    if (clickOutsideHandler) {
        document.removeEventListener('click', clickOutsideHandler);
    }
});
</script>

<template>
    <header
        class="fixed top-0 right-0 bg-white dark:bg-slate-900 shadow-sm border-b border-gray-200 dark:border-slate-700 h-16 transition-all duration-300 z-30"
        :style="{
      left: isMobile ? '0' : (sidebarOpen ? '256px' : '80px')
    }"
        role="banner"
    >
        <div class="flex items-center justify-between h-full px-4 lg:px-6">
            <div class="flex items-center space-x-4">
                <button
                    class="flex items-center justify-center text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition-colors duration-200 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 h-10 w-10"
                    :aria-label="sidebarOpen ? 'Close sidebar' : 'Open sidebar'"
                    :aria-expanded="sidebarOpen"
                    type="button"
                    @click="toggleSidebar"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
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

                <nav
                    class="hidden md:flex items-center text-sm text-gray-600 dark:text-gray-300"
                    aria-label="Breadcrumb"
                >
                    <span class="text-gray-500 dark:text-gray-400 font-medium">{{ pageSection }}</span>
                    <svg
                        class="flex-shrink-0 mx-3 h-4 w-4 text-gray-400 dark:text-gray-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                    <span class="text-blue-600 dark:text-blue-400 font-semibold">{{ pageTitle }}</span>
                </nav>

                <div class="md:hidden">
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ pageTitle }}
                    </h1>
                </div>
            </div>

            <div class="flex items-center">
                <div class="relative">
                    <button
                        data-dropdown="user"
                        class="flex items-center focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg p-1.5 transition-colors duration-200"
                        :aria-label="`User menu for ${currentUser.name}`"
                        :aria-expanded="showUserMenu"
                        type="button"
                        @click="toggleUserMenu"
                    >
                        <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center text-white font-medium shadow-sm">
                            <img
                                v-if="currentUser.avatar && !avatarError"
                                :src="currentUser.avatar"
                                :alt="`${currentUser.name} avatar`"
                                class="h-8 w-8 rounded-full object-cover"
                                @error="handleAvatarError"
                            >
                            <span
                                v-else
                                class="text-sm"
                            >{{ getUserInitials }}</span>
                        </div>
                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-200 font-medium hidden sm:block max-w-32 truncate">
              {{ currentUser.name }}
            </span>
                        <svg
                            class="h-4 w-4 ml-2 text-gray-500 dark:text-gray-400 hidden sm:block transition-transform duration-200"
                            :class="{ 'rotate-180': showUserMenu }"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M19 9L12 16L5 9"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>

                    <Transition name="dropdown">
                        <div
                            v-if="showUserMenu"
                            data-dropdown-content="user"
                            class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg py-1 z-50 border border-gray-200 dark:border-slate-600"
                            role="menu"
                            aria-label="User account menu"
                        >
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-600">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ currentUser.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ currentUser.email }}
                                </p>
                            </div>
                            <Link
                                href="/admin/profile"
                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors duration-200"
                                role="menuitem"
                                @click="handleMenuItemClick"
                            >
                                <div class="flex items-center">
                                    <svg
                                        class="h-4 w-4 mr-3 text-gray-500 dark:text-gray-400"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        aria-hidden="true"
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
                                    Profile
                                </div>
                            </Link>
                            <Link
                                href="/admin/settings"
                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors duration-200"
                                role="menuitem"
                                @click="handleMenuItemClick"
                            >
                                <div class="flex items-center">
                                    <svg
                                        class="h-4 w-4 mr-3 text-gray-500 dark:text-gray-400"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        aria-hidden="true"
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
                                    Settings
                                </div>
                            </Link>
                            <hr class="my-1 border-gray-200 dark:border-slate-600">
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                class="w-full flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-700 dark:hover:text-red-300 transition-all duration-200 ease-in-out group"
                                role="menuitem"
                                @click="handleLogout"
                            >
                                <svg
                                    class="h-4 w-4 mr-3 text-red-500 dark:text-red-400 group-hover:text-red-600 dark:group-hover:text-red-300"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                    />
                                </svg>
                                <span class="font-medium">Logout</span>
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
.max-w-32 {
    max-width: 8rem;
}
button:focus {
    outline: 2px solid rgb(59 130 246);
    outline-offset: 2px;
}

@media (prefers-contrast: more) {
    .border-gray-200 {
        border-color: rgb(0 0 0);
    }

    .dark .border-slate-700 {
        border-color: rgb(255 255 255);
    }
}

@media (prefers-reduced-motion: reduce) {
    .dropdown-enter-active,
    .dropdown-leave-active,
    .transition-colors,
    .transition-all {
        transition: none !important;
        animation: none !important;
    }
}

@media (max-width: 640px) {
    .max-w-32 {
        max-width: 6rem;
    }
}

@media print {
    header {
        display: none !important;
    }
}
</style>
