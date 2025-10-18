<template>
    <section
        class="relative py-16 lg:py-24 overflow-hidden"
        id="advanced-features"
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
                class="absolute top-1/2 left-1/4 w-96 h-96 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute bottom-1/4 right-1/3 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-2000"
                :style="blob2Style"
            ></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                    <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('advancedFeatures') }}</span>
                </div>

                <h2 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('advancedFeaturesTitle') }}
                    </span>
                </h2>

                <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                    {{ t('advancedFeaturesDescription') }}
                </p>

                <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
            </div>

            <!-- Features Container -->
            <div class="backdrop-blur-xl border rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up animation-delay-300" :style="containerStyle">
                <div class="h-1 bg-gradient-to-r bg-[length:200%_100%] animate-gradient-x" :style="headerBorderStyle"></div>
                <div class="p-8 lg:p-12">
                    <div class="flex flex-wrap justify-center gap-4 mb-12">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="px-6 py-3 rounded-full font-semibold transition-all duration-300 relative overflow-hidden"
                            :style="getTabStyle(tab.key)"
                        >
                            <span class="relative z-10 flex items-center gap-2">
                                <i :class="tab.icon"></i>
                                {{ t(tab.labelKey) }}
                            </span>
                            <div
                                v-if="activeTab !== tab.key"
                                class="absolute inset-0 -translate-x-full hover:translate-x-full transition-transform duration-500"
                                :style="{ background: `linear-gradient(90deg, transparent, ${derivedColors.accent}10, transparent)` }"
                            ></div>
                        </button>
                    </div>

                    <div class="transition-all duration-500 ease-in-out">
                        <div v-show="activeTab === 'gaming'" class="animate-fade-in-up">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <div
                                    v-for="(feature, index) in gamingFeatures"
                                    :key="feature.id"
                                    :style="{ animationDelay: `${index * 0.1}s` }"
                                    class="group feature-card backdrop-blur-xl border rounded-2xl p-6 lg:p-8 transition-all duration-500 hover:-translate-y-2 hover:scale-105 animate-fade-in-up relative overflow-hidden"
                                >
                                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 rounded-2xl" :style="{ background: `radial-gradient(circle at center, ${feature.color}10, transparent 70%)` }"></div>
                                    <div class="relative z-10 mb-6">
                                        <div
                                            class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg transition-all duration-300 group-hover:scale-110"
                                            :style="{ background: `linear-gradient(135deg, ${feature.color}, ${adjustColor(feature.color, 30)})` }"
                                        >
                                            <i :class="feature.icon"></i>
                                        </div>
                                    </div>

                                    <div class="relative z-10">
                                        <h3 class="text-xl font-bold mb-4 transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                                            {{ t(feature.titleKey) }}
                                        </h3>
                                        <p class="leading-relaxed mb-6 transition-colors duration-300" :style="{ color: derivedColors.textSecondary }">
                                            {{ t(feature.descriptionKey) }}
                                        </p>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div
                                                v-for="metric in feature.metrics"
                                                :key="metric.labelKey"
                                                class="text-center p-3 rounded-xl border transition-colors duration-300"
                                                :style="metricCardStyle"
                                            >
                                                <div class="text-lg font-bold mb-1" :style="{ color: feature.color }">{{ metric.value }}</div>
                                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t(metric.labelKey) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeTab === 'trading'" class="animate-fade-in-up">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                                <div class="space-y-6">
                                    <div
                                        v-for="(feature, index) in tradingFeatures"
                                        :key="feature.id"
                                        class="group backdrop-blur-xl border rounded-2xl p-6 transition-all duration-300 animate-fade-in-up"
                                        :style="tradingCardStyle"
                                    >
                                        <div class="flex items-start gap-4">
                                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white flex-shrink-0" :style="{ background: `linear-gradient(135deg, ${derivedColors.success}, ${adjustColor(derivedColors.success, 30)})` }">
                                                <i :class="feature.icon"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-bold mb-2" :style="{ color: derivedColors.textPrimary }">{{ t(feature.titleKey) }}</h3>
                                                <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">{{ t(feature.descriptionKey) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="relative">
                                    <div class="backdrop-blur-xl border rounded-2xl p-8" :style="visualizationStyle">
                                        <h4 class="font-bold mb-6 text-center" :style="{ color: derivedColors.textPrimary }">{{ t('securityLayers') }}</h4>
                                        <div class="relative w-64 h-64 mx-auto">
                                            <div
                                                v-for="(ring, index) in securityRings"
                                                :key="ring.layer"
                                                :style="{
                                                    width: `${240 - (index * 60)}px`,
                                                    height: `${240 - (index * 60)}px`,
                                                    animationDelay: `${index * 0.5}s`,
                                                    borderColor: ring.color
                                                }"
                                                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 border-2 rounded-full animate-pulse"
                                            >
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span
                                                        v-if="index === 0"
                                                        class="text-sm font-semibold text-center"
                                                        :style="{ color: derivedColors.textPrimary }"
                                                    >
                                                        {{ t('coreSecurity') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Security Stats -->
                                        <div class="grid grid-cols-3 gap-4 mt-8">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold" :style="{ color: derivedColors.success }">100%</div>
                                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('encrypted') }}</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold" :style="{ color: derivedColors.accent }">24/7</div>
                                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('monitoring') }}</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold" :style="{ color: derivedColors.warning }">0</div>
                                                <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ t('breaches') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeTab === 'staking'" class="animate-fade-in-up">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div class="space-y-6">
                                    <div
                                        v-for="(chart, index) in stakingCharts"
                                        :key="chart.id"
                                        class="backdrop-blur-xl border rounded-2xl p-6 animate-fade-in-up"
                                        :style="chartCardStyle"
                                    >
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="font-bold" :style="{ color: derivedColors.textPrimary }">{{ t(chart.titleKey) }}</h3>
                                            <div class="flex items-center" :style="{ color: derivedColors.accent }">
                                                <div class="w-2 h-2 rounded-full mr-2 animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                                                <span class="text-sm">{{ t('live') }}</span>
                                            </div>
                                        </div>

                                        <div class="h-24 rounded-xl relative overflow-hidden" :style="chartBgStyle">
                                            <div
                                                class="absolute bottom-0 left-0 h-full rounded-xl transition-all duration-1000"
                                                :style="{
                                                    width: `${chart.percentage}%`,
                                                    background: `linear-gradient(90deg, ${derivedColors.accent}50, ${derivedColors.secondary}50)`
                                                }"
                                            ></div>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <span class="font-bold text-lg" :style="{ color: derivedColors.textPrimary }">{{ chart.value }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div
                                        v-for="(feature, index) in stakingFeatures"
                                        :key="feature.id"
                                        class="group backdrop-blur-xl border rounded-2xl p-6 transition-all duration-300 animate-fade-in-up"
                                        :style="stakingCardStyle"
                                    >
                                        <div class="flex items-start gap-4">
                                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white flex-shrink-0" :style="{ background: `linear-gradient(135deg, ${derivedColors.warning}, ${adjustColor(derivedColors.warning, 30)})` }">
                                                <i :class="feature.icon"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h3 class="text-lg font-bold mb-2" :style="{ color: derivedColors.textPrimary }">{{ t(feature.titleKey) }}</h3>
                                                <p class="text-sm leading-relaxed mb-3" :style="{ color: derivedColors.textSecondary }">{{ t(feature.descriptionKey) }}</p>
                                                <div class="font-semibold text-sm" :style="{ color: derivedColors.warning }">{{ t(feature.benefitKey) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center mt-16 animate-fade-in-up animation-delay-900">
                <h3 class="text-2xl md:text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('ctaTitle') }}
                </h3>
                <p class="mb-8 max-w-2xl mx-auto" :style="{ color: derivedColors.textSecondary }">
                    {{ t('ctaDescription') }}
                </p>
                <div class="flex justify-center">
                    <button
                        class="inline-flex items-center gap-2 px-8 py-3 font-semibold rounded-full transition-all duration-300 hover:scale-105 hover:shadow-lg"
                        :style="primaryButtonStyle"
                        @click="handleGetAccess"
                    >
                        <i class="fas fa-rocket"></i>
                        {{ t('getEnterpriseAccess') }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslation } from '@/composables/useTranslation'

// Composable
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

const badgeStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '80',
    borderColor: derivedColors.value.border + '30'
}))

