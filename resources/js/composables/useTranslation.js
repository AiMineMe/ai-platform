import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const languages = ref([
    {
        code: 'en',
        name: 'English',
        nativeName: 'English',
        flag: ''
    },
])

const translations = ref({})
const currentLanguage = ref({
    code: 'en',
    name: 'English',
    nativeName: 'English',
    flag: ''
})

const isChangingLanguage = ref(false)
const isInitialized = ref(false)

const getSavedLanguage = () => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('app-language')
        return saved || 'en'
    }
    return 'en'
}

const loadStaticTranslations = async () => {
    try {
        const en = await import('@/lang/en.json')

        translations.value = {
            en: en.default || en,
        }
    } catch (error) {
    }
}

const loadLanguagesFromDatabase = async () => {
    try {
        const response = await fetch('/api/languages', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })

        if (response.ok) {
            const dbLanguages = await response.json()

            if (dbLanguages && dbLanguages.length > 0) {
                languages.value = dbLanguages.map(lang => ({
                    code: lang.code,
                    name: lang.name,
                    nativeName: lang.nativeName,
                    flag: lang.flag
                }))
            }
        }
    } catch (error) {
    }
}

const loadTranslationsFromDatabase = async (langCode) => {
    try {
        const response = await fetch(`/api/languages/${langCode}/translations`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })

        if (response.ok) {
            const dbTranslations = await response.json()

            if (dbTranslations && Object.keys(dbTranslations).length > 0) {
                translations.value[langCode] = {
                    ...translations.value[langCode],
                    ...dbTranslations
                }
            }
        }
    } catch (error) {
    }
}

const initializeLanguage = async () => {
    const page = usePage()
    if (page.props.languages && page.props.languages.length > 0) {
        languages.value = page.props.languages
    } else {
        await loadLanguagesFromDatabase()
    }

    if (page.props.currentLanguage) {
        currentLanguage.value = {
            code: page.props.currentLanguage.code,
            name: page.props.currentLanguage.name,
            nativeName: page.props.currentLanguage.native_name,
            flag: page.props.currentLanguage.flag
        }
    } else {
        const savedLang = getSavedLanguage()
        const lang = languages.value.find(l => l.code === savedLang) || languages.value[0]
        if (lang) {
            currentLanguage.value = { ...lang }
        }
    }

    await loadStaticTranslations()
    if (page.props.translations && Object.keys(page.props.translations).length > 0) {
        translations.value[currentLanguage.value.code] = {
            ...translations.value[currentLanguage.value.code],
            ...page.props.translations
        }
    } else {
        await loadTranslationsFromDatabase(currentLanguage.value.code)
    }

    updateDocumentLanguage(currentLanguage.value.code)
    isInitialized.value = true
}

const updateDocumentLanguage = (langCode) => {
    if (typeof document !== 'undefined') {
        document.documentElement.lang = langCode
    }
}

const t = (key, params = {}) => {
    const page = usePage()
    if (page.props.translations && page.props.translations[key]) {
        const translation = page.props.translations[key]
        if (typeof translation === 'string' && Object.keys(params).length > 0) {
            return translation.replace(/\{(\w+)\}/g, (match, param) => {
                return params[param] || match
            })
        }
        return translation
    }

    const translation = translations.value[currentLanguage.value.code]?.[key] ||
        translations.value.en?.[key] ||
        key

    if (typeof translation === 'string' && Object.keys(params).length > 0) {
        return translation.replace(/\{(\w+)\}/g, (match, param) => {
            return params[param] || match
        })
    }

    return translation
}

const changeLanguage = async (langCode) => {
    if (isChangingLanguage.value) return

    const newLang = languages.value.find(lang => lang.code === langCode)
    if (!newLang) return

    isChangingLanguage.value = true

    try {
        await loadTranslationsFromDatabase(langCode)
        currentLanguage.value = { ...newLang }
        if (typeof window !== 'undefined') {
            localStorage.setItem('app-language', langCode)
        }

        updateDocumentLanguage(langCode)
        router.post('/language/change', {
            language: langCode
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
            },
            onFinish: () => {
                isChangingLanguage.value = false
            }
        })

    } catch (error) {
        isChangingLanguage.value = false
    }
}

export const useTranslation = () => {
    const page = usePage()

    if (!isInitialized.value) {
        initializeLanguage()
    }

    if (page.props.translations &&
        Object.keys(page.props.translations).length > 0 &&
        (!translations.value[currentLanguage.value.code] ||
            Object.keys(translations.value[currentLanguage.value.code] || {}).length === 0)) {
        initializeLanguage()
    }

    return {
        t,
        currentLanguage,
        languages,
        changeLanguage,
        getLanguages: () => languages.value,
        getCurrentLanguage: () => currentLanguage.value,
        initializeLanguage,
        isChangingLanguage: computed(() => isChangingLanguage.value)
    }
}
