<template>
    <nav
        class="fixed top-0 w-full z-50 transition-all duration-300 ease-in-out"
        :style="navStyle"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div
                    class="flex items-center space-x-3 cursor-pointer relative overflow-hidden group transition-all duration-300"
                    :style="{ color: derivedColors.textPrimary }"
                >
                    <Link
                        href="/"
                        class="flex items-center space-x-3 relative overflow-hidden group transition-all duration-300"
                        :style="{ color: derivedColors.textPrimary }"
                    >
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-lg overflow-hidden">
                            <img
                                :src="logoError || !logoUrl ? defaultLogoUrl : logoUrl"
                                :alt="appName + ' Logo'"
                                class="w-full h-full object-cover"
                                @error="handleLogoError"
                            />
                        </div>
                        <span class="text-2xl font-bold relative z-10">{{ appName }}</span>
                        <div
                            class="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-500 bg-gradient-to-r from-transparent to-transparent"
                            :style="{ background: `linear-gradient(to right, transparent, ${derivedColors.textPrimary}20, transparent)` }"
                        ></div>
                    </Link>
                </div>

                <ul class="hidden lg:flex items-center space-x-8">
                    <li v-for="item in navigationItems" :key="item.id">
                        <Link
                            :href="`/page/${item.path}`"
                            class="font-medium text-sm transition-all duration-300 relative group py-2 uppercase"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            <span>{{ t(item.label) }}</span>
                            <span
                                v-if="item.badge"
                                class="ml-2 text-xs px-2 py-1 rounded-full"
                                :style="{
                                    backgroundColor: derivedColors.badge,
                                    color: derivedColors.textPrimary
                                }"
                            >
                                {{ t(item.badge) }}
                            </span>
                            <div
                                class="absolute bottom-0 left-0 w-0 h-0.5 group-hover:w-full transition-all duration-300"
                                :style="{ backgroundColor: primaryColor }"
                            ></div>
                        </Link>
                    </li>
                </ul>

                <div class="hidden lg:flex items-center space-x-4">
                    <div class="relative" ref="languageDropdown">
                        <button
                            @click="toggleLanguageDropdown"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg transition-all duration-300 border"
                            :style="languageButtonStyle"
                        >
                            <span class="text-lg">{{ currentLanguage.flag }}</span>
                            <span class="text-sm font-medium">{{ currentLanguage.code.toUpperCase() }}</span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-200" :class="{ 'rotate-180': showLanguageDropdown }"></i>
                        </button>

                        <div
                            v-show="showLanguageDropdown"
                            class="absolute top-full right-0 mt-2 w-48 backdrop-blur-xl border rounded-xl shadow-2xl z-50"
                            :style="dropdownStyle"
                        >
                            <div class="py-2">
                                <button
                                    v-for="lang in languages"
                                    :key="lang.code"
                                    @click="handleLanguageChange(lang.code)"
                                    class="w-full px-4 py-2 text-left flex items-center gap-3 transition-colors duration-200"
                                    :style="getLanguageItemStyle(lang.code)"
                                >
                                    <span class="text-lg">{{ lang.flag }}</span>
                                    <div>
                                        <div class="text-sm font-medium">{{ lang.name }}</div>
                                        <div class="text-xs" :style="{ color: derivedColors.textMuted }">{{ lang.nativeName }}</div>
                                    </div>
                                    <i v-if="currentLanguage.code === lang.code" class="fas fa-check text-xs ml-auto"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="handleAuthAction"
                        class="inline-flex items-center px-6 py-3 font-semibold text-sm rounded-full border transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 relative overflow-hidden group"
                        :style="ctaButtonStyle"
                    >
                        <span class="relative z-10">{{ authButtonText }}</span>
                        <div
                            class="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-500 bg-gradient-to-r from-transparent to-transparent"
                            :style="{ background: `linear-gradient(to right, transparent, ${derivedColors.textPrimary}20, transparent)` }"
                        ></div>
                    </button>
                </div>

                <button
                    @click="toggleMobileMenu"
                    class="lg:hidden p-2 transition-colors duration-300"
                    :style="{ color: mobileMenuOpen ? derivedColors.textSecondary : derivedColors.textPrimary }"
                >
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <div
                class="lg:hidden overflow-hidden transition-all duration-300 ease-in-out"
                :class="[
                    mobileMenuOpen
                        ? 'max-h-screen opacity-100 pb-6'
                        : 'max-h-0 opacity-0 pb-0'
                ]"
            >
                <div class="border-t pt-6 space-y-4" :style="{ borderColor: derivedColors.border }">
                    <Link
                        v-for="item in navigationItems"
                        :key="`mobile-${item.id}`"
                        :href="`/page/${item.path}`"
                        @click="closeMobileMenu"
                        class="flex items-center justify-between px-4 py-2 rounded-lg transition-all duration-300 uppercase"
                        :style="{ color: derivedColors.textSecondary }"
                    >
                        <span>{{ t(item.label) }}</span>
                        <span
                            v-if="item.badge"
                            class="text-xs px-2 py-1 rounded-full"
                            :style="{
                                backgroundColor: derivedColors.badge,
                                color: derivedColors.textPrimary
                            }"
                        >
                            {{ t(item.badge) }}
                        </span>
                    </Link>

                    <div class="border-t pt-4 space-y-3" :style="{ borderColor: derivedColors.borderDark }">
                        <div>
                            <label class="block text-xs mb-2" :style="{ color: derivedColors.textMuted }">{{ t('language') }}</label>
                            <select
                                v-model="currentLanguage.code"
                                @change="handleLanguageChange($event.target.value)"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none"
                                :style="mobileSelectStyle"
                            >
                                <option v-for="lang in languages" :key="lang.code" :value="lang.code">
                                    {{ lang.flag }} {{ lang.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <button
                        @click="handleAuthAction"
                        class="w-full px-4 py-3 font-semibold text-center rounded-lg transition-all duration-300"
                        :style="mobileCtaStyle"
                    >
                        {{ authButtonText }}
                    </button>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import {usePage, router, Link} from '@inertiajs/vue3'
import { useTranslation } from '@/composables/useTranslation'

const emit = defineEmits(['language-changed'])
const page = usePage()
const appName = computed(() => page.props.appName || '')
const logoUrl = computed(() => page.props.logoUrl || null)
const defaultLogoUrl = computed(() => page.props.defaultLogoUrl || 'default-logo.png')

const primaryColor = computed(() => page.props.primaryColor || '#1f2937')
const navigationItems = computed(() => page.props.navigationItems || [])

const auth = computed(() => page.props.auth || null)
const isAuthenticated = computed(() => auth.value && auth.value.user)
const authButtonText = computed(() => {
    if (isAuthenticated.value) {
        return t('dashboard')
    }
    return t('signIn')
})

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
        secondary: adjustColor(primary, 30),
        background: adjustColor(primary, -20),
        backgroundSecondary: primary,
        textPrimary: '#ffffff',
        textSecondary: '#d1d5db',
        textMuted: '#9ca3af',
        border: adjustColor(primary, 30),
        borderDark: adjustColor(primary, 50),
        badge: adjustColor(primary, 50)
    }
})

