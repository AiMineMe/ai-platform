<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    is_enabled: {
        type: Boolean,
        default: false,
        validator: (value) => typeof value === 'boolean'
    },
    qr_code: {
        type: String,
        default: null,
        validator: (value) => value === null || typeof value === 'string'
    },
    secret: {
        type: String,
        default: null,
        validator: (value) => value === null || typeof value === 'string'
    },
    recovery_codes: {
        type: Array,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const isProcessing = ref(false);
const showDisablePassword = ref(false);
const enableForm = ref({
    code: '',
    secret: props.secret || ''
});

const verifyForm = ref({
    code: ''
});

const disableForm = ref({
    password: ''
});

const { showToast } = useToast();
const { t } = useTranslation();
const page = usePage();

const primaryColor = computed(() => page.props.primaryColor || '#1f2937');
const derivedColors = computed(() => {
    const primary = primaryColor.value;

    const adjustColor = (color, amount) => {
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);

        const newR = Math.min(255, Math.max(0, r + amount));
        const newG = Math.min(255, Math.max(0, g + amount));
        const newB = Math.min(255, Math.max(0, b + amount));

        return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
    };

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
        warning: '#f59e0b'
    };
});

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));

const inputStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border,
    color: derivedColors.value.textPrimary
}));

const requirementsBgStyle = computed(() => ({
    backgroundColor: derivedColors.value.accent + '10',
    borderColor: derivedColors.value.accent + '30'
}));

const sanitizeCode = (code) => {
    return code.replace(/[^0-9]/g, '').substring(0, 6);
};

const isValidCode = (code) => {
    return /^\d{6}$/.test(code);
};

const canEnableSubmit = computed(() => {
    return enableForm.value.code.length === 6 &&
        isValidCode(enableForm.value.code) &&
        !isProcessing.value;
});

const canVerifySubmit = computed(() => {
    return verifyForm.value.code.length === 6 &&
        isValidCode(verifyForm.value.code) &&
        !isProcessing.value;
});

const canDisableSubmit = computed(() => {
    return disableForm.value.password.length >= 6 &&
        !isProcessing.value;
});

const copySecretToClipboard = async () => {
    if (!props.secret) {
        showToast(t('noSecretAvailable'), 'error');
        return;
    }

    try {
        await navigator.clipboard.writeText(props.secret);
        showToast(t('secretKeyCopied'), 'success');
    } catch (err) {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = props.secret;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            textArea.setSelectionRange(0, 99999);
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast(t('secretKeyCopied'), 'success');
        } catch (fallbackErr) {
            showToast(t('failedToCopySecret'), 'error');
        }
    }
};

const resetForms = () => {
    enableForm.value = {
        code: '',
        secret: props.secret || ''
    };
    verifyForm.value = { code: '' };
    disableForm.value = { password: '' };
    showDisablePassword.value = false;
};

