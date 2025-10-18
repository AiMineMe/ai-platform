<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    tickets: {
        type: Array,
        required: true,
        default: () => []
    },
    meta: {
        type: Object,
        default: () => ({
            total: 0,
            current_page: 1,
            per_page: 10,
            last_page: 1
        })
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'created_at',
            sort_direction: 'desc'
        })
    }
});

const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

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

const statusOptions = [
    { value: 'open', label: t('open') },
    { value: 'in_progress', label: t('inProgress') },
    { value: 'resolved', label: t('resolved') },
    { value: 'closed', label: t('closed') }
];

const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);

const applyFilters = () => {
    const params = {
        page: 1
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/user/support-tickets/list', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

const clearFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const changePage = (page) => {
    const params = { page };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;

    router.get('/user/support-tickets/list', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

const viewTicket = (ticket) => {
    router.get(`/user/support-tickets/${ticket.id}`);
};

const formatDate = (dateString) => {
    if (!dateString) return t('unknown');
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getStatusClass = (status) => {
    const classes = {
        'open': 'bg-blue-100 text-blue-800 border-blue-200',
        'in_progress': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'resolved': 'bg-green-100 text-green-800 border-green-200',
        'closed': 'bg-gray-100 text-gray-800 border-gray-200'
    };
    return classes[status] || classes.open;
};

const getPriorityClass = (priority) => {
    const classes = {
        'low': 'bg-green-100 text-green-800 border-green-200',
        'medium': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'high': 'bg-orange-100 text-orange-800 border-orange-200',
        'urgent': 'bg-red-100 text-red-800 border-red-200'
    };
    return classes[priority] || classes.medium;
};

onMounted(() => {
    const flash = page.props.flash;

    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.info) showToast(flash.info, 'info');
    if (flash?.warning) showToast(flash.warning, 'warning');
});
</script>

<template>
    <UserLayout
        :page-title="t('supportTickets')"
        :page-section="t('support')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4">
            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('supportTickets') }}
                        </h1>
                        <p class="mt-1 text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                            {{ t('manageYourSupportRequests') }}
                        </p>
                    </div>
                    <Link
                        href="/user/support-tickets/create"
                        class="px-4 py-2 rounded-lg font-medium transition-colors duration-200 text-center"
                        :style="{ backgroundColor: derivedColors.accent, color: derivedColors.background }"
                    >
                        {{ t('createTicket') }}
                    </Link>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex-1 max-w-full lg:max-w-md">
                        <div class="relative">
                            <svg
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5"
                                :style="{ color: derivedColors.textMuted }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                            <input
                                v-model="searchTerm"
                                type="text"
                                :placeholder="t('searchTickets')"
                                class="w-full pl-10 pr-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm"
                                :style="{
                                    backgroundColor: derivedColors.surface + '50',
                                    borderColor: derivedColors.border,
                                    color: derivedColors.textPrimary
                                }"
                                @input="applyFilters"
                            >
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <select
                            v-model="selectedStatus"
                            class="px-3 py-2.5 text-sm border rounded-lg min-w-0"
                            :style="{
                                backgroundColor: derivedColors.surface + '50',
                                borderColor: derivedColors.border,
                                color: derivedColors.textPrimary
                            }"
                            @change="applyFilters"
                        >
                            <option value="">{{ t('allStatus') }}</option>
                            <option
                                v-for="status in statusOptions"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>

                        <button
                            v-if="searchTerm || selectedStatus"
                            class="px-4 py-2.5 text-sm font-medium border rounded-lg transition-colors whitespace-nowrap"
                            :style="{
                                backgroundColor: derivedColors.surface,
                                borderColor: derivedColors.border,
                                color: derivedColors.textPrimary
                            }"
                            @click="clearFilters"
                        >
                            {{ t('clearFilters') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border overflow-hidden" :style="cardStyle">
                <div v-if="tickets && tickets.length > 0">
                    <div class="divide-y" :style="{ borderColor: derivedColors.border + '20' }">
                        <div
                            v-for="ticket in tickets"
                            :key="ticket.id"
                            class="p-4 sm:p-6 hover:scale-[1.02] sm:hover:scale-105 transition-all duration-200 cursor-pointer"
                            @click="viewTicket(ticket)"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-2">
                                        <h3 class="text-base sm:text-lg font-semibold truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ ticket.subject }}
                                        </h3>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                class="px-2.5 py-1 text-xs font-medium rounded-full border inline-block"
                                                :class="getStatusClass(ticket.status)"
                                            >
                                                {{ ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1) }}
                                            </span>
                                            <span
                                                class="px-2.5 py-1 text-xs font-medium rounded-full border inline-block"
                                                :class="getPriorityClass(ticket.priority)"
                                            >
                                                {{ ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1) }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-sm mb-2 leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                                        {{ ticket.description.substring(0, 150) }}{{ ticket.description.length > 150 ? '...' : '' }}
                                    </p>
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                        <span>{{ t('ticket') }}: {{ ticket.ticket_number }}</span>
                                        <span>{{ formatDate(ticket.created_at) }}</span>
                                        <span v-if="ticket.replies_count > 0">
                                            {{ ticket.replies_count }} {{ ticket.replies_count === 1 ? t('reply') : t('replies') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 self-center sm:self-start sm:ml-4">
                                    <svg
                                        class="w-5 h-5"
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12 px-4">
                    <svg
                        class="mx-auto h-12 w-12 mb-4"
                        :style="{ color: derivedColors.textMuted }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"
                        />
                    </svg>
                    <p class="text-base sm:text-lg font-medium" :style="{ color: derivedColors.textMuted }">
                        {{ t('noTicketsFound') }}
                    </p>
                    <p class="text-sm mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('createYourFirstTicket') }}
                    </p>
                </div>

                <div
                    v-if="tickets && tickets.length > 0 && lastPage > 1"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20' }"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="text-xs sm:text-sm text-center sm:text-left" :style="{ color: derivedColors.textMuted }">
                            {{ t('showing') }} {{ meta.from || 0 }} {{ t('to') }} {{ meta.to || 0 }} {{ t('of') }} {{ meta.total || 0 }} {{ t('results') }}
                        </div>
                        <div class="flex justify-center gap-2">
                            <button
                                v-if="currentPage > 1"
                                class="px-3 py-2 text-sm font-medium rounded-md transition-colors"
                                :style="{ backgroundColor: derivedColors.surface, color: derivedColors.textSecondary }"
                                @click="changePage(currentPage - 1)"
                            >
                                {{ t('previous') }}
                            </button>
                            <span class="px-3 py-2 text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ currentPage }} / {{ lastPage }}
                            </span>
                            <button
                                v-if="currentPage < lastPage"
                                class="px-3 py-2 text-sm font-medium rounded-md transition-colors"
                                :style="{ backgroundColor: derivedColors.surface, color: derivedColors.textSecondary }"
                                @click="changePage(currentPage + 1)"
                            >
                                {{ t('next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
