<template>
    <FrontendLayout>
        <section
            class="relative py-16 lg:py-24 overflow-hidden"
            id="contact-us"
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
                        <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">{{ t('contactBadge') }}</span>
                    </div>

                    <h1 class="text-4xl lg:text-5xl xl:text-6xl font-black mb-6 leading-tight">
                    <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                        {{ t('contactTitle') }}
                    </span>
                    </h1>

                    <p class="text-lg lg:text-xl max-w-4xl mx-auto leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                        {{ t('contactSubtitle') }}
                    </p>

                    <div class="mt-6 w-20 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
                    <div class="backdrop-blur-xl border rounded-3xl overflow-hidden shadow-2xl animate-fade-in-up animation-delay-300" :style="containerStyle">
                        <div class="h-1 bg-gradient-to-r bg-[length:200%_100%] animate-gradient-x" :style="headerBorderStyle"></div>

                        <div class="p-8 lg:p-10">
                            <div class="mb-8">
                                <h2 class="text-2xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                                    {{ t('sendMessageTitle') }}
                                </h2>
                                <p class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('sendMessageDescription') }}
                                </p>
                            </div>

                            <form @submit.prevent="submitForm" class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('fullName') }} <span :style="{ color: derivedColors.error }">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        class="w-full px-4 py-3 rounded-xl border backdrop-blur-xl transition-all duration-300 focus:outline-none focus:ring-2"
                                        :style="inputStyle"
                                        :placeholder="t('fullNamePlaceholder')"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('emailAddress') }} <span :style="{ color: derivedColors.error }">*</span>
                                    </label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        required
                                        class="w-full px-4 py-3 rounded-xl border backdrop-blur-xl transition-all duration-300 focus:outline-none focus:ring-2"
                                        :style="inputStyle"
                                        :placeholder="t('emailPlaceholder')"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('subject') }} <span :style="{ color: derivedColors.error }">*</span>
                                    </label>
                                    <input
                                        v-model="form.subject"
                                        type="text"
                                        required
                                        class="w-full px-4 py-3 rounded-xl border backdrop-blur-xl transition-all duration-300 focus:outline-none focus:ring-2"
                                        :style="inputStyle"
                                        :placeholder="t('subjectPlaceholder')"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('message') }} <span :style="{ color: derivedColors.error }">*</span>
                                    </label>
                                    <textarea
                                        v-model="form.message"
                                        rows="5"
                                        required
                                        class="w-full px-4 py-3 rounded-xl border backdrop-blur-xl transition-all duration-300 focus:outline-none focus:ring-2 resize-none"
                                        :style="inputStyle"
                                        :placeholder="t('messagePlaceholder')"
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="w-full px-8 py-4 rounded-xl font-semibold transition-all duration-300 hover:scale-105 hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                    :style="submitButtonStyle"
                                >
                                <span v-if="!isSubmitting" class="flex items-center justify-center gap-2">
                                    <i class="fas fa-paper-plane"></i>
                                    {{ t('sendMessage') }}
                                </span>
                                    <span v-else class="flex items-center justify-center gap-2">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    {{ t('sending') }}
                                </span>
                                </button>
                            </form>

                            <div v-if="showSuccess" class="mt-6 p-4 rounded-xl border animate-fade-in-up" :style="successMessageStyle">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-check-circle animate-bounce" :style="{ color: derivedColors.success }"></i>
                                    <p class="text-sm" :style="{ color: derivedColors.success }">{{ t('messageSuccess') }}</p>
                                </div>
                            </div>

                            <div v-if="showError" class="mt-6 p-4 rounded-xl border animate-fade-in-up" :style="errorMessageStyle">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-exclamation-circle animate-shake" :style="{ color: derivedColors.error }"></i>
                                    <p class="text-sm" :style="{ color: derivedColors.error }">{{ errorMessage }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-8 animate-fade-in-up animation-delay-600">
                        <div
                            v-for="(contact, index) in contactMethods"
                            :key="contact.id"
                            class="group backdrop-blur-xl border rounded-2xl p-6 transition-all duration-500 hover:-translate-y-2 hover:scale-105 relative overflow-hidden animate-slide-in-right"
                            :class="`animation-delay-${index * 200}`"
                            :style="contactCardStyle"
                        >
                            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 rounded-2xl" :style="{ background: `radial-gradient(circle at center, ${contact.color}10, transparent 70%)` }"></div>
                            <div class="relative z-10 mb-4">
                                <div
                                    class="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:rotate-12"
                                    :style="{ background: `linear-gradient(135deg, ${contact.color}, ${adjustColor(contact.color, 30)})` }"
                                >
                                    <i :class="contact.icon"></i>
                                </div>
                            </div>

                            <div class="relative z-10">
                                <h3 class="text-xl font-bold mb-2" :style="{ color: derivedColors.textPrimary }">
                                    {{ t(contact.titleKey) }}
                                </h3>
                                <p class="text-sm mb-4" :style="{ color: derivedColors.textSecondary }">
                                    {{ t(contact.descriptionKey) }}
                                </p>
                                <div class="space-y-2">
                                    <div
                                        v-for="detail in contact.details"
                                        :key="detail.type"
                                        class="flex items-center gap-3 group-hover:translate-x-1 transition-transform duration-300"
                                    >
                                        <i :class="detail.icon" class="text-xs" :style="{ color: contact.color }"></i>
                                        <span class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ detail.value }}
                                    </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-full animate-fade-in-up animation-delay-900">
                    <div class="backdrop-blur-xl border rounded-3xl p-8 lg:p-10 relative overflow-hidden" :style="faqCardStyle">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r bg-[length:200%_100%] animate-gradient-x" :style="headerBorderStyle"></div>
                        <div class="text-center mb-12">
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border backdrop-blur-xl mb-6" :style="badgeStyle">
                                <div class="w-2 h-2 rounded-full animate-pulse" :style="{ backgroundColor: derivedColors.accent }"></div>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.textSecondary }">FAQ</span>
                            </div>

                            <h3 class="text-3xl lg:text-4xl font-bold mb-4" :style="{ color: derivedColors.textPrimary }">
                                {{ t('frequentlyAskedTitle') }}
                            </h3>

                            <p class="text-lg max-w-3xl mx-auto" :style="{ color: derivedColors.textSecondary }">
                                {{ t('frequentlyAskedSubTitle') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div
                                v-for="(faq, index) in frequentQuestions"
                                :key="faq.id"
                                class="group backdrop-blur-xl border rounded-2xl overflow-hidden transition-all duration-500 hover:scale-105 hover:-translate-y-2 animate-fade-in-up cursor-pointer"
                                :class="`animation-delay-${index * 150}`"
                                :style="faqItemStyle"
                                @click="toggleFaq(faq.id)"
                            >
                                <div class="p-6 relative">
                                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 rounded-2xl" :style="{ background: `radial-gradient(circle at center, ${derivedColors.accent}10, transparent 70%)` }"></div>

                                    <div class="relative z-10 flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-sm font-bold transition-all duration-300 group-hover:scale-110" :style="{ background: `linear-gradient(135deg, ${derivedColors.accent}, ${derivedColors.secondary})` }">
                                                {{ index + 1 }}
                                            </div>
                                            <h4 class="font-semibold text-lg group-hover:text-white transition-colors duration-300" :style="{ color: derivedColors.textPrimary }">
                                                {{ t(faq.questionKey) }}
                                            </h4>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center transition-all duration-300 group-hover:scale-110" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                                <i
                                                    class="fas fa-chevron-down text-sm transition-all duration-300"
                                                    :class="{ 'rotate-180 text-white': openFaq === faq.id }"
                                                    :style="{ color: openFaq === faq.id ? derivedColors.accent : derivedColors.textSecondary }"
                                                ></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="overflow-hidden transition-all duration-500"
                                    :class="{ 'max-h-0': openFaq !== faq.id, 'max-h-96': openFaq === faq.id }"
                                >
                                    <div class="px-6 pb-6 pt-0">
                                        <div class="border-t pt-4 transition-all duration-500 animate-fade-in-up" :style="{ borderColor: derivedColors.border + '20' }">
                                            <p class="text-sm leading-relaxed" :style="{ color: derivedColors.textSecondary }">
                                                {{ t(faq.answerKey) }}
                                            </p>
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
import { useToast } from '@/composables/useToast'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue";

const { t } = useTranslation()
const { showToast } = useToast()
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

const form = ref({
    name: '',
    email: '',
    subject: '',
    message: ''
})

const isSubmitting = ref(false)
const showSuccess = ref(false)
const showError = ref(false)
const errorMessage = ref('')
const openFaq = ref(null)
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

const inputStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '30',
    color: derivedColors.value.textPrimary,
    '--focus-ring-color': derivedColors.value.accent
}))

const submitButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary
}))

const successMessageStyle = computed(() => ({
    backgroundColor: derivedColors.value.success + '10',
    borderColor: derivedColors.value.success + '30'
}))

const errorMessageStyle = computed(() => ({
    backgroundColor: derivedColors.value.error + '10',
    borderColor: derivedColors.value.error + '30'
}))

const contactCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '20'
}))

const faqCardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '60',
    borderColor: derivedColors.value.border + '40'
}))

const faqItemStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border + '20'
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

const contactMethods = computed(() => [
    {
        id: 'email',
        titleKey: 'emailSupportTitle',
        descriptionKey: 'emailSupportDescription',
        icon: t('emailIcon'),
        color: t('emailColor'),
        details: [
            { type: 'primary', icon: t('emailDetailIcon1'), value: t('emailDetailValue1') },
            { type: 'response', icon: t('emailDetailIcon2'), value: t('emailDetailValue2') }
        ]
    },
    {
        id: 'live-chat',
        titleKey: 'liveChatTitle',
        descriptionKey: 'liveChatDescription',
        icon: t('liveChatIcon'),
        color: t('liveChatColor'),
        details: [
            { type: 'availability', icon: t('liveChatDetailIcon1'), value: t('liveChatDetailValue1') },
            { type: 'language', icon: t('liveChatDetailIcon2'), value: t('liveChatDetailValue2') }
        ]
    },
    {
        id: 'phone',
        titleKey: 'phoneTitle',
        descriptionKey: 'phoneDescription',
        icon: t('phoneIcon'),
        color: t('phoneColor'),
        details: [
            { type: 'number', icon: t('phoneDetailIcon1'), value: t('phoneDetailValue1') },
            { type: 'hours', icon: t('phoneDetailIcon2'), value: t('phoneDetailValue2') }
        ]
    }
])

