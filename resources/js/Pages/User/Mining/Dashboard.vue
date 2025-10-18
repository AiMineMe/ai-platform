<script setup>
import { computed, ref, onMounted, onUnmounted, reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    miningSession: {
        type: Object,
        default: () => ({
            id: null,
            is_active: false,
            total_mined: 0,
            current_balance: 0,
            level: 1,
            experience_points: 0,
            streak_days: 0,
            mining_rate: 0.00001,
            multiplier: 1.0,
            session_type: 'standard',
            last_claim_at: null,
            session_started_at: null,
            session_ends_at: null
        })
    },
    achievements: {
        type: Array,
        default: () => []
    },
    availableAchievements: {
        type: Array,
        default: () => []
    },
    activeCompetitions: {
        type: Array,
        default: () => []
    },
    leaderboard: {
        type: Array,
        default: () => []
    },
    userRank: {
        type: Number,
        default: null
    },
    currentSubscription: {
        type: Object,
        default: null
    },
    miningFeeRate: {
        type: Number,
        default: 10.0
    },
    subscriptionMultiplier: {
        type: Number,
        default: 1.0
    }
});

const { currencySymbol } = useSettings();
const { showToast } = useToast();
const { t } = useTranslation();
const page = usePage();

const miningState = reactive({
    ...props.miningSession
});

const isProcessing = ref(false);
const currentBalance = ref(props.miningSession.current_balance || 0);
const totalMined = ref(props.miningSession.total_mined || 0);
const miningInterval = ref(null);
const claimModal = ref({
    isOpen: false,
    amount: 0,
    netAmount: 0,
    feeAmount: 0,
    feeRate: 0
});

const loadingStates = ref({
    starting: false,
    stopping: false,
    claiming: false,
    joiningCompetition: false
});

const colorCache = new Map();
const validateMiningData = (data) => {
    const errors = [];

    if (data.current_balance < 0 || data.current_balance > 1000) {
        errors.push('Invalid balance detected');
    }

    if (data.mining_rate < 0 || data.mining_rate > 0.001) {
        errors.push('Invalid mining rate detected');
    }

    if (data.level < 1 || data.level > 1000) {
        errors.push('Invalid level detected');
    }

    if (errors.length > 0) {
        console.error('Mining data validation failed:', errors);
        showToast('Invalid data received from server', 'error');
        return false;
    }

    return true;
};

const syncMiningData = (newMiningSession) => {
    if (!newMiningSession) return;
    const validatedData = {
        id: newMiningSession.id,
        current_balance: Math.max(0, parseFloat(newMiningSession.current_balance) || 0),
        total_mined: Math.max(0, parseFloat(newMiningSession.total_mined) || 0),
        is_active: Boolean(newMiningSession.is_active),
        level: Math.max(1, parseInt(newMiningSession.level) || 1),
        experience_points: Math.max(0, parseInt(newMiningSession.experience_points) || 0),
        streak_days: Math.max(0, parseInt(newMiningSession.streak_days) || 0),
        mining_rate: Math.max(0, parseFloat(newMiningSession.mining_rate) || 0.00001),
        multiplier: Math.max(1, parseFloat(newMiningSession.multiplier) || 1.0),
        session_type: newMiningSession.session_type || 'standard',
        last_claim_at: newMiningSession.last_claim_at,
        session_started_at: newMiningSession.session_started_at,
        session_ends_at: newMiningSession.session_ends_at
    };

    if (!validateMiningData(validatedData)) {
        return;
    }

    Object.assign(miningState, validatedData);
    currentBalance.value = validatedData.current_balance;
    totalMined.value = validatedData.total_mined;
    if (validatedData.is_active) {
        startMiningSimulation();
    } else {
        stopMiningSimulation();
    }
};

