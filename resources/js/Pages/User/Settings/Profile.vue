<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    user: {
        type: Object,
        required: true,
        validator: (value) => typeof value === 'object' && value.name && value.email && value.phone
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const isProcessing = ref(false);
const avatarUploading = ref(false);
const fileInput = ref(null);
const profileForm = ref({
    name: props.user.name || '',
    email: props.user.email || '',
    phone: props.user.phone || ''
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

const linkStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.border
}));

const canSubmit = computed(() => {
    const name = profileForm.value.name || '';
    const email = profileForm.value.email || '';
    const phone = profileForm.value.phone || '';

    return name.trim() &&
        email.trim() &&
        phone.trim() &&
        !isProcessing.value &&
        !avatarUploading.value;
});

const getInitials = (name) => {
    return name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
};

const formatDate = (dateString) => {
    if (!dateString) return t('notAvailable');
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleAvatarUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        showToast(t('selectValidImageFile'), 'error');
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        showToast(t('imageSizeTooLarge'), 'error');
        return;
    }

    avatarUploading.value = true;
    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('name', profileForm.value.name || '');
    formData.append('email', profileForm.value.email || '');
    formData.append('phone', profileForm.value.phone || '');

    router.post('/user/settings/profile', formData, {
        onSuccess: () => {
            showToast(t('profilePictureUpdated'), 'success');
        },
        onError: () => {
            showToast(t('failedToUpdatePicture'), 'error');
        },
        onFinish: () => {
            avatarUploading.value = false;
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
        preserveScroll: true
    });
};

