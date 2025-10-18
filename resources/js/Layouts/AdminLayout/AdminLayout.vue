<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Topbar from './Topbar.vue';
import AdminSidebar from "@/Layouts/AdminLayout/AdminSidebar.vue";
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

const sidebarOpen = ref(true);
const isMobile = ref(false);
const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const darkMode = ref(false);

const toggleDarkMode = () => {
    darkMode.value = !darkMode.value;
    updateDarkModeClass();
};

const updateDarkModeClass = () => {
    if (darkMode.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('mineinvest-dark-mode', 'true');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('mineinvest-dark-mode', 'false');
    }
};

const page = usePage();
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
    const savedDarkMode = localStorage.getItem('mineinvest-dark-mode');

    if (savedDarkMode === 'true') {
        darkMode.value = true;
    } else if (savedDarkMode === null && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        darkMode.value = true;
    }

    updateDarkModeClass();
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
  <div class="admin-layout flex h-screen bg-gray-50 dark:bg-slate-950 transition-colors duration-300">
    <AdminSidebar
      :is-open="sidebarOpen"
      :is-mobile="isMobile"
      @close-sidebar="closeSidebar"
    />

    <div
      v-if="sidebarOpen && isMobile"
      class="fixed inset-0 bg-black/50 z-20 backdrop-blur-sm transition-all duration-300"
      @click="toggleSidebar"
    />

    <div class="flex flex-col flex-1 w-full overflow-hidden">
      <Topbar
        :dark-mode="darkMode"
        :page-title="pageTitle"
        :page-section="pageSection"
        :sidebar-open="sidebarOpen"
        :is-mobile="isMobile"
        @toggle-sidebar="toggleSidebar"
        @toggle-dark-mode="toggleDarkMode"
      />

      <main
        class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 dark:bg-slate-950 transition-colors duration-300 p-4 lg:p-6"
        :style="{
          marginTop: '64px',
          marginLeft: isMobile ? '0' : (sidebarOpen ? '256px' : '80px')
        }"
      >
        <div class="md:hidden flex items-center text-gray-600 dark:text-gray-300 mb-6">
          <span class="text-gray-400 dark:text-gray-500 text-sm">{{ pageSection }}</span>
          <svg
            class="mx-2 h-4 w-4 text-gray-400 dark:text-gray-500"
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
          <span class="text-blue-600 dark:text-blue-400 font-medium text-sm">{{ pageTitle }}</span>
        </div>

        <div class="w-full">
          <slot />
        </div>
      </main>
    </div>

    <ToastContainer />
  </div>
</template>

<style>
.admin-layout select,
.admin-layout .form-select,
.admin-layout select.form-control,
.admin-layout div select,
.admin-layout main select {
    width: 100% !important;
    padding: 0.625rem 2.5rem 0.625rem 1rem !important;
    background-color: white !important;
    border: 1px solid rgb(229 231 235) !important;
    border-radius: 0.5rem !important;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    color: rgb(55 65 81) !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;

    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
    background-position: right 0.75rem center !important;
    background-repeat: no-repeat !important;
    background-size: 1rem !important;
}

@media (max-width: 768px) {
    .admin-layout select,
    .admin-layout .form-select,
    .admin-layout select.form-control,
    .admin-layout div select,
    .admin-layout main select {
        padding: 0.5rem 2rem 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        background-position: right 0.5rem center !important;
    }
}
</style>
