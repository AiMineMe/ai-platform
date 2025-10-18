<script setup>
import {computed, ref, watch} from 'vue';
import {router, usePage} from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import {useToast} from "@/composables/useToast.js";

const props = defineProps({
    miningSession: {
        type: Object,
        required: true
    },
    users: {
        type: Array,
        default: () => []
    },
    errors: {
        type: Object,
        default: () => ({})
    }
});

const pageProps = usePage();
const { success, error } = useToast();

const isProcessing = ref(false);
const errors = ref({});
const flash = computed(() => pageProps.props.flash);

const form = ref({
    user_id: props.miningSession?.user_id || '',
    session_type: props.miningSession?.session_type || 'standard',
    mining_rate: Number(props.miningSession?.mining_rate) || 0.00001,
    level: Number(props.miningSession?.level) || 1,
    experience_points: Number(props.miningSession?.experience_points) || 0,
    streak_days: Number(props.miningSession?.streak_days) || 0,
    multiplier: Number(props.miningSession?.multiplier) || 1.0,
    is_active: Boolean(props.miningSession?.is_active) ?? true
});

const sessionTypeOptions = [
    { value: 'standard', label: 'Standard', description: 'Normal mining rate' },
    { value: 'boost', label: 'Boost', description: 'Enhanced mining rate' },
    { value: 'premium', label: 'Premium', description: 'Maximum mining rate' }
];

const requiredXpForNextLevel = computed(() => {
    return form.value.level * 100;
});

const progressToNextLevel = computed(() => {
    const currentLevelXp = (form.value.level - 1) * 100;
    const xpInCurrentLevel = form.value.experience_points - currentLevelXp;
    return Math.min((xpInCurrentLevel / 100) * 100, 100);
});

const selectedUser = computed(() => {
    return props.users.find(user => user.id === form.value.user_id);
});

const estimatedTokensPerDay = computed(() => {
    const tokensPerSecond = form.value.mining_rate * form.value.multiplier;
    return tokensPerSecond * 86400;
});

const goBack = () => {
    router.get('/admin/mining-sessions');
};

const submitForm = () => {
    isProcessing.value = true;
    errors.value = {};

    const formData = {
        user_id: parseInt(form.value.user_id),
        session_type: form.value.session_type,
        mining_rate: parseFloat(form.value.mining_rate),
        level: parseInt(form.value.level),
        experience_points: parseInt(form.value.experience_points),
        streak_days: parseInt(form.value.streak_days),
        multiplier: parseFloat(form.value.multiplier),
        is_active: form.value.is_active
    };

    router.patch(`/admin/mining-sessions/${props.miningSession.id}`, formData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (formErrors) => {
            errors.value = formErrors;
            const errorMessages = Object.values(formErrors).flat();
            const errorMessage = errorMessages.length > 0
                ? errorMessages[0]
                : 'Failed to update mining session. Please check the form for errors.';
            error(errorMessage);
        },
        onFinish: () => isProcessing.value = false
    });
};

watch(() => form.value.level, (newLevel, oldLevel) => {
    if (newLevel && oldLevel && newLevel !== oldLevel) {
        form.value.experience_points = Math.max(form.value.experience_points, (newLevel - 1) * 100);
    }
});

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
</script>

