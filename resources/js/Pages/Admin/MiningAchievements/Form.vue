<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    achievement: {
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

const isEditing = computed(() => !!props.achievement);
const flash = computed(() => pageProps.props.flash);

const form = ref({
    name: props.achievement?.name || '',
    description: props.achievement?.description || '',
    icon: props.achievement?.icon || '',
    type: props.achievement?.type || 'mining',
    condition: props.achievement?.condition || '',
    reward_amount: Number(props.achievement?.reward_amount) || '',
    reward_type: props.achievement?.reward_type || 'tokens',
    required_value: Number(props.achievement?.required_value) || '',
    is_active: Boolean(props.achievement?.is_active) ?? true
});

const typeOptions = [
    { value: 'mining', label: 'Mining Achievement', description: 'Based on mining activity' },
    { value: 'streak', label: 'Streak Achievement', description: 'Based on consecutive days' },
    { value: 'level', label: 'Level Achievement', description: 'Based on user level' },
    { value: 'total', label: 'Total Achievement', description: 'Based on cumulative totals' },
    { value: 'competition', label: 'Competition Achievement', description: 'Based on competition results' }
];

const rewardTypeOptions = [
    { value: 'tokens', label: 'Tokens', description: 'Reward tokens' },
    { value: 'multiplier', label: 'Multiplier', description: 'Mining rate multiplier' },
    { value: 'boost', label: 'Boost', description: 'Temporary mining boost' }
];

const conditionSuggestions = computed(() => {
    const suggestions = {
        'mining': [
            'Mine 100 tokens in a day',
            'Complete 10 mining sessions',
            'Mine for 24 hours continuously'
        ],
        'streak': [
            'Mine for 7 consecutive days',
            'Maintain 30-day streak',
            'Complete weekly challenges'
        ],
        'level': [
            'Reach level 10',
            'Achieve level 25',
            'Max out at level 50'
        ],
        'total': [
            'Mine 10,000 total tokens',
            'Complete 100 total sessions',
            'Earn 1M tokens lifetime'
        ],
        'competition': [
            'Win first place in competition',
            'Participate in 5 competitions',
            'Top 10 finish in monthly contest'
        ]
    };
    return suggestions[form.value.type] || [];
});

const goBack = () => {
    router.get('/admin/mining-achievements');
};

const submitForm = () => {
    isProcessing.value = true;
    errors.value = {};

    const formData = {
        name: form.value.name,
        description: form.value.description,
        icon: form.value.icon,
        type: form.value.type,
        condition: form.value.condition,
        reward_amount: parseFloat(form.value.reward_amount),
        reward_type: form.value.reward_type,
        required_value: parseInt(form.value.required_value),
        is_active: form.value.is_active
    };

    const url = isEditing.value
        ? `/admin/mining-achievements/${props.achievement.id}`
        : '/admin/mining-achievements';

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
                : `Failed to ${isEditing.value ? 'update' : 'create'} achievement. Please check the form for errors.`;
            error(errorMessage);
        },
        onFinish: () => isProcessing.value = false
    });
};

const fillSuggestion = (suggestion) => {
    form.value.condition = suggestion;
};