const frequentQuestions = ref([
    { id: 1, questionKey: 'faqQuestion1', answerKey: 'faqAnswer1' },
    { id: 2, questionKey: 'faqQuestion2', answerKey: 'faqAnswer2' },
    { id: 3, questionKey: 'faqQuestion3', answerKey: 'faqAnswer3' },
    { id: 4, questionKey: 'faqQuestion4', answerKey: 'faqAnswer4' },
    { id: 5, questionKey: 'faqQuestion5', answerKey: 'faqAnswer5' },
    { id: 6, questionKey: 'faqQuestion6', answerKey: 'faqAnswer6' }
])

const toggleFaq = (faqId) => {
    openFaq.value = openFaq.value === faqId ? null : faqId
}

const submitForm = async () => {
    isSubmitting.value = true
    showSuccess.value = false
    showError.value = false
    errorMessage.value = ''

    try {
        const response = await fetch('/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify(form.value)
        })

        const data = await response.json()

        if (data.success) {
            showToast(data.message, 'success')
            form.value = {
                name: '',
                email: '',
                subject: '',
                message: ''
            }

            showSuccess.value = true
            setTimeout(() => {
                showSuccess.value = false
            }, 5000)
        } else {
            showToast(data.message, 'error')
            errorMessage.value = data.message
            showError.value = true
            setTimeout(() => {
                showError.value = false
            }, 5000)
        }
    } catch (error) {
        console.error('Contact form error:', error)
        const message = 'Something went wrong. Please try again later.'
        showToast(message, 'error')
        errorMessage.value = message
        showError.value = true

        setTimeout(() => {
            showError.value = false
        }, 5000)
    } finally {
        isSubmitting.value = false
    }
}

const emit = defineEmits(['form-submitted'])
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

@keyframes slide-in-right {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        opacity: 1;
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        opacity: 1;
        transform: scale(1);
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

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
    20%, 40%, 60%, 80% { transform: translateX(2px); }
}

.animate-gradient-x {
    animation: gradient-x 3s ease infinite;
}

.animate-fade-in-up {
    animation: fade-in-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-in-right {
    animation: slide-in-right 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-bounce-in {
    animation: bounce-in 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
}

.animate-blob {
    animation: blob 20s infinite;
}

.animate-shake {
    animation: shake 0.5s ease-in-out;
}

.animation-delay-150 { animation-delay: 0.15s; }
.animation-delay-200 { animation-delay: 0.2s; }
.animation-delay-300 { animation-delay: 0.3s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-900 { animation-delay: 0.9s; }
.animation-delay-1200 { animation-delay: 1.2s; }
.animation-delay-2000 { animation-delay: 2s; }
.animation-delay-0 { animation-delay: 0s; }
.animation-delay-100 { animation-delay: 0.1s; }
.animation-delay-400 { animation-delay: 0.4s; }
.animation-delay-500 { animation-delay: 0.5s; }

/* FAQ specific animations */
.max-h-0 {
    max-height: 0;
    opacity: 0;
    transform: translateY(-10px);
}

.max-h-96 {
    max-height: 24rem;
    opacity: 1;
    transform: translateY(0);
}

input:focus, select:focus, textarea:focus {
    ring: 2px;
    ring-color: var(--focus-ring-color);
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

* {
    transition-property: color, background-color, border-color, opacity, transform, max-height;
    transition-duration: 0.3s;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}

.group:hover .group-hover\:rotate-12 {
    transform: rotate(12deg);
}

.group:hover .group-hover\:translate-x-1 {
    transform: translateX(0.25rem);
}

textarea::-webkit-scrollbar {
    width: 6px;
}

textarea::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

textarea::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

textarea::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

@media (max-width: 768px) {
    .animate-fade-in-up {
        animation-delay: 0.1s;
    }

    .animate-slide-in-right {
        animation: fade-in-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
}

.loading {
    position: relative;
    overflow: hidden;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: loading-shimmer 1.5s infinite;
}

@keyframes loading-shimmer {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}
</style>

