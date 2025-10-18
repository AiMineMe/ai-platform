<template>
    <section
        class="relative py-16 lg:py-20 overflow-hidden"
        id="networks"
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
                class="absolute top-1/4 right-1/4 w-96 h-96 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute bottom-1/4 left-1/4 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-1000"
                :style="blob2Style"
            ></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                    <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('supportedNetworks') }}</span>
                </div>

                <h2 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('networksTitle') }}
                    </span>
                </h2>

                <p class="text-lg lg:text-xl max-w-3xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                    {{ t('networksDescription') }}
                </p>

                <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12 animate-fade-in-up animation-delay-300">
                <div
                    v-for="(stat, index) in networkStats"
                    :key="stat.labelKey"
                    class="text-center p-6 backdrop-blur-xl border rounded-2xl transition-all duration-300 hover:-translate-y-2 animate-fade-in-up"
                    :style="statsCardStyle"
                >
                    <div class="text-3xl font-black mb-2" :style="{ color: derivedColors.accent }">{{ stat.value }}</div>
                    <div class="text-sm" :style="{ color: derivedColors.textMuted }">{{ t(stat.labelKey) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12 animate-fade-in-up animation-delay-600">
                <div
                    v-for="(network, index) in featuredNetworks"
                    :key="network.id"
                    :style="{ animationDelay: `${(index + 4) * 0.1}s` }"
                    class="group network-card backdrop-blur-xl border rounded-2xl p-6 transition-all duration-300 hover:-translate-y-2 hover:scale-105 animate-fade-in-up cursor-pointer text-center"
                >
                    <div class="mb-4">
                        <div
                            class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-white text-xl font-bold shadow-lg transition-all duration-300 group-hover:scale-110"
                            :style="{ background: `linear-gradient(135deg, ${network.color}, ${adjustColor(network.color, 30)})` }"
                        >
                            {{ network.symbol }}
                        </div>
                    </div>

                    <h3 class="font-bold mb-2 transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                        {{ network.name }}
                    </h3>

                    <div class="text-sm mb-4" :style="{ color: derivedColors.textSecondary }">
                        {{ t(network.typeKey) }}
                    </div>

                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.success }"></div>
                        <span class="text-xs font-medium" :style="{ color: derivedColors.success }">
                            {{ t('active') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="text-center animate-fade-in-up animation-delay-900">
                <h3 class="text-2xl md:text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('networkCtaTitle') }}
                </h3>
                <p class="mb-8 max-w-2xl mx-auto" :style="{ color: derivedColors.textSecondary }">
                    {{ t('networkCtaDescription') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button
                        class="inline-flex items-center gap-2 px-6 py-3 font-semibold rounded-full transition-all duration-300 hover:scale-105 hover:shadow-lg"
                        :style="primaryButtonStyle"
                        @click="handleViewAllNetworks"
                    >
                        <i class="fas fa-globe"></i>
                        {{ t('viewAllNetworks') }}
                    </button>
                    <button
                        class="inline-flex items-center gap-2 px-6 py-3 font-semibold rounded-full border transition-all duration-300 hover:scale-105"
                        :style="secondaryButtonStyle"
                        @click="handleRequestIntegration"
                    >
                        <i class="fas fa-plus"></i>
                        {{ t('requestIntegration') }}
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

const statsCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '30'
}))

const primaryButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

const secondaryButtonStyle = computed(() => ({
    color: derivedColors.value.textPrimary,
    borderColor: derivedColors.value.border + '60',
    backgroundColor: derivedColors.value.surface + '30'
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

const networkStats = ref([
    { value: '35+', labelKey: 'totalNetworks' },
    { value: '99.9%', labelKey: 'averageUptime' },
    { value: '$2.5B+', labelKey: 'valueSecured' },
    { value: '50K+', labelKey: 'activeDelegators' }
])

const featuredNetworks = ref([
    {
        id: 'bitcoin',
        name: 'Bitcoin',
        symbol: '₿',
        typeKey: 'layer1Network',
        color: '#f7931a'
    },
    {
        id: 'ethereum',
        name: 'Ethereum',
        symbol: 'Ξ',
        typeKey: 'layer1Network',
        color: '#627eea'
    },
    {
        id: 'cosmos',
        name: 'Cosmos Hub',
        symbol: 'ATOM',
        typeKey: 'cosmosEcosystem',
        color: '#2e3148'
    },
    {
        id: 'solana',
        name: 'Solana',
        symbol: 'SOL',
        typeKey: 'highPerformance',
        color: '#9945ff'
    }
])

const handleViewAllNetworks = () => {
    const viewAllNetworksUrl = t('viewAllNetworksUrl')
    if (viewAllNetworksUrl) {
        window.open(viewAllNetworksUrl, '_blank')
    }
}

const handleRequestIntegration = () => {
    const requestIntegrationUrl = t('requestIntegrationUrl')
    if (requestIntegrationUrl) {
        window.open(requestIntegrationUrl, '_blank')
    }
}

// Emits
const emit = defineEmits([
    'network-selected',
    'view-all-networks-clicked',
    'request-integration-clicked'
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

/* Network card hover effects */
.network-card {
    background-color: var(--surface-color);
    border-color: var(--border-color);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.network-card:hover {
    box-shadow: 0 20px 40px -12px var(--hover-shadow);
}

.network-card * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
