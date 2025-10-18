<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useToast } from '@/composables/useToast'
import { useTranslation } from '@/composables/useTranslation'
import FrontendLayout from "@/Layouts/FrontendLayout/FrontendLayout.vue"

const props = defineProps({
    appName: {
        type: String,
        default: 'MineInvest'
    },
    referralCode: {
        type: String,
        default: null
    },
    registrationEnabled: {
        type: Boolean,
        default: true
    }
})

const referralCode = ref(null);

const page = usePage()
const { success, error, warning, info } = useToast()
const { t } = useTranslation()

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
const currentView = ref('login')
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const resetToken = ref(null)
const resetEmail = ref(null)

const loginForm = useForm({
    email: '',
    password: '',
    remember: false
})

const registerForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    ref: props.referralCode,
})

const forgotForm = useForm({
    email: ''
})

const resetPasswordForm = useForm({
    token: '',
    email: '',
    password: '',
    password_confirmation: ''
})

const getViewTitle = () => {
    const titles = {
        'login': t('welcomeBack'),
        'register': t('createNewAccount'),
        'forgot': t('resetPassword'),
        'reset': t('resetPassword') || 'Reset Password'
    }
    return titles[currentView.value] || t('authenticationRequired')
}

const getViewDescription = () => {
    const descriptions = {
        'login': t('loginToAccount'),
        'register': t('createYourAccount'),
        'forgot': t('enterEmailForReset'),
        'reset': t('enterNewPassword') || 'Enter your new password'
    }
    return descriptions[currentView.value] || t('authenticationRequired')
}

const setCurrentView = (view) => {
    currentView.value = view
    loginForm.reset()
    registerForm.reset()
    forgotForm.reset()
    if (view !== 'reset') {
        resetPasswordForm.reset()
    }
}

