<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    competition: {
        type: Object,
        default: null
    },
    errors: {
        type: Object,
        default: () => ({})
    }
});

const pageProps = usePage();
const { currencySymbol } = useSettings();
const { success, error } = useToast();
const isProcessing = ref(false);
const errors = ref({});

const isEditing = computed(() => !!props.competition);
const today = new Date().toISOString().split('T')[0];
const flash = computed(() => pageProps.props.flash);
const formatDateTimeForInput = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toISOString().slice(0, 16);
};

const form = ref({
    name: props.competition?.name || '',
    description: props.competition?.description || '',
    type: props.competition?.type || 'weekly',
    prize_pool: Number(props.competition?.prize_pool) || '',
    entry_fee: Number(props.competition?.entry_fee) || 0,
    admin_fee_percentage: Number(props.competition?.admin_fee_percentage) || 30.00,
    prizes: props.competition?.prizes || [
        { position: 1, percentage: 50, amount: 0 },
        { position: 2, percentage: 30, amount: 0 },
        { position: 3, percentage: 20, amount: 0 }
    ],
    starts_at: formatDateTimeForInput(props.competition?.starts_at) || '',
    ends_at: formatDateTimeForInput(props.competition?.ends_at) || '',
    max_participants: Number(props.competition?.max_participants) || '',
    is_active: Boolean(props.competition?.is_active) ?? true
});

const typeOptions = [
    { value: 'daily', label: 'Daily Competition', duration: '1 day' },
    { value: 'weekly', label: 'Weekly Competition', duration: '7 days' },
    { value: 'monthly', label: 'Monthly Competition', duration: '30 days' }
];

const totalPercentage = computed(() => {
    return form.value.prizes.reduce((sum, prize) => sum + (prize.percentage || 0), 0);
});

const isValidPercentage = computed(() => {
    return totalPercentage.value === 100;
});

const totalEntryFees = computed(() => {
    const entryFee = parseFloat(form.value.entry_fee) || 0;
    const maxParticipants = parseInt(form.value.max_participants) || 0;
    return maxParticipants > 0 ? entryFee * maxParticipants : entryFee;
});

const adminFeeAmount = computed(() => {
    const adminPercentage = parseFloat(form.value.admin_fee_percentage) || 0;
    return (totalEntryFees.value * adminPercentage) / 100;
});

const netPrizePool = computed(() => {
    return totalEntryFees.value - adminFeeAmount.value;
});

const updatePrizeAmounts = () => {
    const pool = parseFloat(form.value.prize_pool) || 0;
    form.value.prizes.forEach(prize => {
        prize.amount = (pool * (prize.percentage || 0)) / 100;
    });
};

watch(() => form.value.prize_pool, updatePrizeAmounts);
watch(() => form.value.prizes, updatePrizeAmounts, { deep: true });

const goBack = () => {
    router.get('/admin/mining-competitions');
};

const addPrize = () => {
    const nextPosition = form.value.prizes.length + 1;
    form.value.prizes.push({
        position: nextPosition,
        percentage: 0,
        amount: 0
    });
};

const removePrize = (index) => {
    if (form.value.prizes.length > 1) {
        form.value.prizes.splice(index, 1);
        // Reorder positions
        form.value.prizes.forEach((prize, i) => {
            prize.position = i + 1;
        });
    }
};

const setQuickDuration = (type) => {
    if (!form.value.starts_at) return;

    const startDate = new Date(form.value.starts_at);
    const endDate = new Date(startDate);

    switch (type) {
        case 'daily':
            endDate.setDate(startDate.getDate() + 1);
            break;
        case 'weekly':
            endDate.setDate(startDate.getDate() + 7);
            break;
        case 'monthly':
            endDate.setMonth(startDate.getMonth() + 1);
            break;
    }

    form.value.ends_at = endDate.toISOString().slice(0, 16);
};

