<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useForm, Link, usePage } from '@inertiajs/vue3'
import { useTranslation } from '@/composables/useTranslation'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue"

const { t } = useTranslation()
const props = defineProps({
    appName: {
        type: String,
        default: 'MineInvest'
    },
    errors: {
        type: Object,
        default: () => ({})
    }
})

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
    background: `radial-gradient(ellipse at top, ${derivedColors.value.primary}50 0%, ${derivedColors.value.background} 50%, ${derivedColors.value.background} 100%)`
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

const underlineStyle = computed(() => ({
    background: `linear-gradient(90deg, ${derivedColors.value.accent}, ${derivedColors.value.secondary})`
}))

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + 'e6',
    borderColor: derivedColors.value.border + '40',
    boxShadow: `0 25px 50px -12px ${derivedColors.value.primary}40`
}))

const tabContainerStyle = computed(() => ({
    backgroundColor: derivedColors.value.background + '60',
    borderColor: derivedColors.value.border + '30'
}))

const primaryButtonStyle = computed(() => ({
    background: `linear-gradient(135deg, ${derivedColors.value.accent} 0%, ${derivedColors.value.secondary} 100%)`,
    color: derivedColors.value.textPrimary,
    boxShadow: `0 20px 40px -12px ${derivedColors.value.accent}60`
}))

const securityNoticeStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '40',
    borderColor: derivedColors.value.border + '30'
}))

const particlesContainer = ref(null)
const codeInput = ref(null)
const showRecoveryForm = ref(false)
const form = useForm({
    code: ''
})

const recoveryForm = useForm({
    code: ''
})
const isFormValid = computed(() => {
    return form.code.length === 6 && /^\d{6}$/.test(form.code)
})
const getViewTitle = () => {
    return showRecoveryForm.value ? t('recoveryCodeTitle') : t('authenticatorTitle')
}
const getViewDescription = () => {
    return showRecoveryForm.value ? t('recoveryCodeDescription') : t('authenticatorDescription')
}

const setCurrentView = (view) => {
    showRecoveryForm.value = view === 'recovery'
    form.reset()
    recoveryForm.reset()

    nextTick(() => {
        if (showRecoveryForm.value) {
            document.getElementById('recovery-code')?.focus()
        } else {
            codeInput.value?.focus()
        }
    })
}

const getTabStyle = (tab) => {
    const isActive = (tab === 'auth' && !showRecoveryForm.value) || (tab === 'recovery' && showRecoveryForm.value)
    return {
        backgroundColor: isActive ? derivedColors.value.accent : 'transparent',
        color: isActive ? derivedColors.value.textPrimary : derivedColors.value.textSecondary,
        boxShadow: isActive ? `0 8px 30px -12px ${derivedColors.value.accent}60` : 'none'
    }
}

const getInputStyle = (error) => {
    return {
        backgroundColor: derivedColors.value.surface + '60',
        borderColor: error ? derivedColors.value.error : derivedColors.value.border + '50',
        color: derivedColors.value.textPrimary,
        focusRingColor: derivedColors.value.accent + '50'
    }
}

const handleCodeInput = (event) => {
    const value = event.target.value.replace(/\D/g, '')
    form.code = value.slice(0, 6)
}

const handleRecoveryInput = (event) => {
    recoveryForm.code = event.target.value.trim()
}

const submit = () => {
    if (!isFormValid.value) return

    form.post('/auth/2fa/verify', {
        onError: () => {
            nextTick(() => codeInput.value?.focus())
        }
    })
}

const submitRecovery = () => {
    if (!recoveryForm.code) return

    recoveryForm.post('/auth/2fa/recovery', {
        onError: () => {
            nextTick(() => document.getElementById('recovery-code')?.focus())
        }
    })
}

const createModernParticles = () => {
    if (!particlesContainer.value) return

    const particleCount = 30

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div')
        particle.className = 'absolute rounded-full opacity-0 animate-ping'

        const size = Math.random() * 4 + 2
        particle.style.width = size + 'px'
        particle.style.height = size + 'px'
        particle.style.backgroundColor = derivedColors.value.accent
        particle.style.left = Math.random() * 100 + '%'
        particle.style.top = Math.random() * 100 + '%'
        particle.style.animationDelay = Math.random() * 15 + 's'
        particle.style.animationDuration = (Math.random() * 10 + 8) + 's'

        particlesContainer.value.appendChild(particle)
    }
}

onMounted(() => {
    createModernParticles()
    nextTick(() => codeInput.value?.focus())
})
</script>