<template>
    <AdminLayout
        title="Edit Mining Session"
        page-section="Gamified Mining System"
    >
        <div class="py-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                Edit Mining Session
                            </h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Update mining session settings for {{ selectedUser?.name || 'user' }}
                            </p>
                        </div>
                        <button
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            @click="goBack"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to Sessions
                        </button>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 mb-8 border border-blue-200 dark:border-blue-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                            Current Session Status
                        </h3>
                        <span
                            class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="miningSession.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'"
                        >
              {{ miningSession.is_active ? 'ACTIVE' : 'INACTIVE' }}
            </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Total Mined:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatNumber(miningSession.total_mined) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Current Balance:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatNumber(miningSession.current_balance) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Last Claim:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatDate(miningSession.last_claim_at) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Session Started:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatDate(miningSession.session_started_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md overflow-hidden border border-gray-200 dark:border-slate-700">
                    <form class="p-6 space-y-6" @submit.prevent="submitForm">
                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                User Assignment
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        User *
                                    </label>
                                    <select
                                        id="user_id"
                                        v-model="form.user_id"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.user_id }"
                                    >
                                        <option value="">Select User</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.name }} ({{ user.email }})
                                        </option>
                                    </select>
                                    <p v-if="errors.user_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.user_id) ? errors.user_id[0] : errors.user_id }}
                                    </p>
                                </div>

                                <div>
                                    <label for="session_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Session Type *
                                    </label>
                                    <select
                                        id="session_type"
                                        v-model="form.session_type"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.session_type }"
                                    >
                                        <option v-for="type in sessionTypeOptions" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.session_type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.session_type) ? errors.session_type[0] : errors.session_type }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ sessionTypeOptions.find(t => t.value === form.session_type)?.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Mining Configuration
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="mining_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Mining Rate (per second) *
                                    </label>
                                    <input
                                        id="mining_rate"
                                        v-model="form.mining_rate"
                                        type="number"
                                        step="0.00001"
                                        min="0"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="0.00001"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.mining_rate }"
                                    >
                                    <p v-if="errors.mining_rate" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.mining_rate) ? errors.mining_rate[0] : errors.mining_rate }}
                                    </p>
                                </div>

                                <div>
                                    <label for="multiplier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Rate Multiplier *
                                    </label>
                                    <input
                                        id="multiplier"
                                        v-model="form.multiplier"
                                        type="number"
                                        step="0.1"
                                        min="1"
                                        max="10"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="1.0"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.multiplier }"
                                    >
                                    <p v-if="errors.multiplier" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.multiplier) ? errors.multiplier[0] : errors.multiplier }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Effective rate: {{ formatNumber(form.mining_rate * form.multiplier) }}/sec
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
                                <h4 class="text-sm font-medium text-green-800 dark:text-green-200 mb-2">
                                    Daily Mining Estimate
                                </h4>
                                <p class="text-sm text-green-700 dark:text-green-300">
                                    At current rate: <span class="font-bold">{{ formatNumber(estimatedTokensPerDay) }} tokens/day</span>
                                </p>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Level & Experience
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="level" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Level *
                                    </label>
                                    <input
                                        id="level"
                                        v-model="form.level"
                                        type="number"
                                        min="1"
                                        max="100"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="1"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.level }"
                                    >
                                    <p v-if="errors.level" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.level) ? errors.level[0] : errors.level }}
                                    </p>
                                </div>

                                <div>
                                    <label for="experience_points" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Experience Points *
                                    </label>
                                    <input
                                        id="experience_points"
                                        v-model="form.experience_points"
                                        type="number"
                                        min="0"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="0"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.experience_points }"
                                    >
                                    <p v-if="errors.experience_points" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.experience_points) ? errors.experience_points[0] : errors.experience_points }}
                                    </p>
                                </div>

                                <div>
                                    <label for="streak_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Streak Days *
                                    </label>
                                    <input
                                        id="streak_days"
                                        v-model="form.streak_days"
                                        type="number"
                                        min="0"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="0"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.streak_days }"
                                    >
                                    <p v-if="errors.streak_days" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.streak_days) ? errors.streak_days[0] : errors.streak_days }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                                <h4 class="text-sm font-medium text-purple-800 dark:text-purple-200 mb-2">
                                    Level Progress
                                </h4>
                                <div class="w-full bg-purple-200 dark:bg-purple-800 rounded-full h-3 mb-2">
                                    <div
                                        class="bg-gradient-to-r from-purple-500 to-indigo-600 h-3 rounded-full transition-all duration-300"
                                        :style="{ width: progressToNextLevel + '%' }"
                                    />
                                </div>
                                <div class="flex justify-between text-xs text-purple-700 dark:text-purple-300">
                                    <span>Level {{ form.level }}</span>
                                    <span>{{ Math.floor(progressToNextLevel) }}% to next level</span>
                                    <span>Level {{ form.level + 1 }}</span>
                                </div>
                                <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                    {{ form.experience_points }} / {{ requiredXpForNextLevel }} XP
                                </p>
                            </div>
                        </div>

                        <div class="pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Session Status
                            </h3>
                            <div class="flex items-center justify-start">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-700"
                                    >
                                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active Session</span>
                                </label>
                                <p class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                                    Enable to allow mining for this user
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Last updated: {{ formatDate(miningSession.updated_at) }}
                            </div>

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
                                    :disabled="isProcessing"
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
                                    {{ isProcessing ? 'Updating...' : 'Update Session' }}
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
