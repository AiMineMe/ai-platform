<template>
    <section
        class="relative min-h-screen flex items-center justify-center overflow-hidden pt-20 pb-12 px-4"
        :style="sectionStyle"
    >
        <div class="absolute inset-0 opacity-30">
            <div
                class="absolute inset-0"
                :style="gridStyle"
            ></div>
        </div>

        <div class="absolute inset-0 overflow-hidden">
            <div
                class="absolute top-20 left-20 w-72 h-72 rounded-full blur-3xl animate-blob"
                :style="blob1Style"
            ></div>
            <div
                class="absolute top-40 right-32 w-56 h-56 rounded-full blur-3xl animate-blob animation-delay-2000"
                :style="blob2Style"
            ></div>
            <div
                class="absolute bottom-32 left-40 w-64 h-64 rounded-full blur-3xl animate-blob animation-delay-4000"
                :style="blob3Style"
            ></div>
        </div>

        <div class="absolute inset-0" ref="particlesContainer"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-8 lg:gap-16 items-center">
                <div class="lg:col-span-7 space-y-8 lg:space-y-10 animate-fade-in-up mt-15 lg:mt-15">
                    <div class="space-y-8 lg:space-y-10">
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-black leading-[0.95] tracking-tight">
                            <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                                {{ t('heroTitle1') }}
                            </span>
                            <br>
                            <span class="relative inline-block mt-2">
                                <span :style="{ background: gradientAccent, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                                    {{ t('heroTitle2') }}
                                </span>
                                <div
                                    class="absolute -bottom-4 left-0 h-2 w-32 rounded-full animate-pulse"
                                    :style="underlineStyle"
                                ></div>
                            </span>
                            <br>
                            <span class="mt-2 inline-block" :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                                {{ t('heroTitle3') }}
                            </span>
                        </h1>

                        <p
                            class="text-lg lg:text-xl xl:text-2xl leading-relaxed max-w-2xl font-light animate-fade-in-up animation-delay-300 mt-6"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('heroDescription') }}
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 lg:gap-6 pt-6 lg:pt-8 animate-fade-in-up animation-delay-600">
                        <button
                            @click="handlePrimaryAction"
                            class="group relative px-6 lg:px-8 py-3 lg:py-4 font-semibold text-base lg:text-lg rounded-2xl transition-all duration-500 hover:scale-105 hover:shadow-2xl overflow-hidden"
                            :style="primaryButtonStyle"
                        >
                            <span class="relative z-10 flex items-center justify-center gap-3">
                                <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                                </svg>
                                {{ t('startBuilding') }}
                            </span>
                            <div
                                class="absolute inset-0 bg-gradient-to-r opacity-0 group-hover:opacity-100 transition-opacity duration-500"
                                :style="buttonHoverStyle"
                            ></div>
                        </button>

                        <button
                            @click="handleSecondaryAction"
                            class="group relative px-6 lg:px-8 py-3 lg:py-4 font-semibold text-base lg:text-lg rounded-2xl border-2 transition-all duration-500 hover:scale-105 backdrop-blur-xl overflow-hidden"
                            :style="secondaryButtonStyle"
                        >
                            <span class="relative z-10 flex items-center justify-center gap-3">
                                <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                </svg>
                                {{ t('watchDemo') }}
                            </span>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-6 lg:gap-8 pt-8 lg:pt-12 animate-fade-in-up animation-delay-900">
                        <div class="text-center space-y-2 lg:space-y-3">
                            <div
                                class="text-3xl lg:text-4xl xl:text-5xl font-black"
                                :style="{ background: statsGradient, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }"
                            >
                                {{ animatedStats.uptime }}%
                            </div>
                            <div class="text-xs lg:text-sm font-medium uppercase tracking-wider" :style="{ color: derivedColors.textMuted }">
                                {{ t('uptime') }}
                            </div>
                        </div>

                        <div class="text-center space-y-2 lg:space-y-3">
                            <div
                                class="text-3xl lg:text-4xl xl:text-5xl font-black"
                                :style="{ background: statsGradient, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }"
                            >
                                {{ animatedStats.networks }}+
                            </div>
                            <div class="text-xs lg:text-sm font-medium uppercase tracking-wider" :style="{ color: derivedColors.textMuted }">
                                {{ t('networks') }}
                            </div>
                        </div>

                        <div class="text-center space-y-2 lg:space-y-3">
                            <div
                                class="text-3xl lg:text-4xl xl:text-5xl font-black"
                                :style="{ background: statsGradient, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }"
                            >
                                ${{ animatedStats.tvl }}B+
                            </div>
                            <div class="text-xs lg:text-sm font-medium uppercase tracking-wider" :style="{ color: derivedColors.textMuted }">
                                {{ t('tvl') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-5 relative h-[500px] lg:h-[600px] animate-fade-in-right animation-delay-500 mt-8 lg:mt-0">
                    <div
                        class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-sm mx-auto backdrop-blur-2xl border rounded-3xl p-6 lg:p-8 transition-all duration-700 hover:scale-105 animate-float"
                        :style="dashboardStyle"
                    >
                        <div class="flex items-center justify-between mb-6 lg:mb-8">
                            <div>
                                <h3 class="text-lg lg:text-xl font-bold mb-1" :style="{ color: derivedColors.textPrimary }">
                                    {{ t('networkOverview') }}
                                </h3>
                                <p class="text-sm" :style="{ color: derivedColors.textMuted }">   {{ t('realTimeMetrics')}}</p>
                            </div>
                            <div
                                class="w-10 lg:w-12 h-10 lg:h-12 rounded-2xl flex items-center justify-center"
                                :style="iconBgStyle"
                            >
                                <svg class="w-5 lg:w-6 h-5 lg:h-6" :style="{ color: derivedColors.textPrimary }" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                                </svg>
                            </div>
                        </div>

                        <div
                            class="h-28 lg:h-32 rounded-2xl relative overflow-hidden mb-6"
                            :style="chartBgStyle"
                        >
                            <div
                                class="absolute bottom-6 left-6 right-6 h-1 rounded-full animate-pulse"
                                :style="chartLineStyle"
                                ref="chartLine"
                            ></div>
                            <div class="absolute inset-0 grid grid-cols-6 gap-px opacity-20">
                                <div v-for="i in 6" :key="i" class="border-r" :style="{ borderColor: derivedColors.border }"></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-green-400 animate-pulse"></div>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('allOperationsSystem')}}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="absolute top-4 lg:top-8 right-0 w-56 lg:w-64 backdrop-blur-2xl border rounded-2xl p-4 lg:p-6 transition-all duration-700 hover:scale-105 animate-float animation-delay-1000"
                        :style="pricesCardStyle"
                    >
                        <div class="flex items-center justify-between mb-4 lg:mb-6">
                            <h3 class="text-base lg:text-lg font-bold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('livePrices') }}
                            </h3>
                            <div class="w-8 lg:w-10 h-8 lg:h-10 bg-gradient-to-br from-orange-500 to-yellow-500 rounded-xl flex items-center justify-center">
                                <svg class="w-4 lg:w-5 h-4 lg:h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>

                        <div class="space-y-3 lg:space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 lg:w-8 h-6 lg:h-8 bg-orange-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">₿</span>
                                    </div>
                                    <span class="font-medium text-sm lg:text-base" :style="{ color: derivedColors.textPrimary }">BTC</span>
                                </div>
                                <span class="text-green-400 font-bold text-sm lg:text-base">${{ formatPrice(cryptoPrices.btc.value) }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 lg:w-8 h-6 lg:h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">Ξ</span>
                                    </div>
                                    <span class="font-medium text-sm lg:text-base" :style="{ color: derivedColors.textPrimary }">ETH</span>
                                </div>
                                <span class="text-green-400 font-bold text-sm lg:text-base">${{ formatPrice(cryptoPrices.eth.value) }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="absolute bottom-4 lg:bottom-8 left-0 w-48 lg:w-56 backdrop-blur-2xl border rounded-2xl p-4 lg:p-6 transition-all duration-700 hover:scale-105 animate-float animation-delay-2000"
                        :style="performanceCardStyle"
                    >
                        <div class="flex items-center justify-between mb-4 lg:mb-6">
                            <h3 class="text-base lg:text-lg font-bold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('performance') }}
                            </h3>
                            <div class="w-8 lg:w-10 h-8 lg:h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
                                <svg class="w-4 lg:w-5 h-4 lg:h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>

                        <div class="text-center">
                            <div class="text-2xl lg:text-3xl font-black text-green-400 mb-2">
                                {{ animatedPerformance }}ms
                            </div>
                            <div class="text-xs lg:text-sm font-medium" :style="{ color: derivedColors.textMuted }">
                                {{ t('avgResponseTime') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      <div
          v-if="showVideoModal"
          class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4"
          @click="showVideoModal = false"
      >
        <div
            class="relative w-full max-w-4xl mx-auto"
            @click.stop
        >
          <button
              @click="showVideoModal = false"
              class="absolute -top-10 right-0 text-white hover:text-gray-300 text-xl"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
          <div class="relative w-full" style="padding-bottom: 56.25%;">
            <iframe
                :src="getYouTubeEmbedUrl(t('demoVideoUrl'))"
                class="absolute inset-0 w-full h-full rounded-lg"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
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
        warning: '#f59e0b'
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

const gradientText = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.textPrimary} 0%, ${derivedColors.value.textSecondary} 50%, ${derivedColors.value.accent} 100%)`
)

const gradientAccent = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`
)

const underlineStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary})`
}))

const statsGradient = computed(() =>
    `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`
)

const primaryButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary,
    boxShadow: `0 20px 40px -12px ${derivedColors.value.accent}50`
}))

const buttonHoverStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.secondary} 0%, ${derivedColors.value.accent} 100%)`
}))

