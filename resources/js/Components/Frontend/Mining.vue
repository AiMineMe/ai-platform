<template>
    <section
        class="relative py-16 lg:py-24 overflow-hidden"
        id="'gaming-mining"
        :style="sectionStyle"
    >
        <div class="absolute inset-0 opacity-20">
            <div
                class="absolute inset-0"
                :style="gridStyle"
            ></div>
        </div>

        <div class="absolute inset-0 overflow-hidden">
            <div
                class="absolute top-1/3 left-1/5 w-80 h-80 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute bottom-1/3 right-1/5 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-1000"
                :style="blob2Style"
            ></div>
            <div
                class="absolute top-2/3 left-2/3 w-60 h-60 rounded-full blur-3xl animate-blob animation-delay-2000"
                :style="blob3Style"
            ></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                    <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('gamingTeam') }}</span>
                </div>

                <h2 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('gamifiedMiningTeam') }}
                    </span>
                </h2>

                <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                    {{ t('gamingTeamDescription') }}
                </p>

                <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-16 animate-fade-in-up animation-delay-300">
                <div class="backdrop-blur-xl border rounded-3xl p-8 relative overflow-hidden" :style="rewardsContainerStyle">
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white" :style="{ background: `linear-gradient(135deg, ${derivedColors.warning}, ${adjustColor(derivedColors.warning, 30)})` }">
                                <i class="fas fa-coins"></i>
                            </div>
                            <h3 class="text-2xl font-bold" :style="{ color: derivedColors.textPrimary }">{{ t('tokenomicsTitle') }}</h3>
                        </div>

                        <div class="space-y-4 mb-8">
                            <div
                                v-for="reward in rewardDistribution"
                                :key="reward.key"
                                class="flex items-center justify-between p-4 rounded-xl border"
                                :style="rewardCardStyle"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm" :style="{ backgroundColor: reward.color }">
                                        <i :class="reward.icon"></i>
                                    </div>
                                    <span class="font-medium" :style="{ color: derivedColors.textPrimary }">{{ t(reward.labelKey) }}</span>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold" :style="{ color: reward.color }">{{ reward.percentage }}%</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ reward.amount }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-xl p-6" :style="calculatorStyle">
                            <h4 class="font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">{{ t('earningCalculator') }}</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold" :style="{ color: derivedColors.success }">{{ calculatorData.daily }}</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('dailyEarnings') }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold" :style="{ color: derivedColors.accent }">{{ calculatorData.monthly }}</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('monthlyEarnings') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="backdrop-blur-xl border rounded-3xl p-8 relative overflow-hidden" :style="processContainerStyle">
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white" :style="{ background: `linear-gradient(135deg, ${derivedColors.accent}, ${adjustColor(derivedColors.accent, 30)})` }">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h3 class="text-2xl font-bold" :style="{ color: derivedColors.textPrimary }">{{ t('miningProcessTitle') }}</h3>
                        </div>

                        <div class="space-y-6">
                            <div
                                v-for="(step, index) in miningSteps"
                                :key="step.key"
                                class="flex items-start gap-4"
                            >
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0" :style="{ backgroundColor: step.color }">
                                    {{ index + 1 }}
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold mb-2" :style="{ color: derivedColors.textPrimary }">{{ t(step.titleKey) }}</h4>
                                    <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">{{ t(step.descriptionKey) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-xl border rounded-3xl p-8 lg:p-12 mb-16 animate-fade-in-up animation-delay-600" :style="visualizationContainerStyle">
                <div class="text-center mb-8">
                    <h3 class="text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">{{ t('howMiningWorksTitle') }}</h3>
                    <p class="text-lg" :style="{ color: derivedColors.textSecondary }">{{ t('howMiningWorksDescription') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div
                        v-for="(flow, index) in miningFlow"
                        :key="flow.key"
                        class="text-center relative"
                    >
                        <div v-if="index < miningFlow.length - 1" class="hidden md:block absolute top-1/2 -right-4 transform -translate-y-1/2 z-10">
                            <i class="fas fa-arrow-right text-2xl" :style="{ color: derivedColors.accent }"></i>
                        </div>

                        <div class="backdrop-blur-xl border rounded-2xl p-6 transition-all duration-300 hover:scale-105" :style="flowCardStyle">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center text-white text-2xl" :style="{ background: `linear-gradient(135deg, ${flow.color}, ${adjustColor(flow.color, 30)})` }">
                                <i :class="flow.icon"></i>
                            </div>
                            <h4 class="font-bold mb-2" :style="{ color: derivedColors.textPrimary }">{{ t(flow.titleKey) }}</h4>
                            <p class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ t(flow.descriptionKey) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-16 animate-fade-in-up animation-delay-600">
                <div
                    v-for="(stat, index) in gamingStats"
                    :key="stat.labelKey"
                    class="text-center p-6 backdrop-blur-xl border rounded-2xl transition-all duration-300 hover:-translate-y-2 animate-fade-in-up"
                    :style="statsCardStyle"
                >
                    <div class="text-3xl font-black mb-2" :style="{ color: derivedColors.accent }">{{ stat.value }}</div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t(stat.labelKey) }}</div>
                </div>
            </div>

            <div class="backdrop-blur-xl border rounded-3xl p-8 lg:p-12 mb-16 animate-fade-in-up animation-delay-900" :style="featuresContainerStyle">
                <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                    <div>
                        <div class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium mb-6" :style="featureBadgeStyle">
                            <i class="fas fa-gamepad mr-2"></i>
                            {{ t('miningFeatures') }}
                        </div>

                        <h3 class="text-3xl lg:text-4xl font-black mb-6" :style="{ color: derivedColors.textPrimary }">
                            {{ t('miningSystemTitle') }}
                            <span :style="{ background: gradientAccent, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                                {{ t('miningSystemHighlight') }}
                            </span>
                        </h3>

                        <p class="text-lg lg:text-xl mb-8 leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                            {{ t('miningSystemDescription') }}
                        </p>

                        <div class="space-y-4 mb-8">
                            <div
                                v-for="feature in miningFeatures"
                                :key="feature.key"
                                class="flex items-center gap-4"
                            >
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white flex-shrink-0" :style="{ background: `linear-gradient(135deg, ${feature.color}, ${adjustColor(feature.color, 30)})` }">
                                    <i :class="feature.icon"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold mb-1" :style="{ color: derivedColors.textPrimary }">{{ t(feature.titleKey) }}</h4>
                                    <p class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ t(feature.descriptionKey) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center lg:justify-start">
                            <button
                                class="inline-flex items-center gap-2 px-8 py-3 font-semibold rounded-full transition-all duration-300 hover:scale-105 hover:shadow-lg"
                                :style="primaryButtonStyle"
                                @click="handleStartMining"
                            >
                                <i class="fas fa-play"></i>
                                {{ t('startMining') }}
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="rounded-2xl p-6 lg:p-8 backdrop-blur-sm border relative overflow-hidden" :style="dashboardStyle">
                            <div class="absolute inset-0 opacity-5" :style="{ backgroundImage: `radial-gradient(circle at 2px 2px, ${derivedColors.accent} 1px, transparent 0)`, backgroundSize: '20px 20px' }"></div>
                            <div class="relative z-10 flex items-center justify-between mb-6">
                                <h4 class="font-bold" :style="{ color: derivedColors.textPrimary }">{{ t('miningDashboard') }}</h4>
                                <div class="flex items-center" :style="{ color: derivedColors.success }">
                                    <div class="w-2 h-2 rounded-full mr-2 animate-pulse" :style="{ backgroundColor: derivedColors.success }"></div>
                                    <span class="text-sm">{{ t('activeMining') }}</span>
                                </div>
                            </div>

                            <div class="relative z-10 grid grid-cols-2 gap-4 mb-6">
                                <div class="rounded-xl p-4 text-center backdrop-blur-sm" :style="dashboardCardStyle">
                                    <div class="text-2xl font-black mb-1" :style="{ color: derivedColors.accent }">{{ miningDashboard.hashRate }}</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('hashRate') }}</div>
                                </div>
                                <div class="rounded-xl p-4 text-center backdrop-blur-sm" :style="dashboardCardStyle">
                                    <div class="text-2xl font-black mb-1" :style="{ color: derivedColors.warning }">{{ miningDashboard.rewards }}</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('dailyRewards') }}</div>
                                </div>
                                <div class="rounded-xl p-4 text-center backdrop-blur-sm" :style="dashboardCardStyle">
                                    <div class="text-2xl font-black mb-1" :style="{ color: derivedColors.success }">{{ miningDashboard.miners }}</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('activeMiners') }}</div>
                                </div>
                                <div class="rounded-xl p-4 text-center backdrop-blur-sm" :style="dashboardCardStyle">
                                    <div class="text-2xl font-black mb-1" :style="{ color: derivedColors.secondary }">{{ miningDashboard.difficulty }}</div>
                                    <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('difficulty') }}</div>
                                </div>
                            </div>

                            <div class="relative z-10 space-y-3">
                                <h5 class="font-semibold mb-3" :style="{ color: derivedColors.textPrimary }">{{ t('topMiners') }}</h5>
                                <div
                                    v-for="(miner, index) in topMiners"
                                    :key="miner.id"
                                    class="flex items-center justify-between p-3 rounded-lg backdrop-blur-sm"
                                    :style="minerCardStyle"
                                >
                                    <div class="flex items-center">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold mr-3"
                                            :style="{ background: `linear-gradient(135deg, ${miner.color}, ${adjustColor(miner.color, 30)})` }"
                                        >
                                            {{ index + 1 }}
                                        </div>
                                        <span class="text-sm" :style="{ color: derivedColors.textPrimary }">{{ miner.name }}</span>
                                    </div>
                                    <div class="text-sm font-semibold" :style="{ color: miner.color }">
                                        {{ miner.points }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -top-4 -right-4 w-20 h-20 rounded-full blur-xl animate-pulse" :style="{ background: `radial-gradient(circle, ${derivedColors.accent}20, transparent 70%)` }"></div>
                        <div class="absolute -bottom-4 -left-4 w-16 h-16 rounded-full blur-xl animate-pulse animation-delay-1000" :style="{ background: `radial-gradient(circle, ${derivedColors.secondary}20, transparent 70%)` }"></div>
                    </div>
                </div>
            </div>

            <div class="text-center animate-fade-in-up animation-delay-1200">
                <h3 class="text-2xl md:text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('joinMiningTitle') }}
                </h3>
                <p class="mb-8 max-w-2xl mx-auto" :style="{ color: derivedColors.textSecondary }">
                    {{ t('joinMiningDescription') }}
                </p>
                <div class="flex justify-center">
                    <button
                        class="inline-flex items-center gap-2 px-8 py-3 font-semibold rounded-full transition-all duration-300 hover:scale-105 hover:shadow-lg"
                        :style="primaryButtonStyle"
                        @click="handleJoinMining"
                    >
                        <i class="fas fa-rocket"></i>
                        {{ t('joinMining') }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslation } from '@/composables/useTranslation'

const { t } = useTranslation()
const page = usePage()

const primaryColor = computed(() => page.props.primaryColor || '#1f2937')
const derivedColors = computed(() => {
    const primary = primaryColor.value

    const adjustColor = (color, amount) => {
        const hex = color.replace('#', '')
        const r = parseInt(hex.substr(0, 2), 16)
        const g = parseInt(hex.substr(2, 2), 16)
        const b = parseInt(hex.substr(4, 2), 16)

        const newR = Math.min(255, Math.max(0, r + amount))
        const newG = Math.min(255, Math.max(0, g + amount))
        const newB = Math.min(255, Math.max(0, b + amount))

        return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`
    }

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
        warning: '#f59e0b',
        error: '#ef4444'
    }
})

const sectionStyle = computed(() => ({
    background: `radial-gradient(ellipse at top, ${derivedColors.value.primary}30 0%, ${derivedColors.value.background} 50%, ${derivedColors.value.background} 100%)`
}))

const gridStyle = computed(() => ({
    backgroundImage: `linear-gradient(${derivedColors.value.border}15 1px, transparent 1px), linear-gradient(90deg, ${derivedColors.value.border}15 1px, transparent 1px)`,
    backgroundSize: '50px 50px'
}))

const blob1Style = computed(() => ({
    background: `linear-gradient(45deg, ${derivedColors.value.accent}20, ${derivedColors.value.secondary}15)`
}))

const blob2Style = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.secondary}20, ${derivedColors.value.accent}15)`
}))

const blob3Style = computed(() => ({
    background: `linear-gradient(225deg, ${derivedColors.value.accent}15, ${derivedColors.value.primary}10)`
}))

const badgeStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '80',
    borderColor: derivedColors.value.border + '30'
}))