<template>
    <FrontendLayout>
        <div
            class="min-h-screen flex items-center justify-center px-4 py-8 relative overflow-hidden"
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
                    class="absolute top-20 left-20 w-96 h-96 rounded-full blur-3xl animate-blob"
                    :style="blob1Style"
                ></div>
                <div
                    class="absolute bottom-20 right-20 w-80 h-80 rounded-full blur-3xl animate-blob animation-delay-2000"
                    :style="blob2Style"
                ></div>
                <div
                    class="absolute top-1/2 left-1/2 w-64 h-64 rounded-full blur-3xl animate-blob animation-delay-4000"
                    :style="blob3Style"
                ></div>
            </div>

            <div class="absolute inset-0" ref="particlesContainer"></div>

            <div class="relative z-10 w-full max-w-lg mt-20">
                <div class="text-center mb-8 animate-fade-in-up">
                    <h1 class="text-4xl lg:text-5xl font-black mb-3 leading-tight">
                        <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                            {{ appName }}
                        </span>
                    </h1>
                    <p class="text-base lg:text-lg font-medium" :style="{ color: derivedColors.textSecondary }">
                        {{ t('twoFactorSubtitle') }}
                    </p>
                    <div class="mt-4 w-16 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
                </div>

                <div
                    class="backdrop-blur-xl border rounded-3xl p-8 shadow-2xl animate-fade-in-up animation-delay-300"
                    :style="cardStyle"
                >
                    <div class="text-center mb-8">
                        <h2 class="text-2xl lg:text-3xl font-bold mb-3" :style="{ color: derivedColors.textPrimary }">
                            {{ getViewTitle() }}
                        </h2>
                        <p class="text-base" :style="{ color: derivedColors.textSecondary }">
                            {{ getViewDescription() }}
                        </p>
                    </div>

                    <div class="mb-8">
                        <div
                            class="flex rounded-2xl p-1.5 border shadow-inner"
                            :style="tabContainerStyle"
                        >
                            <button
                                :class="[
                                    'flex-1 py-4 px-6 text-base font-semibold rounded-xl transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-opacity-50',
                                    !showRecoveryForm ? 'shadow-lg transform scale-105' : 'hover:opacity-80'
                                ]"
                                :style="getTabStyle('auth')"
                                @click="setCurrentView('auth')"
                            >
                                {{ t('authenticatorCode') }}
                            </button>
                            <button
                                :class="[
                                    'flex-1 py-4 px-6 text-base font-semibold rounded-xl transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-opacity-50',
                                    showRecoveryForm ? 'shadow-lg transform scale-105' : 'hover:opacity-80'
                                ]"
                                :style="getTabStyle('recovery')"
                                @click="setCurrentView('recovery')"
                            >
                                {{ t('recoveryCode') }}
                            </button>
                        </div>
                    </div>

                    <form
                        v-if="!showRecoveryForm"
                        class="space-y-6"
                        @submit.prevent="submit"
                    >
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('authenticationCode') }}
                                </label>
                                <input
                                    ref="codeInput"
                                    v-model="form.code"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="6"
                                    required
                                    autocomplete="one-time-code"
                                    class="w-full px-5 py-4 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300 text-center tracking-widest font-mono text-lg"
                                    :style="getInputStyle(errors.code)"
                                    :placeholder="t('codePlaceholder')"
                                    :disabled="form.processing"
                                    @input="handleCodeInput"
                                >
                                <div v-if="errors.code" class="mt-2 text-sm text-red-400">
                                    {{ errors.code }}
                                </div>
                                <div v-else-if="form.code.length > 0" class="mt-2 text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ form.code.length }}/6 {{ t('digitsEntered') }}
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing || !isFormValid"
                            class="w-full py-4 font-bold text-lg rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 disabled:opacity-50 disabled:transform-none"
                            :style="primaryButtonStyle"
                        >
                            <span v-if="form.processing" class="flex items-center justify-center gap-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                {{ t('verifying') }}
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <i class="fas fa-shield-alt"></i>
                                {{ t('verifyCode') }}
                            </span>
                        </button>
                    </form>

                    <form
                        v-else
                        class="space-y-6"
                        @submit.prevent="submitRecovery"
                    >
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('recoveryCode') }}
                                </label>
                                <input
                                    id="recovery-code"
                                    v-model="recoveryForm.code"
                                    type="text"
                                    required
                                    autocomplete="one-time-code"
                                    class="w-full px-5 py-4 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300 font-mono"
                                    :style="getInputStyle(recoveryForm.errors.code)"
                                    :placeholder="t('recoveryCodePlaceholder')"
                                    :disabled="recoveryForm.processing"
                                    @input="handleRecoveryInput"
                                >
                                <div v-if="recoveryForm.errors.code" class="mt-2 text-sm text-red-400">
                                    {{ recoveryForm.errors.code }}
                                </div>
                                <div v-else class="mt-2 text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ t('recoveryCodeHelp') }}
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="recoveryForm.processing || !recoveryForm.code"
                            class="w-full py-4 font-bold text-lg rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 disabled:opacity-50 disabled:transform-none"
                            :style="primaryButtonStyle"
                        >
                            <span v-if="recoveryForm.processing" class="flex items-center justify-center gap-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                {{ t('verifying') }}
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <i class="fas fa-key"></i>
                                {{ t('useRecoveryCode') }}
                            </span>
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <Link
                            href="/logout"
                            method="post"
                            class="text-sm font-semibold hover:opacity-80 transition-all duration-300 hover:scale-105"
                            :style="{ color: derivedColors.accent }"
                        >
                            {{ t('signOut') }}
                        </Link>
                    </div>
                </div>

                <div class="mt-8 text-center animate-fade-in-up animation-delay-600">
                    <div class="flex items-center justify-center gap-3 text-sm backdrop-blur-xl border rounded-2xl p-4" :style="securityNoticeStyle">
                        <i class="fas fa-shield-alt text-green-400"></i>
                        <span :style="{ color: derivedColors.textSecondary }">{{ t('securityNotice') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </FrontendLayout>
</template>

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
    animation: fade-in-up 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-blob {
    animation: blob 20s infinite;
}

.animation-delay-300 { animation-delay: 0.3s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-2000 { animation-delay: 2s; }
.animation-delay-4000 { animation-delay: 4s; }

* {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

input:focus {
    outline: none;
    ring: 2px;
    ring-opacity: 60%;
    transform: translateY(-1px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

button:not(:disabled):hover {
    transform: translateY(-2px);
}

.backdrop-blur-xl {
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

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
</style>