const secondaryButtonStyle = computed(() => ({
    color: derivedColors.value.textPrimary,
    borderColor: derivedColors.value.border + '60',
    backgroundColor: derivedColors.value.surface + '20'
}))

const dashboardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + 'e6',
    borderColor: derivedColors.value.border + '40',
    boxShadow: `0 25px 50px -12px ${derivedColors.value.primary}30`
}))

const pricesCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + 'f0',
    borderColor: derivedColors.value.border + '30'
}))

const performanceCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + 'f0',
    borderColor: derivedColors.value.border + '30'
}))

const iconBgStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`
}))

const chartBgStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent}10, ${derivedColors.value.secondary}10)`
}))

const chartLineStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.success}, ${derivedColors.value.accent})`
}))

const animatedStats = reactive({
    uptime: 0,
    networks: 0,
    tvl: 0
})

const cryptoPrices = {
    btc: computed(() => page.props.cryptoPrices?.btc || 109195),
    eth: computed(() => page.props.cryptoPrices?.eth || 2570)
}

const animatedPerformance = ref(0)
const particlesContainer = ref(null)
const chartLine = ref(null)
const showVideoModal = ref(false)

const handlePrimaryAction = () => {
  const primaryUrl = t('primaryActionUrl')
  if (primaryUrl) {
    window.open(primaryUrl, '_blank')
  }
}

const handleSecondaryAction = () => {
  const videoUrl = t('demoVideoUrl')
  if (videoUrl) {
    showVideoModal.value = true
  }
}

const getYouTubeEmbedUrl = (url) => {
  if (!url) return ''

  const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/
  const match = url.match(regExp)

  if (match && match[2].length === 11) {
    return `https://www.youtube.com/embed/${match[2]}?autoplay=1`
  }

  return url
}