const getTabStyle = (tab) => {
    const isActive = currentView.value === tab
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

const handleLogin = () => {
    loginForm.post('/login', {
        onSuccess: () => {
            loginForm.reset()
        },
        onError: (errors) => {
        }
    })
}

const handleRegister = () => {
    if (props.referralCode || referralCode.value) {
        registerForm.ref = props.referralCode || referralCode.value;
    }
    registerForm.post('/register', {
        onSuccess: () => {
            registerForm.reset()
        },
        onError: (errors) => {

        },
    })
}

const handleForgotPassword = () => {
    forgotForm.post('/forgot-password', {
        onSuccess: () => {
            forgotForm.reset()
        },
        onError: (errors) => {
        }
    })
}

const handleResetPassword = () => {
    resetPasswordForm.post('/reset-password', {
        onSuccess: () => {
            resetPasswordForm.reset()
            success(t('passwordResetSuccess') || 'Password reset successfully! You can now login with your new password.')
            setCurrentView('login')
        },
        onError: (errors) => {
            console.error('Reset password errors:', errors)
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

const handleFlashMessages = () => {
    const flashData = page.props.flash || {}

    if (flashData.success) {
        success(flashData.success)
    }

    if (flashData.error) {
        error(flashData.error)
    }

    if (flashData.warning) {
        warning(flashData.warning)
    }

    if (flashData.info) {
        info(flashData.info)
    }
}

const checkForResetToken = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const email = urlParams.get('email');

    if (token && email) {
        resetToken.value = token;
        resetEmail.value = email;
        resetPasswordForm.token = token;
        resetPasswordForm.email = email;
        currentView.value = 'reset';

        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
}

watch(() => page.props.flash, () => {
    handleFlashMessages()
}, { deep: true })

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const refCode = urlParams.get('ref');

    if (refCode) {
        referralCode.value = refCode;
        registerForm.ref = refCode;
    } else if (props.referralCode) {
        referralCode.value = props.referralCode;
        registerForm.ref = props.referralCode;
    }

    checkForResetToken();

    createModernParticles()
    handleFlashMessages()
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

            <div class="relative z-10 w-full max-w-sm sm:max-w-md lg:max-w-lg mx-auto mt-16 sm:mt-20">
                <div class="text-center mb-6 sm:mb-8 animate-fade-in-up">
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black mb-2 sm:mb-3 leading-tight">
                        <span :style="{ background: gradientText, WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }">
                            {{ appName }}
                        </span>
                    </h1>
                    <p class="text-sm sm:text-base lg:text-lg font-medium" :style="{ color: derivedColors.textSecondary }">
                        {{ t('authSubtitle') }}
                    </p>
                    <div class="mt-3 sm:mt-4 w-12 sm:w-16 h-1 rounded-full mx-auto" :style="underlineStyle"></div>
                </div>

                <div
                    class="backdrop-blur-xl border rounded-2xl sm:rounded-3xl p-6 sm:p-8 shadow-2xl animate-fade-in-up animation-delay-300"
                    :style="cardStyle"
                >
                    <div class="text-center mb-6 sm:mb-8">
                        <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-2 sm:mb-3" :style="{ color: derivedColors.textPrimary }">
                            {{ getViewTitle() }}
                        </h2>
                        <p class="text-sm sm:text-base" :style="{ color: derivedColors.textSecondary }">
                            {{ getViewDescription() }}
                        </p>
                    </div>

                    <!-- Tab Navigation - Hide for reset password view -->
                    <div v-if="currentView !== 'reset' && currentView !== 'forgot'" class="mb-6 sm:mb-8">
                        <div
                            class="flex rounded-xl sm:rounded-2xl p-1 sm:p-1.5 border shadow-inner"
                            :style="tabContainerStyle"
                        >
                            <button
                                :class="[
                                    'flex-1 py-3 sm:py-4 px-3 sm:px-6 text-sm sm:text-base font-semibold rounded-lg sm:rounded-xl transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-opacity-50 min-w-0',
                                    currentView === 'login' ? 'shadow-lg transform scale-105' : 'hover:opacity-80'
                                ]"
                                :style="getTabStyle('login')"
                                @click="setCurrentView('login')"
                            >
                                <span class="truncate">{{ t('signIn') || 'LOGIN' }}</span>
                            </button>
                            <button
                                :class="[
                                    'flex-1 py-3 sm:py-4 px-3 sm:px-6 text-sm sm:text-base font-semibold rounded-lg sm:rounded-xl transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-opacity-50 min-w-0',
                                    currentView === 'register' ? 'shadow-lg transform scale-105' : 'hover:opacity-80'
                                ]"
                                :style="getTabStyle('register')"
                                @click="setCurrentView('register')"
                            >
                                <span class="truncate">{{ t('signUp') || 'Create Account' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Login Form -->
                    <form
                        v-if="currentView === 'login'"
                        class="space-y-4 sm:space-y-6"
                        @submit.prevent="handleLogin"
                    >
                        <div class="space-y-4 sm:space-y-5">
                            <div>
                                <label class="block text-sm font-semibold mb-2 sm:mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('email') || 'Email Address' }}
                                </label>
                                <input
                                    v-model="loginForm.email"
                                    type="email"
                                    required
                                    class="w-full px-4 sm:px-5 py-3 sm:py-4 rounded-lg sm:rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300 text-sm sm:text-base"
                                    :style="getInputStyle(loginForm.errors.email)"
                                    :placeholder="t('emailPlaceholder') || 'Enter your email'"
                                >
                                <div v-if="loginForm.errors.email" class="mt-2 text-sm text-red-400">
                                    {{ loginForm.errors.email }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 sm:mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('password') || 'Password' }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="loginForm.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        class="w-full px-4 sm:px-5 py-3 sm:py-4 pr-12 sm:pr-14 rounded-lg sm:rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300 text-sm sm:text-base"
                                        :style="getInputStyle(loginForm.errors.password)"
                                        :placeholder="t('passwordPlaceholder') || 'Enter your password'"
                                    >
                                    <button
                                        type="button"
                                        class="absolute right-3 sm:right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-all duration-300 hover:scale-110"
                                        @click="showPassword = !showPassword"
                                    >
                                        <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                    </button>
                                </div>
                                <div v-if="loginForm.errors.password" class="mt-2 text-sm text-red-400">
                                    {{ loginForm.errors.password }}
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                            <label class="flex items-center">
                                <input
                                    v-model="loginForm.remember"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="ml-2 sm:ml-3 text-sm font-medium" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('rememberMe') || 'Remember me' }}
                                </span>
                            </label>
                            <button
                                type="button"
                                class="text-sm font-semibold hover:opacity-80 transition-all duration-300 hover:scale-105 text-left sm:text-right"
                                :style="{ color: derivedColors.accent }"
                                @click="setCurrentView('forgot')"
                            >
                                {{ t('forgotPassword') || 'Forgot Password?' }}
                            </button>
                        </div>

                        <button
                            type="submit"
                            :disabled="loginForm.processing"
                            class="w-full py-3 sm:py-4 font-bold text-sm sm:text-lg rounded-lg sm:rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 disabled:opacity-50 disabled:transform-none"
                            :style="primaryButtonStyle"
                        >
                            <span v-if="loginForm.processing" class="flex items-center justify-center gap-2 sm:gap-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                {{ t('signingIn') || 'Signing In...' }}
                            </span>
                            <span v-else class="flex items-center justify-center gap-2 sm:gap-3">
                                <i class="fas fa-sign-in-alt"></i>
                                {{ t('signIn') || 'Sign In' }}
                            </span>
                        </button>
                    </form>

                    <!-- Register Form -->
                    <form
                        v-else-if="currentView === 'register'"
                        class="space-y-6"
                        @submit.prevent="handleRegister"
                    >
                        <div v-if="referralCode" class="mb-6 p-4 rounded-xl border-2 border-green-400/30 bg-green-400/10">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-gift text-green-400 text-xl"></i>
                                <div>
                                    <p class="font-semibold text-green-400">{{ t('referralBonus') || 'Referral Bonus!' }}</p>
                                    <p class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                        {{ t('referredBy') || 'You were referred by code:' }} <strong>{{ referralCode }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('fullName') }}
                                </label>
                                <input
                                    v-model="registerForm.name"
                                    type="text"
                                    required
                                    class="w-full px-5 py-4 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                    :style="getInputStyle(registerForm.errors.name)"
                                    :placeholder="t('namePlaceholder')"
                                >
                                <div v-if="registerForm.errors.name" class="mt-2 text-sm text-red-400">
                                    {{ registerForm.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('email') }}
                                </label>
                                <input
                                    v-model="registerForm.email"
                                    type="email"
                                    required
                                    class="w-full px-5 py-4 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                    :style="getInputStyle(registerForm.errors.email)"
                                    :placeholder="t('emailPlaceholder')"
                                >
                                <div v-if="registerForm.errors.email" class="mt-2 text-sm text-red-400">
                                    {{ registerForm.errors.email }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('password') }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="registerForm.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        class="w-full px-5 py-4 pr-14 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                        :style="getInputStyle(registerForm.errors.password)"
                                        :placeholder="t('passwordPlaceholder')"
                                    >
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-all duration-300 hover:scale-110"
                                        @click="showPassword = !showPassword"
                                    >
                                        <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                    </button>
                                </div>
                                <div v-if="registerForm.errors.password" class="mt-2 text-sm text-red-400">
                                    {{ registerForm.errors.password }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('confirmPassword') }}
                                </label>
                                <input
                                    v-model="registerForm.password_confirmation"
                                    type="password"
                                    required
                                    class="w-full px-5 py-4 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                    :style="getInputStyle(registerForm.errors.password_confirmation)"
                                    :placeholder="t('confirmPasswordPlaceholder')"
                                >
                                <div v-if="registerForm.errors.password_confirmation" class="mt-2 text-sm text-red-400">
                                    {{ registerForm.errors.password_confirmation }}
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="registerForm.processing"
                            class="w-full py-4 font-bold text-lg rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 disabled:opacity-50 disabled:transform-none"
                            :style="primaryButtonStyle"
                        >
                            <span v-if="registerForm.processing" class="flex items-center justify-center gap-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                {{ t('creatingAccount') }}
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <i class="fas fa-user-plus"></i>
                                {{ t('createAccount') }}
                            </span>
                        </button>
                    </form>

                    <!-- Forgot Password Form -->
                    <form
                        v-else-if="currentView === 'forgot'"
                        class="space-y-6"
                        @submit.prevent="handleForgotPassword"
                    >
                        <div>
                            <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                {{ t('email') }}
                            </label>
                            <input
                                v-model="forgotForm.email"
                                type="email"
                                required
                                class="w-full px-5 py-4 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                :style="getInputStyle(forgotForm.errors.email)"
                                :placeholder="t('emailPlaceholder')"
                            >
                            <div v-if="forgotForm.errors.email" class="mt-2 text-sm text-red-400">
                                {{ forgotForm.errors.email }}
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="forgotForm.processing"
                            class="w-full py-4 font-bold text-lg rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 disabled:opacity-50 disabled:transform-none"
                            :style="primaryButtonStyle"
                        >
                            <span v-if="forgotForm.processing" class="flex items-center justify-center gap-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                {{ t('sendingResetLink') }}
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <i class="fas fa-paper-plane"></i>
                                {{ t('sendResetLink') }}
                            </span>
                        </button>

                        <div class="text-center">
                            <button
                                type="button"
                                class="text-sm font-semibold hover:opacity-80 transition-all duration-300 hover:scale-105"
                                :style="{ color: derivedColors.accent }"
                                @click="setCurrentView('login')"
                            >
                                {{ t('backToSignIn') }}
                            </button>
                        </div>
                    </form>

                    <!-- Reset Password Form -->
                    <form
                        v-else-if="currentView === 'reset'"
                        class="space-y-6"
                        @submit.prevent="handleResetPassword"
                    >
                        <!-- Show email being reset -->
                        <div class="mb-6 p-4 rounded-xl border border-blue-400/30 bg-blue-400/10">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-info-circle text-blue-400 text-xl"></i>
                                <div>
                                    <p class="font-semibold text-blue-400">{{ t('resetPasswordFor') || 'Reset Password' }}</p>
                                    <p class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                        {{ t('resettingPasswordFor') || 'Resetting password for:' }} <strong>{{ resetEmail }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('newPassword') || 'New Password' }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="resetPasswordForm.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        class="w-full px-5 py-4 pr-14 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                        :style="getInputStyle(resetPasswordForm.errors.password)"
                                        :placeholder="t('enterNewPassword') || 'Enter your new password'"
                                    >
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-all duration-300 hover:scale-110"
                                        @click="showPassword = !showPassword"
                                    >
                                        <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                    </button>
                                </div>
                                <div v-if="resetPasswordForm.errors.password" class="mt-2 text-sm text-red-400">
                                    {{ resetPasswordForm.errors.password }}
                                </div>
                                <!-- Password requirements -->
                                <div class="mt-2 text-xs" :style="{ color: derivedColors.textMuted }">
                                    {{ t('passwordRequirements') || 'Password must contain uppercase, lowercase, number, and special character' }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-3" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('confirmNewPassword') || 'Confirm New Password' }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="resetPasswordForm.password_confirmation"
                                        :type="showConfirmPassword ? 'text' : 'password'"
                                        required
                                        class="w-full px-5 py-4 pr-14 rounded-xl border focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                                        :style="getInputStyle(resetPasswordForm.errors.password_confirmation)"
                                        :placeholder="t('confirmNewPassword') || 'Confirm your new password'"
                                    >
                                    <button
                                        type="button"
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 transition-all duration-300 hover:scale-110"
                                        @click="showConfirmPassword = !showConfirmPassword"
                                    >
                                        <i :class="showConfirmPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                                    </button>
                                </div>
                                <div v-if="resetPasswordForm.errors.password_confirmation" class="mt-2 text-sm text-red-400">
                                    {{ resetPasswordForm.errors.password_confirmation }}
                                </div>
                            </div>
                        </div>

                        <!-- Display general errors -->
                        <div v-if="resetPasswordForm.errors.email || resetPasswordForm.errors.token" class="text-center">
                            <div class="text-sm text-red-400">
                                {{ resetPasswordForm.errors.email || resetPasswordForm.errors.token }}
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="resetPasswordForm.processing"
                            class="w-full py-4 font-bold text-lg rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 disabled:opacity-50 disabled:transform-none"
                            :style="primaryButtonStyle"
                        >
                            <span v-if="resetPasswordForm.processing" class="flex items-center justify-center gap-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                {{ t('resettingPassword') || 'Resetting Password...' }}
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <i class="fas fa-key"></i>
                                {{ t('resetPassword') || 'Reset Password' }}
                            </span>
                        </button>

                        <div class="text-center">
                            <button
                                type="button"
                                class="text-sm font-semibold hover:opacity-80 transition-all duration-300 hover:scale-105"
                                :style="{ color: derivedColors.accent }"
                                @click="setCurrentView('login')"
                            >
                                {{ t('backToSignIn') || 'Back to Sign In' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-6 sm:mt-8 text-center animate-fade-in-up animation-delay-600">
                    <div class="flex items-center justify-center gap-2 sm:gap-3 text-xs sm:text-sm backdrop-blur-xl border rounded-xl sm:rounded-2xl p-3 sm:p-4" :style="securityNoticeStyle">
                        <i class="fas fa-shield-alt text-green-400 text-sm sm:text-base"></i>
                        <span :style="{ color: derivedColors.textSecondary }">{{ t('securityNotice') || 'Your data is secure with us' }}</span>
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

@media (prefers-contrast: high) {
    .border {
        border-width: 2px;
    }
}

@media (max-width: 640px) {
    .backdrop-blur-xl {
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
    }
}

button:focus-visible,
input:focus-visible {
    outline: 2px solid rgba(59, 130, 246, 0.6);
    outline-offset: 2px;
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
