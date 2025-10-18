<template>
    <section
        class="relative py-16 lg:py-24 overflow-hidden"
        id="services"
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
                class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute bottom-1/4 right-1/4 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-2000"
                :style="blob2Style"
            ></div>
            <div
                class="absolute top-1/2 right-1/3 w-64 h-64 rounded-full blur-3xl animate-blob animation-delay-4000"
                :style="blob3Style"
            ></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                    <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('ourServices') }}</span>
                </div>

                <h2 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('servicesTitle') }}
                    </span>
                </h2>

                <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                    {{ t('servicesDescription') }}
                </p>

                <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 animate-fade-in-up animation-delay-300">
                <div
                    v-for="(service, index) in services"
                    :key="service.id"
                    :style="{
                        animationDelay: `${index * 0.1}s`,
                        '--hover-glow': service.glowColor
                    }"
                    class="group service-card backdrop-blur-xl border rounded-3xl p-6 lg:p-8 transition-all duration-500 hover:-translate-y-3 hover:scale-105 animate-fade-in-up relative overflow-hidden cursor-pointer"
                    :class="serviceCardClasses"
                    @click="selectService(service)"
                >
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 rounded-3xl" :style="{ background: `radial-gradient(circle at center, ${service.glowColor}05, transparent 70%)` }"></div>
                    <div class="relative z-10 mb-6">
                        <div
                            class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg transition-all duration-500 group-hover:scale-110 group-hover:rotate-3"
                            :style="getIconStyle(service)"
                        >
                            <i :class="service.icon"></i>
                        </div>
                    </div>

                    <div class="relative z-10">
                        <h3 class="text-xl lg:text-2xl font-bold mb-4 transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                            {{ t(service.titleKey) }}
                        </h3>
                        <p class="leading-relaxed mb-6 transition-colors duration-300" :style="{ color: derivedColors.textSecondary }">
                            {{ t(service.descriptionKey) }}
                        </p>

                        <ul class="space-y-2 mb-6">
                            <li
                                v-for="feature in service.features"
                                :key="feature"
                                class="flex items-center text-sm transition-colors duration-300"
                                :style="{ color: derivedColors.textMuted }"
                            >
                                <i class="fas fa-check text-xs mr-3 transition-colors duration-300" :style="{ color: derivedColors.success }"></i>
                                {{ t(feature) }}
                            </li>
                        </ul>

                        <div class="flex items-center justify-between">
                            <span
                                class="px-3 py-1 rounded-full text-xs font-semibold transition-all duration-300"
                                :style="getBadgeStyle(service)"
                            >
                                {{ t(service.badgeKey) }}
                            </span>
                            <i class="fas fa-arrow-right transition-all duration-300 group-hover:translate-x-1" :style="{ color: service.accentColor }"></i>
                        </div>
                    </div>

                    <div class="absolute top-0 left-0 w-full h-1 rounded-t-3xl transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left" :style="{ background: `linear-gradient(90deg, ${service.accentColor}, ${service.glowColor})` }"></div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
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
    background: `radial-gradient(ellipse at top, ${derivedColors.value.primary}40 0%, ${derivedColors.value.background} 50%, ${derivedColors.value.background} 100%)`
}))

const gridStyle = computed(() => ({
    backgroundImage: `linear-gradient(${derivedColors.value.border}20 1px, transparent 1px), linear-gradient(90deg, ${derivedColors.value.border}20 1px, transparent 1px)`,
    backgroundSize: '50px 50px'
}))

const blob1Style = computed(() => ({
    background: `linear-gradient(45deg, ${derivedColors.value.accent}30, ${derivedColors.value.secondary}20)`
}))