const gradientText = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.textPrimary} 0%, ${derivedColors.value.textSecondary} 50%, ${derivedColors.value.accent} 100%)`
)

const gradientAccent = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`
)

const underlineStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary})`
}))

const statsCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '30'
}))

const featuresContainerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const featureBadgeStyle = computed(() => ({
    backgroundColor: derivedColors.value.accent + '20',
    color: derivedColors.value.accent
}))

const primaryButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

const dashboardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const dashboardCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40'
}))

const minerCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30'
}))

const rewardsContainerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const processContainerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const visualizationContainerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const rewardCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '20'
}))

const calculatorStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '30'
}))

const flowCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '30'
}))

const adjustColor = (color, amount) => {
    const hex = color.replace('#', '')
    const r = parseInt(hex.substr(0, 2), 16)
    const g = parseInt(hex.substr(2, 2), 16)
    const b = parseInt(hex.substr(4, 2), 16)

    const newR = Math.min(255, Math.max(0, r + amount))
    const newG = Math.min(255, Math.max(0, g + amount))
    const newB = Math.min(255, Math.max(0, b + amount))

    return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`
}

const rewardDistribution = ref([
    {
        key: 'mining-rewards',
        labelKey: 'miningRewards',
        percentage: 60,
        amount: '600M',
        color: derivedColors.value.success,
        icon: 'fas fa-pickaxe'
    },
    {
        key: 'staking-rewards',
        labelKey: 'stakingRewards',
        percentage: 25,
        amount: '250M',
        color: derivedColors.value.accent,
        icon: 'fas fa-coins'
    },
    {
        key: 'tournament-prizes',
        labelKey: 'tournamentPrizes',
        percentage: 10,
        amount: '100M',
        color: derivedColors.value.warning,
        icon: 'fas fa-trophy'
    },
    {
        key: 'team-development',
        labelKey: 'teamDevelopment',
        percentage: 5,
        amount: '50M',
        color: derivedColors.value.secondary,
        icon: 'fas fa-users'
    }
])

