<template>
    <FrontendLayout>
        <section
            class="relative py-16 lg:py-24 overflow-hidden"
            id="cookies-policy"
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

            <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12 lg:mb-16 animate-fade-in-up">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                        <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                        <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('cookiesBadge') }}</span>
                    </div>

                    <h1 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                        <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                            {{ t('cookiesPageTitle') }}
                        </span>
                    </h1>

                    <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                        {{ t('cookiesPageSubtitle') }}
                    </p>

                    <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
                </div>

                <div class="backdrop-blur-xl border rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up animation-delay-300" :style="containerStyle">
                    <div class="h-1 bg-gradient-to-r bg-[length:200%_100%] animate-gradient-x" :style="headerBorderStyle"></div>

                    <div class="p-8 lg:p-12">
                        <div class="text-center mb-8 p-4 rounded-xl border" :style="updatedStyle">
                            <p class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                {{ t('lastUpdated') }}: <span :style="{ color: derivedColors.accent }">{{ t('cookiesLastUpdatedDate') }}</span>
                            </p>
                        </div>

                        <div class="space-y-12">
                            <div
                                v-for="(section, index) in cookieSections"
                                :key="section.id"
                                class="group cookie-section backdrop-blur-xl border rounded-2xl p-6 lg:p-8 transition-all duration-500 animate-fade-in-up relative overflow-hidden"
                                :class="`animation-delay-${index * 100}`"
                                :style="sectionCardStyle"
                            >
                                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 rounded-2xl" :style="{ background: `radial-gradient(circle at center, ${section.color}10, transparent 70%)` }"></div>
                                <div class="relative z-10 mb-6 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg transition-all duration-300 group-hover:scale-110"
                                            :style="{ background: `linear-gradient(135deg, ${section.color}, ${adjustColor(section.color, 30)})` }"
                                        >
                                            <i :class="section.icon"></i>
                                        </div>
                                        <div>
                                            <h2 class="text-2xl font-bold transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                                                {{ t(section.titleKey) }}
                                            </h2>
                                            <span class="text-sm px-3 py-1 rounded-full" :style="{
                                                backgroundColor: section.required ? derivedColors.success + '20' : derivedColors.accent + '20',
                                                color: section.required ? derivedColors.success : derivedColors.accent
                                            }">
                                                {{ section.required ? t('required') : t('optional') }}
                                            </span>
                                        </div>
                                    </div>

                                    <div v-if="!section.required" class="flex items-center">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="cookiePreferences[section.id]"
                                                @change="updateCookiePreference(section.id, $event.target.checked)"
                                                class="sr-only"
                                            >
                                            <div
                                                class="w-14 h-8 rounded-full transition-colors duration-200"
                                                :style="{
                                                    backgroundColor: cookiePreferences[section.id] ? derivedColors.accent : derivedColors.surface
                                                }"
                                            >
                                                <div
                                                    class="w-6 h-6 rounded-full bg-white transition-transform duration-200 mt-1"
                                                    :class="cookiePreferences[section.id] ? 'translate-x-7' : 'translate-x-1'"
                                                ></div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="relative z-10">
                                    <div class="space-y-4 mb-6">
                                        <p
                                            v-for="(paragraphKey, pIndex) in section.contentKeys"
                                            :key="pIndex"
                                            class="leading-relaxed transition-colors duration-300"
                                            :style="{ color: derivedColors.textSecondary }"
                                        >
                                            {{ t(paragraphKey) }}
                                        </p>
                                    </div>

                                    <div v-if="section.examples" class="mt-6">
                                        <h4 class="font-semibold mb-3" :style="{ color: derivedColors.textPrimary }">{{ t('examples') }}:</h4>
                                        <ul class="space-y-2">
                                            <li
                                                v-for="example in section.examples"
                                                :key="example"
                                                class="flex items-start gap-3"
                                            >
                                                <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0" :style="{ backgroundColor: section.color }"></div>
                                                <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">{{ t(example) }}</p>
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="section.retention" class="mt-6 p-4 rounded-xl border" :style="retentionStyle">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-clock text-sm mt-1" :style="{ color: derivedColors.accent }"></i>
                                            <div>
                                                <h4 class="font-semibold mb-1" :style="{ color: derivedColors.textPrimary }">{{ t('dataRetention') }}</h4>
                                                <p class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ t(section.retention) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12 p-6 rounded-2xl border" :style="manageCookiesStyle">
                            <h2 class="text-xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                                {{ t('managingCookiesTitle') }}
                            </h2>
                            <p class="mb-4" :style="{ color: derivedColors.textSecondary }">
                                {{ t('managingCookiesDescription') }}
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    v-for="browser in browserSettings"
                                    :key="browser.name"
                                    class="p-4 rounded-xl border transition-colors duration-300"
                                    :style="browserCardStyle"
                                >
                                    <div class="flex items-center gap-3 mb-2">
                                        <i :class="browser.icon" :style="{ color: derivedColors.accent }"></i>
                                        <h4 class="font-semibold" :style="{ color: derivedColors.textPrimary }">{{ browser.name }}</h4>
                                    </div>
                                    <p class="text-sm" :style="{ color: derivedColors.textSecondary }">{{ t(browser.instructionKey) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-16 animate-fade-in-up animation-delay-900">
                    <div class="backdrop-blur-xl border rounded-2xl p-8" :style="contactCardStyle">
                        <h3 class="text-2xl md:text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('cookiesContactTitle') }}
                        </h3>
                        <p class="mb-8 max-w-2xl mx-auto" :style="{ color: derivedColors.textSecondary }">
                            {{ t('cookiesContactDescription') }}
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <button
                                class="inline-flex items-center gap-2 px-8 py-3 font-semibold rounded-full transition-all duration-300 hover:scale-105 hover:shadow-lg"
                                :style="primaryButtonStyle"
                                @click="handleContactUs"
                            >
                                <i class="fas fa-envelope"></i>
                                {{ t('contactUs') }}
                            </button>
                            <button
                                class="inline-flex items-center gap-2 px-8 py-3 font-semibold rounded-full border transition-all duration-300 hover:scale-105 hover:shadow-lg"
                                :style="secondaryButtonStyle"
                                @click="handleViewPrivacy"
                            >
                                <i class="fas fa-user-shield"></i>
                                {{ t('viewPrivacy') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useTranslation } from '@/composables/useTranslation'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue";

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

const cookiePreferences = ref({
    essential: true,
    analytics: false,
    marketing: false,
    preferences: false
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

const updatedStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '20'
}))

const quickSettingsStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.accent + '30'
}))

const sectionCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '20'
}))

const retentionStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '15'
}))

const manageCookiesStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '30'
}))

const browserCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '20',
    borderColor: derivedColors.value.border + '15'
}))

const contactCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const acceptButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

const declineButtonStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '40',
    color: derivedColors.value.textSecondary
}))

const primaryButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

const secondaryButtonStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '40',
    color: derivedColors.value.textSecondary
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

// Cookie sections data
const cookieSections = ref([
    {
        id: 'essential',
        titleKey: 'essentialCookiesTitle',
        contentKeys: ['essentialCookiesContent1', 'essentialCookiesContent2'],
        icon: 'fas fa-shield-alt',
        color: '#10b981',
        required: true,
        examples: ['essentialExample1', 'essentialExample2', 'essentialExample3'],
        retention: 'essentialRetention'
    },
    {
        id: 'analytics',
        titleKey: 'analyticsCookiesTitle',
        contentKeys: ['analyticsCookiesContent1', 'analyticsCookiesContent2'],
        icon: 'fas fa-chart-bar',
        color: '#8b5cf6',
        required: false,
        examples: ['analyticsExample1', 'analyticsExample2', 'analyticsExample3'],
        retention: 'analyticsRetention'
    },
    {
        id: 'marketing',
        titleKey: 'marketingCookiesTitle',
        contentKeys: ['marketingCookiesContent1', 'marketingCookiesContent2'],
        icon: 'fas fa-bullhorn',
        color: '#f59e0b',
        required: false,
        examples: ['marketingExample1', 'marketingExample2', 'marketingExample3'],
        retention: 'marketingRetention'
    },
    {
        id: 'preferences',
        titleKey: 'preferencesCookiesTitle',
        contentKeys: ['preferencesCookiesContent1', 'preferencesCookiesContent2'],
        icon: 'fas fa-cog',
        color: '#06b6d4',
        required: false,
        examples: ['preferencesExample1', 'preferencesExample2', 'preferencesExample3'],
        retention: 'preferencesRetention'
    }
])

const browserSettings = ref([
    { name: 'Chrome', icon: 'fab fa-chrome', instructionKey: 'chromeInstructions' },
    { name: 'Firefox', icon: 'fab fa-firefox', instructionKey: 'firefoxInstructions' },
    { name: 'Safari', icon: 'fab fa-safari', instructionKey: 'safariInstructions' },
    { name: 'Edge', icon: 'fab fa-edge', instructionKey: 'edgeInstructions' }
])

const updateCookiePreference = (cookieId, enabled) => {
    if (cookieId !== 'essential') {
        cookiePreferences.value[cookieId] = enabled
    }
}

const saveCookiePreferences = () => {
    localStorage.setItem('cookie_consent', JSON.stringify({
        accepted: true,
        preferences: cookiePreferences.value,
        timestamp: Date.now()
    }))
}

const handleContactUs = () => {
    router.visit('/contact')
}

const handleViewPrivacy = () => {
    router.visit('/privacy')
}

onMounted(() => {
    const savedConsent = localStorage.getItem('cookie_consent')
    if (savedConsent) {
        const consent = JSON.parse(savedConsent)
        if (consent.preferences) {
            cookiePreferences.value = { ...consent.preferences }
        }
    }
})

const emit = defineEmits(['contact-clicked', 'privacy-clicked'])
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
.animation-delay-0 { animation-delay: 0s; }
.animation-delay-100 { animation-delay: 0.1s; }
.animation-delay-200 { animation-delay: 0.2s; }
.animation-delay-300 { animation-delay: 0.3s; }

/* Enhanced section card hover effects */
.cookie-section {
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.cookie-section:hover {
    box-shadow: 0 25px 50px -12px var(--hover-shadow);
}

/* Smooth transitions */
.cookie-section * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