const { t, currentLanguage, languages, changeLanguage } = useTranslation()
const navStyle = computed(() => ({
    backgroundColor: isScrolled.value ? derivedColors.value.background + 'f2' : derivedColors.value.background + 'e6',
    borderBottomColor: derivedColors.value.border + '33',
    borderBottomWidth: '1px',
    backdropFilter: 'blur(12px)'
}))

const languageButtonStyle = computed(() => ({
    backgroundColor: derivedColors.value.secondary + '80',
    color: derivedColors.value.textSecondary,
    borderColor: derivedColors.value.border + '4d'
}))

const dropdownStyle = computed(() => ({
    backgroundColor: derivedColors.value.backgroundSecondary + 'f2',
    borderColor: derivedColors.value.border
}))

const ctaButtonStyle = computed(() => ({
    backgroundColor: derivedColors.value.secondary,
    color: derivedColors.value.textPrimary,
    borderColor: derivedColors.value.border + '4d'
}))

const mobileSelectStyle = computed(() => ({
    backgroundColor: derivedColors.value.backgroundSecondary,
    borderColor: derivedColors.value.border + '4d',
    color: derivedColors.value.textPrimary
}))

const mobileCtaStyle = computed(() => ({
    backgroundColor: derivedColors.value.secondary,
    color: derivedColors.value.textPrimary
}))

const handleLanguageChange = async (langCode) => {
    try {
        await changeLanguage(langCode)
        showLanguageDropdown.value = false
    } catch (error) {
        console.error('Failed to change language:', error)
    }
}

const getLanguageItemStyle = (langCode) => ({
    color: currentLanguage.value.code === langCode ? derivedColors.value.textPrimary : derivedColors.value.textSecondary,
    backgroundColor: currentLanguage.value.code === langCode ? derivedColors.value.secondary + '4d' : 'transparent'
})

const handleAuthAction = () => {
    if (isAuthenticated.value) {
        const userRole = auth.value.user.role
        if (userRole === 'admin') {
            router.visit('/admin/dashboard')
        } else {
            router.visit('/user/dashboard')
        }
    } else {
        router.visit('/login')
    }
}

const isScrolled = ref(false)
const mobileMenuOpen = ref(false)
const showLanguageDropdown = ref(false)
const logoError = ref(false)

const languageDropdown = ref(null)

const handleScroll = () => {
    isScrolled.value = window.scrollY > 100
}

const handleLogoError = () => {
    logoError.value = true
    if (logoUrl.value !== defaultLogoUrl.value) {
        console.warn('Logo failed to load, falling back to default logo')
    }
}

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value
    if (mobileMenuOpen.value) {
        showLanguageDropdown.value = false
    }
}

const toggleLanguageDropdown = () => {
    showLanguageDropdown.value = !showLanguageDropdown.value
}

const closeMobileMenu = () => {
    mobileMenuOpen.value = false
}

const handleClickOutside = (event) => {
    if (languageDropdown.value && !languageDropdown.value.contains(event.target)) {
        showLanguageDropdown.value = false
    }
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
* {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
}

.rotate-180 {
    transform: rotate(180deg);
}
</style>
