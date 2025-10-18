<script setup>
import { computed, ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    currentPlan: {
        type: Object,
        default: null
    },
    expiresAt: {
        type: String,
        default: null
    },
    plans: {
        type: Array,
        default: () => []
    },
    hasActiveSubscription: {
        type: Boolean,
        default: false
    }
});

const { currencySymbol } = useSettings();
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

const surfaceStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '10'
}));

const buttonPrimaryStyle = computed(() => ({
    backgroundColor: derivedColors.value.accent,
    color: derivedColors.value.background
}));

const buttonSecondaryStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface,
    color: derivedColors.value.textPrimary,
    borderColor: derivedColors.value.border
}));

const isProcessing = ref(false);
const selectedPlan = ref(null);
const showSubscribeModal = ref(false);
const showCancelModal = ref(false);

const timeUntilExpiry = computed(() => {
    if (!props.expiresAt) return null;

    const expiry = new Date(props.expiresAt);
    const now = new Date();
    const diff = expiry - now;

    if (diff <= 0) return t('expired');

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

    if (days > 0) {
        return t('daysLeft', { days });
    } else {
        return t('hoursLeft', { hours });
    }
});

const formatNumber = (num) => {
    const number = parseFloat(num) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
};

const formatDate = (dateString) => {
    if (!dateString) return t('never');
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getPlanBenefits = (plan) => {
    const benefits = [];

    if (plan.mining_fee_percentage === 0) {
        benefits.push(t('noMiningFees'));
    } else {
        benefits.push(t('reducedMiningFees', { percentage: plan.mining_fee_percentage }));
    }

    if (plan.mining_rate_multiplier > 1) {
        benefits.push(t('miningRateMultiplier', { multiplier: plan.mining_rate_multiplier }));
    }

    if (plan.features && plan.features.length > 0) {
        benefits.push(...plan.features);
    }

    return benefits;
};

const getPlanButtonStyle = (plan) => {
    if (props.currentPlan && props.currentPlan.id === plan.id) {
        return {
            backgroundColor: derivedColors.value.success,
            color: derivedColors.value.textPrimary,
            cursor: 'default'
        };
    } else {
        return {
            backgroundColor: derivedColors.value.accent,
            color: derivedColors.value.background
        };
    }
};

const getPlanButtonText = (plan) => {
    if (props.currentPlan && props.currentPlan.id === plan.id) {
        return t('currentPlan');
    } else {
        return t('subscribe');
    }
};

const isCurrentPlan = (plan) => {
    return props.currentPlan && props.currentPlan.id === plan.id;
};

const openSubscribeModal = (plan) => {
    selectedPlan.value = plan;
    showSubscribeModal.value = true;
};

const closeSubscribeModal = () => {
    showSubscribeModal.value = false;
    selectedPlan.value = null;
};

const openCancelModal = () => {
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
};

const subscribe = () => {
    if (!selectedPlan.value) return;

    isProcessing.value = true;

    router.post(`/user/subscribe/${selectedPlan.value.id}`, {
        payment_method: 'wallet'
    }, {
        onSuccess: () => {
            showToast(t('subscriptionSuccessful', { plan: selectedPlan.value.name }), 'success');
            closeSubscribeModal();
        },
        onError: (errors) => {
            console.error('Subscribe errors:', errors);
            const errorMessages = Object.values(errors).flat();
            const errorMessage = errorMessages[0] || t('subscriptionFailed');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

const cancelSubscription = () => {
    isProcessing.value = true;

    router.post('/user/cancel-subscription', {}, {
        onSuccess: () => {
            showToast(t('subscriptionCancelled'), 'success');
            closeCancelModal();
        },
        onError: (errors) => {
            console.error('Cancel errors:', errors);
            const errorMessages = Object.values(errors).flat();
            const errorMessage = errorMessages[0] || t('cancellationFailed');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

onMounted(() => {
    const pageFlash = page.props.flash;
    if (pageFlash?.success) showToast(pageFlash.success, 'success');
    if (pageFlash?.error) showToast(pageFlash.error, 'error');
});
</script>

<template>
    <UserLayout
        :page-title="t('subscriptionPlans')"
        :page-section="t('subscription')"
    >
        <div class="max-w-6xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Current Subscription Status -->
            <div v-if="hasActiveSubscription" class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                        {{ t('currentSubscription') }}
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('planName') }}
                                </div>
                                <div class="text-lg sm:text-xl font-bold truncate" :style="{ color: derivedColors.accent }">
                                    {{ currentPlan?.name }}
                                </div>
                            </div>

                            <div>
                                <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('monthlyPrice') }}
                                </div>
                                <div class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ currencySymbol }}{{ formatNumber(currentPlan?.price) }}/{{ t('month') }}
                                </div>
                            </div>

                            <div>
                                <div class="text-sm mb-1" :style="{ color: derivedColors.textMuted }">
                                    {{ t('expiresOn') }}
                                </div>
                                <div class="text-base sm:text-lg font-semibold truncate" :style="{ color: derivedColors.warning }">
                                    {{ formatDate(expiresAt) }}
                                </div>
                                <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                                    {{ timeUntilExpiry }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('planBenefits') }}
                                </div>
                                <div class="space-y-2">
                                    <div v-for="benefit in getPlanBenefits(currentPlan)" :key="benefit" class="flex items-start space-x-2">
                                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" :style="{ color: derivedColors.success }" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ benefit }}</span>
                                    </div>
                                </div>
                            </div>

                            <button
                                @click="openCancelModal"
                                class="w-full py-3 px-4 rounded-lg font-medium transition-colors border"
                                :style="{ backgroundColor: 'transparent', color: '#ef4444', borderColor: '#ef4444' }"
                            >
                                {{ t('cancelSubscription') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Free User Notice -->
            <div v-else class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="p-4 sm:p-6 text-center">
                    <div class="mb-4">
                        <svg class="w-12 h-12 mx-auto" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold mb-2" :style="{ color: derivedColors.textPrimary }">
                        {{ t('freeUser') }}
                    </h3>
                    <p class="text-sm mb-4" :style="{ color: derivedColors.textMuted }">
                        {{ t('freeUserDescription') }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div class="rounded-lg p-3" :style="surfaceStyle">
                            <div :style="{ color: derivedColors.textPrimary }">{{ t('miningFee') }}</div>
                            <div class="font-semibold text-lg" :style="{ color: '#ef4444' }">10%</div>
                        </div>
                        <div class="rounded-lg p-3" :style="surfaceStyle">
                            <div :style="{ color: derivedColors.textPrimary }">{{ t('miningRate') }}</div>
                            <div class="font-semibold text-lg" :style="{ color: derivedColors.textMuted }">1x</div>
                        </div>
                        <div class="rounded-lg p-3" :style="surfaceStyle">
                            <div :style="{ color: derivedColors.textPrimary }">{{ t('features') }}</div>
                            <div class="font-semibold text-lg truncate" :style="{ color: derivedColors.textMuted }">{{ t('basic') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Plans -->
            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                        {{ t('availablePlans') }}
                    </h2>
                    <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('choosePlanThatSuitsYou') }}
                    </p>
                </div>
                <div class="p-4 sm:p-6">
                    <div v-if="plans.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
                        <div v-for="plan in plans" :key="plan.id" class="rounded-xl border overflow-hidden transition-all duration-300 hover:shadow-lg" :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border + '50' }">
                            <!-- Plan Header -->
                            <div class="p-4 sm:p-6 text-center" :style="isCurrentPlan(plan) ? { backgroundColor: derivedColors.success + '20' } : {}">
                                <h3 class="text-lg sm:text-xl font-bold mb-2 truncate" :style="{ color: derivedColors.textPrimary }">
                                    {{ plan.name }}
                                </h3>
                                <div class="text-2xl sm:text-3xl font-bold mb-1" :style="{ color: derivedColors.accent }">
                                    {{ currencySymbol }}{{ formatNumber(plan.price) }}
                                </div>
                                <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ t('perMonth') }}
                                </div>
                                <div v-if="isCurrentPlan(plan)" class="mt-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium" :style="{ backgroundColor: derivedColors.success, color: derivedColors.textPrimary }">
                                        {{ t('currentPlan') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Plan Benefits -->
                            <div class="p-4 sm:p-6 border-t" :style="{ borderColor: derivedColors.border + '20' }">
                                <div class="space-y-3 mb-6">
                                    <div class="flex items-center justify-between rounded-lg p-3" :style="surfaceStyle">
                                        <span class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ t('miningFee') }}</span>
                                        <span class="font-semibold" :style="{ color: plan.mining_fee_percentage === 0 ? derivedColors.success : derivedColors.warning }">
                                            {{ plan.mining_fee_percentage }}%
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg p-3" :style="surfaceStyle">
                                        <span class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ t('miningRate') }}</span>
                                        <span class="font-semibold" :style="{ color: derivedColors.accent }">
                                            {{ plan.mining_rate_multiplier }}x
                                        </span>
                                    </div>
                                </div>

                                <div v-if="plan.features && plan.features.length > 0" class="mb-6">
                                    <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                        {{ t('features') }}:
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="feature in plan.features.slice(0, 4)" :key="feature" class="flex items-start space-x-2">
                                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" :style="{ color: derivedColors.success }" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ feature }}</span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    @click="isCurrentPlan(plan) ? null : openSubscribeModal(plan)"
                                    :disabled="isCurrentPlan(plan)"
                                    class="w-full py-3 px-4 rounded-lg font-bold transition-all duration-300 disabled:cursor-not-allowed"
                                    :style="getPlanButtonStyle(plan)"
                                >
                                    {{ getPlanButtonText(plan) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <div :style="{ color: derivedColors.textMuted }">{{ t('noPlansAvailable') }}</div>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('checkBackLaterForPlans') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscribe Modal -->
        <div
            v-if="showSubscribeModal"
            class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            :style="{ backgroundColor: 'rgba(0, 0, 0, 0.7)' }"
            role="dialog"
            aria-modal="true"
            @click.self="closeSubscribeModal"
            @keydown.escape="closeSubscribeModal"
        >
            <div class="rounded-2xl shadow-2xl border max-w-md w-full mx-4 backdrop-blur-md" :style="{ backgroundColor: derivedColors.surface + '95', borderColor: derivedColors.border + '20' }">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('confirmSubscription') }}
                        </h3>
                        <button @click="closeSubscribeModal" class="transition-colors p-1 rounded" :style="{ color: derivedColors.textMuted }">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div v-if="selectedPlan" class="text-center mb-6">
                        <div class="text-xl sm:text-2xl font-bold mb-2 truncate" :style="{ color: derivedColors.accent }">
                            {{ selectedPlan.name }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold mb-2" :style="{ color: derivedColors.textPrimary }">
                            {{ currencySymbol }}{{ formatNumber(selectedPlan.price) }}
                        </div>
                        <div :style="{ color: derivedColors.textMuted }">{{ t('chargedMonthly') }}</div>
                    </div>

                    <div class="rounded-lg p-4 mb-6" :style="surfaceStyle">
                        <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                            {{ t('paymentMethod') }}:
                        </div>
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 flex-shrink-0" :style="{ color: derivedColors.accent }" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"/>
                            </svg>
                            <span :style="{ color: derivedColors.textPrimary }">{{ t('walletBalance') }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                        <button
                            @click="closeSubscribeModal"
                            class="flex-1 py-3 px-4 rounded-xl font-medium transition-colors border"
                            :style="buttonSecondaryStyle"
                        >
                            {{ t('cancel') }}
                        </button>
                        <button
                            @click="subscribe"
                            :disabled="isProcessing"
                            class="flex-1 py-3 px-4 rounded-xl font-bold disabled:opacity-50 transition-all"
                            :style="buttonPrimaryStyle"
                        >
                            <span v-if="isProcessing">{{ t('processing') }}...</span>
                            <span v-else>{{ t('subscribe') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showCancelModal"
            class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            :style="{ backgroundColor: 'rgba(0, 0, 0, 0.7)' }"
            role="dialog"
            aria-modal="true"
            @click.self="closeCancelModal"
            @keydown.escape="closeCancelModal"
        >
            <div class="rounded-2xl shadow-2xl border max-w-md w-full mx-4 backdrop-blur-md" :style="{ backgroundColor: derivedColors.surface + '95', borderColor: derivedColors.border + '20' }">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('cancelSubscription') }}
                        </h3>
                        <button @click="closeCancelModal" class="transition-colors p-1 rounded" :style="{ color: derivedColors.textMuted }">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="text-center mb-6">
                        <div class="text-4xl mb-4">
                            <svg class="w-12 h-12 mx-auto" :style="{ color: '#ef4444' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="text-base sm:text-lg font-semibold mb-2" :style="{ color: derivedColors.textPrimary }">
                            {{ t('areYouSure') }}
                        </div>
                        <div class="text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                            {{ t('cancelSubscriptionWarning') }}
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                        <button
                            @click="closeCancelModal"
                            class="flex-1 py-3 px-4 rounded-xl font-medium transition-colors border"
                            :style="buttonSecondaryStyle"
                        >
                            {{ t('keepSubscription') }}
                        </button>
                        <button
                            @click="cancelSubscription"
                            :disabled="isProcessing"
                            class="flex-1 py-3 px-4 rounded-xl font-bold disabled:opacity-50 transition-all"
                            :style="{ backgroundColor: '#ef4444', color: derivedColors.textPrimary }"
                        >
                            <span v-if="isProcessing">{{ t('cancelling') }}...</span>
                            <span v-else>{{ t('confirmCancel') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