const primaryColor = computed(() => page.props.primaryColor || '#1f2937');
const derivedColors = computed(() => {
    const primary = primaryColor.value;

    if (!colorCache.has(primary)) {
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

        colorCache.set(primary, {
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
        });
    }

    return colorCache.get(primary);
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

const subscriptionBenefits = computed(() => {
    if (!props.currentSubscription) {
        return {
            planName: 'Free Plan',
            miningFee: props.miningFeeRate + '%',
            rateMultiplier: props.subscriptionMultiplier + 'x',
            features: ['Basic Features'],
            isActive: false,
            expiresAt: null
        };
    }

    return {
        planName: props.currentSubscription?.name || 'Unknown',
        miningFee: props.miningFeeRate + '%',
        rateMultiplier: props.subscriptionMultiplier + 'x',
        features: props.currentSubscription?.features || [],
        isActive: true,
        expiresAt: props.currentSubscription.ends_at
    };
});

const effectiveMiningRate = computed(() => {
    const baseRate = miningState.mining_rate || 0;
    const sessionMultiplier = miningState.multiplier || 1;
    const subscriptionMult = props.subscriptionMultiplier || 1;
    const rate = baseRate * sessionMultiplier * subscriptionMult;
    return Math.min(Math.max(0, rate), 0.01);
});

const claimCalculation = computed(() => {
    const grossAmount = currentBalance.value;
    const feeAmount = (grossAmount * props.miningFeeRate) / 100;
    const netAmount = Math.max(0, grossAmount - feeAmount);

    return {
        gross: grossAmount,
        fee: feeAmount,
        net: netAmount,
        feeRate: props.miningFeeRate
    };
});

const canStartMining = computed(() => {
    return !miningState.is_active && !loadingStates.value.starting;
});

const canStopMining = computed(() => {
    return miningState.is_active && !loadingStates.value.stopping;
});

const canClaim = computed(() => {
    return currentBalance.value > 0 && !loadingStates.value.claiming;
});

const dailyEarnings = computed(() => {
    return effectiveMiningRate.value * 86400;
});

const levelProgress = computed(() => {
    const currentLevel = miningState.level;
    const currentXp = miningState.experience_points;
    const xpForCurrentLevel = (currentLevel - 1) * 100;
    const xpForNextLevel = currentLevel * 100;
    const xpInCurrentLevel = currentXp - xpForCurrentLevel;
    const xpNeededForNextLevel = xpForNextLevel - xpForCurrentLevel;

    if (xpNeededForNextLevel <= 0) return 100;
    return Math.min(Math.max(0, (xpInCurrentLevel / xpNeededForNextLevel) * 100), 100);
});

const xpToNextLevel = computed(() => {
    const currentLevel = miningState.level;
    const currentXp = miningState.experience_points;
    const xpForNextLevel = currentLevel * 100;
    return Math.max(0, xpForNextLevel - currentXp);
});

const sessionTimeRemaining = computed(() => {
    if (!miningState.session_ends_at) return null;
    const endTime = new Date(miningState.session_ends_at);
    const now = new Date();
    const diff = endTime - now;

    if (diff <= 0) return 'Expired';

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return `${hours}h ${minutes}m`;
});
const handleApiError = (errors, operation) => {
    console.error(`${operation} errors:`, errors);

    const errorMessages = [];
    if (typeof errors === 'object') {
        Object.values(errors).forEach(errorArray => {
            if (Array.isArray(errorArray)) {
                errorMessages.push(...errorArray);
            } else {
                errorMessages.push(String(errorArray));
            }
        });
    }

    const primaryError = errorMessages[0] || `Failed to ${operation}`;

    if (primaryError.includes('rate limit') || primaryError.includes('too many')) {
        showToast('Too many attempts. Please wait before trying again.', 'warning');
    } else if (primaryError.includes('insufficient')) {
        showToast('Insufficient balance for this operation.', 'error');
    } else if (primaryError.includes('session')) {
        showToast('Mining session error. Please refresh the page.', 'error');
    } else {
        showToast(primaryError, 'error');
    }
};

const formatNumber = (num) => {
    const number = parseFloat(num) || 0;
    if (!isFinite(number)) return '0';

    if (number >= 1000000000) {
        return (number / 1000000000).toFixed(2) + 'B';
    } else if (number >= 1000000) {
        return (number / 1000000).toFixed(2) + 'M';
    } else if (number >= 1000) {
        return (number / 1000).toFixed(2) + 'K';
    } else if (Number.isInteger(number)) {
        return number.toString();
    } else {
        return number.toFixed(8);
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'Never';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
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

const getSessionTypeClass = (type) => {
    const typeClasses = {
        'standard': { backgroundColor: derivedColors.value.accent + '20', color: derivedColors.value.accent, borderColor: derivedColors.value.accent + '30' },
        'boost': { backgroundColor: '#a855f7' + '20', color: '#a855f7', borderColor: '#a855f7' + '30' },
        'premium': { backgroundColor: derivedColors.value.warning + '20', color: derivedColors.value.warning, borderColor: derivedColors.value.warning + '30' }
    };
    return typeClasses[type] || typeClasses.standard;
};

const getAchievementTypeClass = (type) => {
    const typeClasses = {
        'mining': { backgroundColor: derivedColors.value.accent + '20', color: derivedColors.value.accent },
        'streak': { backgroundColor: '#f97316' + '20', color: '#f97316' },
        'level': { backgroundColor: '#a855f7' + '20', color: '#a855f7' },
        'total': { backgroundColor: derivedColors.value.success + '20', color: derivedColors.value.success },
        'competition': { backgroundColor: '#ef4444' + '20', color: '#ef4444' }
    };
    return typeClasses[type] || typeClasses.mining;
};

const goToSubscriptions = () => {
    router.get('/user/subscriptions');
};

const startMiningSimulation = () => {
    stopMiningSimulation();

    if (!miningState.is_active) return;

    miningInterval.value = setInterval(() => {
        try {
            const rate = effectiveMiningRate.value;
            if (rate <= 0 || rate > 0.01 || !isFinite(rate)) {
                console.warn('Invalid mining rate detected:', rate);
                stopMiningSimulation();
                return;
            }

            if (miningState.session_ends_at) {
                const endTime = new Date(miningState.session_ends_at);
                if (new Date() > endTime) {
                    miningState.is_active = false;
                    stopMiningSimulation();
                    showToast('Mining session has expired', 'info');
                    return;
                }
            }

            const newBalance = currentBalance.value + rate;
            currentBalance.value = Math.min(newBalance, 1000);
            totalMined.value = Math.min(totalMined.value + rate, 1000000);

        } catch (error) {
            console.error('Mining simulation error:', error);
            stopMiningSimulation();
            showToast('Mining simulation error occurred', 'error');
        }
    }, 1000);
};

const stopMiningSimulation = () => {
    if (miningInterval.value) {
        clearInterval(miningInterval.value);
        miningInterval.value = null;
    }
};

const startMining = async () => {
    if (loadingStates.value.starting) return;

    loadingStates.value.starting = true;

    try {
        router.post('/user/mining/start', {}, {
            onSuccess: (page) => {
                syncMiningData(page.props.miningSession);
                showToast('Mining started successfully!', 'success');
            },
            onError: (errors) => handleApiError(errors, 'start mining'),
            onFinish: () => {
                loadingStates.value.starting = false;
            },
            preserveScroll: true
        });
    } catch (error) {
        console.error('Unexpected error starting mining:', error);
        showToast('An unexpected error occurred. Please try again.', 'error');
        loadingStates.value.starting = false;
    }
};

const stopMining = async () => {
    if (loadingStates.value.stopping) return;

    loadingStates.value.stopping = true;

    try {
        router.post('/user/mining/stop', {}, {
            onSuccess: (page) => {
                syncMiningData(page.props.miningSession);
                showToast('Mining stopped successfully!', 'success');
                stopMiningSimulation();
            },
            onError: (errors) => handleApiError(errors, 'stop mining'),
            onFinish: () => {
                loadingStates.value.stopping = false;
            },
            preserveScroll: true
        });
    } catch (error) {
        console.error('Unexpected error stopping mining:', error);
        showToast('An unexpected error occurred. Please try again.', 'error');
        loadingStates.value.stopping = false;
    }
};

const openClaimModal = () => {
    const calculation = claimCalculation.value;
    claimModal.value.amount = calculation.gross;
    claimModal.value.netAmount = calculation.net;
    claimModal.value.feeAmount = calculation.fee;
    claimModal.value.feeRate = calculation.feeRate;
    claimModal.value.isOpen = true;
};

const closeClaimModal = () => {
    claimModal.value.isOpen = false;
    claimModal.value.amount = 0;
    claimModal.value.netAmount = 0;
    claimModal.value.feeAmount = 0;
    claimModal.value.feeRate = 0;
};

const claimTokens = async () => {
    if (loadingStates.value.claiming) return;

    loadingStates.value.claiming = true;

    try {
        router.post('/user/mining/claim', {}, {
            onSuccess: (page) => {
                syncMiningData(page.props.miningSession);
                showToast(`Claimed ${formatNumber(claimModal.value.netAmount)} tokens successfully!`, 'success');
                closeClaimModal();
            },
            onError: (errors) => handleApiError(errors, 'claim tokens'),
            onFinish: () => {
                loadingStates.value.claiming = false;
            },
            preserveScroll: true
        });
    } catch (error) {
        console.error('Unexpected error claiming tokens:', error);
        showToast('An unexpected error occurred. Please try again.', 'error');
        loadingStates.value.claiming = false;
    }
};

const claimAchievement = (achievement) => {
    if (!achievement?.id) {
        showToast('Invalid achievement', 'error');
        return;
    }

    router.post(`/user/mining/achievements/${achievement.id}/claim`, {}, {
        onSuccess: () => {
            showToast(`Achievement "${achievement.achievement?.name}" claimed!`, 'success');
        },
        onError: (errors) => handleApiError(errors, 'claim achievement'),
        preserveScroll: true
    });
};

const joinCompetition = (competition) => {
    if (!competition?.id) {
        showToast('Invalid competition', 'error');
        return;
    }

    if (competition.user_joined) {
        showToast('Already joined this competition', 'info');
        return;
    }

    if (competition.is_full) {
        showToast('Competition is full', 'error');
        return;
    }

    if (loadingStates.value.joiningCompetition) return;

    loadingStates.value.joiningCompetition = true;

    router.post(`/user/mining/competitions/${competition.id}/join`, {}, {
        onSuccess: () => {
            showToast(`Joined "${competition.name}" competition successfully!`, 'success');
            if (page.props.activeCompetitions) {
                router.reload({
                    only: ['activeCompetitions', 'leaderboard'],
                    preserveScroll: true
                });
            }
        },
        onError: (errors) => handleApiError(errors, 'join competition'),
        onFinish: () => {
            loadingStates.value.joiningCompetition = false;
        },
        preserveScroll: true
    });
};

const getCompetitionButtonStyle = (competition) => {
    if (competition.user_joined) {
        return {
            backgroundColor: derivedColors.value.success,
            color: derivedColors.value.textPrimary,
            cursor: 'default'
        };
    } else if (competition.is_full) {
        return {
            backgroundColor: '#6b7280',
            color: derivedColors.value.textPrimary,
            cursor: 'not-allowed'
        };
    } else {
        return {
            backgroundColor: derivedColors.value.secondary,
            color: derivedColors.value.textPrimary
        };
    }
};

const getCompetitionButtonText = (competition) => {
    if (competition.user_joined) {
        return 'Joined';
    } else if (competition.is_full) {
        return 'Full';
    } else {
        return 'Join Competition';
    }
};

const handleVisibilityChange = () => {
    if (document.hidden) {
        stopMiningSimulation();
    } else if (miningState.is_active) {
        router.reload({ only: ['miningSession'] });
    }
};

const handleKeydown = (event) => {
    if (claimModal.value.isOpen) {
        if (event.key === 'Escape') {
            closeClaimModal();
        } else if (event.key === 'Enter' && event.ctrlKey && !loadingStates.value.claiming) {
            claimTokens();
        }
    }
};

watch(
    () => props.miningSession,
    (newSession) => {
        if (newSession) {
            syncMiningData(newSession);
        }
    },
    { deep: true, immediate: true }
);

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('keydown', handleKeydown);
    if (miningState.is_active) {
        startMiningSimulation();
    }
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    document.removeEventListener('keydown', handleKeydown);
    stopMiningSimulation();
});
</script>

<template>
    <UserLayout
        :page-title="t('miningDashboard')"
        :page-section="t('mining')"
    >
        <div class="max-w-none mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" role="region" :aria-label="t('miningStatistics')">
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.accent }"
                        :aria-label="t('currentBalance')"
                    >
                        {{ currencySymbol }}{{ formatNumber(currentBalance) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('currentBalance') }}
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.textPrimary }"
                        :aria-label="t('totalMined')"
                    >
                        {{ currencySymbol }}{{ formatNumber(totalMined) }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalMined') }}
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.secondary }"
                        :aria-label="t('miningLevel')"
                    >
                        {{ t('level') }} {{ miningSession.level }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('xpToNextLevel', { xp: formatNumber(xpToNextLevel) }) }}
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-6 hover:opacity-90 transition-all duration-200" :style="cardStyle">
                    <div
                        class="text-lg sm:text-2xl font-bold truncate"
                        :style="{ color: derivedColors.warning }"
                        :aria-label="t('streakDays')"
                    >
                        {{ miningSession.streak_days }} {{ t('days') }}
                    </div>
                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                        {{ t('miningStreak') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                        <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('subscriptionStatus') }}
                        </h2>
                        <button
                            @click="goToSubscriptions"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors w-full sm:w-auto"
                            :style="{ backgroundColor: derivedColors.accent, color: derivedColors.background }"
                        >
                            {{ subscriptionBenefits.isActive ? t('manageSubscription') : t('upgradePlan') }}
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                            <div class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('currentPlan') }}
                            </div>
                            <div class="text-base sm:text-lg font-bold truncate" :style="{ color: subscriptionBenefits.isActive ? derivedColors.accent : derivedColors.textMuted }">
                                {{ subscriptionBenefits.planName }}
                            </div>
                        </div>

                        <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                            <div class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('miningFee') }}
                            </div>
                            <div class="text-base sm:text-lg font-bold truncate" :style="{ color: miningFeeRate === 0 ? derivedColors.success : derivedColors.warning }">
                                {{ subscriptionBenefits.miningFee }}
                            </div>
                        </div>

                        <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                            <div class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('miningRate') }}
                            </div>
                            <div class="text-base sm:text-lg font-bold truncate" :style="{ color: derivedColors.accent }">
                                {{ subscriptionBenefits.rateMultiplier }}
                            </div>
                        </div>

                        <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                            <div class="text-sm mb-1 truncate" :style="{ color: derivedColors.textMuted }">
                                {{ t('status') }}
                            </div>
                            <div class="text-base sm:text-lg font-bold truncate" :style="{ color: subscriptionBenefits.isActive ? derivedColors.success : derivedColors.textMuted }">
                                {{ subscriptionBenefits.isActive ? t('active') : t('free') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden shadow-lg" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                        <h2 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('miningControl') }}
                        </h2>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-3">
                            <span class="px-3 py-1 rounded-full text-xs font-medium border" :style="getSessionTypeClass(miningSession.session_type)">
                                {{ t(miningSession.session_type) }}
                            </span>
                            <div class="flex items-center space-x-2">
                                <div
                                    class="w-2 h-2 rounded-full flex-shrink-0"
                                    :class="miningSession.is_active ? 'animate-pulse' : ''"
                                    :style="{ backgroundColor: miningSession.is_active ? derivedColors.success : '#ef4444' }"
                                ></div>
                                <span class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ miningSession.is_active ? t('mining') : t('idle') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                                <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('miningRate') }}
                                </div>
                                <div class="text-lg sm:text-xl font-bold" :style="{ color: derivedColors.textPrimary }">
                                    {{ formatNumber(effectiveMiningRate) }}/{{ t('sec') }}
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ formatNumber(dailyEarnings) }} {{ t('perDay') }}
                                </div>
                            </div>

                            <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                                <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('levelProgress') }}
                                </div>
                                <div class="w-full rounded-full h-3 mb-2" :style="{ backgroundColor: derivedColors.background }">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :style="{
                                            width: levelProgress + '%',
                                            background: `linear-gradient(to right, ${derivedColors.secondary}, ${derivedColors.accent})`
                                        }"
                                    ></div>
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ miningSession.experience_points }} / {{ miningSession.level * 100 }} {{ t('xp') }} ({{ Math.floor(levelProgress) }}%)
                                </div>
                            </div>

                            <div v-if="sessionTimeRemaining && miningSession.is_active" class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                                <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('sessionTimeLeft') }}
                                </div>
                                <div class="text-base sm:text-lg font-bold" :style="{ color: derivedColors.warning }">
                                    {{ sessionTimeRemaining }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                                <div class="text-sm mb-3" :style="{ color: derivedColors.textMuted }">
                                    {{ t('miningStatus') }}
                                </div>
                                <div class="space-y-3">
                                    <button
                                        v-if="canStartMining"
                                        :disabled="isProcessing"
                                        @click="startMining"
                                        class="w-full py-3 px-4 rounded-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
                                        :style="buttonPrimaryStyle"
                                    >
                                        <span v-if="isProcessing" class="flex items-center justify-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                            </svg>
                                            {{ t('starting') }}...
                                        </span>
                                        <span v-else>{{ t('startMining') }}</span>
                                    </button>

                                    <button
                                        v-if="canStopMining"
                                        :disabled="isProcessing"
                                        @click="stopMining"
                                        class="w-full py-3 px-4 rounded-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
                                        :style="{ backgroundColor: '#ef4444', color: derivedColors.textPrimary }"
                                    >
                                        <span v-if="isProcessing">{{ t('stopping') }}...</span>
                                        <span v-else>{{ t('stopMining') }}</span>
                                    </button>

                                    <button
                                        v-if="canClaim"
                                        :disabled="isProcessing"
                                        @click="openClaimModal"
                                        class="w-full py-3 px-4 rounded-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
                                        :style="{ backgroundColor: derivedColors.warning, color: derivedColors.background }"
                                    >
                                        <span class="block sm:inline">{{ t('claimTokens', { amount: formatNumber(currentBalance) }) }}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                                <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('lastClaim') }}
                                </div>
                                <div class="truncate" :style="{ color: derivedColors.textPrimary }">
                                    {{ formatDate(miningSession.last_claim_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="backdrop-blur-sm rounded-xl border overflow-hidden" :style="cardStyle">
                    <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('achievements') }}
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div v-if="achievements.length > 0" class="space-y-3 mb-4">
                            <div v-for="achievement in achievements.slice(0, 3)" :key="achievement.id" class="flex items-center justify-between rounded-lg p-3" :style="surfaceStyle">
                                <div class="flex items-center space-x-3 min-w-0 flex-1">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                            {{ achievement.achievement?.name }}
                                        </div>
                                        <div class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ formatDate(achievement.earned_at) }}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    v-if="!achievement.claimed"
                                    @click="claimAchievement(achievement)"
                                    class="px-3 py-1 rounded-lg text-sm font-medium transition-colors flex-shrink-0"
                                    :style="{ backgroundColor: derivedColors.accent, color: derivedColors.background }"
                                >
                                    {{ t('claim') }}
                                </button>
                                <span v-else class="text-sm flex-shrink-0" :style="{ color: derivedColors.success }">
                                    {{ t('claimed') }}
                                </span>
                            </div>
                        </div>

                        <div v-if="availableAchievements.length > 0" class="space-y-3">
                            <div class="text-sm border-t pt-3" :style="{ color: derivedColors.textMuted, borderColor: derivedColors.border + '20' }">
                                {{ t('availableToUnlock') }}:
                            </div>
                            <div v-for="achievement in availableAchievements.slice(0, 2)" :key="achievement.id" class="rounded-lg p-3 border" :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border + '50' }">
                                <div class="flex items-center space-x-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium truncate" :style="{ color: derivedColors.textSecondary }">
                                            {{ achievement.name }}
                                        </div>
                                        <div class="text-xs truncate" :style="{ color: derivedColors.textMuted }">
                                            {{ achievement.condition }}
                                        </div>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium mt-1 inline-block" :style="getAchievementTypeClass(achievement.type)">
                                            {{ t(achievement.type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="achievements.length === 0 && availableAchievements.length === 0" class="text-center py-4">
                            <div :style="{ color: derivedColors.textMuted }">{{ t('noAchievementsYet') }}</div>
                            <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('startMiningToUnlock') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-xl border overflow-hidden" :style="cardStyle">
                    <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('activeCompetitions') }}
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div v-if="activeCompetitions.length > 0" class="space-y-3">
                            <div v-for="competition in activeCompetitions.slice(0, 3)" :key="competition.id" class="rounded-lg p-3 sm:p-4" :style="surfaceStyle">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2 space-y-2 sm:space-y-0">
                                    <div class="font-medium truncate pr-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ competition.name }}
                                    </div>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium flex-shrink-0" :style="{ backgroundColor: derivedColors.warning + '20', color: derivedColors.warning }">
                                        {{ t(competition.type) }}
                                    </span>
                                </div>

                                <div class="text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ competition.description }}
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-3 space-y-1 sm:space-y-0">
                                    <div class="text-sm truncate" :style="{ color: derivedColors.textMuted }">
                                        {{ t('prizePool') }}: {{ currencySymbol }}{{ formatNumber(competition.prize_pool) }}
                                    </div>
                                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ competition.participants_count }}{{ competition.max_participants ? '/' + competition.max_participants : '' }} {{ t('participants') }}
                                    </div>
                                </div>

                                <div v-if="competition.entry_fee > 0" class="text-xs mb-2" :style="{ color: derivedColors.warning }">
                                    {{ t('entryFee') }}: {{ currencySymbol }}{{ formatNumber(competition.entry_fee) }}
                                </div>

                                <div v-if="competition.user_joined" class="mb-3 p-2 rounded-lg" :style="{ backgroundColor: derivedColors.success + '10', borderColor: derivedColors.success + '30' }">
                                    <div class="flex items-center justify-between text-sm">
                                        <span :style="{ color: derivedColors.textPrimary }">{{ t('yourProgress') }}:</span>
                                        <span :style="{ color: derivedColors.success }">{{ formatNumber(competition.user_mined_amount) }}</span>
                                    </div>
                                    <div v-if="competition.user_rank" class="text-xs mt-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('currentRank') }}: #{{ competition.user_rank }}
                                    </div>
                                </div>

                                <button
                                    @click="joinCompetition(competition)"
                                    :disabled="competition.user_joined || competition.is_full"
                                    class="w-full py-2 px-4 rounded-lg text-sm font-medium transition-colors disabled:cursor-not-allowed"
                                    :style="getCompetitionButtonStyle(competition)"
                                >
                                    {{ getCompetitionButtonText(competition) }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-center py-4">
                            <div :style="{ color: derivedColors.textMuted }">{{ t('noActiveCompetitions') }}</div>
                            <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('checkBackLaterForCompetitions') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-2 sm:space-y-0">
                        <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('miningLeaderboard') }}
                        </h3>
                        <div v-if="userRank" class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('yourRank') }}: #{{ userRank }}
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-6">
                    <div v-if="leaderboard.length > 0" class="space-y-2">
                        <div v-for="(user, index) in leaderboard.slice(0, 5)" :key="user.id" class="flex items-center justify-between rounded-lg p-3" :style="surfaceStyle">
                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                <div class="text-base sm:text-lg font-bold flex-shrink-0" :style="{
                                    color: index === 0 ? '#fbbf24' :
                                           index === 1 ? '#d1d5db' :
                                           index === 2 ? '#fb923c' :
                                           derivedColors.textMuted
                                }">
                                    #{{ index + 1 }}
                                </div>
                                <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ background: `linear-gradient(135deg, ${derivedColors.accent}, ${derivedColors.secondary})` }">
                                    <span class="text-white font-bold text-xs">{{ user.name?.charAt(0).toUpperCase() || '?' }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium truncate" :style="{ color: derivedColors.textPrimary }">{{ user.name }}</div>
                                    <div class="text-xs truncate" :style="{ color: derivedColors.textMuted }">{{ t('level') }} {{ user.level }}</div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-bold text-sm sm:text-base" :style="{ color: derivedColors.accent }">{{ formatNumber(user.total_mined) }}</div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('totalMined') }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-4">
                        <div :style="{ color: derivedColors.textMuted }">{{ t('noLeaderboardData') }}</div>
                        <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('startMiningToAppearOnLeaderboard') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="claimModal.isOpen"
            class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            :style="{ backgroundColor: 'rgba(0, 0, 0, 0.7)' }"
            role="dialog"
            aria-modal="true"
            @click.self="closeClaimModal"
            @keydown.escape="closeClaimModal"
        >
            <div class="rounded-2xl shadow-2xl border max-w-md w-full mx-4 backdrop-blur-md focus:outline-none" :style="{ backgroundColor: derivedColors.surface + '95', borderColor: derivedColors.border + '20' }">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '50' }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                            {{ t('claimTokens', { amount: formatNumber(claimModal.amount) }) }}
                        </h3>
                        <button @click="closeClaimModal" class="transition-colors p-1 rounded" :style="{ color: derivedColors.textMuted }">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="text-center mb-6">
                        <div class="text-4xl mb-2">
                            <svg class="w-12 h-12 mx-auto" :style="{ color: derivedColors.accent }" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="space-y-2">
                            <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('grossAmount') }}
                            </div>
                            <div class="text-xl sm:text-2xl font-bold break-words" :style="{ color: derivedColors.textPrimary }">
                                {{ currencySymbol }}{{ formatNumber(claimModal.amount) }}
                            </div>

                            <div v-if="claimModal.feeAmount > 0" class="border-t border-b py-3 my-3" :style="{ borderColor: derivedColors.border + '30' }">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t('miningFee') }} ({{ claimModal.feeRate }}%)</span>
                                    <span class="text-sm font-medium" :style="{ color: derivedColors.warning }">
                                        -{{ currencySymbol }}{{ formatNumber(claimModal.feeAmount) }}
                                    </span>
                                </div>
                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ subscriptionBenefits.isActive ? t('subscriptionDiscountApplied') : t('upgradeToReduceFees') }}
                                </div>
                            </div>

                            <div class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('youWillReceive') }}
                            </div>
                            <div class="text-2xl sm:text-3xl font-bold break-words" :style="{ color: derivedColors.accent }">
                                {{ currencySymbol }}{{ formatNumber(claimModal.netAmount) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                        <button
                            @click="closeClaimModal"
                            class="flex-1 py-3 px-4 rounded-xl font-medium transition-colors"
                            :style="buttonSecondaryStyle"
                        >
                            {{ t('cancel') }}
                        </button>
                        <button
                            @click="claimTokens"
                            :disabled="isProcessing"
                            class="flex-1 py-3 px-4 rounded-xl font-bold disabled:opacity-50 transition-all"
                            :style="buttonPrimaryStyle"
                        >
                            <span v-if="isProcessing">{{ t('claiming') }}...</span>
                            <span v-else>{{ t('claimNow') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
