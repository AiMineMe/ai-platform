<script setup>
import { ref, onMounted, watch, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';
import UserTopbar from './UserTopbar.vue';
import UserSidebar from "@/Layouts/UserLayout/UserSidebar.vue";
import ToastContainer from "@/Components/Toast/ToastContainer.vue";

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'Overview'
    },
    pageSection: {
        type: String,
        default: 'Dashboard'
    }
});

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

const layoutStyle = computed(() => ({
    backgroundColor: derivedColors.value.background
}));

const mainContentStyle = computed(() => ({
    backgroundColor: derivedColors.value.background
}));

const overlayStyle = computed(() => ({
    backgroundColor: derivedColors.value.primary + '80'
}));

const sidebarOpen = ref(true);
const isMobile = ref(false);

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const darkMode = ref(true);

const toggleDarkMode = () => {
    darkMode.value = true;
};

const handleResize = () => {
    const wasMobile = isMobile.value;
    isMobile.value = window.innerWidth < 768;

    if (!wasMobile && isMobile.value && sidebarOpen.value) {
        sidebarOpen.value = false;
    }
    else if (wasMobile && !isMobile.value && !sidebarOpen.value) {
        sidebarOpen.value = true;
    }
};

const showToastMessages = (flash) => {
    if (flash?.success) window.toast?.success(flash.success);
    if (flash?.error) window.toast?.error(flash.error);
    if (flash?.warning) window.toast?.warning(flash.warning);
    if (flash?.info) window.toast?.info(flash.info);
};

onMounted(() => {
    darkMode.value = true;
    isMobile.value = window.innerWidth < 768;

    if (isMobile.value) {
        sidebarOpen.value = false;
    }

    window.addEventListener('resize', handleResize);
    const flash = page.props.flash;
    showToastMessages(flash);

    const handleInertiaSuccess = (event) => {
        const pageProps = event.detail.page.props;
        const flash = pageProps.flash;
        showToastMessages(flash);
    };

    document.addEventListener('inertia:success', handleInertiaSuccess);
    onUnmounted(() => {
        window.removeEventListener('resize', handleResize);
        document.removeEventListener('inertia:success', handleInertiaSuccess);
    });
});

watch(isMobile, (newValue) => {
    if (newValue && sidebarOpen.value) {
        sidebarOpen.value = false;
    }
});
</script>

<template>
    <div class="user-layout flex h-screen transition-colors duration-300" :style="layoutStyle">
        <UserSidebar
            :is-open="sidebarOpen"
            :is-mobile="isMobile"
            @close-sidebar="closeSidebar"
        />

        <div
            v-if="sidebarOpen && isMobile"
            class="fixed inset-0 z-20 backdrop-blur-sm transition-all duration-300"
            :style="overlayStyle"
            @click="toggleSidebar"
        />

        <div class="flex flex-col flex-1 w-full overflow-hidden">
            <UserTopbar
                :dark-mode="darkMode"
                :page-title="pageTitle"
                :page-section="pageSection"
                :sidebar-open="sidebarOpen"
                :is-mobile="isMobile"
                @toggle-sidebar="toggleSidebar"
                @toggle-dark-mode="toggleDarkMode"
            />

            <main
                class="flex-1 overflow-x-hidden overflow-y-auto transition-colors duration-300 p-4 lg:p-6"
                :style="{
          ...mainContentStyle,
          marginTop: '64px',
          marginLeft: isMobile ? '0' : (sidebarOpen ? '256px' : '80px')
        }"
            >
                <div class="md:hidden flex items-center mb-6">
                    <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ pageSection }}</span>
                    <svg
                        class="mx-2 h-4 w-4"
                        :style="{ color: derivedColors.textMuted }"
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
                    <span class="font-medium text-sm" :style="{ color: derivedColors.accent }">{{ pageTitle }}</span>
                </div>
                <div class="w-full">
                    <slot />
                </div>
            </main>
        </div>
        <ToastContainer />
    </div>
</template>