const calculatorData = reactive({
    daily: '125',
    monthly: '3.7K'
})

const miningSteps = ref([
    {
        key: 'connect-wallet',
        titleKey: 'connectWallet',
        descriptionKey: 'connectWalletDescription',
        color: derivedColors.value.accent
    },
    {
        key: 'choose-mining-plan',
        titleKey: 'chooseMiningPlan',
        descriptionKey: 'chooseMiningPlanDescription',
        color: derivedColors.value.warning
    },
    {
        key: 'start-mining',
        titleKey: 'startMiningProcess',
        descriptionKey: 'startMiningProcessDescription',
        color: derivedColors.value.success
    },
    {
        key: 'earn-rewards',
        titleKey: 'earnRewards',
        descriptionKey: 'earnRewardsDescription',
        color: derivedColors.value.secondary
    }
])

const miningFlow = ref([
    {
        key: 'setup',
        titleKey: 'setupMining',
        descriptionKey: 'setupMiningDescription',
        icon: 'fas fa-cog',
        color: derivedColors.value.accent
    },
    {
        key: 'process',
        titleKey: 'processMining',
        descriptionKey: 'processMiningDescription',
        icon: 'fas fa-microchip',
        color: derivedColors.value.warning
    },
    {
        key: 'rewards',
        titleKey: 'collectRewards',
        descriptionKey: 'collectRewardsDescription',
        icon: 'fas fa-gift',
        color: derivedColors.value.success
    }
])