const updateProfile = () => {
    if (!canSubmit.value) {
        showToast(t('fillAllRequiredFields'), 'error');
        return;
    }

    isProcessing.value = true;
    router.post('/user/settings/profile', profileForm.value, {
        onSuccess: () => {
            showToast(t('profileUpdatedSuccessfully'), 'success');
        },
        onError: () => {
            showToast(t('failedToUpdateProfile'), 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
};

const removeAvatar = () => {
    if (avatarUploading.value) return;
    avatarUploading.value = true;
    router.delete('/user/settings/profile/avatar', {
        onSuccess: () => {
            showToast(t('profilePictureRemoved'), 'success');
        },
        onError: () => {
            showToast(t('failedToRemovePicture'), 'error');
        },
        onFinish: () => {
            avatarUploading.value = false;
        },
        preserveScroll: true
    });
};

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
        :page-title="t('profileSettings')"
        :page-section="t('settings')"
    >
        <div class="max-w-6xl mx-auto space-y-6 px-4">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="relative flex-shrink-0 self-center sm:self-auto">
                        <div
                            v-if="user.avatar_url"
                            class="h-16 w-16 sm:h-20 sm:w-20 rounded-full overflow-hidden border-2"
                            :style="{ borderColor: derivedColors.border + '30' }"
                        >
                            <img
                                :src="user.avatar_url"
                                :alt="user.name"
                                class="h-full w-full object-cover"
                            >
                        </div>
                        <div
                            v-else
                            class="h-16 w-16 sm:h-20 sm:w-20 rounded-full flex items-center justify-center"
                            :style="{ backgroundColor: derivedColors.accent }"
                        >
                            <span class="text-xl sm:text-2xl font-bold" :style="{ color: derivedColors.background }">
                                {{ getInitials(user.name) }}
                            </span>
                        </div>

                        <button
                            :aria-label="t('uploadProfilePicture')"
                            :disabled="avatarUploading"
                            class="absolute -bottom-1 -right-1 h-8 w-8 rounded-full flex items-center justify-center disabled:opacity-50 transition-all duration-200 shadow-lg hover:scale-105"
                            :style="{ backgroundColor: derivedColors.accent }"
                            @click="triggerFileInput"
                        >
                            <svg
                                class="w-4 h-4"
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
                                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                        </button>

                        <div
                            v-if="avatarUploading"
                            class="absolute inset-0 bg-black/50 flex items-center justify-center rounded-full"
                        >
                            <div class="animate-spin w-6 h-6 border-2 border-white border-t-transparent rounded-full" />
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleAvatarUpload"
                        >
                    </div>
                    <div class="min-w-0 flex-1 text-center sm:text-left">
                        <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                            {{ user.name }}
                        </h1>
                        <p class="break-all" :style="{ color: derivedColors.textMuted }">
                            {{ user.email }}
                        </p>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mt-2">
                            <div class="flex items-center justify-center sm:justify-start gap-1">
                                <svg
                                    v-if="user.email_verified_at"
                                    class="w-4 h-4 flex-shrink-0"
                                    :style="{ color: derivedColors.success }"
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
                                <svg
                                    v-else
                                    class="w-4 h-4 text-red-400 flex-shrink-0"
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
                                <span
                                    class="text-xs sm:text-sm"
                                    :style="{ color: user.email_verified_at ? derivedColors.success : '#ef4444' }"
                                >
                                    {{ user.email_verified_at ? t('emailVerified') : t('emailNotVerified') }}
                                </span>
                            </div>
                            <span class="text-xs sm:text-sm text-center sm:text-left" :style="{ color: derivedColors.textMuted }">
                                {{ t('memberSince') }} {{ formatDate(user.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
                        <div class="h-12 w-12 rounded-full flex items-center justify-center flex-shrink-0 self-center sm:self-auto" :style="{ backgroundColor: derivedColors.accent }">
                            <svg
                                class="w-6 h-6"
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
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                        </div>
                        <div class="text-center sm:text-left">
                            <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                {{ t('profileInformation') }}
                            </h3>
                            <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                {{ t('updateAccountDetails') }}
                            </p>
                        </div>
                    </div>

                    <form class="space-y-4" novalidate @submit.prevent="updateProfile">
                        <div>
                            <label
                                for="profile_name"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('fullName') }}
                            </label>
                            <input
                                id="profile_name"
                                v-model="profileForm.name"
                                name="profile_name"
                                type="text"
                                autocomplete="name"
                                :aria-label="t('enterFullName')"
                                :aria-invalid="errors?.name ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-sm sm:text-base"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterFullName')"
                                required
                            >
                            <div
                                v-if="errors?.name"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="profile_email"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('emailAddress') }}
                            </label>
                            <input
                                id="profile_email"
                                v-model="profileForm.email"
                                name="profile_email"
                                type="email"
                                autocomplete="email"
                                :aria-label="t('enterEmailAddress')"
                                :aria-invalid="errors?.email ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 text-sm sm:text-base break-all"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterEmailAddress')"
                                required
                            >
                            <div
                                v-if="errors?.email"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.email) ? errors.email[0] : errors.email }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="profile_phone"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('phoneNumber') }}
                            </label>
                            <input
                                id="profile_phone"
                                v-model="profileForm.phone"
                                name="profile_phone"
                                type="tel"
                                autocomplete="tel"
                                :aria-label="t('enterPhoneNumber')"
                                :aria-invalid="errors?.phone ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 focus:border-opacity-100 text-sm sm:text-base"
                                :style="inputStyle"
                                :placeholder="t('enterPhoneNumber')"
                                required
                            >
                            <div
                                v-if="errors?.phone"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.phone) ? errors.phone[0] : errors.phone }}
                            </div>
                        </div>

                        <div
                            v-if="user.avatar_url"
                            class="flex items-center justify-between p-3 rounded-lg border"
                            :style="{ backgroundColor: derivedColors.surface + '30', borderColor: derivedColors.border }"
                        >
                            <span class="text-sm truncate" :style="{ color: derivedColors.textSecondary }">
                                {{ t('profilePicture') }}
                            </span>
                            <button
                                type="button"
                                :disabled="avatarUploading"
                                class="text-sm text-red-400 hover:text-red-300 transition-colors duration-200 disabled:opacity-50 flex-shrink-0 ml-2"
                                @click="removeAvatar"
                            >
                                {{ t('remove') }}
                            </button>
                        </div>

                        <div
                            v-if="errors && Object.keys(errors).length > 0"
                            class="border rounded-lg p-4"
                            :style="{ backgroundColor: '#ef444420', borderColor: '#ef444450' }"
                            role="alert"
                        >
                            <h4 class="text-red-300 font-medium mb-2 flex items-center">
                                <div class="w-2 h-2 bg-red-400 rounded-full mr-3 flex-shrink-0"></div>
                                <span class="text-sm">{{ t('pleaseFixFollowingErrors') }}</span>
                            </h4>
                            <ul class="text-red-200 text-sm space-y-1">
                                <li
                                    v-for="(error, field) in errors"
                                    :key="field"
                                    class="break-words"
                                >
                                    <strong>{{ field }}:</strong> {{ Array.isArray(error) ? error[0] : error }}
                                </li>
                            </ul>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            :aria-label="isProcessing ? t('updating') : t('updateProfile')"
                            class="w-full py-3 px-4 rounded-lg font-bold disabled:opacity-50 transition-all duration-200 shadow-lg hover:scale-105 text-sm sm:text-base"
                            :style="{
                                backgroundColor: canSubmit ? derivedColors.accent : derivedColors.surface,
                                color: canSubmit ? derivedColors.background : derivedColors.textMuted
                            }"
                        >
                            <span v-if="isProcessing" class="flex items-center justify-center">
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
                                {{ t('updating') }}...
                            </span>
                            <span v-else>{{ t('updateProfile') }}</span>
                        </button>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                            <div class="h-12 w-12 rounded-full flex items-center justify-center flex-shrink-0 self-center sm:self-auto" :style="{ backgroundColor: derivedColors.accent }">
                                <svg
                                    class="w-6 h-6"
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
                            <div class="text-center sm:text-left">
                                <h3 class="text-lg font-semibold flex items-center justify-center sm:justify-start" :style="{ color: derivedColors.textPrimary }">
                                    <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                                    {{ t('accountSecurity') }}
                                </h3>
                                <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                    {{ t('manageSecuritySettings') }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <Link
                                href="/user/security/password"
                                class="flex items-center justify-between p-3 rounded-lg border hover:opacity-80 transition-all duration-200"
                                :style="{ ...linkStyle, ':hover': { borderColor: derivedColors.accent + '40' } }"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-6 w-6 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                        <svg
                                            class="w-3 h-3"
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
                                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1 1 21 9z"
                                            />
                                        </svg>
                                    </div>
                                    <span class="text-sm sm:text-base truncate" :style="{ color: derivedColors.textPrimary }">{{ t('changePassword') }}</span>
                                </div>
                                <svg
                                    class="w-4 h-4 flex-shrink-0"
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
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                            </Link>

                            <Link
                                href="/user/security/2fa"
                                class="flex items-center justify-between p-3 rounded-lg border hover:opacity-80 transition-all duration-200"
                                :style="{ ...linkStyle, ':hover': { borderColor: derivedColors.accent + '40' } }"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-6 w-6 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                        <svg
                                            class="w-3 h-3"
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
                                    <span class="text-sm sm:text-base truncate" :style="{ color: derivedColors.textPrimary }">{{ t('twoFactorAuthentication') }}</span>
                                </div>
                                <svg
                                    class="w-4 h-4 flex-shrink-0"
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
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