const submitForm = () => {
    isProcessing.value = true;
    errors.value = {};

    const formData = {
        name: form.value.name,
        description: form.value.description,
        type: form.value.type,
        prize_pool: parseFloat(form.value.prize_pool),
        entry_fee: parseFloat(form.value.entry_fee),
        admin_fee_percentage: parseFloat(form.value.admin_fee_percentage),
        prizes: form.value.prizes,
        starts_at: form.value.starts_at,
        ends_at: form.value.ends_at,
        max_participants: form.value.max_participants ? parseInt(form.value.max_participants) : null,
        is_active: form.value.is_active
    };

    const url = isEditing.value
        ? `/admin/mining-competitions/${props.competition.id}`
        : '/admin/mining-competitions';

    const method = isEditing.value ? 'patch' : 'post';

    router[method](url, formData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (formErrors) => {
            errors.value = formErrors;
            const errorMessages = Object.values(formErrors).flat();
            const errorMessage = errorMessages.length > 0
                ? errorMessages[0]
                : `Failed to ${isEditing.value ? 'update' : 'create'} competition. Please check the form for errors.`;
            error(errorMessage);
        },
        onFinish: () => isProcessing.value = false
    });
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
    }).format(num);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        console.error('Date formatting error:', error);
        return 'Invalid Date';
    }
};

onMounted(() => {
    updatePrizeAmounts();
});

watch(() => props.errors, (newErrors) => {
    errors.value = { ...errors.value, ...newErrors };
}, { immediate: true });

watch(flash, (newFlash) => {
    if (newFlash?.success) {
        success(newFlash.success);
    } else if (newFlash?.error) {
        error(newFlash.error);
    }
}, { immediate: true });

watch(() => form.value.starts_at, (newDate) => {
    if (newDate && form.value.ends_at && form.value.ends_at < newDate) {
        form.value.ends_at = newDate;
    }
});

watch(() => form.value.type, (newType) => {
    if (form.value.starts_at) {
        setQuickDuration(newType);
    }
});
</script>