const gamingStats = ref([
    { value: '50K+', labelKey: 'activeMiners' },
    { value: '500M+', labelKey: 'totalRewards' },
    { value: '99.9%', labelKey: 'systemUptime' },
    { value: '24/7', labelKey: 'miningSupport' }
])

const miningFeatures = ref([
    {
        key: 'instant-rewards',
        titleKey: 'instantRewardsTitle',
        descriptionKey: 'instantRewardsDescription',
        icon: 'fas fa-bolt',
        color: derivedColors.value.warning
    },
    {
        key: 'competitive-mining',
        titleKey: 'competitiveMiningTitle',
        descriptionKey: 'competitiveMiningDescription',
        icon: 'fas fa-trophy',
        color: derivedColors.value.success
    },
    {
        key: 'achievement-system',
        titleKey: 'achievementSystemFeature',
        descriptionKey: 'achievementSystemFeatureDescription',
        icon: 'fas fa-medal',
        color: derivedColors.value.accent
    }
])

const miningDashboard = reactive({
    hashRate: '125TH/s',
    rewards: '247',
    miners: '50K',
    difficulty: 'High'
})

const topMiners = ref([
    { id: 1, name: 'CryptoMiner Pro', points: '15,247', color: '#8b5cf6' },
    { id: 2, name: 'HashMaster', points: '14,892', color: '#f59e0b' },
    { id: 3, name: 'DigitalGold', points: '14,156', color: '#10b981' }
])

const handleStartMining = () => {
    const startMiningUrl = t('startMiningUrl')
    if (startMiningUrl) {
        window.open(startMiningUrl, '_blank')
    }
}
const handleJoinMining = () => {
    const joinMiningUrl = t('joinMiningUrl')
    if (joinMiningUrl) {
        window.open(joinMiningUrl, '_blank')
    }
}

const emit = defineEmits([
    'start-mining-clicked',
    'join-mining-clicked'
])
</script>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes blob {
    0%, 100% {
        transform: translate(0px, 0px) scale(1);
    }
    33% {
        transform: translate(30px, -50px) scale(1.1);
    }
    66% {
        transform: translate(-20px, 20px) scale(0.9);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-blob {
    animation: blob 20s infinite;
}

.animation-delay-300 { animation-delay: 0.3s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-900 { animation-delay: 0.9s; }
.animation-delay-1000 { animation-delay: 1s; }
.animation-delay-1200 { animation-delay: 1.2s; }
.animation-delay-2000 { animation-delay: 2s; }

.mining-card * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
