<template>
    <FrontendLayout>
        <section
            class="relative py-16 lg:py-24 overflow-hidden"
            id="privacy-policy"
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
                        <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('privacyBadge') }}</span>
                    </div>

                    <h1 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                        <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                            {{ t('privacyTitle') }}
                        </span>
                    </h1>

                    <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                        {{ t('privacySubtitle') }}
                    </p>

                    <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
                </div>

                <div class="backdrop-blur-xl border rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up animation-delay-300" :style="containerStyle">
                    <div class="h-1 bg-gradient-to-r bg-[length:200%_100%] animate-gradient-x" :style="headerBorderStyle"></div>

                    <div class="p-8 lg:p-12">
                        <div class="text-center mb-8 p-4 rounded-xl border" :style="updatedStyle">
                            <p class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                {{ t('lastUpdated') }}: <span :style="{ color: derivedColors.accent }">{{ t('privacyLastUpdatedDate') }}</span>
                            </p>
                        </div>

                        <div class="space-y-12">
                            <div
                                v-for="(section, index) in privacySections"
                                :key="section.id"
                                class="group privacy-section backdrop-blur-xl border rounded-2xl p-6 lg:p-8 transition-all duration-500 animate-fade-in-up relative overflow-hidden"
                                :style="sectionCardStyle"
                            >
                                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 rounded-2xl" :style="{ background: `radial-gradient(circle at center, ${derivedColors.accent}10, transparent 70%)` }"></div>
                                <div class="relative z-10 mb-6">
                                    <div
                                        class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg transition-all duration-300 group-hover:scale-110"
                                        :style="{ background: `linear-gradient(135deg, ${section.color}, ${adjustColor(section.color, 30)})` }"
                                    >
                                        <i :class="section.icon"></i>
                                    </div>
                                </div>

                                <div class="relative z-10">
                                    <h2 class="text-2xl font-bold mb-6 transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                                        {{ t(section.titleKey) }}
                                    </h2>

                                    <div class="space-y-4">
                                        <p
                                            v-for="(paragraphKey, pIndex) in section.contentKeys"
                                            :key="pIndex"
                                            class="leading-relaxed transition-colors duration-300"
                                            :style="{ color: derivedColors.textSecondary }"
                                        >
                                            {{ t(paragraphKey) }}
                                        </p>
                                    </div>

                                    <div v-if="section.subSections" class="mt-6 space-y-4">
                                        <div
                                            v-for="subSection in section.subSections"
                                            :key="subSection.id"
                                            class="p-4 rounded-xl border transition-colors duration-300"
                                            :style="subSectionStyle"
                                        >
                                            <h3 class="font-bold mb-2" :style="{ color: derivedColors.textPrimary }">{{ t(subSection.titleKey) }}</h3>
                                            <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">{{ t(subSection.contentKey) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-16 animate-fade-in-up animation-delay-900">
                    <div class="backdrop-blur-xl border rounded-2xl p-8" :style="contactCardStyle">
                        <h3 class="text-2xl md:text-3xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('privacyContactTitle') }}
                        </h3>
                        <p class="mb-8 max-w-2xl mx-auto" :style="{ color: derivedColors.textSecondary }">
                            {{ t('privacyContactDescription') }}
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
                                @click="handleViewTerms"
                            >
                                <i class="fas fa-file-contract"></i>
                                {{ t('viewTerms') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
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

const sectionCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '20'
}))

const subSectionStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '15'
}))

const contactCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
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

const privacySections = ref([
    {
        id: 'information-collection',
        titleKey: 'informationCollectionTitle',
        contentKeys: ['informationCollectionContent1', 'informationCollectionContent2'],
        icon: 'fas fa-database',
        color: '#8b5cf6',
        subSections: [
            {
                id: 'personal-info',
                titleKey: 'personalInfoTitle',
                contentKey: 'personalInfoContent'
            },
            {
                id: 'usage-data',
                titleKey: 'usageDataTitle',
                contentKey: 'usageDataContent'
            }
        ]
    },
    {
        id: 'data-usage',
        titleKey: 'dataUsageTitle',
        contentKeys: ['dataUsageContent1', 'dataUsageContent2'],
        icon: 'fas fa-cogs',
        color: '#10b981'
    },
    {
        id: 'data-protection',
        titleKey: 'dataProtectionTitle',
        contentKeys: ['dataProtectionContent1', 'dataProtectionContent2'],
        icon: 'fas fa-shield-alt',
        color: '#f59e0b'
    },
    {
        id: 'cookies',
        titleKey: 'cookiesTitle',
        contentKeys: ['cookiesContent1', 'cookiesContent2'],
        icon: 'fas fa-cookie-bite',
        color: '#ef4444'
    },
    {
        id: 'third-party',
        titleKey: 'thirdPartyTitle',
        contentKeys: ['thirdPartyContent1', 'thirdPartyContent2'],
        icon: 'fas fa-link',
        color: '#06b6d4'
    },
    {
        id: 'user-rights',
        titleKey: 'userRightsTitle',
        contentKeys: ['userRightsContent1', 'userRightsContent2'],
        icon: 'fas fa-user-shield',
        color: '#8b5cf6',
        subSections: [
            {
                id: 'access-right',
                titleKey: 'accessRightTitle',
                contentKey: 'accessRightContent'
            },
            {
                id: 'deletion-right',
                titleKey: 'deletionRightTitle',
                contentKey: 'deletionRightContent'
            }
        ]
    },
    {
        id: 'policy-updates',
        titleKey: 'policyUpdatesTitle',
        contentKeys: ['policyUpdatesContent1', 'policyUpdatesContent2'],
        icon: 'fas fa-sync-alt',
        color: '#10b981'
    }
])

const handleContactUs = () => {
    router.visit('/contact')
}

const handleViewTerms = () => {
    router.visit('/terms')
}

const emit = defineEmits(['contact-clicked', 'terms-clicked'])
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

/* Enhanced section card hover effects */
.privacy-section {
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.privacy-section:hover {
    box-shadow: 0 25px 50px -12px var(--hover-shadow);
}

/* Smooth transitions */
.privacy-section * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