const formatPrice = (price) => {
    return new Intl.NumberFormat('en-US').format(price)
}

const animateCounters = () => {
    const targets = { uptime: 99.9, networks: 35, tvl: 2.5 }
    const duration = 2500
    const steps = 80
    const stepTime = duration / steps

    Object.keys(targets).forEach(key => {
        const target = targets[key]
        const increment = target / steps
        let current = 0

        const timer = setInterval(() => {
            current += increment
            if (current >= target) {
                animatedStats[key] = target
                clearInterval(timer)
            } else {
                animatedStats[key] = Math.floor(current * 10) / 10
            }
        }, stepTime)
    })
}

const animatePerformanceCounter = () => {
    let value = 0
    const target = 14

    const animate = () => {
        value += (target - value) * 0.08
        animatedPerformance.value = Math.floor(value)

        if (Math.abs(target - value) > 0.1) {
            requestAnimationFrame(animate)
        }
    }

    setTimeout(animate, 2500)
}

const createModernParticles = () => {
    if (!particlesContainer.value) return

    const particleCount = 40

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div')
        particle.className = 'absolute rounded-full opacity-0 animate-ping'
        const size = Math.random() * 3 + 1
        particle.style.width = size + 'px'
        particle.style.height = size + 'px'
        particle.style.backgroundColor = derivedColors.value.accent
        particle.style.left = Math.random() * 100 + '%'
        particle.style.top = Math.random() * 100 + '%'
        particle.style.animationDelay = Math.random() * 10 + 's'
        particle.style.animationDuration = (Math.random() * 8 + 6) + 's'

        particlesContainer.value.appendChild(particle)
    }
}

const initChartAnimation = () => {
    if (!chartLine.value) return
    let frequency = 0
    function visualize() {
        frequency += 0.05
        const intensity = (Math.sin(frequency) * 0.3 + 0.7)
        chartLine.value.style.opacity = intensity
        chartLine.value.style.transform = `scaleX(${intensity})`

        requestAnimationFrame(visualize)
    }

    visualize()
}

onMounted(() => {
    createModernParticles()
    setTimeout(() => {
        animateCounters()
        animatePerformanceCounter()
    }, 1000)

    initChartAnimation()
})
</script>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fade-in-right {
    from {
        opacity: 0;
        transform: translateX(40px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-15px) rotate(2deg);
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
    animation: fade-in-up 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-fade-in-right {
    animation: fade-in-right 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-float {
    animation: float 8s ease-in-out infinite;
}

.animate-blob {
    animation: blob 20s infinite;
}

.animation-delay-300 { animation-delay: 0.3s; }
.animation-delay-500 { animation-delay: 0.5s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-900 { animation-delay: 0.9s; }
.animation-delay-1000 { animation-delay: 1s; }
.animation-delay-2000 { animation-delay: 2s; }
.animation-delay-4000 { animation-delay: 4s; }

::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}

@media (max-width: 768px) {
    .section {
        padding-top: 6rem;
        padding-bottom: 3rem;
    }
}

@media (max-width: 640px) {
    .section {
        padding-top: 5rem;
        padding-bottom: 2rem;
    }
}
</style>