<template>
    <AdminLayout
        :title="isEditing ? 'Edit Mining Competition' : 'Create Mining Competition'"
        page-section="Gamified Mining System"
    >
        <div class="py-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ isEditing ? 'Edit Mining Competition' : 'Create New Mining Competition' }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ isEditing ? `Update ${competition?.name} competition settings and prizes` : 'Set up a new mining competition with prize pools and leaderboards' }}
                            </p>
                        </div>
                        <button
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            @click="goBack"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to Competitions
                        </button>
                    </div>
                </div>

                <div
                    v-if="isEditing"
                    class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-xl p-6 mb-8 border border-yellow-200 dark:border-yellow-700"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-yellow-900 dark:text-yellow-100">
                            Current Competition Status
                        </h3>
                        <span
                            class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="competition.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'"
                        >
                            {{ competition.is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">Type:</span>
                            <div class="text-yellow-900 dark:text-yellow-100 font-bold capitalize">
                                {{ competition.type }}
                            </div>
                        </div>
                        <div>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">Prize Pool:</span>
                            <div class="text-yellow-900 dark:text-yellow-100 font-bold">
                                {{ currencySymbol }}{{ formatNumber(competition.prize_pool) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">Entry Fee:</span>
                            <div class="text-yellow-900 dark:text-yellow-100 font-bold">
                                {{ currencySymbol }}{{ formatNumber(competition.entry_fee) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">Participants:</span>
                            <div class="text-yellow-900 dark:text-yellow-100 font-bold">
                                {{ competition.participants_count || 0 }}
                                {{ competition.max_participants ? `/ ${competition.max_participants}` : '' }}
                            </div>
                        </div>
                        <div>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">Duration:</span>
                            <div class="text-yellow-900 dark:text-yellow-100 font-bold">
                                {{ formatDate(competition.starts_at) }} - {{ formatDate(competition.ends_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md overflow-hidden border border-gray-200 dark:border-slate-700">
                    <form class="p-6 space-y-6" @submit.prevent="submitForm">
                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Basic Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Competition Name *
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="e.g., Weekly Mining Championship"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.name }"
                                    >
                                    <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Competition Type *
                                    </label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.type }"
                                    >
                                        <option v-for="type in typeOptions" :key="type.value" :value="type.value">
                                            {{ type.label }} ({{ type.duration }})
                                        </option>
                                    </select>
                                    <p v-if="errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.type) ? errors.type[0] : errors.type }}
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Description *
                                    </label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="3"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="Describe the competition rules, objectives, and what participants need to do..."
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.description }"
                                    />
                                    <p v-if="errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Competition Schedule
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="starts_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Start Date & Time *
                                    </label>
                                    <input
                                        id="starts_at"
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        required
                                        :min="!isEditing ? today + 'T00:00' : null"
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.starts_at }"
                                    >
                                    <p v-if="errors.starts_at" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.starts_at) ? errors.starts_at[0] : errors.starts_at }}
                                    </p>
                                </div>

                                <div>
                                    <label for="ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        End Date & Time *
                                    </label>
                                    <input
                                        id="ends_at"
                                        v-model="form.ends_at"
                                        type="datetime-local"
                                        required
                                        :min="form.starts_at || (!isEditing ? today + 'T00:00' : null)"
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.ends_at }"
                                    >
                                    <p v-if="errors.ends_at" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.ends_at) ? errors.ends_at[0] : errors.ends_at }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Quick Duration Setup
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="type in typeOptions"
                                        :key="type.value"
                                        type="button"
                                        class="px-3 py-1.5 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors duration-200"
                                        @click="setQuickDuration(type.value)"
                                    >
                                        {{ type.label }}
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Click to auto-set end date based on start date and competition type
                                </p>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Entry Fees & Participation
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="entry_fee" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Entry Fee *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">{{ currencySymbol }}</span>
                                        </div>
                                        <input
                                            id="entry_fee"
                                            v-model="form.entry_fee"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            class="pl-8 w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                            placeholder="0.00"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.entry_fee }"
                                        >
                                    </div>
                                    <p v-if="errors.entry_fee" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.entry_fee) ? errors.entry_fee[0] : errors.entry_fee }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Amount each participant pays to join
                                    </p>
                                </div>

                                <div>
                                    <label for="admin_fee_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Admin Fee Percentage *
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="admin_fee_percentage"
                                            v-model="form.admin_fee_percentage"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            required
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all pr-8"
                                            placeholder="30.00"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.admin_fee_percentage }"
                                        >
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">%</span>
                                        </div>
                                    </div>
                                    <p v-if="errors.admin_fee_percentage" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.admin_fee_percentage) ? errors.admin_fee_percentage[0] : errors.admin_fee_percentage }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Platform fee from total entry fees
                                    </p>
                                </div>
                            </div>

                            <!-- Fee Calculation Summary -->
                            <div v-if="form.entry_fee > 0" class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                                    Entry Fee Breakdown
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">Entry Fee:</span>
                                        <div class="text-blue-800 dark:text-blue-200 font-bold">
                                            {{ currencySymbol }}{{ formatNumber(form.entry_fee) }}
                                        </div>
                                    </div>
                                    <div v-if="form.max_participants">
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">Total Entry Fees:</span>
                                        <div class="text-blue-800 dark:text-blue-200 font-bold">
                                            {{ currencySymbol }}{{ formatNumber(totalEntryFees) }}
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">Admin Fee ({{ form.admin_fee_percentage }}%):</span>
                                        <div class="text-blue-800 dark:text-blue-200 font-bold">
                                            {{ currencySymbol }}{{ formatNumber(adminFeeAmount) }}
                                        </div>
                                    </div>
                                    <div v-if="form.max_participants">
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">Net to Prize Pool:</span>
                                        <div class="text-blue-800 dark:text-blue-200 font-bold">
                                            {{ currencySymbol }}{{ formatNumber(netPrizePool) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Prize Pool Configuration
                                </h3>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors duration-200"
                                    @click="addPrize"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Add Prize
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="prize_pool" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Total Prize Pool *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">{{ currencySymbol }}</span>
                                        </div>
                                        <input
                                            id="prize_pool"
                                            v-model="form.prize_pool"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            class="pl-8 w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                            placeholder="0.00"
                                            :class="{ 'border-red-500 focus:ring-red-500': errors.prize_pool }"
                                        >
                                    </div>
                                    <p v-if="errors.prize_pool" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.prize_pool) ? errors.prize_pool[0] : errors.prize_pool }}
                                    </p>
                                </div>

                                <div>
                                    <label for="max_participants" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Max Participants (Optional)
                                    </label>
                                    <input
                                        id="max_participants"
                                        v-model="form.max_participants"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="Unlimited"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.max_participants }"
                                    >
                                    <p v-if="errors.max_participants" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.max_participants) ? errors.max_participants[0] : errors.max_participants }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Leave empty for unlimited participants
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">
                                        Prize Distribution
                                    </h4>
                                    <div class="text-xs">
                                        <span class="text-gray-500 dark:text-gray-400">Total Percentage: </span>
                                        <span :class="isValidPercentage ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="font-semibold">
                                            {{ totalPercentage }}%
                                        </span>
                                    </div>
                                </div>

                                <div v-for="(prize, index) in form.prizes" :key="index" class="grid grid-cols-12 gap-3 items-end p-4 bg-gray-50 dark:bg-slate-700 rounded-lg">
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Position
                                        </label>
                                        <input
                                            v-model="prize.position"
                                            type="number"
                                            min="1"
                                            class="w-full rounded border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-2 text-xs bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100"
                                            readonly
                                        >
                                    </div>

                                    <div class="col-span-3">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Percentage %
                                        </label>
                                        <input
                                            v-model.number="prize.percentage"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="w-full rounded border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-2 text-xs bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100"
                                        >
                                    </div>

                                    <div class="col-span-4">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Amount ({{ currencySymbol }})
                                        </label>
                                        <input
                                            :value="formatNumber(prize.amount)"
                                            type="text"
                                            class="w-full rounded border border-gray-300 dark:border-slate-600 bg-gray-100 dark:bg-slate-600 p-2 text-xs text-gray-700 dark:text-gray-300"
                                            readonly
                                        >
                                    </div>

                                    <div class="col-span-3 flex justify-end">
                                        <button
                                            v-if="form.prizes.length > 1"
                                            type="button"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors duration-200"
                                            @click="removePrize(index)"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!isValidPercentage" class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                                    <p class="text-xs text-red-600 dark:text-red-400">
                                        ⚠️ Prize percentages must total exactly 100%. Current total: {{ totalPercentage }}%
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Settings
                            </h3>
                            <div class="flex items-center justify-start">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-700"
                                    >
                                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active Competition</span>
                                </label>
                                <p class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                                    Users can participate when active and within schedule
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="form.name && form.prize_pool && form.starts_at && form.ends_at"
                            class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6 border border-blue-200 dark:border-blue-700"
                        >
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                                Competition Preview
                            </h4>
                            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                                <div class="flex justify-between">
                                    <span>Competition Name:</span>
                                    <span class="font-medium">{{ form.name || 'Not set' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Type:</span>
                                    <span class="font-medium capitalize">{{ form.type }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Prize Pool:</span>
                                    <span class="font-medium">{{ currencySymbol }}{{ formatNumber(form.prize_pool) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Prize Tiers:</span>
                                    <span class="font-medium">{{ form.prizes.length }} positions</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Duration:</span>
                                    <span class="font-medium">{{ formatDate(form.starts_at) }} - {{ formatDate(form.ends_at) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Max Participants:</span>
                                    <span class="font-medium">{{ form.max_participants || 'Unlimited' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Entry Fee:</span>
                                    <span class="font-medium">{{ currencySymbol }}{{ formatNumber(form.entry_fee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Admin Fee:</span>
                                    <span class="font-medium">{{ form.admin_fee_percentage }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Status:</span>
                                    <span class="font-medium">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
                            <div v-if="isEditing" class="text-sm text-gray-500 dark:text-gray-400">
                                Last updated: {{ formatDate(competition.updated_at) }}
                            </div>
                            <div v-else />

                            <div class="flex space-x-4">
                                <button
                                    type="button"
                                    class="px-6 py-3 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                                    @click="goBack"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isProcessing || !isValidPercentage"
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center"
                                >
                                    <svg
                                        v-if="isProcessing"
                                        class="animate-spin -ml-1 mr-3 h-4 w-4 text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                    </svg>
                                    {{ isProcessing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update Competition' : 'Create Competition') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
