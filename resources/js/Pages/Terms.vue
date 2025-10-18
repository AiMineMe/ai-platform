<template>
    <FrontendLayout>
        <section
            class="relative py-16 lg:py-24 overflow-hidden"
            id="terms-of-service"
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
                        <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('termsBadge') }}</span>
                    </div>

                    <h1 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                        <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                            {{ t('termsTitle') }}
                        </span>
                    </h1>

                    <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                        {{ t('termsSubtitle') }}
                    </p>

                    <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
                </div>

                <div class="backdrop-blur-xl border rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up animation-delay-300" :style="containerStyle">
                    <div class="h-1 bg-gradient-to-r bg-[length:200%_100%] animate-gradient-x" :style="headerBorderStyle"></div>

                    <div class="p-8 lg:p-12">
                        <div class="text-center mb-8 p-4 rounded-xl border" :style="updatedStyle">
                            <p class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                {{ t('lastUpdated') }}: <span :style="{ color: derivedColors.accent }">{{ t('termsLastUpdatedDate') }}</span>
                            </p>
                        </div>

                        <div class="mb-8 flex flex-wrap justify-center gap-2">
                            <button
                                v-for="(section, index) in termsSections"
                                :key="section.id"
                                @click="scrollToSection(section.id)"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all duration-300 hover:scale-105"
                                :style="navigationButtonStyle"
                            >
                                {{ t(section.titleKey) }}
                            </button>
                        </div>

                        <div class="space-y-12">
                            <div
                                v-for="(section, index) in termsSections"
                                :key="section.id"
                                :id="section.id"
                                class="group terms-section backdrop-blur-xl border rounded-2xl p-6 lg:p-8 transition-all duration-500 animate-fade-in-up relative overflow-hidden"
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

                                    <div v-if="section.listItems" class="mt-6">
                                        <ul class="space-y-3">
                                            <li
                                                v-for="listItem in section.listItems"
                                                :key="listItem"
                                                class="flex items-start gap-3"
                                            >
                                                <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0" :style="{ backgroundColor: section.color }"></div>
                                                <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">{{ t(listItem) }}</p>
                                            </li>
                                        </ul>
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

                                    <div v-if="section.important" class="mt-6 p-4 rounded-xl border-2" :style="importantNoticeStyle">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-exclamation-triangle text-lg mt-1" :style="{ color: derivedColors.warning }"></i>
                                            <div>
                                                <h4 class="font-bold mb-2" :style="{ color: derivedColors.warning }">{{ t('importantNotice') }}</h4>
                                                <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">{{ t(section.important) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

const navigationButtonStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '30',
    color: derivedColors.value.textSecondary
}))

const sectionCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '20'
}))

const subSectionStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '15'
}))

const importantNoticeStyle = computed(() => ({
    backgroundColor: derivedColors.value.warning + '10',
    borderColor: derivedColors.value.warning + '40'
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

const termsSections = ref([
    {
        id: 'acceptance',
        titleKey: 'acceptanceTitle',
        contentKeys: ['acceptanceContent1', 'acceptanceContent2'],
        icon: 'fas fa-handshake',
        color: '#8b5cf6',
        important: 'acceptanceImportant'
    },
    {
        id: 'services',
        titleKey: 'servicesTitle',
        contentKeys: ['servicesContent1', 'servicesContent2'],
        icon: 'fas fa-cogs',
        color: '#10b981',
        listItems: ['serviceItem1', 'serviceItem2', 'serviceItem3', 'serviceItem4']
    },
    {
        id: 'user-accounts',
        titleKey: 'userAccountsTitle',
        contentKeys: ['userAccountsContent1', 'userAccountsContent2'],
        icon: 'fas fa-user-circle',
        color: '#f59e0b',
        subSections: [
            {
                id: 'account-responsibility',
                titleKey: 'accountResponsibilityTitle',
                contentKey: 'accountResponsibilityContent'
            },
            {
                id: 'account-security',
                titleKey: 'accountSecurityTitle',
                contentKey: 'accountSecurityContent'
            }
        ]
    },
    {
        id: 'prohibited-activities',
        titleKey: 'prohibitedActivitiesTitle',
        contentKeys: ['prohibitedActivitiesContent1', 'prohibitedActivitiesContent2'],
        icon: 'fas fa-ban',
        color: '#ef4444',
        listItems: ['prohibitedItem1', 'prohibitedItem2', 'prohibitedItem3', 'prohibitedItem4', 'prohibitedItem5'],
        important: 'prohibitedImportant'
    },
    {
        id: 'payments-fees',
        titleKey: 'paymentsFeesTitle',
        contentKeys: ['paymentsFeesContent1', 'paymentsFeesContent2'],
        icon: 'fas fa-credit-card',
        color: '#06b6d4',
        subSections: [
            {
                id: 'fee-structure',
                titleKey: 'feeStructureTitle',
                contentKey: 'feeStructureContent'
            },
            {
                id: 'refund-policy',
                titleKey: 'refundPolicyTitle',
                contentKey: 'refundPolicyContent'
            }
        ]
    },
    {
        id: 'intellectual-property',
        titleKey: 'intellectualPropertyTitle',
        contentKeys: ['intellectualPropertyContent1', 'intellectualPropertyContent2'],
        icon: 'fas fa-copyright',
        color: '#8b5cf6'
    },
    {
        id: 'limitation-liability',
        titleKey: 'limitationLiabilityTitle',
        contentKeys: ['limitationLiabilityContent1', 'limitationLiabilityContent2'],
        icon: 'fas fa-balance-scale',
        color: '#10b981',
        important: 'limitationImportant'
    },
    {
        id: 'termination',
        titleKey: 'terminationTitle',
        contentKeys: ['terminationContent1', 'terminationContent2'],
        icon: 'fas fa-times-circle',
        color: '#ef4444',
        listItems: ['terminationReason1', 'terminationReason2', 'terminationReason3']
    },
    {
        id: 'governing-law',
        titleKey: 'governingLawTitle',
        contentKeys: ['governingLawContent1', 'governingLawContent2'],
        icon: 'fas fa-gavel',
        color: '#f59e0b'
    },
    {
        id: 'changes-terms',
        titleKey: 'changesTermsTitle',
        contentKeys: ['changesTermsContent1', 'changesTermsContent2'],
        icon: 'fas fa-edit',
        color: '#06b6d4'
    }
])

const scrollToSection = (sectionId) => {
    const element = document.getElementById(sectionId)
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        })
    }
}

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

.terms-section {
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.terms-section:hover {
    box-shadow: 0 25px 50px -12px var(--hover-shadow);
}

.terms-section * {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