const formatDate = (dateString) => {
    if (!dateString) return 'Never';

    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return `Today at ${date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    } else if (diffDays === 1) {
        return `Yesterday at ${date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        })}`;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
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
        :title="isEditing ? 'Edit Mining Achievement' : 'Create Mining Achievement'"
        page-section="Gamified Mining System"
    >
        <div class="py-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ isEditing ? 'Edit Mining Achievement' : 'Create New Mining Achievement' }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ isEditing ? `Update ${achievement?.name} achievement settings and rewards` : 'Set up a new achievement for miners to unlock' }}
                            </p>
                        </div>
                        <button
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            @click="goBack"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to Achievements
                        </button>
                    </div>
                </div>

                <div
                    v-if="isEditing"
                    class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-xl p-6 mb-8 border border-purple-200 dark:border-purple-700"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-purple-900 dark:text-purple-100">
                            Current Achievement Status
                        </h3>
                        <span
                            class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="achievement.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'"
                                    >
                          {{ achievement.is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-purple-600 dark:text-purple-400 font-medium">Type:</span>
                            <div class="text-purple-900 dark:text-purple-100 font-bold capitalize">
                                {{ achievement.type }}
                            </div>
                        </div>
                        <div>
                            <span class="text-purple-600 dark:text-purple-400 font-medium">Reward:</span>
                            <div class="text-purple-900 dark:text-purple-100 font-bold">
                                {{ achievement.reward_amount }} {{ achievement.reward_type }}
                            </div>
                        </div>
                        <div>
                            <span class="text-purple-600 dark:text-purple-400 font-medium">Required Value:</span>
                            <div class="text-purple-900 dark:text-purple-100 font-bold">
                                {{ achievement.required_value }}
                            </div>
                        </div>
                        <div>
                            <span class="text-purple-600 dark:text-purple-400 font-medium">Unlocked By:</span>
                            <div class="text-purple-900 dark:text-purple-100 font-bold">
                                {{ achievement.user_achievements_count || 0 }} users
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
                            <div class="grid gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Achievement Name *
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="e.g., Mining Pioneer"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.name }"
                                    >
                                    <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
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
                                        placeholder="Describe what users need to do to unlock this achievement..."
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
                                Achievement Configuration
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Achievement Type *
                                    </label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.type }"
                                    >
                                        <option v-for="type in typeOptions" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.type) ? errors.type[0] : errors.type }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ typeOptions.find(t => t.value === form.type)?.description }}
                                    </p>
                                </div>

                                <div>
                                    <label for="required_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Required Value *
                                    </label>
                                    <input
                                        id="required_value"
                                        v-model="form.required_value"
                                        type="number"
                                        min="1"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="e.g., 100"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.required_value }"
                                    >
                                    <p v-if="errors.required_value" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.required_value) ? errors.required_value[0] : errors.required_value }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Target value to unlock achievement
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="condition" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Condition Description *
                                    </label>
                                    <textarea
                                        id="condition"
                                        v-model="form.condition"
                                        rows="2"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="Describe the exact condition to unlock this achievement..."
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.condition }"
                                    />
                                    <p v-if="errors.condition" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.condition) ? errors.condition[0] : errors.condition }}
                                    </p>

                                    <div v-if="conditionSuggestions.length > 0" class="mt-3">
                                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">
                                            Suggestions for {{ form.type }} achievements:
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="suggestion in conditionSuggestions"
                                                :key="suggestion"
                                                type="button"
                                                class="px-3 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 rounded-full hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors duration-200"
                                                @click="fillSuggestion(suggestion)"
                                            >
                                                {{ suggestion }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Reward Configuration
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="reward_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reward Type *
                                    </label>
                                    <select
                                        id="reward_type"
                                        v-model="form.reward_type"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.reward_type }"
                                    >
                                        <option v-for="reward in rewardTypeOptions" :key="reward.value" :value="reward.value">
                                            {{ reward.label }}
                                        </option>
                                    </select>
                                    <p v-if="errors.reward_type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.reward_type) ? errors.reward_type[0] : errors.reward_type }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ rewardTypeOptions.find(r => r.value === form.reward_type)?.description }}
                                    </p>
                                </div>

                                <div>
                                    <label for="reward_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reward Amount *
                                    </label>
                                    <div class="relative">
                                        <div v-if="form.reward_type === 'tokens'" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">{{ currencySymbol }}</span>
                                        </div>
                                        <input
                                            id="reward_amount"
                                            v-model="form.reward_amount"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            required
                                            :class="[
                                                'w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all',
                                                form.reward_type === 'tokens' ? 'pl-8' : '',
                                                errors.reward_amount ? 'border-red-500 focus:ring-red-500' : ''
                                              ]"
                                            :placeholder="form.reward_type === 'multiplier' ? '1.5' : form.reward_type === 'boost' ? '24' : '100'"
                                        >
                                        <div v-if="form.reward_type === 'multiplier'" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">x</span>
                                        </div>
                                        <div v-if="form.reward_type === 'boost'" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">hrs</span>
                                        </div>
                                    </div>
                                    <p v-if="errors.reward_amount" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ Array.isArray(errors.reward_amount) ? errors.reward_amount[0] : errors.reward_amount }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ form.reward_type === 'tokens' ? 'Number of tokens to award' :
                                        form.reward_type === 'multiplier' ? 'Mining rate multiplier (e.g., 1.5x)' :
                                            'Boost duration in hours' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Status
                            </h3>
                            <div class="flex items-center justify-start">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-700"
                                    >
                                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active Achievement</span>
                                </label>
                                <p class="ml-4 text-xs text-gray-500 dark:text-gray-400">
                                    Users can unlock this achievement when active
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="form.name && form.reward_amount && form.required_value"
                            class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6 border border-blue-200 dark:border-blue-700"
                        >
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                                Achievement Preview
                            </h4>
                            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                                <div class="flex justify-between">
                                    <span>Achievement Name:</span>
                                    <span class="font-medium">{{ form.name || 'Not set' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Type:</span>
                                    <span class="font-medium capitalize">{{ form.type }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Required Value:</span>
                                    <span class="font-medium">{{ form.required_value }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reward:</span>
                                    <span class="font-medium">
                    {{ form.reward_amount }} {{ form.reward_type }}
                    <span v-if="form.reward_type === 'tokens'">{{ currencySymbol }}</span>
                    <span v-if="form.reward_type === 'multiplier'">x multiplier</span>
                    <span v-if="form.reward_type === 'boost'">hour boost</span>
                  </span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Status:</span>
                                    <span class="font-medium">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
                            <div v-if="isEditing" class="text-sm text-gray-500 dark:text-gray-400">
                                Last updated: {{ formatDate(achievement.updated_at) }}
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
                                    {{ isProcessing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update Achievement' : 'Create Achievement') }}
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
