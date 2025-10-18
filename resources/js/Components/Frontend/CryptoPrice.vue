<template>
    <section
        class="relative py-16 lg:py-24 overflow-hidden"
        id="crypto-prices"
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
                class="absolute top-20 left-20 w-96 h-96 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute bottom-20 right-20 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-2000"
                :style="blob2Style"
            ></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                    <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('liveMarketData') }}</span>
                </div>

                <h2 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('liveCryptoPrices') }}
                    </span>
                </h2>

                <p class="text-lg lg:text-xl max-w-3xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                    {{ t('cryptoPricesDescription') }}
                </p>

                <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 animate-fade-in-up animation-delay-300">
                <div
                    class="backdrop-blur-xl border rounded-2xl p-6 text-center transition-all duration-300 hover:scale-105 hover:shadow-xl"
                    :style="statsCardStyle"
                >
                    <div class="text-3xl lg:text-4xl font-black mb-2" :style="{ color: derivedColors.success }">
                        {{ totalMarketCap }}
                    </div>
                    <div class="text-sm font-medium" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalMarketCap') }}
                    </div>
                </div>

                <div
                    class="backdrop-blur-xl border rounded-2xl p-6 text-center transition-all duration-300 hover:scale-105 hover:shadow-xl"
                    :style="statsCardStyle"
                >
                    <div class="text-3xl lg:text-4xl font-black mb-2" :style="{ color: derivedColors.accent }">
                        {{ totalVolume }}
                    </div>
                    <div class="text-sm font-medium" :style="{ color: derivedColors.textMuted }">
                        {{ t('tradingVolume24h') }}
                    </div>
                </div>

                <div
                    class="backdrop-blur-xl border rounded-2xl p-6 text-center transition-all duration-300 hover:scale-105 hover:shadow-xl"
                    :style="statsCardStyle"
                >
                    <div class="text-3xl lg:text-4xl font-black mb-2" :style="{ color: derivedColors.warning }">
                        {{ dominancePercentage }}%
                    </div>
                    <div class="text-sm font-medium" :style="{ color: derivedColors.textMuted }">
                        {{ t('bitcoinDominance') }}
                    </div>
                </div>
            </div>

            <div
                class="backdrop-blur-xl border rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up animation-delay-500"
                :style="tableContainerStyle"
            >
                <div class="p-6 lg:p-8 text-center border-b" :style="{ borderColor: derivedColors.border + '30' }">
                    <h3 class="text-xl lg:text-2xl font-bold mb-2" :style="{ color: derivedColors.textPrimary }">
                        {{ t('marketOverview') }}
                    </h3>
                    <p class="mb-4" :style="{ color: derivedColors.textSecondary }">
                        {{ t('updateFrequency') }}
                    </p>
                    <div class="flex justify-center gap-1">
                        <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                        <div class="w-2 h-2 rounded-full animate-pulse animation-delay-300" :style="{ backgroundColor: derivedColors.accent }"></div>
                        <div class="w-2 h-2 rounded-full animate-pulse animation-delay-600" :style="{ backgroundColor: derivedColors.accent }"></div>
                    </div>
                </div>

                <div class="p-4 lg:p-6 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col lg:flex-row gap-4 justify-between items-center">
                        <div class="relative flex-1 max-w-md">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2" :style="{ color: derivedColors.textMuted }"></i>
                            <input
                                type="text"
                                v-model="searchQuery"
                                :placeholder="t('searchCryptos')"
                                class="w-full pl-12 pr-4 py-3 border rounded-full focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                :style="searchInputStyle"
                            >
                        </div>

                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-for="filter in filters"
                                :key="filter.key"
                                @click="activeFilter = filter.key"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all duration-300 hover:scale-105"
                                :style="getFilterButtonStyle(filter.key)"
                            >
                                {{ t(filter.labelKey) }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="filteredCryptos.length === 0" class="p-8 text-center">
                    <div :style="{ color: derivedColors.textMuted }">
                        No crypto data available.
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 backdrop-blur-sm" :style="{ backgroundColor: derivedColors.surface + '90' }">
                        <tr class="border-b" :style="{ borderColor: derivedColors.border + '30' }">
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:opacity-80 transition-colors" :style="{ color: derivedColors.textMuted }">
                                {{ t('rank') }}
                            </th>
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:opacity-80 transition-colors" :style="{ color: derivedColors.textMuted }">
                                {{ t('name') }}
                            </th>
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:opacity-80 transition-colors" :style="{ color: derivedColors.textMuted }">
                                {{ t('price') }}
                            </th>
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:opacity-80 transition-colors" :style="{ color: derivedColors.textMuted }">
                                {{ t('change24h') }}
                            </th>
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:opacity-80 transition-colors hidden md:table-cell" :style="{ color: derivedColors.textMuted }">
                                {{ t('volume24h') }}
                            </th>
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:opacity-80 transition-colors hidden lg:table-cell" :style="{ color: derivedColors.textMuted }">
                                {{ t('marketCap') }}
                            </th>
                            <th class="px-4 lg:px-6 py-4 text-left text-xs font-bold uppercase tracking-wider hidden xl:table-cell" :style="{ color: derivedColors.textMuted }">
                                {{ t('chart') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody class="crypto-table-body">
                        <tr
                            v-for="(crypto, index) in filteredCryptos"
                            :key="crypto.id || crypto.symbol || index"
                            :style="{
                                    animationDelay: `${index * 0.1}s`,
                                    '--hover-bg': derivedColors.accent + '08',
                                    '--hover-shadow': derivedColors.accent + '20'
                                }"
                            class="crypto-row animate-fade-in-up transition-all duration-300 cursor-pointer group"
                            @click="selectCrypto(crypto)"
                        >
                            <td class="px-4 lg:px-6 py-4 font-medium" :style="{ color: derivedColors.textMuted }">
                                {{ crypto.rank || index + 1 }}
                            </td>
                            <td class="px-4 lg:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg overflow-hidden"
                                    >
                                        <img
                                            :src="crypto.image_url"
                                            :alt="crypto.symbol"
                                            class="w-full h-full object-cover rounded-full"
                                        />
                                    </div>
                                    <div>
                                        <div class="font-bold transition-colors duration-300 group-hover:text-opacity-90" :style="{ color: derivedColors.textPrimary }">
                                            {{ crypto.symbol || 'N/A' }}
                                        </div>
                                        <div class="text-sm transition-colors duration-300" :style="{ color: derivedColors.textSecondary }">
                                            {{ crypto.name || 'Unknown' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 lg:px-6 py-4 font-bold text-lg transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                                ${{ formatPrice(crypto.price || 0) }}
                            </td>
                            <td class="px-4 lg:px-6 py-4">
                                    <span
                                        class="px-3 py-1 rounded-full text-sm font-semibold transition-all duration-300 group-hover:scale-105"
                                        :style="getChangeStyle(crypto.change || 0)"
                                    >
                                        {{ (crypto.change || 0) >= 0 ? '+' : '' }}{{ (crypto.change || 0).toFixed(2) }}%
                                    </span>
                            </td>
                            <td class="px-4 lg:px-6 py-4 font-medium hidden md:table-cell transition-colors duration-300" :style="{ color: derivedColors.textSecondary }">
                                ${{ formatVolume(crypto.volume || 0) }}
                            </td>
                            <td class="px-4 lg:px-6 py-4 font-semibold hidden lg:table-cell transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                                ${{ formatVolume(crypto.marketCap || 0) }}
                            </td>
                            <td class="px-4 lg:px-6 py-4 hidden xl:table-cell">
                                <div class="w-16 h-8 rounded-md relative overflow-hidden transition-all duration-300 group-hover:scale-105" :style="chartBgStyle">
                                    <div
                                        class="absolute bottom-1 left-2 right-2 h-0.5 rounded-full animate-pulse"
                                        :style="{
                                                background: `linear-gradient(90deg, ${derivedColors.success}, ${derivedColors.accent})`,
                                                opacity: Math.random() * 0.5 + 0.5
                                            }"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 lg:p-8 border-t text-center" :style="{ borderColor: derivedColors.border + '20' }">
                    <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                        {{ t('dataProvider') }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from 'vue'
import {usePage} from '@inertiajs/vue3'
import {useTranslation} from '@/composables/useTranslation'

const props = defineProps({
    cryptoData: {
        type: [Array, Object],
        default: () => []
    },
    marketStats: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['crypto-selected', 'view-all-clicked', 'start-trading-clicked'])
const { t } = useTranslation()
const page = usePage()

const searchQuery = ref('')
const activeFilter = ref('all')
const selectedCrypto = ref(null)
const cryptos = ref([])

watch(() => props.cryptoData, (newData) => {
}, { deep: true, immediate: true })

const primaryColor = computed(() => page.props.primaryColor || '#1f2937')
const derivedColors = computed(() => {
    const primary = primaryColor.value

    const adjustColor = (color, amount) => {
        if (!color || typeof color !== 'string') return '#1f2937'
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

// Computed styles (keeping all your existing styles)
const sectionStyle = computed(() => ({
    background: `radial-gradient(ellipse at center, ${derivedColors.value.primary}30 0%, ${derivedColors.value.background} 50%, ${derivedColors.value.background} 100%)`
}))

const gridStyle = computed(() => ({
    backgroundImage: `linear-gradient(${derivedColors.value.border}15 1px, transparent 1px), linear-gradient(90deg, ${derivedColors.value.border}15 1px, transparent 1px)`,
    backgroundSize: '40px 40px'
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

const tableContainerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '80',
    borderColor: derivedColors.value.border + '40'
}))

const searchInputStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '40',
    color: derivedColors.value.textPrimary,
    focusRingColor: derivedColors.value.accent + '50'
}))

const chartBgStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent}10, ${derivedColors.value.secondary}10)`
}))

const filters = [
    { key: 'all', labelKey: 'all' },
    { key: 'gainers', labelKey: 'topGainers' },
    { key: 'losers', labelKey: 'topLosers' },
    { key: 'volume', labelKey: 'highVolume' }
]

const filteredCryptos = computed(() => {
    let dataSource = []
    if (Array.isArray(props.cryptoData)) {
        dataSource = props.cryptoData
    } else if (props.cryptoData && typeof props.cryptoData === 'object') {
        dataSource = Object.values(props.cryptoData)
    }

    if (dataSource.length === 0) {
        dataSource = Array.isArray(cryptos.value) ? cryptos.value : []
    }

    let filtered = [...dataSource]
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(crypto =>
            (crypto.name && crypto.name.toLowerCase().includes(query)) ||
            (crypto.symbol && crypto.symbol.toLowerCase().includes(query))
        )
    }

    switch (activeFilter.value) {
        case 'gainers':
            filtered = filtered.filter(crypto => (crypto.change || 0) > 0)
            break
        case 'losers':
            filtered = filtered.filter(crypto => (crypto.change || 0) < 0)
            break
        case 'volume':
            filtered = filtered.filter(crypto => (crypto.volume || 0) > 5000000000)
            break
    }

    return filtered
})

const totalMarketCap = computed(() => {
    if (props.marketStats && props.marketStats.totalMarketCap) {
        return formatVolume(props.marketStats.totalMarketCap)
    }

    const dataSource = Array.isArray(props.cryptoData) ? props.cryptoData : cryptos.value
    const total = dataSource.reduce((sum, crypto) => sum + (crypto.marketCap || 0), 0)
    return formatVolume(total)
})

const totalVolume = computed(() => {
    if (props.marketStats && props.marketStats.totalVolume) {
        return formatVolume(props.marketStats.totalVolume)
    }

    const dataSource = Array.isArray(props.cryptoData) ? props.cryptoData : cryptos.value
    const total = dataSource.reduce((sum, crypto) => sum + (crypto.volume || 0), 0)
    return formatVolume(total)
})

const dominancePercentage = computed(() => {
    if (props.marketStats && props.marketStats.dominancePercentage) {
        return formatVolume(props.marketStats.dominancePercentage)
    }

    const dataSource = Array.isArray(props.cryptoData) ? props.cryptoData : cryptos.value
    const total = dataSource.reduce((sum, crypto) => sum + (crypto.marketCap || 0), 0)
    return formatVolume(total)})

const getFilterButtonStyle = (filterKey) => {
    const isActive = activeFilter.value === filterKey
    return {
        backgroundColor: isActive ? derivedColors.value.accent : derivedColors.value.surface + '40',
        color: isActive ? derivedColors.value.textPrimary : derivedColors.value.textSecondary,
        borderColor: isActive ? derivedColors.value.accent : derivedColors.value.border + '30'
    }
}

const getChangeStyle = (change) => {
    const isPositive = change >= 0
    return {
        backgroundColor: isPositive ? derivedColors.value.success + '20' : derivedColors.value.error + '20',
        color: isPositive ? derivedColors.value.success : derivedColors.value.error
    }
}

const formatPrice = (price) => {
    if (!price || isNaN(price)) return '0.00'
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: price < 1 ? 4 : 2,
        maximumFractionDigits: price < 1 ? 4 : 2
    }).format(price)
}

const formatVolume = (volume) => {
    if (!volume || isNaN(volume)) return '0.00'
    if (volume >= 1000000000000) {
        return (volume / 1000000000000).toFixed(2) + 'T'
    } else if (volume >= 1000000000) {
        return (volume / 1000000000).toFixed(2) + 'B'
    } else if (volume >= 1000000) {
        return (volume / 1000000).toFixed(2) + 'M'
    }
    return volume.toFixed(2)
}

const selectCrypto = (crypto) => {
    selectedCrypto.value = crypto
    emit('crypto-selected', crypto)
}

const fetchCryptoPrices = async () => {
    if (!Array.isArray(props.cryptoData) || props.cryptoData.length === 0) {
        cryptos.value.forEach(crypto => {
            const randomChange = (Math.random() - 0.5) * 0.02
            crypto.price *= (1 + randomChange)
            crypto.change += randomChange * 100
        })
    }
}

let priceUpdateInterval
onMounted(() => {
    priceUpdateInterval = setInterval(fetchCryptoPrices, 30000)
})

onUnmounted(() => {
    if (priceUpdateInterval) {
        clearInterval(priceUpdateInterval)
    }
})
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
.animation-delay-500 { animation-delay: 0.5s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-2000 { animation-delay: 2s; }

.crypto-table-body {
    position: relative;
}

.crypto-row {
    position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 0.75rem;
    margin: 1px 0;
}

.crypto-row:hover {
    background-color: var(--hover-bg);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px var(--hover-shadow);
    backdrop-filter: blur(10px);
    border-radius: 0.875rem;
    z-index: 1;
}

.crypto-row:hover td {
    border-color: transparent;
}

.crypto-row:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}

.crypto-row:hover .group-hover\:scale-105 {
    transform: scale(1.05);
}

.crypto-row:hover .group-hover\:shadow-lg {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.crypto-row * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (max-width: 768px) {
    .crypto-row:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px -3px var(--hover-shadow);
    }

    .crypto-row:hover .group-hover\:scale-110 {
        transform: scale(1.05);
    }

    .crypto-row:hover .group-hover\:scale-105 {
        transform: scale(1.02);
    }
}

.crypto-row {
    isolation: isolate;
}

.overflow-x-auto {
    scroll-behavior: smooth;
}

.crypto-row:focus-within {
    outline: 2px solid rgba(59, 130, 246, 0.5);
    outline-offset: 2px;
}
</style>