const blob2Style = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.secondary}30, ${derivedColors.value.accent}20)`
}))

const blob3Style = computed(() => ({
    background: `linear-gradient(225deg, ${derivedColors.value.accent}25, ${derivedColors.value.primary}20)`
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

const serviceCardClasses = computed(() => ({
    'border-color': derivedColors.value.border + '30',
    'bg-color': derivedColors.value.surface + '60'
}))

const services = ref([
    {
        id: 'gamified-mining',
        titleKey: 'gamifiedMiningTitle',
        descriptionKey: 'gamifiedMiningDescription',
        icon: 'fas fa-gamepad',
        accentColor: '#8b5cf6',
        glowColor: '#a855f7',
        badgeKey: 'gamingBadge',
        features: [
            'realTimeRewards',
            'multiLevelSystem',
            'achievementSystem',
            'competitiveRanking'
        ]
    },
    {
        id: 'trade-trading',
        titleKey: 'tradeTradingTitle',
        descriptionKey: 'tradeTradingDescription',
        icon: 'fas fa-chart-line',
        accentColor: '#06b6d4',
        glowColor: '#0891b2',
        badgeKey: 'tradingBadge',
        features: [
            'instantTrades',
            'multipleAssets',
            'riskManagement',
            'tradingSignals'
        ]
    },
    {
        id: 'ico-platform',
        titleKey: 'icoPlatformTitle',
        descriptionKey: 'icoPlatformDescription',
        icon: 'fas fa-coins',
        accentColor: '#f59e0b',
        glowColor: '#d97706',
        badgeKey: 'launchpadBadge',
        features: [
            'tokenLaunch',
            'smartContracts',
            'complianceTools',
            'investorProtection'
        ]
    },
    {
        id: 'staking-rewards',
        titleKey: 'stakingRewardsTitle',
        descriptionKey: 'stakingRewardsDescription',
        icon: 'fas fa-piggy-bank',
        accentColor: '#10b981',
        glowColor: '#059669',
        badgeKey: 'stakingBadge',
        features: [
            'flexibleStaking',
            'competitiveAPY',
            'autoCompounding',
            'instantUnstaking'
        ]
    },
    {
        id: 'referral-commission',
        titleKey: 'referralCommissionTitle',
        descriptionKey: 'referralCommissionDescription',
        icon: 'fas fa-users',
        accentColor: '#ec4899',
        glowColor: '#db2777',
        badgeKey: 'affiliateBadge',
        features: [
            'multiLevelCommission',
            'realTimeTracking',
            'bonusRewards',
            'leaderboards'
        ]
    },
    {
        id: 'security-suite',
        titleKey: 'securitySuiteTitle',
        descriptionKey: 'securitySuiteDescription',
        icon: 'fas fa-shield-alt',
        accentColor: '#ef4444',
        glowColor: '#dc2626',
        badgeKey: 'securityBadge',
        features: [
            'multiFactorAuth',
            'coldStorage',
            'insuranceFund',
            'auditReports'
        ]
    }
])

const validatorStats = reactive({
    uptime: 99.99,
    rewards: '1,247',
    delegators: '5,832',
    networks: 35
})

const getIconStyle = (service) => ({
    background: `linear-gradient(135deg, ${service.accentColor}, ${service.glowColor})`,
    boxShadow: `0 4px 15px ${service.accentColor}25`
})

const getBadgeStyle = (service) => ({
    backgroundColor: service.accentColor + '20',
    color: service.accentColor
})

const selectService = (service) => {
    emit('service-selected', service)
}
const animateStats = () => {
    const targets = {
        uptime: 99.99,
        rewards: 1247,
        delegators: 5832,
        networks: 35
    }

    Object.keys(targets).forEach(key => {
        let current = 0
        const target = targets[key]
        const increment = target / 50

        const timer = setInterval(() => {
            current += increment
            if (current >= target) {
                if (key === 'uptime') {
                    validatorStats[key] = target
                } else {
                    validatorStats[key] = target.toLocaleString()
                }
                clearInterval(timer)
            } else {
                if (key === 'uptime') {
                    validatorStats[key] = Math.floor(current * 100) / 100
                } else {
                    validatorStats[key] = Math.floor(current).toLocaleString()
                }
            }
        }, 50)
    })
}

onMounted(() => {
    setTimeout(animateStats, 1000)
})

const emit = defineEmits([
    'service-selected',
    'get-started-clicked'
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
.animation-delay-2000 { animation-delay: 2s; }
.animation-delay-4000 { animation-delay: 4s; }

/* Enhanced service card hover effects */
.service-card {
    background-color: var(--surface-color);
    border-color: var(--border-color);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.service-card:hover {
    background-color: var(--surface-hover-color);
    border-color: var(--border-hover-color);
    box-shadow: 0 25px 50px -12px var(--hover-glow);
}

.service-card:hover .fas {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.service-card * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (max-width: 768px) {
    .service-card:hover {
        transform: translateY(-1px) scale(1.02);
    }
}
</style>