const enableTwoFactor = () => {
    if (!canEnableSubmit.value) {
        showToast(t('enterValid6DigitCode'), 'error');
        return;
    }

    isProcessing.value = true;
    router.post('/user/security/2fa/enable', enableForm.value, {
        onSuccess: () => {
            enableForm.value.code = '';
            showToast(t('twoFactorEnabledSuccessfully'), 'success');
        },
        onError: (errors) => {
            const errorMessage = errors?.code ?
                (Array.isArray(errors.code) ? errors.code[0] : errors.code) :
                t('failedToEnable2FA');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

const verifyCode = () => {
    if (!canVerifySubmit.value) {
        showToast(t('enterValid6DigitCode'), 'error');
        return;
    }

    isProcessing.value = true;
    router.post('/user/security/2fa/verify', verifyForm.value, {
        onSuccess: () => {
            verifyForm.value.code = '';
            showToast(t('codeVerifiedSuccessfully'), 'success');
        },
        onError: (errors) => {
            const errorMessage = errors?.verify_code ?
                (Array.isArray(errors.verify_code) ? errors.verify_code[0] : errors.verify_code) :
                t('invalidVerificationCode');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

const disableTwoFactor = () => {
    if (!canDisableSubmit.value) {
        showToast(t('enterPasswordToDisable'), 'error');
        return;
    }

    isProcessing.value = true;

    router.post('/user/security/2fa/disable', disableForm.value, {
        onSuccess: () => {
            resetForms();
            showToast(t('twoFactorDisabledSuccessfully'), 'success');
        },
        onError: (errors) => {
            const errorMessage = errors?.password ?
                (Array.isArray(errors.password) ? errors.password[0] : errors.password) :
                t('failedToDisable2FA');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

const downloadRecoveryCodes = () => {
    if (!props.recovery_codes || props.recovery_codes.length === 0) {
        showToast(t('noRecoveryCodesAvailable'), 'error');
        return;
    }

    try {
        const timestamp = new Date().toISOString().split('T')[0];
        const header = `${t('twoFactorRecoveryCodes')}\n${t('generated')}: ${timestamp}\n\n${t('recoveryCodesImportant')}\n\n`;
        const codes = props.recovery_codes.map((code, index) => `${index + 1}. ${code}`).join('\n');
        const footer = `\n\n${t('storeCodesSecurely')}`;
        const content = header + codes + footer;

        const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `recovery-codes-${timestamp}.txt`;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showToast(t('recoveryCodesDownloaded'), 'success');
    } catch (error) {
        showToast(t('failedToDownloadCodes'), 'error');
    }
};

const generateNewCodes = () => {
    if (!props.is_enabled) {
        showToast(t('enable2FAFirst'), 'error');
        return;
    }

    isProcessing.value = true;

    router.post('/user/security/2fa/recovery-codes/regenerate', {}, {
        onSuccess: () => {
            showToast(t('newRecoveryCodesGenerated'), 'success');
        },
        onError: (errors) => {
            const errorMessage = errors?.message || t('failedToGenerateNewCodes');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

watch(() => enableForm.value.code, (newCode) => {
    enableForm.value.code = sanitizeCode(newCode);
});

watch(() => verifyForm.value.code, (newCode) => {
    verifyForm.value.code = sanitizeCode(newCode);
});

watch(() => props.secret, (newSecret) => {
    enableForm.value.secret = newSecret || '';
});

onMounted(() => {
    const flash = page.props.flash;

    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.info) showToast(flash.info, 'info');
    if (flash?.warning) showToast(flash.warning, 'warning');
});
</script>

<template>
    <UserLayout
        :page-title="t('twoFactorAuthentication')"
        :page-section="t('security')"
    >
        <div class="max-w-6xl mx-auto space-y-4 sm:space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                    <div class="flex items-center space-x-3">
                        <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }">
                            <svg
                                class="w-5 h-5 sm:w-6 sm:h-6"
                                :style="{ color: derivedColors.background }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ t('twoFactorAuthentication') }}
                            </h1>
                            <p class="mt-1 text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('addExtraSecurityLayer') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 flex-shrink-0">
                        <div
                            class="h-3 w-3 rounded-full"
                            :style="{ backgroundColor: is_enabled ? derivedColors.success : '#ef4444' }"
                        />
                        <span
                            class="text-sm font-medium"
                            :style="{ color: is_enabled ? derivedColors.success : '#ef4444' }"
                        >
                            {{ is_enabled ? t('enabled') : t('disabled') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
                <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="flex items-start sm:items-center space-x-3 mb-4 sm:mb-6">
                        <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }">
                            <svg
                                class="w-5 h-5 sm:w-6 sm:h-6"
                                :style="{ color: derivedColors.background }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ is_enabled ? t('manage2FA') : t('setup2FA') }}
                            </h3>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ is_enabled ? t('accountIsProtected') : t('secureYourAccount') }}
                            </p>
                        </div>
                    </div>

                    <div v-if="!is_enabled">
                        <div class="space-y-4">
                            <div class="border rounded-lg p-3 sm:p-4" :style="requirementsBgStyle">
                                <h4 class="font-medium mb-2 flex items-start sm:items-center text-sm sm:text-base" :style="{ color: derivedColors.accent }">
                                    <div class="w-2 h-2 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                                    <span>{{ t('setupInstructions') }}:</span>
                                </h4>
                                <ol class="text-xs sm:text-sm space-y-1 list-decimal list-inside ml-5" :style="{ color: derivedColors.textSecondary }">
                                    <li>{{ t('installAuthenticatorApp') }}</li>
                                    <li>{{ t('scanQRCodeOrEnterSecret') }}</li>
                                    <li>{{ t('enter6DigitCodeFromApp') }}</li>
                                    <li>{{ t('saveRecoveryCodesSafely') }}</li>
                                </ol>
                            </div>

                            <div v-if="qr_code" class="text-center">
                                <div class="inline-block mb-4 border rounded-lg p-3 sm:p-4 max-w-full overflow-hidden" :style="{ backgroundColor: '#ffffff', borderColor: derivedColors.border }">
                                    <img
                                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qr_code)}`"
                                        :alt="t('qrCodeAlt')"
                                        class="w-32 h-32 sm:w-48 sm:h-48 max-w-full"
                                        loading="lazy"
                                    >
                                </div>
                                <p class="text-xs mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('scanQRCodeWithApp') }}
                                </p>
                                <button
                                    type="button"
                                    class="text-xs sm:text-sm underline transition-colors duration-200"
                                    :style="{ color: derivedColors.accent }"
                                    :aria-label="t('copySecretKeyInstead')"
                                    @click="copySecretToClipboard"
                                >
                                    {{ t('copySecretKeyInstead') }}
                                </button>
                            </div>

                            <div v-if="secret" class="rounded-lg p-3 sm:p-4 border" :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border }">
                                <p class="text-xs sm:text-sm mb-2" :style="{ color: derivedColors.textMuted }">
                                    {{ t('orEnterCodeManually') }}:
                                </p>
                                <div class="flex items-center justify-between rounded p-2 min-w-0" :style="{ backgroundColor: derivedColors.background }">
                                    <code class="font-mono text-xs sm:text-sm break-all flex-1 mr-2" :style="{ color: derivedColors.accent }">{{ secret }}</code>
                                    <button
                                        type="button"
                                        class="transition-colors duration-200 flex-shrink-0"
                                        :style="{ color: derivedColors.accent }"
                                        :aria-label="t('copySecretToClipboard')"
                                        @click="copySecretToClipboard"
                                    >
                                        <svg
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <form class="space-y-4" novalidate @submit.prevent="enableTwoFactor">
                                <div>
                                    <label
                                        for="enable_code"
                                        class="block text-sm font-medium mb-2"
                                        :style="{ color: derivedColors.textSecondary }"
                                    >
                                        {{ t('enterVerificationCodeFromApp') }}
                                    </label>
                                    <input
                                        id="enable_code"
                                        v-model="enableForm.code"
                                        name="enable_code"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]{6}"
                                        maxlength="6"
                                        autocomplete="one-time-code"
                                        :aria-label="t('enter6DigitVerificationCode')"
                                        :aria-describedby="errors?.code ? 'enable_code_error' : null"
                                        :aria-invalid="errors?.code ? 'true' : 'false'"
                                        class="w-full px-3 sm:px-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-center text-lg sm:text-2xl tracking-wider font-mono"
                                        :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                        :placeholder="t('codePlaceholder')"
                                        required
                                    >
                                    <div
                                        v-if="errors?.code"
                                        id="enable_code_error"
                                        class="text-red-400 text-sm mt-1"
                                        role="alert"
                                    >
                                        {{ Array.isArray(errors.code) ? errors.code[0] : errors.code }}
                                    </div>
                                </div>

                                <input v-model="enableForm.secret" type="hidden" :value="secret">

                                <button
                                    type="submit"
                                    :disabled="!canEnableSubmit"
                                    :aria-label="isProcessing ? t('enabling') : t('enableTwoFactorAuth')"
                                    class="w-full py-2 sm:py-3 px-4 rounded-lg font-bold disabled:opacity-50 transition-all duration-200 shadow-lg hover:scale-105 text-sm sm:text-base"
                                    :style="{
                                        backgroundColor: canEnableSubmit ? derivedColors.accent : derivedColors.surface,
                                        color: canEnableSubmit ? derivedColors.background : derivedColors.textMuted
                                    }"
                                >
                                    <span v-if="isProcessing" class="flex items-center justify-center">
                                        <svg
                                            class="animate-spin -ml-1 mr-3 h-4 w-4 sm:h-5 sm:w-5"
                                            :style="{ color: derivedColors.background }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
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
                                        <span class="text-sm sm:text-base">{{ t('enabling') }}...</span>
                                    </span>
                                    <span v-else>{{ t('enableTwoFactorAuth') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div v-else>
                        <div class="space-y-4">
                            <div class="border rounded-lg p-3 sm:p-4" :style="requirementsBgStyle">
                                <div class="flex items-center space-x-2 mb-2">
                                    <svg
                                        class="w-5 h-5 sm:w-6 sm:h-6"
                                        :style="{ color: derivedColors.background }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1 1 21 9z"
                                        />
                                    </svg>
                                    <span class="font-medium text-sm sm:text-base" :style="{ color: derivedColors.accent }">{{ t('twoFactorIsActive') }}</span>
                                </div>
                                <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('accountProtectedWith2FA') }}
                                </p>
                            </div>

                            <div class="border rounded-lg p-3 sm:p-4" :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border }">
                                <h4 class="font-medium mb-3 flex items-start sm:items-center text-sm sm:text-base" :style="{ color: derivedColors.textPrimary }">
                                    <div class="w-2 h-2 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                                    <span>{{ t('testYour2FA') }}</span>
                                </h4>
                                <form class="space-y-3" novalidate @submit.prevent="verifyCode">
                                    <div>
                                        <label for="verify_code" class="sr-only">{{ t('enterVerificationCodeToTest') }}</label>
                                        <input
                                            id="verify_code"
                                            v-model="verifyForm.code"
                                            name="verify_code"
                                            type="text"
                                            inputmode="numeric"
                                            pattern="[0-9]{6}"
                                            maxlength="6"
                                            autocomplete="one-time-code"
                                            :aria-label="t('enter6DigitCodeToTest')"
                                            :aria-describedby="errors?.verify_code ? 'verify_code_error' : null"
                                            :aria-invalid="errors?.verify_code ? 'true' : 'false'"
                                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-center text-lg sm:text-xl tracking-wider font-mono"
                                            :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                            :placeholder="t('enter6DigitCode')"
                                        >
                                        <div
                                            v-if="errors?.verify_code"
                                            id="verify_code_error"
                                            class="text-red-400 text-sm mt-1"
                                            role="alert"
                                        >
                                            {{ Array.isArray(errors.verify_code) ? errors.verify_code[0] : errors.verify_code }}
                                        </div>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="!canVerifySubmit"
                                        :aria-label="isProcessing ? t('verifying') : t('verifyCode')"
                                        class="w-full py-2 px-4 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 shadow-lg text-sm sm:text-base"
                                        :style="{
                                            backgroundColor: canVerifySubmit ? derivedColors.accent : derivedColors.surface,
                                            color: canVerifySubmit ? derivedColors.background : derivedColors.textMuted
                                        }"
                                    >
                                        <span v-if="isProcessing" class="flex items-center justify-center">
                                            <svg
                                                class="animate-spin -ml-1 mr-3 h-4 w-4 sm:h-5 sm:w-5"
                                                :style="{ color: derivedColors.background }"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
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
                                            <span class="text-sm sm:text-base">{{ t('verifying') }}...</span>
                                        </span>
                                        <span v-else>{{ t('verifyCode') }}</span>
                                    </button>
                                </form>
                            </div>

                            <div class="border rounded-lg p-3 sm:p-4" :style="{ backgroundColor: '#ef444420', borderColor: '#ef444450' }">
                                <h4 class="text-red-300 font-medium mb-3 flex items-start sm:items-center text-sm sm:text-base">
                                    <div class="w-2 h-2 bg-red-400 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0"></div>
                                    <span>{{ t('disableTwoFactorAuth') }}</span>
                                </h4>
                                <p class="text-xs sm:text-sm text-red-200 mb-3">
                                    {{ t('removeExtraSecurityLayer') }}
                                </p>

                                <form class="space-y-3" novalidate @submit.prevent="disableTwoFactor">
                                    <div>
                                        <label for="disable_password" class="sr-only">{{ t('enterPasswordToDisable') }}</label>
                                        <div class="relative">
                                            <input
                                                id="disable_password"
                                                v-model="disableForm.password"
                                                name="disable_password"
                                                :type="showDisablePassword ? 'text' : 'password'"
                                                autocomplete="current-password"
                                                :aria-label="t('enterPasswordToConfirmDisabling')"
                                                :aria-describedby="errors?.password ? 'disable_password_error' : null"
                                                :aria-invalid="errors?.password ? 'true' : 'false'"
                                                class="w-full px-3 sm:px-4 py-2 sm:py-3 pr-10 sm:pr-12 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 placeholder-gray-400"
                                                :style="{ ...inputStyle, ':focus': { borderColor: '#ef4444' } }"
                                                :placeholder="t('enterPasswordToConfirm')"
                                                required
                                            >
                                            <button
                                                type="button"
                                                :aria-label="showDisablePassword ? t('hidePassword') : t('showPassword')"
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center transition-colors duration-200"
                                                :style="{ color: derivedColors.textMuted }"
                                                @click="showDisablePassword = !showDisablePassword"
                                            >
                                                <svg
                                                    v-if="showDisablePassword"
                                                    class="w-4 h-4 sm:w-5 sm:h-5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else
                                                    class="w-4 h-4 sm:w-5 sm:h-5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                    />
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                        <div
                                            v-if="errors?.password"
                                            id="disable_password_error"
                                            class="text-red-400 text-sm mt-1"
                                            role="alert"
                                        >
                                            {{ Array.isArray(errors.password) ? errors.password[0] : errors.password }}
                                        </div>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="!canDisableSubmit"
                                        :aria-label="isProcessing ? t('disabling') : t('disable2FA')"
                                        class="w-full bg-red-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 transition-all duration-200 shadow-lg text-sm sm:text-base"
                                    >
                                        <span v-if="isProcessing" class="flex items-center justify-center">
                                            <svg
                                                class="animate-spin -ml-1 mr-3 h-4 w-4 sm:h-5 sm:w-5 text-white"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                aria-hidden="true"
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
                                            <span class="text-sm sm:text-base">{{ t('disabling') }}...</span>
                                        </span>
                                        <span v-else>{{ t('disable2FA') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="flex items-start sm:items-center space-x-3 mb-4 sm:mb-6">
                        <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }">
                            <svg
                                class="w-5 h-5 sm:w-6 sm:h-6"
                                :style="{ color: derivedColors.background }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1721 9z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base sm:text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('recoveryCodes') }}
                            </h3>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('useIfLoseAccess') }}
                            </p>
                        </div>
                    </div>

                    <div v-if="is_enabled && recovery_codes && recovery_codes.length">
                        <div class="border rounded-lg p-3 sm:p-4 mb-4" :style="{ backgroundColor: derivedColors.warning + '20', borderColor: derivedColors.warning + '30' }">
                            <div class="flex items-start space-x-2">
                                <svg
                                    class="w-8 h-8 sm:w-12 sm:h-12 mx-auto mb-4 flex-shrink-0"
                                    :style="{ color: derivedColors.textMuted }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1 1 21 9z"
                                    />
                                </svg>
                                <div>
                                    <p class="font-medium text-xs sm:text-sm" :style="{ color: derivedColors.warning }">
                                        {{ t('important') }}!
                                    </p>
                                    <p class="text-xs mt-1" :style="{ color: derivedColors.textSecondary }">
                                        {{ t('saveCodesSecurely') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4"
                            role="list"
                            :aria-label="t('recoveryCodes')"
                        >
                            <div
                                v-for="(code, index) in recovery_codes"
                                :key="index"
                                role="listitem"
                                class="border rounded p-2 sm:p-3 text-center hover:opacity-80 transition-all duration-200"
                                :style="{ backgroundColor: derivedColors.surface + '50', borderColor: derivedColors.border }"
                            >
                                <code
                                    class="font-mono text-xs sm:text-sm break-all"
                                    :style="{ color: derivedColors.accent }"
                                    :aria-label="`${t('recoveryCode')} ${index + 1}: ${code}`"
                                >{{ code }}</code>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <button
                                :disabled="isProcessing"
                                :aria-label="t('downloadRecoveryCodesAsFile')"
                                class="w-full py-2 px-4 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 shadow-lg hover:scale-105 text-sm sm:text-base"
                                :style="{
                                    backgroundColor: derivedColors.accent,
                                    color: derivedColors.background
                                }"
                                @click="downloadRecoveryCodes"
                            >
                                <span class="flex items-center justify-center">
                                    <svg
                                        class="w-4 h-4 mr-2"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                        />
                                    </svg>
                                    <span class="truncate">{{ t('downloadRecoveryCodes') }}</span>
                                </span>
                            </button>

                            <button
                                :disabled="isProcessing"
                                :aria-label="t('generateNewRecoveryCodes')"
                                class="w-full py-2 px-4 rounded-lg font-medium disabled:opacity-50 transition-all duration-200 text-sm sm:text-base"
                                :style="{
                                    backgroundColor: derivedColors.surface,
                                    color: derivedColors.textPrimary
                                }"
                                @click="generateNewCodes"
                            >
                                <span v-if="isProcessing" class="flex items-center justify-center">
                                    <svg
                                        class="animate-spin -ml-1 mr-3 h-4 w-4 sm:h-5 sm:w-5"
                                        :style="{ color: derivedColors.textPrimary }"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
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
                                    <span class="text-sm sm:text-base">{{ t('generating') }}...</span>
                                </span>
                                <span v-else class="truncate">{{ t('generateNewCodes') }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 sm:py-8" :style="{ color: derivedColors.textMuted }">
                        <svg
                            class="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-4"
                            :style="{ color: derivedColors.textMuted }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1721 9z"
                            />
                        </svg>
                        <p class="text-sm">{{ t('enable2FAToSeeRecoveryCodes') }}</p>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <h3 class="text-base sm:text-lg font-semibold mb-4 flex items-start sm:items-center" :style="{ color: derivedColors.textPrimary }">
                    <div class="w-2 h-2 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span>{{ t('securityTips') }}</span>
                </h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
                    <div class="flex items-start space-x-3">
                        <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-full flex items-center justify-center flex-shrink-0 border" :style="{ backgroundColor: derivedColors.accent + '20', borderColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-3 h-3 sm:w-4 sm:h-4"
                                :style="{ color: derivedColors.accent }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('useAuthenticatorApp') }}
                            </p>
                            <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('downloadGoogleAuthOrAuthy') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-full flex items-center justify-center flex-shrink-0 border" :style="{ backgroundColor: derivedColors.accent + '20', borderColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-3 h-3 sm:w-4 sm:h-4"
                                :style="{ color: derivedColors.accent }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('backupRecoveryCodes') }}
                            </p>
                            <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('storeCodesSecureLocation') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-full flex items-center justify-center flex-shrink-0 border" :style="{ backgroundColor: derivedColors.accent + '20', borderColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-3 h-3 sm:w-4 sm:h-4"
                                :style="{ color: derivedColors.accent }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('testRegularly') }}
                            </p>
                            <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('verifyCodesWork') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-full flex items-center justify-center flex-shrink-0 border" :style="{ backgroundColor: derivedColors.accent + '20', borderColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-3 h-3 sm:w-4 sm:h-4"
                                :style="{ color: derivedColors.accent }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('keepDeviceSecure') }}
                            </p>
                            <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ t('protectPhoneWithLock') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>

