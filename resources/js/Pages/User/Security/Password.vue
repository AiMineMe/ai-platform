<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const isProcessing = ref(false);
const passwordStrength = ref(0);
const hasBlurredConfirmPassword = ref(false);
const form = ref({
    current_password: '',
    password: '',
    password_confirmation: ''
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
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

const passwordStrengthId = computed(() => 'password-strength-' + Math.random().toString(36).substr(2, 9));
const passwordMatchId = computed(() => 'password-match-' + Math.random().toString(36).substr(2, 9));
const hasMinLength = computed(() => form.value.password.length >= 8);
const hasMixedCase = computed(() => /[a-z]/.test(form.value.password) && /[A-Z]/.test(form.value.password));
const hasNumber = computed(() => /\d/.test(form.value.password));
const hasSpecialChar = computed(() => /[^a-zA-Z0-9]/.test(form.value.password));

const passwordsMatch = computed(() => {
    return form.value.password &&
        form.value.password_confirmation &&
        form.value.password === form.value.password_confirmation;
});

const shouldShowPasswordError = computed(() => {
    return hasBlurredConfirmPassword.value &&
        form.value.password_confirmation &&
        !passwordsMatch.value;
});

const canSubmit = computed(() => {
    return form.value.current_password &&
        form.value.password &&
        form.value.password_confirmation &&
        passwordsMatch.value &&
        passwordStrength.value >= 3 &&
        !isProcessing.value;
});

const passwordStrengthColor = computed(() => {
    const colors = ['bg-red-500', 'bg-red-400', 'bg-yellow-500', derivedColors.value.accent, derivedColors.value.success];
    return colors[passwordStrength.value] || 'bg-gray-500';
});

const passwordStrengthTextColor = computed(() => {
    const colors = ['text-red-400', 'text-red-300', 'text-yellow-400', derivedColors.value.accent, derivedColors.value.success];
    return colors[passwordStrength.value] || derivedColors.value.textMuted;
});

const passwordStrengthWidth = computed(() => {
    return `${(passwordStrength.value + 1) * 20}%`;
});

const passwordStrengthText = computed(() => {
    const texts = [
        t('passwordStrengthVeryWeak'),
        t('passwordStrengthWeak'),
        t('passwordStrengthFair'),
        t('passwordStrengthGood'),
        t('passwordStrengthStrong')
    ];
    return texts[passwordStrength.value] || t('passwordStrengthVeryWeak');
});

const checkPasswordStrength = () => {
    const password = form.value.password;
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    if (password.length >= 12) strength++;
    passwordStrength.value = Math.min(strength, 4);
};

const validatePassword = () => {
    const password = form.value.password;

    if (password.length < 8) {
        showToast(t('passwordMustBe8Characters'), 'error');
        return false;
    }

    if (!hasMixedCase.value) {
        showToast(t('passwordMustContainMixedCase'), 'error');
        return false;
    }

    if (!hasNumber.value) {
        showToast(t('passwordMustContainNumber'), 'error');
        return false;
    }

    if (!hasSpecialChar.value) {
        showToast(t('passwordMustContainSpecialChar'), 'error');
        return false;
    }

    return true;
};

const validatePasswordConfirmation = () => {
    hasBlurredConfirmPassword.value = true;

    if (form.value.password_confirmation && !passwordsMatch.value) {
        showToast(t('passwordConfirmationNoMatch'), 'error');
        return false;
    }
    return true;
};

const formatFieldName = (field) => {
    const fieldMap = {
        current_password: t('currentPassword'),
        password: t('newPassword'),
        password_confirmation: t('confirmNewPassword')
    };
    return fieldMap[field] || field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const resetForm = () => {
    form.value = {
        current_password: '',
        password: '',
        password_confirmation: ''
    };
    passwordStrength.value = 0;
    hasBlurredConfirmPassword.value = false;
    showCurrentPassword.value = false;
    showNewPassword.value = false;
    showConfirmPassword.value = false;
};

const updatePassword = () => {
    if (!canSubmit.value) {
        showToast(t('ensureAllFieldsFilled'), 'error');
        return;
    }
    if (!form.value.current_password) {
        showToast(t('enterCurrentPasswordPlease'), 'error');
        return;
    }

    if (form.value.current_password.length < 6) {
        showToast(t('currentPasswordTooShort'), 'error');
        return;
    }

    if (!validatePassword()) {
        return;
    }

    if (!validatePasswordConfirmation()) {
        return;
    }

    if (form.value.current_password === form.value.password) {
        showToast(t('newPasswordMustBeDifferent'), 'error');
        return;
    }

    isProcessing.value = true;
    router.post('/user/security/password/update', form.value, {
        onSuccess: () => {
            resetForm();
            showToast(t('passwordUpdatedSuccessfully'), 'success');
        },
        onError: (errors) => {
            // Handle specific error messages
            if (errors?.current_password) {
                showToast(t('currentPasswordIncorrect'), 'error');
            } else if (errors?.password) {
                const errorMessage = Array.isArray(errors.password) ? errors.password[0] : errors.password;
                showToast(errorMessage, 'error');
            } else {
                showToast(t('failedToUpdatePassword'), 'error');
            }
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

const isWeakPassword = (password) => {
    const weakPasswords = [
        'password', '12345678', 'qwerty', 'abc123', 'password123',
        'admin', 'letmein', 'welcome', 'monkey', '1234567890'
    ];
    return weakPasswords.includes(password.toLowerCase());
};


const enhancedPasswordValidation = () => {
    const password = form.value.password;
    if (isWeakPassword(password)) {
        showToast(t('passwordTooCommon'), 'error');
        return false;
    }

    if (/123456|abcdef|qwerty/i.test(password)) {
        showToast(t('avoidSequentialCharacters'), 'warning');
    }

    if (/(.)\1{2,}/.test(password)) {
        showToast(t('avoidRepeatedCharacters'), 'warning');
    }

    return true;
};

watch(() => form.value.password, (newPassword) => {
    hasBlurredConfirmPassword.value = false;
    if (newPassword) {
        checkPasswordStrength();
        enhancedPasswordValidation();
    }
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
        :page-title="t('changePassword')"
        :page-section="t('security')"
    >
        <div class="max-w-2xl mx-auto space-y-4 sm:space-y-6 px-4 sm:px-6">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
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
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1721 9z"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                            {{ t('changePassword') }}
                        </h1>
                        <p class="mt-1 text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('updateAccountPassword') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="mb-4 sm:mb-6">
                    <h3 class="text-base sm:text-lg font-semibold mb-2 flex items-start sm:items-center" :style="{ color: derivedColors.textPrimary }">
                        <div class="w-2 h-2 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                        <span>{{ t('passwordRequirements') }}</span>
                    </h3>
                    <div class="border rounded-lg p-3 sm:p-4" :style="requirementsBgStyle">
                        <ul
                            class="text-xs sm:text-sm space-y-1"
                            :style="{ color: derivedColors.textSecondary }"
                            role="list"
                        >
                            <li
                                class="flex items-center space-x-2"
                                role="listitem"
                            >
                                <svg
                                    class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0"
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
                                <span>{{ t('atLeast8Characters') }}</span>
                            </li>
                            <li
                                class="flex items-center space-x-2"
                                role="listitem"
                            >
                                <svg
                                    class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0"
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
                                <span>{{ t('containsUppercaseLowercase') }}</span>
                            </li>
                            <li
                                class="flex items-center space-x-2"
                                role="listitem"
                            >
                                <svg
                                    class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0"
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
                                <span>{{ t('containsAtLeastOneNumber') }}</span>
                            </li>
                            <li
                                class="flex items-center space-x-2"
                                role="listitem"
                            >
                                <svg
                                    class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0"
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
                                <span>{{ t('containsSpecialCharacter') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <form
                    class="space-y-4 sm:space-y-6"
                    novalidate
                    @submit.prevent="updatePassword"
                >
                    <div>
                        <label
                            for="current_password"
                            class="block text-sm font-medium mb-2"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('currentPassword') }}
                        </label>
                        <div class="relative">
                            <input
                                id="current_password"
                                v-model="form.current_password"
                                name="current_password"
                                :type="showCurrentPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                :aria-label="t('enterCurrentPassword')"
                                :aria-describedby="errors?.current_password ? 'current_password_error' : null"
                                :aria-invalid="errors?.current_password ? 'true' : 'false'"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 pr-10 sm:pr-12 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterCurrentPassword')"
                                required
                            >
                            <button
                                type="button"
                                :aria-label="showCurrentPassword ? t('hideCurrentPassword') : t('showCurrentPassword')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center transition-colors duration-200"
                                :style="{ color: derivedColors.textMuted }"
                                @click="showCurrentPassword = !showCurrentPassword"
                            >
                                <svg
                                    v-if="showCurrentPassword"
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
                            v-if="errors?.current_password"
                            id="current_password_error"
                            class="text-red-400 text-sm mt-1"
                            role="alert"
                        >
                            {{ Array.isArray(errors.current_password) ? errors.current_password[0] : errors.current_password }}
                        </div>
                    </div>

                    <div>
                        <label
                            for="new_password"
                            class="block text-sm font-medium mb-2"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('newPassword') }}
                        </label>
                        <div class="relative">
                            <input
                                id="new_password"
                                v-model="form.password"
                                name="new_password"
                                :type="showNewPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                :aria-label="t('enterNewPassword')"
                                :aria-describedby="passwordStrengthId"
                                :aria-invalid="errors?.password ? 'true' : 'false'"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 pr-10 sm:pr-12 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterNewPassword')"
                                required
                                @input="checkPasswordStrength"
                                @blur="validatePassword"
                            >
                            <button
                                type="button"
                                :aria-label="showNewPassword ? t('hideNewPassword') : t('showNewPassword')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center transition-colors duration-200"
                                :style="{ color: derivedColors.textMuted }"
                                @click="showNewPassword = !showNewPassword"
                            >
                                <svg
                                    v-if="showNewPassword"
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
                            v-if="form.password"
                            class="mt-2"
                        >
                            <div class="flex items-center space-x-2">
                                <div
                                    class="flex-1 rounded-full h-2"
                                    :style="{ backgroundColor: derivedColors.border }"
                                    role="progressbar"
                                    :aria-valuenow="passwordStrength"
                                    aria-valuemin="0"
                                    aria-valuemax="4"
                                    :aria-label="`${t('passwordStrength')}: ${passwordStrengthText}`"
                                >
                                    <div
                                        class="h-2 rounded-full transition-all duration-300"
                                        :style="{
                                            width: passwordStrengthWidth,
                                            backgroundColor: passwordStrengthColor === 'bg-red-500' ? '#ef4444' :
                                                           passwordStrengthColor === 'bg-red-400' ? '#f87171' :
                                                           passwordStrengthColor === 'bg-yellow-500' ? '#eab308' :
                                                           passwordStrengthColor === derivedColors.accent ? derivedColors.accent :
                                                           derivedColors.success
                                        }"
                                    />
                                </div>
                                <span
                                    :id="passwordStrengthId"
                                    class="text-xs font-medium flex-shrink-0"
                                    :style="{
                                        color: passwordStrengthTextColor === 'text-red-400' ? '#f87171' :
                                               passwordStrengthTextColor === 'text-red-300' ? '#fca5a5' :
                                               passwordStrengthTextColor === 'text-yellow-400' ? '#facc15' :
                                               passwordStrengthTextColor === derivedColors.accent ? derivedColors.accent :
                                               derivedColors.success
                                    }"
                                >
                                  {{ passwordStrengthText }}
                                </span>
                            </div>
                            <div class="mt-2 space-y-1">
                                <div class="flex items-center space-x-2 text-xs">
                                    <svg
                                        class="w-3 h-3 flex-shrink-0"
                                        :style="{ color: hasMinLength ? derivedColors.accent : derivedColors.textMuted }"
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
                                    <span :style="{ color: hasMinLength ? derivedColors.accent : derivedColors.textMuted }">{{ t('atLeast8CharactersShort') }}</span>
                                </div>
                                <div class="flex items-center space-x-2 text-xs">
                                    <svg
                                        class="w-3 h-3 flex-shrink-0"
                                        :style="{ color: hasMixedCase ? derivedColors.accent : derivedColors.textMuted }"
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
                                    <span :style="{ color: hasMixedCase ? derivedColors.accent : derivedColors.textMuted }">{{ t('upperLowercaseLetters') }}</span>
                                </div>
                                <div class="flex items-center space-x-2 text-xs">
                                    <svg
                                        class="w-3 h-3 flex-shrink-0"
                                        :style="{ color: hasNumber ? derivedColors.accent : derivedColors.textMuted }"
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
                                    <span :style="{ color: hasNumber ? derivedColors.accent : derivedColors.textMuted }">{{ t('atLeastOneNumber') }}</span>
                                </div>
                                <div class="flex items-center space-x-2 text-xs">
                                    <svg
                                        class="w-3 h-3 flex-shrink-0"
                                        :style="{ color: hasSpecialChar ? derivedColors.accent : derivedColors.textMuted }"
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
                                    <span :style="{ color: hasSpecialChar ? derivedColors.accent : derivedColors.textMuted }">{{ t('specialCharacter') }}</span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="errors?.password"
                            class="text-red-400 text-sm mt-1"
                            role="alert"
                        >
                            {{ Array.isArray(errors.password) ? errors.password[0] : errors.password }}
                        </div>
                    </div>

                    <div>
                        <label
                            for="confirm_password"
                            class="block text-sm font-medium mb-2"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('confirmNewPassword') }}
                        </label>
                        <div class="relative">
                            <input
                                id="confirm_password"
                                v-model="form.password_confirmation"
                                name="confirm_password"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                :aria-label="t('confirmNewPassword2')"
                                :aria-describedby="passwordMatchId"
                                :aria-invalid="shouldShowPasswordError ? 'true' : 'false'"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 pr-10 sm:pr-12 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{
                                    ...inputStyle,
                                    borderColor: shouldShowPasswordError ? '#ef4444' : inputStyle.borderColor,
                                    ':focus': { borderColor: derivedColors.accent }
                                }"
                                :placeholder="t('confirmNewPassword2')"
                                required
                                @blur="validatePasswordConfirmation"
                            >
                            <button
                                type="button"
                                :aria-label="showConfirmPassword ? t('hidePasswordConfirmation') : t('showPasswordConfirmation')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center transition-colors duration-200"
                                :style="{ color: derivedColors.textMuted }"
                                @click="showConfirmPassword = !showConfirmPassword"
                            >
                                <svg
                                    v-if="showConfirmPassword"
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
                            v-if="form.password && form.password_confirmation"
                            class="mt-2"
                        >
                            <div
                                v-if="passwordsMatch"
                                :id="passwordMatchId"
                                class="flex items-center space-x-2"
                                :style="{ color: derivedColors.accent }"
                                role="status"
                            >
                                <svg
                                    class="w-4 h-4 flex-shrink-0"
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
                                <span class="text-xs">{{ t('passwordsMatch') }}</span>
                            </div>
                            <div
                                v-else-if="shouldShowPasswordError"
                                :id="passwordMatchId"
                                class="flex items-center space-x-2 text-red-400"
                                role="alert"
                            >
                                <svg
                                    class="w-4 h-4 flex-shrink-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                                <span class="text-xs">{{ t('passwordsDoNotMatch') }}</span>
                            </div>
                        </div>
                        <div
                            v-if="errors?.password_confirmation"
                            class="text-red-400 text-sm mt-1"
                            role="alert"
                        >
                            {{ Array.isArray(errors.password_confirmation) ? errors.password_confirmation[0] : errors.password_confirmation }}
                        </div>
                    </div>

                    <div
                        v-if="errors && Object.keys(errors).length > 0"
                        class="border rounded-lg p-3 sm:p-4"
                        :style="{ backgroundColor: '#ef444420', borderColor: '#ef444450' }"
                        role="alert"
                    >
                        <h4 class="text-red-300 font-medium mb-2 flex items-start sm:items-center text-sm">
                            <div class="w-2 h-2 bg-red-400 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0"></div>
                            <span>{{ t('pleaseFixFollowingErrors') }}</span>
                        </h4>
                        <ul class="text-red-200 text-xs sm:text-sm space-y-1">
                            <li
                                v-for="(error, field) in errors"
                                :key="field"
                                class="break-words"
                            >
                                <strong>{{ formatFieldName(field) }}:</strong> {{ Array.isArray(error) ? error[0] : error }}
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3 pt-4 border-t" :style="{ borderColor: derivedColors.border }">
                        <button
                            type="button"
                            :disabled="isProcessing"
                            :aria-label="t('resetFormClear')"
                            class="w-full sm:flex-1 py-2 sm:py-3 px-4 rounded-lg font-medium transition-colors duration-200"
                            :style="{ backgroundColor: derivedColors.surface, color: derivedColors.textPrimary }"
                            @click="resetForm"
                        >
                            {{ t('resetForm') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="!canSubmit || isProcessing"
                            :aria-label="isProcessing ? t('updatingPassword') : t('updatePassword')"
                            class="w-full sm:flex-1 py-2 sm:py-3 px-4 rounded-lg font-bold disabled:opacity-50 transition-all duration-200 shadow-lg hover:scale-105"
                            :style="{
                                backgroundColor: canSubmit && !isProcessing ? derivedColors.accent : derivedColors.surface,
                                color: canSubmit && !isProcessing ? derivedColors.background : derivedColors.textMuted
                            }"
                        >
              <span
                  v-if="isProcessing"
                  class="flex items-center justify-center"
              >
                <svg
                    class="animate-spin -ml-1 mr-2 h-4 w-4"
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
                <span class="text-sm sm:text-base">{{ t('updating') }}</span>
              </span>
                            <span v-else>{{ t('updatePassword') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <h3 class="text-base sm:text-lg font-semibold mb-4 sm:mb-6 flex items-start sm:items-center" :style="{ color: derivedColors.textPrimary }">
                    <div class="w-2 h-2 rounded-full mr-3 mt-1 sm:mt-0 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                    <span>{{ t('securityTips') }}</span>
                </h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-4 h-4 sm:w-5 sm:h-5"
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
                            <p class="text-sm sm:text-base font-medium mb-1" :style="{ color: derivedColors.textPrimary }">
                                {{ t('useUniquePassword') }}
                            </p>
                            <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('dontReusePasswords') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-4 h-4 sm:w-5 sm:h-5"
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
                            <p class="text-sm sm:text-base font-medium mb-1" :style="{ color: derivedColors.textPrimary }">
                                {{ t('enableTwoFA') }}
                            </p>
                            <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('addExtraSecurity') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-4 h-4 sm:w-5 sm:h-5"
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
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm sm:text-base font-medium mb-1" :style="{ color: derivedColors.textPrimary }">
                                {{ t('regularUpdates') }}
                            </p>
                            <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('changePasswordRegularly') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg
                                class="w-4 h-4 sm:w-5 sm:h-5"
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
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm sm:text-base font-medium mb-1" :style="{ color: derivedColors.textPrimary }">
                                {{ t('avoidSharing') }}
                            </p>
                            <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('neverSharePassword') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
