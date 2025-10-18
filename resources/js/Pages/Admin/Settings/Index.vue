<script setup>
import {ref, reactive, onMounted, watch} from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue"
import { useToast } from "@/composables/useToast.js"

const props = defineProps({
    generalSettings: { type: Object, default: () => ({}) },
    emailSettings: { type: Object, default: () => ({}) },
    smsSettings: { type: Object, default: () => ({}) },
    securitySettings: { type: Object, default: () => ({}) },
    seoSettings: { type: Object, default: () => ({}) },
    emailTemplates: { type: Array, default: () => [] },
    smsTemplates: { type: Array, default: () => [] },
    site_logo_url: { type: String, default: null },
    site_favicon_url: { type: String, default: null },
    seo_og_image_url: { type: String, default: null }
})

const pageProps = usePage()
const { success, error, info } = useToast()
const activeTab = ref('general')
const tabs = [
    { key: 'general', name: 'General', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>' },
    { key: 'email', name: 'Email', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>' },
    { key: 'sms', name: 'SMS', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>' },
    { key: 'security', name: 'Security', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>' },
    { key: 'seo', name: 'SEO', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>' }
]

const generalLoading = ref(false)
const emailLoading = ref(false)
const smsLoading = ref(false)
const securityLoading = ref(false)
const seoLoading = ref(false)
const templateLoading = ref(false)
const smsTemplateLoading = ref(false)
const testEmailLoading = ref(false)
const testSmsLoading = ref(false)
const generalErrors = ref({})
const seoErrors = ref({})
const templateErrors = ref({})
const smsTemplateErrors = ref({})
const showEmailModal = ref(false)
const showSmsModal = ref(false)
const showTestEmailModal = ref(false)
const showTestSmsModal = ref(false)
const editingEmailTemplate = ref({})
const editingSmsTemplate = ref({})
const testEmailForm = reactive({
    email: '',
    message: 'This is a test email from your application settings.'
})

const testSmsForm = reactive({
    phone: '',
    message: 'This is a test SMS from your application settings.'
})
const emailTemplates = ref([...props.emailTemplates])
const smsTemplates = ref([...props.smsTemplates])
const flash = ref(pageProps.props.flash)
const generalForm = reactive({
    site_name: props.generalSettings.site_name || 'TradeMine',
    site_logo: null,
    site_favicon: null,
    primary_color: props.generalSettings.primary_color || '#1f2937',
    user_registration: props.generalSettings.user_registration !== false,
    balance_transfer_charge: parseFloat(props.generalSettings.balance_transfer_charge) || 1,
    default_currency: props.generalSettings.default_currency || 'BDT',
    currency_symbol: props.generalSettings.currency_symbol || '৳',
    default_timezone: props.generalSettings.default_timezone || 'Asia/Dhaka',
    referral_deposit_commission_rate: parseFloat(props.generalSettings.referral_deposit_commission_rate) || 10,
    mining_fee: parseFloat(props.generalSettings.mining_fee) || 10,
    mining_rate_multiplier: parseFloat(props.generalSettings.mining_rate_multiplier) || 1,
    tawk_property_id: props.generalSettings.tawk_property_id || '',
    tawk_widget_id: props.generalSettings.tawk_widget_id || ''
})

const emailForm = reactive({
    mail_driver: props.emailSettings.mail_driver || 'smtp',
    mail_host: props.emailSettings.mail_host || '',
    mail_port: props.emailSettings.mail_port || 587,
    mail_username: props.emailSettings.mail_username || '',
    mail_password: props.emailSettings.mail_password || '',
    mail_encryption: props.emailSettings.mail_encryption || 'tls',
    mail_from_address: props.emailSettings.mail_from_address || '',
    mail_from_name: props.emailSettings.mail_from_name || ''
})

const smsForm = reactive({
    sms_driver: props.smsSettings.sms_driver || 'twilio',
    sms_api_key: props.smsSettings.sms_api_key || '',
    sms_api_secret: props.smsSettings.sms_api_secret || '',
    sms_from: props.smsSettings.sms_from || ''
})

const securityForm = reactive({
    two_factor_auth: Boolean(props.securitySettings.two_factor_auth),
    login_attempts: parseInt(props.securitySettings.login_attempts) || 5,
    session_timeout: parseInt(props.securitySettings.session_timeout) || 60,
    password_min_length: parseInt(props.securitySettings.password_min_length) || 8,
    require_email_verification: Boolean(props.securitySettings.require_email_verification),
    kyc_status: Boolean(props.securitySettings.kyc_status)

})

const seoForm = reactive({
    meta_title: props.seoSettings.meta_title || 'TradeMine - Multi-Feature Crypto Platform with Gamified Mining & trade Trading',
    meta_description: props.seoSettings.meta_description || 'TradeMine - Multi-Feature Crypto Platform with Gamified Mining & trade Trading.',
    meta_keywords: props.seoSettings.meta_keywords || 'cryptocurrency, trading, bitcoin, ethereum, crypto exchange, digital assets',
    og_title: props.seoSettings.og_title || 'TradeMine - Advanced Cryptocurrency Trading Platform',
    og_description: props.seoSettings.og_description || 'Trade cryptocurrencies with TradeMine - the most advanced and secure crypto trading platform.',
    og_image: null,
    twitter_card: props.seoSettings.twitter_card || 'summary_large_image',
    google_analytics: props.seoSettings.google_analytics || '',
    google_tag_manager: props.seoSettings.google_tag_manager || '',
    facebook_pixel: props.seoSettings.facebook_pixel || ''
})

const getErrorMessage = (err) => {
    return Array.isArray(err) ? err[0] : err
}

const clearErrors = (errorRef) => {
    errorRef.value = {}
}

const updateGeneral = () => {
    generalLoading.value = true
    clearErrors(generalErrors)

    const formData = new FormData()
    Object.keys(generalForm).forEach(key => {
        if (generalForm[key] !== null) {
            formData.append(key, generalForm[key])
        }
    })
    formData.append('_method', 'PATCH')

    router.post('/admin/settings/general', formData, {
        onSuccess: () => {
            generalLoading.value = false
            success('General settings updated successfully')
        },
        onError: (errors) => {
            generalLoading.value = false
            generalErrors.value = errors

            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const updateEmail = () => {
    emailLoading.value = true

    router.patch('/admin/settings/email', emailForm, {
        onSuccess: () => {
            emailLoading.value = false
            success('Email settings updated successfully')
        },
        onError: (errors) => {
            emailLoading.value = false
            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const updateSms = () => {
    smsLoading.value = true

    router.patch('/admin/settings/sms', smsForm, {
        onSuccess: () => {
            smsLoading.value = false
            success('SMS settings updated successfully')
        },
        onError: (errors) => {
            smsLoading.value = false
            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const updateSecurity = () => {
    securityLoading.value = true

    router.patch('/admin/settings/security', securityForm, {
        onSuccess: () => {
            securityLoading.value = false
            success('Security settings updated successfully')
        },
        onError: (errors) => {
            securityLoading.value = false
            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const updateSeo = () => {
    seoLoading.value = true
    clearErrors(seoErrors)

    const formData = new FormData()
    Object.keys(seoForm).forEach(key => {
        if (seoForm[key] !== null) {
            formData.append(key, seoForm[key])
        }
    })
    formData.append('_method', 'PATCH')

    router.post('/admin/settings/seo', formData, {
        onSuccess: () => {
            seoLoading.value = false
            success('SEO settings updated successfully')
        },
        onError: (errors) => {
            seoLoading.value = false
            seoErrors.value = errors
            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const handleLogoChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        generalForm.site_logo = file
    }
}

const handleFaviconChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        generalForm.site_favicon = file
    }
}

const handleOgImageChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        seoForm.og_image = file
    }
}

const testEmail = () => {
    testEmailForm.email = ''
    testEmailForm.message = 'This is a test email from your application settings.'
    showTestEmailModal.value = true
}

const closeTestEmailModal = () => {
    showTestEmailModal.value = false
    testEmailForm.email = ''
    testEmailForm.message = 'This is a test email from your application settings.'
}

const sendTestEmail = () => {
    testEmailLoading.value = true

    router.post('/admin/settings/test-email', testEmailForm, {
        onSuccess: () => {
            testEmailLoading.value = false
            closeTestEmailModal()
            success('Test email sent successfully')
        },
        onError: (errors) => {
            testEmailLoading.value = false

            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }

        }
    })
}

const testSms = () => {
    testSmsForm.phone = ''
    testSmsForm.message = 'This is a test SMS from your application settings.'
    showTestSmsModal.value = true
}

const closeTestSmsModal = () => {
    showTestSmsModal.value = false
    testSmsForm.phone = ''
    testSmsForm.message = 'This is a test SMS from your application settings.'
}

const sendTestSms = () => {
    testSmsLoading.value = true

    router.post('/admin/settings/test-sms', testSmsForm, {
        onSuccess: () => {
            testSmsLoading.value = false
            closeTestSmsModal()
            success('Test SMS sent successfully')
        },
        onError: (errors) => {
            testSmsLoading.value = false
            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const editEmailTemplate = (template) => {
    editingEmailTemplate.value = {
        ...template,
        is_active: Boolean(template.is_active)
    }
    showEmailModal.value = true
    clearErrors(templateErrors)
}

const closeEmailModal = () => {
    showEmailModal.value = false
    editingEmailTemplate.value = {}
    clearErrors(templateErrors)
}

const saveEmailTemplate = () => {
    if (!editingEmailTemplate.value.id) {
        error('Template ID is missing')
        return
    }

    templateLoading.value = true
    clearErrors(templateErrors)

    const templateData = {
        name: editingEmailTemplate.value.name,
        subject: editingEmailTemplate.value.subject,
        body: editingEmailTemplate.value.body,
        is_active: editingEmailTemplate.value.is_active
    }

    router.patch(`/admin/settings/email-template/${editingEmailTemplate.value.id}`, templateData, {
        onSuccess: () => {
            templateLoading.value = false

            const index = emailTemplates.value.findIndex(t => t.id === editingEmailTemplate.value.id)
            if (index !== -1) {
                emailTemplates.value[index] = {
                    ...emailTemplates.value[index],
                    ...templateData
                }
            }

            closeEmailModal()
            success('Email template updated successfully')
        },
        onError: (errors) => {
            templateLoading.value = false
            templateErrors.value = errors
            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}

const editSmsTemplate = (template) => {
    editingSmsTemplate.value = {
        ...template,
        is_active: Boolean(template.is_active)
    }
    showSmsModal.value = true
    clearErrors(smsTemplateErrors)
}

const closeSmsModal = () => {
    showSmsModal.value = false
    editingSmsTemplate.value = {}
    clearErrors(smsTemplateErrors)
}

const saveSmsTemplate = () => {
    if (!editingSmsTemplate.value.id) {
        error('Template ID is missing')
        return
    }

    smsTemplateLoading.value = true
    clearErrors(smsTemplateErrors)

    const templateData = {
        name: editingSmsTemplate.value.name,
        message: editingSmsTemplate.value.message,
        is_active: editingSmsTemplate.value.is_active
    }

    router.patch(`/admin/settings/sms-template/${editingSmsTemplate.value.id}`, templateData, {
        onSuccess: () => {
            smsTemplateLoading.value = false

            const index = smsTemplates.value.findIndex(t => t.id === editingSmsTemplate.value.id)
            if (index !== -1) {
                smsTemplates.value[index] = {
                    ...smsTemplates.value[index],
                    ...templateData
                }
            }

            closeSmsModal()
        },
        onError: (errors) => {
            smsTemplateLoading.value = false
            smsTemplateErrors.value = errors

            if (errors.error) {
                error(Array.isArray(errors.error) ? errors.error[0] : errors.error)
            }
        }
    })
}


watch(flash, (newFlash) => {
    if (newFlash?.success) {
        success(newFlash.success);
    } else if (newFlash?.error) {
        error(newFlash.error);
    }
}, { immediate: true });

onMounted(() => {
    if (flash.value?.success) {
        success(flash.value.success);
    } else if (flash.value?.error) {
        error(flash.value.error);
    }
});
</script>

<template>
    <AdminLayout title="Settings">
        <div class="py-8">
            <div class="max-w-6xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        Settings
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Manage your platform configuration and preferences
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md overflow-hidden">
                    <div class="border-b border-gray-200 dark:border-slate-700">
                        <nav
                            class="flex space-x-8 px-6"
                            aria-label="Tabs"
                        >
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                                :class="activeTab === tab.key
              ? 'border-blue-500 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                                @click="activeTab = tab.key"
                            >
                        <span class="flex items-center">
                          <span
                              class="mr-2"
                              v-html="tab.icon"
                          />
                          {{ tab.name }}
                        </span>
                            </button>
                        </nav>
                    </div>
                    <div class="p-6">
                        <div v-if="activeTab === 'general'" class="space-y-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                General Settings
                            </h3>
                            <form class="space-y-4" @submit.prevent="updateGeneral">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Site Name</label>
                                        <input
                                            v-model="generalForm.site_name"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.site_name }"
                                            placeholder="Enter site name"
                                        >
                                        <p v-if="generalErrors.site_name" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.site_name) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Primary Color</label>
                                        <input
                                            v-model="generalForm.primary_color"
                                            type="color"
                                            class="w-full h-12 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 focus:ring-blue-500 focus:border-blue-500 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.primary_color }"
                                        >
                                        <p v-if="generalErrors.primary_color" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.primary_color) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Site Logo</label>
                                        <div v-if="site_logo_url" class="mb-3">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Current Logo:</p>
                                            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-slate-600 rounded-lg">
                                                <img
                                                    :src="site_logo_url"
                                                    alt="Current Logo"
                                                    class="h-12 w-auto object-contain rounded"
                                                    @error="$event.target.style.display='none'"
                                                >
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ generalSettings.site_logo?.split('/').pop() || 'Logo file' }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Click below to change</p>
                                                </div>
                                            </div>
                                        </div>

                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.site_logo }"
                                            @change="handleLogoChange"
                                        >
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Recommended: PNG, JPG, SVG. Max size: 2MB
                                        </p>
                                        <p v-if="generalErrors.site_logo" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.site_logo) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Site Favicon</label>

                                        <div v-if="site_favicon_url" class="mb-3">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Current Favicon:</p>
                                            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-slate-600 rounded-lg">
                                                <img
                                                    :src="site_favicon_url"
                                                    alt="Current Favicon"
                                                    class="h-8 w-8 object-contain rounded"
                                                    @error="$event.target.style.display='none'"
                                                >
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ generalSettings.site_favicon?.split('/').pop() || 'Favicon file' }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Click below to change</p>
                                                </div>
                                            </div>
                                        </div>

                                        <input
                                            type="file"
                                            accept=".ico,.png,.gif"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.site_favicon }"
                                            @change="handleFaviconChange"
                                        >
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Recommended: ICO, PNG. Size: 16x16 or 32x32 pixels. Max size: 1MB
                                        </p>
                                        <p v-if="generalErrors.site_favicon" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.site_favicon) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Default Currency</label>
                                        <input
                                            v-model="generalForm.default_currency"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.default_currency }"
                                            placeholder="USD"
                                            maxlength="3"
                                        >
                                        <p v-if="generalErrors.default_currency" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.default_currency) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Currency Symbol</label>
                                        <input
                                            v-model="generalForm.currency_symbol"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.currency_symbol }"
                                            placeholder="$"
                                            maxlength="5"
                                        >
                                        <p v-if="generalErrors.currency_symbol" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.currency_symbol) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Balance Transfer Charge</label>
                                        <input
                                            v-model.number="generalForm.balance_transfer_charge"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.balance_transfer_charge }"
                                            placeholder="1.00"
                                        >
                                        <p v-if="generalErrors.balance_transfer_charge" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.balance_transfer_charge) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Default Timezone</label>
                                        <select
                                            v-model="generalForm.default_timezone"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.default_timezone }"
                                        >
                                            <option value="America/New_York">America/New_York (EST/EDT)</option>
                                            <option value="America/Los_Angeles">America/Los_Angeles (PST/PDT)</option>
                                            <option value="Europe/London">Europe/London (GMT/BST)</option>
                                            <option value="Europe/Paris">Europe/Paris (CET/CEST)</option>
                                            <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                                            <option value="Asia/Shanghai">Asia/Shanghai (CST)</option>
                                            <option value="Asia/Dhaka">Asia/Dhaka (BST)</option>
                                            <option value="UTC">UTC</option>
                                        </select>
                                        <p v-if="generalErrors.default_timezone" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.default_timezone) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Referral Deposit Commission Rate (%)</label>
                                        <input
                                            v-model.number="generalForm.referral_deposit_commission_rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.referral_deposit_commission_rate }"
                                            placeholder="10.00"
                                        >
                                        <p v-if="generalErrors.referral_deposit_commission_rate" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.referral_deposit_commission_rate) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Percentage commission earned from referral deposits
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mining Fee (%)</label>
                                        <input
                                            v-model.number="generalForm.mining_fee"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.mining_fee }"
                                            placeholder="10.00"
                                        >
                                        <p v-if="generalErrors.mining_fee" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.mining_fee) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Fee applied during mining operations
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mining Rate Multiplier</label>
                                        <input
                                            v-model.number="generalForm.mining_rate_multiplier"
                                            type="number"
                                            step="0.01"
                                            min="0.1"
                                            max="10"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.mining_rate_multiplier }"
                                            placeholder="1.00"
                                        >
                                        <p v-if="generalErrors.mining_rate_multiplier" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.mining_rate_multiplier) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Multiplier to adjust the mining rate
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tawk.to Property ID</label>
                                        <input
                                            v-model="generalForm.tawk_property_id"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.tawk_property_id }"
                                            placeholder="Enter Tawk.to Property ID"
                                        >
                                        <p v-if="generalErrors.tawk_property_id" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.tawk_property_id) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Tawk.to Property ID for chat widget
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tawk.to Widget ID</label>
                                        <input
                                            v-model="generalForm.tawk_widget_id"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': generalErrors.tawk_widget_id }"
                                            placeholder="Tawk.to Widget ID"
                                        >
                                        <p v-if="generalErrors.tawk_widget_id" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(generalErrors.tawk_widget_id) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Tawk.to Widget ID for chat widget
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-6">
                                    <label class="flex items-center">
                                        <input
                                            v-model="generalForm.user_registration"
                                            type="checkbox"
                                            class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:bg-slate-700"
                                        >
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Allow Registration</span>
                                    </label>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="generalLoading"
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center"
                                    >
                                        <svg v-if="generalLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 718-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        {{ generalLoading ? 'Saving...' : 'Save Changes' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div
                            v-if="activeTab === 'email'"
                            class="space-y-6"
                        >
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Email Configuration
                            </h3>
                            <form
                                class="space-y-4"
                                @submit.prevent="updateEmail"
                            >
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mail Driver</label>
                                        <select
                                            v-model="emailForm.mail_driver"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                            <option value="smtp">
                                                SMTP
                                            </option>
                                            <option value="mailgun">
                                                Mailgun
                                            </option>
                                            <option value="ses">
                                                AWS SES
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Encryption</label>
                                        <select
                                            v-model="emailForm.mail_encryption"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                            <option value="tls">
                                                TLS
                                            </option>
                                            <option value="ssl">
                                                SSL
                                            </option>
                                            <option value="">
                                                None
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="emailForm.mail_driver === 'smtp'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SMTP Host</label>
                                        <input
                                            v-model="emailForm.mail_host"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                            placeholder="smtp.gmail.com"
                                        >
                                    </div>
                                    <div v-if="emailForm.mail_driver === 'smtp'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SMTP Port</label>
                                        <input
                                            v-model="emailForm.mail_port"
                                            type="number"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                            placeholder="587"
                                        >
                                    </div>
                                    <div v-if="emailForm.mail_driver === 'smtp'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username</label>
                                        <input
                                            v-model="emailForm.mail_username"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div v-if="emailForm.mail_driver === 'smtp'">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
                                        <input
                                            v-model="emailForm.mail_password"
                                            type="password"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Email</label>
                                        <input
                                            v-model="emailForm.mail_from_address"
                                            type="email"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Name</label>
                                        <input
                                            v-model="emailForm.mail_from_name"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors"
                                        @click="testEmail"
                                    >
                                        Test Email
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="emailLoading"
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors duration-200 flex items-center justify-center"
                                    >
                                        <svg
                                            v-if="emailLoading"
                                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            />
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            />
                                        </svg>
                                        {{ emailLoading ? 'Saving...' : 'Save Email Settings' }}
                                    </button>
                                </div>
                            </form>

                            <div class="border-t dark:border-slate-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Email Templates
                                </h3>
                                <div class="space-y-4">
                                    <div
                                        v-for="template in emailTemplates"
                                        :key="template.id"
                                        class="border dark:border-slate-700 rounded-lg p-4"
                                    >
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ template.name }}
                                            </h4>
                                            <div class="flex items-center space-x-2">
                                <span
                                    class="text-xs px-2 py-1 rounded-full"
                                    :class="template.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                                >
                                  {{ template.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                                <button
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm"
                                                    @click="editEmailTemplate(template)"
                                                >
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            <p><strong>Subject:</strong> {{ template.subject }}</p>
                                            <p class="mt-1">
                                                <strong>Variables:</strong> {{ template.variables?.join(', ') || 'None' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="activeTab === 'sms'"
                            class="space-y-6"
                        >
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                SMS Configuration
                            </h3>
                            <form
                                class="space-y-4"
                                @submit.prevent="updateSms"
                            >
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SMS Provider</label>
                                        <input
                                            v-model="smsForm.sms_driver"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Key</label>
                                        <input
                                            v-model="smsForm.sms_api_key"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Secret</label>
                                        <input
                                            v-model="smsForm.sms_api_secret"
                                            type="password"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Number</label>
                                        <input
                                            v-model="smsForm.sms_from"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                            placeholder="+1234567890"
                                        >
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors"
                                        @click="testSms"
                                    >
                                        Test SMS
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="smsLoading"
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors duration-200 flex items-center justify-center"
                                    >
                                        <svg
                                            v-if="smsLoading"
                                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            />
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            />
                                        </svg>
                                        {{ smsLoading ? 'Saving...' : 'Save SMS Settings' }}
                                    </button>
                                </div>
                            </form>

                            <div class="border-t dark:border-slate-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    SMS Templates
                                </h3>
                                <div class="space-y-4">
                                    <div
                                        v-for="template in smsTemplates"
                                        :key="template.id"
                                        class="border dark:border-slate-700 rounded-lg p-4"
                                    >
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ template.name }}
                                            </h4>
                                            <div class="flex items-center space-x-2">
                                <span
                                    class="text-xs px-2 py-1 rounded-full"
                                    :class="template.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                                >
                                  {{ template.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                                <button
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm"
                                                    @click="editSmsTemplate(template)"
                                                >
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            <p><strong>Message:</strong> {{ template.message.substring(0, 100) }}{{ template.message.length > 100 ? '...' : '' }}</p>
                                            <p class="mt-1">
                                                <strong>Variables:</strong> {{ template.variables?.join(', ') || 'None' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="activeTab === 'security'"
                            class="space-y-6"
                        >
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Security Settings
                            </h3>
                            <form
                                class="space-y-4"
                                @submit.prevent="updateSecurity"
                            >
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Login Attempts</label>
                                        <input
                                            v-model.number="securityForm.login_attempts"
                                            type="number"
                                            min="3"
                                            max="10"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Session Timeout (minutes)</label>
                                        <input
                                            v-model.number="securityForm.session_timeout"
                                            type="number"
                                            min="15"
                                            max="1440"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min Password Length</label>
                                        <input
                                            v-model.number="securityForm.password_min_length"
                                            type="number"
                                            min="6"
                                            max="32"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                        >
                                    </div>
                                    <div class="flex flex-col space-y-3">
                                        <label class="flex items-center">
                                            <input
                                                v-model="securityForm.two_factor_auth"
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:bg-slate-700"
                                            >
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable 2FA</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                v-model="securityForm.require_email_verification"
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:bg-slate-700"
                                            >
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Require Email Verification</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                v-model="securityForm.kyc_status"
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:bg-slate-700"
                                            >
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable KYC Verification</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="securityLoading"
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors duration-200 flex items-center justify-center"
                                    >
                                        <svg
                                            v-if="securityLoading"
                                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            />
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            />
                                        </svg>
                                        {{ securityLoading ? 'Saving...' : 'Save Security Settings' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div v-if="activeTab === 'seo'" class="space-y-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                SEO Settings
                            </h3>
                            <form class="space-y-4" @submit.prevent="updateSeo">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Meta Title</label>
                                        <input
                                            v-model="seoForm.meta_title"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.meta_title }"
                                            placeholder="Your site meta title"
                                        >
                                        <p v-if="seoErrors.meta_title" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.meta_title) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Open Graph Title</label>
                                        <input
                                            v-model="seoForm.og_title"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.og_title }"
                                            placeholder="Facebook/social media title"
                                        >
                                        <p v-if="seoErrors.og_title" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.og_title) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Twitter Card Type</label>
                                        <select
                                            v-model="seoForm.twitter_card"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.twitter_card }"
                                        >
                                            <option value="summary">Summary</option>
                                            <option value="summary_large_image">Summary Large Image</option>
                                            <option value="app">App</option>
                                            <option value="player">Player</option>
                                        </select>
                                        <p v-if="seoErrors.twitter_card" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.twitter_card) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Open Graph Image</label>

                                        <!-- Current OG Image Preview -->
                                        <div v-if="seo_og_image_url" class="mb-3">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Current Open Graph Image:</p>
                                            <div class="p-3 bg-gray-50 dark:bg-slate-600 rounded-lg">
                                                <img
                                                    :src="seo_og_image_url"
                                                    alt="Current OG Image"
                                                    class="w-full max-w-sm h-32 object-cover rounded border"
                                                    @error="$event.target.style.display='none'"
                                                >
                                                <div class="mt-2">
                                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ seoSettings.og_image?.split('/').pop() || 'OG Image file' }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Click below to change</p>
                                                </div>
                                            </div>
                                        </div>

                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.og_image }"
                                            @change="handleOgImageChange"
                                        >
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Recommended: 1200x630 pixels. JPG, PNG. Max size: 2MB
                                        </p>
                                        <p v-if="seoErrors.og_image" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.og_image) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Google Analytics ID</label>
                                        <input
                                            v-model="seoForm.google_analytics"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.google_analytics }"
                                            placeholder="GA4-XXXXXXXXX"
                                        >
                                        <p v-if="seoErrors.google_analytics" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.google_analytics) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Google Tag Manager ID</label>
                                        <input
                                            v-model="seoForm.google_tag_manager"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.google_tag_manager }"
                                            placeholder="GTM-XXXXXXX"
                                        >
                                        <p v-if="seoErrors.google_tag_manager" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.google_tag_manager) }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Facebook Pixel ID</label>
                                        <input
                                            v-model="seoForm.facebook_pixel"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                            :class="{ 'border-red-500 focus:ring-red-500': seoErrors.facebook_pixel }"
                                            placeholder="123456789012345"
                                        >
                                        <p v-if="seoErrors.facebook_pixel" class="text-red-500 text-sm mt-1">
                                            {{ getErrorMessage(seoErrors.facebook_pixel) }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Meta Description</label>
                                    <textarea
                                        v-model="seoForm.meta_description"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': seoErrors.meta_description }"
                                        placeholder="SEO meta description for homepage"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Keep under 160 characters for best SEO results
                                    </p>
                                    <p v-if="seoErrors.meta_description" class="text-red-500 text-sm mt-1">
                                        {{ getErrorMessage(seoErrors.meta_description) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Open Graph Description</label>
                                    <textarea
                                        v-model="seoForm.og_description"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': seoErrors.og_description }"
                                        placeholder="Facebook/social media description"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Description for social media sharing (Facebook, LinkedIn, etc.)
                                    </p>
                                    <p v-if="seoErrors.og_description" class="text-red-500 text-sm mt-1">
                                        {{ getErrorMessage(seoErrors.og_description) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Meta Keywords</label>
                                    <textarea
                                        v-model="seoForm.meta_keywords"
                                        rows="2"
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500 p-3 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': seoErrors.meta_keywords }"
                                        placeholder="keyword1, keyword2, keyword3"
                                    />
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Comma separated keywords (optional - most search engines ignore this)
                                    </p>
                                    <p v-if="seoErrors.meta_keywords" class="text-red-500 text-sm mt-1">
                                        {{ getErrorMessage(seoErrors.meta_keywords) }}
                                    </p>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="seoLoading"
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center"
                                    >
                                        <svg v-if="seoLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 718-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        {{ seoLoading ? 'Saving...' : 'Save SEO Settings' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showEmailModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-slate-800">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Edit Email Template
                    </h3>
                    <form
                        class="space-y-4"
                        @submit.prevent="saveEmailTemplate"
                    >
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Template Name</label>
                                <input
                                    v-model="editingEmailTemplate.name"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                    :class="{ 'border-red-500 focus:ring-red-500': templateErrors.name }"
                                    required
                                >
                                <p
                                    v-if="templateErrors.name"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ getErrorMessage(templateErrors.name) }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject</label>
                                <input
                                    v-model="editingEmailTemplate.subject"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                    :class="{ 'border-red-500 focus:ring-red-500': templateErrors.subject }"
                                    required
                                >
                                <p
                                    v-if="templateErrors.subject"
                                    class="text-red-500 text-sm mt-1"
                                >
                                    {{ getErrorMessage(templateErrors.subject) }}
                                </p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Body</label>
                            <textarea
                                v-model="editingEmailTemplate.body"
                                rows="10"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                :class="{ 'border-red-500 focus:ring-red-500': templateErrors.body }"
                                required
                            />
                            <p
                                v-if="templateErrors.body"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ getErrorMessage(templateErrors.body) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Available variables: {{ editingEmailTemplate.variables?.join(', ') || 'None' }}
                            </p>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input
                                    v-model="editingEmailTemplate.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:bg-slate-700"
                                >
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
                                @click="closeEmailModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="templateLoading"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 transition-colors duration-200 flex items-center justify-center"
                            >
                                <svg
                                    v-if="templateLoading"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                {{ templateLoading ? 'Saving...' : 'Save Template' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showSmsModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white dark:bg-slate-800">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Edit SMS Template
                    </h3>
                    <form
                        class="space-y-4"
                        @submit.prevent="saveSmsTemplate"
                    >
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Template Name</label>
                            <input
                                v-model="editingSmsTemplate.name"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                :class="{ 'border-red-500 focus:ring-red-500': smsTemplateErrors.name }"
                                required
                            >
                            <p
                                v-if="smsTemplateErrors.name"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ getErrorMessage(smsTemplateErrors.name) }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SMS Message</label>
                            <textarea
                                v-model="editingSmsTemplate.message"
                                rows="5"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                :class="{ 'border-red-500 focus:ring-red-500': smsTemplateErrors.message }"
                                required
                                maxlength="160"
                            />
                            <p
                                v-if="smsTemplateErrors.message"
                                class="text-red-500 text-sm mt-1"
                            >
                                {{ getErrorMessage(smsTemplateErrors.message) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ editingSmsTemplate.message?.length || 0 }}/160 characters
                                | Available variables: {{ editingSmsTemplate.variables?.join(', ') || 'None' }}
                            </p>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input
                                    v-model="editingSmsTemplate.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:bg-slate-700"
                                >
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
                                @click="closeSmsModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="smsTemplateLoading"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 transition-colors duration-200 flex items-center justify-center"
                            >
                                <svg
                                    v-if="smsTemplateLoading"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                {{ smsTemplateLoading ? 'Saving...' : 'Save Template' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showTestEmailModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Test Email Configuration
                    </h3>
                    <form
                        class="space-y-4"
                        @submit.prevent="sendTestEmail"
                    >
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                            <input
                                v-model="testEmailForm.email"
                                type="email"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                placeholder="test@example.com"
                                required
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Test Message</label>
                            <textarea
                                v-model="testEmailForm.message"
                                rows="4"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                placeholder="This is a test email message..."
                                required
                            />
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
                                @click="closeTestEmailModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="testEmailLoading"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-blue-400 transition-colors duration-200 flex items-center justify-center"
                            >
                                <svg
                                    v-if="testEmailLoading"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                {{ testEmailLoading ? 'Sending...' : 'Send Test Email' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            v-if="showTestSmsModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
        >
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-slate-800">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Test SMS Configuration
                    </h3>
                    <form
                        class="space-y-4"
                        @submit.prevent="sendTestSms"
                    >
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone Number</label>
                            <input
                                v-model="testSmsForm.phone"
                                type="tel"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                placeholder="+1234567890"
                                required
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Test Message</label>
                            <textarea
                                v-model="testSmsForm.message"
                                rows="3"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-gray-100 p-3 transition-all"
                                placeholder="This is a test SMS message..."
                                required
                                maxlength="160"
                            />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ testSmsForm.message?.length || 0 }}/160 characters
                            </p>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
                                @click="closeTestSmsModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="testSmsLoading"
                                class="px-4 py-2 bg-lime-500 text-white rounded-lg hover:bg-lime-600 disabled:bg-lime-400 transition-colors duration-200 flex items-center justify-center"
                            >
                                <svg
                                    v-if="testSmsLoading"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                {{ testSmsLoading ? 'Sending...' : 'Send Test SMS' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
