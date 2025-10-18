<script setup>
import {ref, computed, onMounted} from 'vue'
import {Link, usePage} from '@inertiajs/vue3'
import {useTranslation} from '@/composables/useTranslation'
import {useToast} from '@/composables/useToast'

const {t} = useTranslation()
const {showToast} = useToast()
const page = usePage()

const primaryColor = computed(() => page.props.primaryColor || '#1f2937')
const appName = computed(() => page.props.appName || 'MineInvest')
const logoUrl = computed(() => page.props.logoUrl || null)
const defaultLogoUrl = computed(() => page.props.defaultLogoUrl || '/images/default-logo.png')

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

const showCookiesBanner = ref(false)
const logoError = ref(false)

const footerStyle = computed(() => ({
    backgroundColor: derivedColors.value.background,
    borderColor: derivedColors.value.border + '30'
}))

const gridStyle = computed(() => ({
    backgroundImage: `linear-gradient(${derivedColors.value.border}15 1px, transparent 1px), linear-gradient(90deg, ${derivedColors.value.border}15 1px, transparent 1px)`,
    backgroundSize: '50px 50px'
}))

const cookiesBannerStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + 'f0',
    borderColor: derivedColors.value.border + '40'
}))

const cookieIconStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.warning}, ${derivedColors.value.warning}cc)`
}))

const acceptButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

const declineButtonStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40',
    color: derivedColors.value.textSecondary
}))

const currentYear = computed(() => new Date().getFullYear())
const handleLogoError = () => {
    logoError.value = true
}

const getSocialLinkStyle = () => ({
    backgroundColor: derivedColors.value.surface + '60',
    color: derivedColors.value.textMuted,
    '--hover-bg': derivedColors.value.accent,
    '--hover-color': derivedColors.value.textPrimary
})

const checkCookieConsent = () => {
    const consent = localStorage.getItem('cookie_consent')
    if (!consent) {
        setTimeout(() => {
            showCookiesBanner.value = true
        }, 2000)
    }
}

const acceptCookies = () => {
    try {
        localStorage.setItem('cookie_consent', JSON.stringify({
            accepted: true,
            timestamp: Date.now()
        }))
        showCookiesBanner.value = false
        showToast('Cookies preferences saved successfully!', 'success')
    } catch (error) {
        showToast('Something went wrong. Please try again later.', 'error')
    }
}

const declineCookies = () => {
    try {
        localStorage.setItem('cookie_consent', JSON.stringify({
            accepted: false,
            timestamp: Date.now()
        }))
        showCookiesBanner.value = false
        showToast( 'Cookie preferences updated. Only essential cookies will be used.', 'info')
    } catch (error) {
        showToast('Something went wrong. Please try again later.', 'error')
    }
}

const socialLinks = computed(() => {
    return [
        {
            platform: t('socialTwitterName'),
            icon: t('socialTwitterIcon'),
            url: t('socialTwitterUrl')
        },
        {
            platform: t('socialTelegramName'),
            icon: t('socialTelegramIcon'),
            url: t('socialTelegramUrl')
        },
        {
            platform: t('socialDiscordName'),
            icon: t('socialDiscordIcon'),
            url: t('socialDiscordUrl')
        },
        {
            platform: t('socialGithubName'),
            icon: t('socialGithubIcon'),
            url: t('socialGithubUrl')
        }
    ]
})

onMounted(() => {
    checkCookieConsent()
})

const emit = defineEmits(['link-clicked'])
</script>

<template>
    <div>
        <footer class="relative overflow-hidden border-t" :style="footerStyle">
            <div class="absolute inset-0 opacity-10">
                <div
                    class="absolute inset-0"
                    :style="gridStyle"
                ></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-6 sm:py-8 lg:py-12">
                    <div class="flex flex-col lg:flex-row items-center lg:items-start justify-between gap-6 lg:gap-8">
                        <div
                            class="flex flex-col items-center lg:items-start gap-4 lg:gap-6 flex-1 max-w-full lg:max-w-md">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-6 h-6 sm:w-8 sm:h-8 lg:w-10 lg:h-10 rounded-lg flex items-center justify-center shadow-lg overflow-hidden flex-shrink-0">
                                    <img
                                        :src="logoError || !logoUrl ? defaultLogoUrl : logoUrl"
                                        :alt="appName + ' Logo'"
                                        class="w-full h-full object-cover"
                                        @error="handleLogoError"
                                    />
                                </div>
                                <span
                                    class="text-lg sm:text-xl lg:text-2xl font-bold truncate max-w-32 sm:max-w-48 lg:max-w-none"
                                    :style="{ color: derivedColors.textPrimary }"
                                >
                                    {{ t('appName') }}
                                </span>
                            </div>

                            <p
                                class="text-sm sm:text-base text-center lg:text-left max-w-xs sm:max-w-md lg:max-w-full leading-relaxed"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('footerDescriptionShort') }}
                            </p>
                        </div>

                        <div class="flex flex-col items-center lg:items-end gap-4">
                            <h4 class="text-sm font-semibold text-center lg:text-right"
                                :style="{ color: derivedColors.textPrimary }">
                                {{ t('followUs') }}
                            </h4>
                            <div class="flex flex-wrap justify-center lg:justify-end gap-2 sm:gap-3">
                                <a
                                    v-for="social in socialLinks"
                                    :key="social.platform"
                                    :href="social.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center transition-all duration-300 hover:scale-110 hover:-translate-y-1 active:scale-95"
                                    :style="getSocialLinkStyle()"
                                    :aria-label="`Follow us on ${social.platform}`"
                                >
                                    <i :class="social.icon" class="text-sm sm:text-base"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="py-4 sm:py-6 border-t" :style="{ borderColor: derivedColors.border + '20' }">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-sm">
                        <div class="text-center md:text-left" :style="{ color: derivedColors.textMuted }">
                            <p class="text-xs sm:text-sm">
                                &copy; {{ currentYear }} {{ t('appName') }}. {{ t('allRightsReserved') }}
                            </p>
                        </div>

                        <nav class="flex flex-wrap justify-center md:justify-end gap-2 sm:gap-4" role="navigation"
                             aria-label="Footer navigation">
                            <Link
                                href="/privacy-policy"
                                class="text-xs sm:text-sm px-2 py-1 transition-all duration-300 hover:opacity-80 hover:scale-105 active:scale-95 rounded min-h-[44px] md:min-h-0 flex items-center"
                                :style="{ color: derivedColors.textMuted }"
                            >
                                {{ t('privacy') }}
                            </Link>
                            <Link
                                href="/terms"
                                class="text-xs sm:text-sm px-2 py-1 transition-all duration-300 hover:opacity-80 hover:scale-105 active:scale-95 rounded min-h-[44px] md:min-h-0 flex items-center"
                                :style="{ color: derivedColors.textMuted }"
                            >
                                {{ t('terms') }}
                            </Link>
                            <Link
                                href="/cookies"
                                class="text-xs sm:text-sm px-2 py-1 transition-all duration-300 hover:opacity-80 hover:scale-105 active:scale-95 rounded min-h-[44px] md:min-h-0 flex items-center"
                                :style="{ color: derivedColors.textMuted }"
                            >
                                {{ t('cookiesPolicy') }}
                            </Link>
                            <Link
                                href="/contact"
                                class="text-xs sm:text-sm px-2 py-1 transition-all duration-300 hover:opacity-80 hover:scale-105 active:scale-95 rounded min-h-[44px] md:min-h-0 flex items-center"
                                :style="{ color: derivedColors.textMuted }"
                            >
                                {{ t('contact') }}
                            </Link>
                        </nav>
                    </div>
                </div>
            </div>
        </footer>

        <div
            v-if="showCookiesBanner"
            class="fixed bottom-0 left-0 right-0 z-50 p-2 sm:p-4 transform transition-all duration-500 ease-in-out"
        >
            <div class="max-w-xs sm:max-w-md md:max-w-2xl lg:max-w-4xl mx-auto">
                <div class="backdrop-blur-xl border rounded-xl sm:rounded-2xl p-4 sm:p-6 shadow-2xl"
                     :style="cookiesBannerStyle">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                        <div class="flex-shrink-0 self-center sm:self-start">
                            <div
                                class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl flex items-center justify-center"
                                :style="cookieIconStyle">
                                <i class="fas fa-cookie-bite text-sm sm:text-lg text-white"></i>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <h3 class="text-base sm:text-lg font-bold mb-2 text-center sm:text-left"
                                :style="{ color: derivedColors.textPrimary }">
                                {{ t('cookiesBannerTitle') }}
                            </h3>
                            <p class="text-xs sm:text-sm leading-relaxed text-center sm:text-left"
                               :style="{ color: derivedColors.textSecondary }">
                                {{ t('cookiesBannerDescription') }}
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full sm:w-auto flex-shrink-0">
                            <button
                                @click="acceptCookies"
                                class="px-4 sm:px-6 py-2 sm:py-3 rounded-full font-semibold text-xs sm:text-sm transition-all duration-300 hover:scale-105 active:scale-95 order-2 sm:order-1 min-h-[44px] sm:min-h-0"
                                :style="acceptButtonStyle"
                            >
                                {{ t('acceptCookies') }}
                            </button>
                            <button
                                @click="declineCookies"
                                class="px-4 sm:px-6 py-2 sm:py-3 rounded-full font-semibold text-xs sm:text-sm border transition-all duration-300 hover:scale-105 active:scale-95 order-1 sm:order-2 min-h-[44px] sm:min-h-0"
                                :style="declineButtonStyle"
                            >
                                {{ t('declineCookies') }}
                            </button>
                        </div>
                    </div>

                    <div class="sm:hidden mt-4 pt-3 border-t border-opacity-20"
                         :style="{ borderColor: derivedColors.border }">
                        <div class="text-center">
                            <p class="text-xs mb-3" :style="{ color: derivedColors.textMuted }">
                                {{ t('cookiesBannerMobileNote') }}
                            </p>
                            <div class="flex gap-2">
                                <button
                                    @click="acceptCookies"
                                    class="flex-1 px-4 py-3 rounded-lg font-semibold text-sm transition-all duration-300 active:scale-95"
                                    :style="acceptButtonStyle"
                                >
                                    {{ t('accept') }}
                                </button>
                                <button
                                    @click="declineCookies"
                                    class="flex-1 px-4 py-3 rounded-lg font-semibold text-sm border transition-all duration-300 active:scale-95"
                                    :style="declineButtonStyle"
                                >
                                    {{ t('decline') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