const gradientText = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.textPrimary} 0%, ${derivedColors.value.textSecondary} 50%, ${derivedColors.value.accent} 100%)`
)

const underlineStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary})`
}))

const containerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const headerBorderStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary}, ${derivedColors.value.accent})`
}))

const metricCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '20'
}))

const tradingCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.success + '20'
}))

const visualizationStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.success + '20'
}))

const chartCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.accent + '20'
}))

const chartBgStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30'
}))

const stakingCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.warning + '20'
}))

const primaryButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
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

const activeTab = ref('gaming')
const tabs = [
    { key: 'gaming', labelKey: 'gamingInfrastructure', icon: 'fas fa-gamepad' },
    { key: 'trading', labelKey: 'tradingSecurity', icon: 'fas fa-shield-alt' },
    { key: 'staking', labelKey: 'stakingPerformance', icon: 'fas fa-chart-line' }
]

const gamingFeatures = ref([
    {
        id: 'real-time-rewards',
        titleKey: 'realTimeRewardsTitle',
        descriptionKey: 'realTimeRewardsDescription',
        icon: 'fas fa-trophy',
        color: '#8b5cf6',
        metrics: [
            { value: '1ms', labelKey: 'rewardLatency' },
            { value: '24/7', labelKey: 'availability' }
        ]
    },
    {
        id: 'achievement-system',
        titleKey: 'achievementSystemTitle',
        descriptionKey: 'achievementSystemDescription',
        icon: 'fas fa-medal',
        color: '#f59e0b',
        metrics: [
            { value: '1000+', labelKey: 'achievements' },
            { value: '5X', labelKey: 'multiplier' }
        ]
    },
    {
        id: 'competitive-ranking',
        titleKey: 'competitiveRankingTitle',
        descriptionKey: 'competitiveRankingDescription',
        icon: 'fas fa-crown',
        color: '#10b981',
        metrics: [
            { value: '50K+', labelKey: 'players' },
            { value: 'Live', labelKey: 'leaderboard' }
        ]
    }
])

const tradingFeatures = ref([
    {
        id: 'advanced-encryption',
        titleKey: 'advancedEncryptionTitle',
        descriptionKey: 'advancedEncryptionDescription',
        icon: 'fas fa-lock'
    },
    {
        id: 'risk-management',
        titleKey: 'riskManagementTitle',
        descriptionKey: 'riskManagementDescription',
        icon: 'fas fa-shield-alt'
    },
    {
        id: 'instant-execution',
        titleKey: 'instantExecutionTitle',
        descriptionKey: 'instantExecutionDescription',
        icon: 'fas fa-bolt'
    }
])

const securityRings = [
    { layer: 'Core', color: derivedColors.value.success },
    { layer: 'Application', color: derivedColors.value.accent },
    { layer: 'Network', color: derivedColors.value.secondary },
    { layer: 'Physical', color: derivedColors.value.warning }
]

const stakingCharts = ref([
    {
        id: 'apy-rate',
        titleKey: 'apyRate',
        value: '12.5%',
        percentage: 85
    },
    {
        id: 'staking-rewards',
        titleKey: 'stakingRewards',
        value: '1.2M',
        percentage: 95
    },
    {
        id: 'validator-uptime',
        titleKey: 'validatorUptime',
        value: '99.9%',
        percentage: 99
    }
])

const stakingFeatures = ref([
    {
        id: 'auto-compounding',
        titleKey: 'autoCompoundingTitle',
        descriptionKey: 'autoCompoundingDescription',
        icon: 'fas fa-sync-alt',
        benefitKey: 'autoCompoundingBenefit'
    },
    {
        id: 'flexible-staking',
        titleKey: 'flexibleStakingTitle',
        descriptionKey: 'flexibleStakingDescription',
        icon: 'fas fa-coins',
        benefitKey: 'flexibleStakingBenefit'
    },
    {
        id: 'instant-unstaking',
        titleKey: 'instantUnstakingTitle',
        descriptionKey: 'instantUnstakingDescription',
        icon: 'fas fa-fast-forward',
        benefitKey: 'instantUnstakingBenefit'
    }
])

const getTabStyle = (tabKey) => {
    const isActive = activeTab.value === tabKey
    return {
        backgroundColor: isActive ? derivedColors.value.accent : derivedColors.value.surface + '40',
        color: isActive ? derivedColors.value.textPrimary : derivedColors.value.textSecondary,
        borderColor: isActive ? derivedColors.value.accent : derivedColors.value.border + '30',
        boxShadow: isActive ? `0 4px 15px ${derivedColors.value.accent}25` : 'none'
    }
}

const handleGetAccess = () => {
  const getAccessActionUrl = t('getAccessActionUrl')
  if (getAccessActionUrl) {
    window.open(getAccessActionUrl, '_blank')
  }
}

const emit = defineEmits(['get-access-clicked'])
</script>

<style scoped>
@keyframes gradient-x {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

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

.animate-gradient-x {
    animation: gradient-x 3s ease infinite;
}

.animate-fade-in-up {
    animation: fade-in-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-blob {
    animation: blob 20s infinite;
}

.animation-delay-300 { animation-delay: 0.3s; }
.animation-delay-900 { animation-delay: 0.9s; }
.animation-delay-2000 { animation-delay: 2s; }

.feature-card {
    background-color: var(--surface-color);
    border-color: var(--border-color);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.feature-card:hover {
    box-shadow: 0 25px 50px -12px var(--hover-shadow);
}

.feature-card * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
