<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    kyc: {
        type: Object,
        default: null,
        validator: (value) => value === null || typeof value === 'object'
    },
    countries: {
        type: Array,
        required: true,
        validator: (value) => Array.isArray(value)
    },
    errors: {
        type: Object,
        default: () => ({}),
        validator: (value) => typeof value === 'object'
    }
});

const submitting = ref(false);
const fileUploading = reactive({
    document_front: false,
    document_back: false,
    selfie: false
});

const form = reactive({
    first_name: props.kyc?.first_name || '',
    last_name: props.kyc?.last_name || '',
    date_of_birth: props.kyc?.date_of_birth || '',
    phone: props.kyc?.phone || '',
    address: props.kyc?.address || '',
    city: props.kyc?.city || '',
    state: props.kyc?.state || '',
    country: props.kyc?.country || '',
    postal_code: props.kyc?.postal_code || '',
    document_type: props.kyc?.document_type || '',
    document_number: props.kyc?.document_number || '',
    document_front: null,
    document_back: null,
    selfie: null,
    agree_terms: false
});

const filePreviews = reactive({});
const documentFrontInput = ref(null);
const documentBackInput = ref(null);
const selfieInput = ref(null);
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

const uploadStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '30',
    borderColor: derivedColors.value.accent + '30'
}));

const canSubmit = computed(() => {
    return form.agree_terms &&
        !submitting.value &&
        !isAnyFileUploading.value &&
        form.first_name &&
        form.last_name &&
        form.date_of_birth &&
        form.phone &&
        form.address &&
        form.city &&
        form.state &&
        form.country &&
        form.postal_code &&
        form.document_type &&
        form.document_number &&
        form.document_front &&
        form.selfie &&
        (form.document_type === 'passport' || form.document_back);
});

const isAnyFileUploading = computed(() => {
    return Object.values(fileUploading).some(uploading => uploading);
});

const getStatusTitle = () => {
    const titles = {
        pending: t('verificationPending'),
        reviewing: t('underReview'),
        approved: t('verificationApproved'),
        rejected: t('verificationRejected')
    };
    return titles[props.kyc?.status] || t('unknownStatus');
};

const getStatusDescription = () => {
    const descriptions = {
        pending: t('pendingDescription'),
        reviewing: t('reviewingDescription'),
        approved: t('approvedDescription'),
        rejected: t('rejectedDescription')
    };
    return descriptions[props.kyc?.status] || t('statusUnavailable');
};

const getStatusColor = () => {
    switch (props.kyc?.status) {
        case 'approved':
            return derivedColors.value.success;
        case 'rejected':
            return '#ef4444';
        case 'reviewing':
            return '#3b82f6';
        default:
            return derivedColors.value.warning;
    }
};

const formatDateTime = (datetime) => {
    if (!datetime) return '';
    return new Date(datetime).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatFieldName = (field) => {
    return field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const handleFileUpload = (field, event) => {
    const file = event.target.files[0];
    if (!file) return;

    fileUploading[field] = true;
    const maxSize = 8 * 1024 * 1024;
    if (file.size > maxSize) {
        showToast(t('fileSizeTooLarge', { size: (file.size / (1024 * 1024)).toFixed(2) }), 'error');
        event.target.value = '';
        fileUploading[field] = false;
        return;
    }

    if (!file.type.startsWith('image/')) {
        showToast(t('selectImageFileOnly'), 'error');
        event.target.value = '';
        fileUploading[field] = false;
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        showToast(t('onlyJpegPngAllowed'), 'error');
        event.target.value = '';
        fileUploading[field] = false;
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        compressImage(file, field);
    } else {
        form[field] = file;
        createPreview(file, field);
        showToast(t('fileUploadedSuccessfully'), 'success');
        fileUploading[field] = false;
    }
};

const compressImage = (file, field) => {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();

    img.onload = () => {
        const maxWidth = 1920;
        const maxHeight = 1920;
        let { width, height } = img;

        if (width > height) {
            if (width > maxWidth) {
                height = (height * maxWidth) / width;
                width = maxWidth;
            }
        } else {
            if (height > maxHeight) {
                width = (width * maxHeight) / height;
                height = maxHeight;
            }
        }

        canvas.width = width;
        canvas.height = height;
        ctx.drawImage(img, 0, 0, width, height);

        canvas.toBlob((blob) => {
            if (blob) {
                const compressedFile = new File([blob], file.name, {
                    type: 'image/jpeg',
                    lastModified: Date.now()
                });

                form[field] = compressedFile;
                createPreview(compressedFile, field);
                showToast(t('imageCompressedSuccessfully'), 'success');
                fileUploading[field] = false;
            } else {
                showToast(t('failedToCompressImage'), 'error');
                fileUploading[field] = false;
            }
        }, 'image/jpeg', 0.8);
    };

    img.onerror = () => {
        showToast(t('failedToLoadImage'), 'error');
        fileUploading[field] = false;
    };

    img.src = URL.createObjectURL(file);
};

const createPreview = (file, field) => {
    const reader = new FileReader();
    reader.onload = (e) => {
        filePreviews[field] = e.target.result;
    };
    reader.readAsDataURL(file);
};

const getFilePreview = (field) => {
    return filePreviews[field] || '';
};

const removeFile = (field) => {
    form[field] = null;
    delete filePreviews[field];

    const inputRefMap = {
        document_front: documentFrontInput,
        document_back: documentBackInput,
        selfie: selfieInput
    };

    const inputRef = inputRefMap[field];
    if (inputRef?.value) {
        inputRef.value.value = '';
    }
};

const submitKyc = async () => {
    if (!canSubmit.value) {
        if (!form.agree_terms) {
            showToast(t('agreeToTermsRequired'), 'error');
        } else {
            showToast(t('fillAllRequiredFields'), 'error');
        }
        return;
    }

    submitting.value = true;

    try {
        const formData = new FormData();

        Object.keys(form).forEach(key => {
            if (key !== 'agree_terms' && form[key] !== null) {
                formData.append(key, form[key]);
            }
        });

        const url = props.kyc && props.kyc.status === 'rejected'
            ? '/user/settings/kyc/resubmit'
            : '/user/settings/kyc/submit';

        router.post(url, formData, {
            forceFormData: true,
            onSuccess: () => {
                resetForm();
                showToast(t('kycSubmittedSuccessfully'), 'success');
            },
            onError: () => {
                showToast(t('failedToSubmitKyc'), 'error');
            },
            onFinish: () => {
                submitting.value = false;
            }
        });
    } catch (error) {
        showToast(t('unexpectedError'), 'error');
        submitting.value = false;
    }
};

const resetForm = () => {
    Object.assign(form, {
        first_name: '',
        last_name: '',
        date_of_birth: '',
        phone: '',
        address: '',
        city: '',
        state: '',
        country: '',
        postal_code: '',
        document_type: '',
        document_number: '',
        document_front: null,
        document_back: null,
        selfie: null,
        agree_terms: false
    });

    Object.keys(filePreviews).forEach(key => {
        delete filePreviews[key];
    });

    Object.assign(fileUploading, {
        document_front: false,
        document_back: false,
        selfie: false
    });
};

watch(() => form.document_type, (newType) => {
    if (newType === 'passport') {
        removeFile('document_back');
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
        :page-title="t('kycVerification')"
        :page-section="t('settings')"
    >
        <div class="max-w-6xl mx-auto space-y-6 px-4">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
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
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ t('kycVerification') }}
                            </h1>
                            <p class="mt-1 text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                                {{ t('verifyIdentityToUnlock') }}
                            </p>
                        </div>
                    </div>
                    <div v-if="kyc" class="flex items-center space-x-2 flex-shrink-0">
                        <div
                            class="h-3 w-3 rounded-full"
                            :style="{ backgroundColor: getStatusColor() }"
                        />
                        <span
                            class="text-xs sm:text-sm font-medium whitespace-nowrap"
                            :style="{ color: getStatusColor() }"
                        >
                            {{ kyc.status_label }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="kyc" class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-start space-y-3 sm:space-y-0 sm:space-x-4">
                    <div
                        class="h-10 w-10 sm:h-12 sm:w-12 rounded-full flex items-center justify-center flex-shrink-0"
                        :style="{ backgroundColor: getStatusColor() + '20' }"
                    >
                        <svg
                            v-if="kyc.status === 'approved'"
                            class="w-5 h-5 sm:w-6 sm:h-6"
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
                            v-else-if="kyc.status === 'rejected'"
                            class="w-5 h-5 sm:w-6 sm:h-6 text-red-400"
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
                        <svg
                            v-else-if="kyc.status === 'reviewing'"
                            class="w-5 h-5 sm:w-6 sm:h-6 text-blue-400"
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
                        <svg
                            v-else
                            class="w-5 h-5 sm:w-6 sm:h-6"
                            :style="{ color: derivedColors.warning }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base sm:text-lg font-semibold mb-2 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }"></div>
                            <span class="truncate">{{ getStatusTitle() }}</span>
                        </h3>
                        <p class="mb-4 text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                            {{ getStatusDescription() }}
                        </p>

                        <div
                            v-if="kyc.status === 'rejected'"
                            class="border rounded-lg p-3 sm:p-4 mb-4"
                            :style="{ backgroundColor: '#ef444420', borderColor: '#ef444450' }"
                        >
                            <h4 class="text-red-300 font-medium mb-2 flex items-center text-sm sm:text-base">
                                <div class="w-2 h-2 bg-red-400 rounded-full mr-3 flex-shrink-0"></div>
                                <span class="truncate">{{ t('rejectionReason') }}</span>
                            </h4>
                            <p class="text-red-200 text-xs sm:text-sm break-words">
                                {{ kyc.rejection_reason }}
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 space-y-2 sm:space-y-0 text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            <span v-if="kyc.submitted_at" class="break-words">{{ t('submitted') }}: {{ formatDateTime(kyc.submitted_at) }}</span>
                            <span v-if="kyc.reviewed_at" class="break-words">{{ t('reviewed') }}: {{ formatDateTime(kyc.reviewed_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!kyc || kyc.status === 'rejected'" class="backdrop-blur-sm rounded-xl shadow-lg border p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-center space-y-3 sm:space-y-0 sm:space-x-3 mb-4 sm:mb-6">
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
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base sm:text-lg font-semibold truncate" :style="{ color: derivedColors.textPrimary }">
                            {{ kyc ? t('resubmitKycDocuments') : t('submitKycDocuments') }}
                        </h3>
                        <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('provideAccurateInformation') }}
                        </p>
                    </div>
                </div>

                <form class="space-y-6" novalidate @submit.prevent="submitKyc">
                    <fieldset class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <legend class="sr-only">{{ t('personalInformation') }}</legend>

                        <div>
                            <label
                                for="first_name"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('firstName') }}</label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                name="first_name"
                                type="text"
                                autocomplete="given-name"
                                :aria-describedby="errors.first_name ? 'first_name_error' : null"
                                :aria-invalid="errors.first_name ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterFirstName')"
                                required
                            >
                            <div
                                v-if="errors.first_name"
                                id="first_name_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.first_name) ? errors.first_name[0] : errors.first_name }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="last_name"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('lastName') }}</label>
                            <input
                                id="last_name"
                                v-model="form.last_name"
                                name="last_name"
                                type="text"
                                autocomplete="family-name"
                                :aria-describedby="errors.last_name ? 'last_name_error' : null"
                                :aria-invalid="errors.last_name ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterLastName')"
                                required
                            >
                            <div
                                v-if="errors.last_name"
                                id="last_name_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.last_name) ? errors.last_name[0] : errors.last_name }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="date_of_birth"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('dateOfBirth') }}</label>
                            <input
                                id="date_of_birth"
                                v-model="form.date_of_birth"
                                name="date_of_birth"
                                type="date"
                                autocomplete="bday"
                                :aria-describedby="errors.date_of_birth ? 'date_of_birth_error' : null"
                                :aria-invalid="errors.date_of_birth ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                required
                            >
                            <div
                                v-if="errors.date_of_birth"
                                id="date_of_birth_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.date_of_birth) ? errors.date_of_birth[0] : errors.date_of_birth }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="phone"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('phoneNumber') }}</label>
                            <input
                                id="phone"
                                v-model="form.phone"
                                name="phone"
                                type="tel"
                                autocomplete="tel"
                                :aria-describedby="errors.phone ? 'phone_error' : null"
                                :aria-invalid="errors.phone ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterPhoneNumber')"
                                required
                            >
                            <div
                                v-if="errors.phone"
                                id="phone_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.phone) ? errors.phone[0] : errors.phone }}
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="sr-only">{{ t('addressInformation') }}</legend>

                        <div class="mb-4">
                            <label
                                for="address"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('address') }}</label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                name="address"
                                rows="3"
                                autocomplete="street-address"
                                :aria-describedby="errors.address ? 'address_error' : null"
                                :aria-invalid="errors.address ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400 resize-none"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterAddress')"
                                required
                            />
                            <div
                                v-if="errors.address"
                                id="address_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.address) ? errors.address[0] : errors.address }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label
                                    for="city"
                                    class="block text-sm font-medium mb-2"
                                    :style="{ color: derivedColors.textSecondary }"
                                >{{ t('city') }}</label>
                                <input
                                    id="city"
                                    v-model="form.city"
                                    name="city"
                                    type="text"
                                    autocomplete="address-level2"
                                    :aria-describedby="errors.city ? 'city_error' : null"
                                    :aria-invalid="errors.city ? 'true' : 'false'"
                                    class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    :placeholder="t('enterCity')"
                                    required
                                >
                                <div
                                    v-if="errors.city"
                                    id="city_error"
                                    class="text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    {{ Array.isArray(errors.city) ? errors.city[0] : errors.city }}
                                </div>
                            </div>

                            <div>
                                <label
                                    for="state"
                                    class="block text-sm font-medium mb-2"
                                    :style="{ color: derivedColors.textSecondary }"
                                >{{ t('stateProvince') }}</label>
                                <input
                                    id="state"
                                    v-model="form.state"
                                    name="state"
                                    type="text"
                                    autocomplete="address-level1"
                                    :aria-describedby="errors.state ? 'state_error' : null"
                                    :aria-invalid="errors.state ? 'true' : 'false'"
                                    class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    :placeholder="t('enterState')"
                                    required
                                >
                                <div
                                    v-if="errors.state"
                                    id="state_error"
                                    class="text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    {{ Array.isArray(errors.state) ? errors.state[0] : errors.state }}
                                </div>
                            </div>

                            <div>
                                <label
                                    for="postal_code"
                                    class="block text-sm font-medium mb-2"
                                    :style="{ color: derivedColors.textSecondary }"
                                >{{ t('postalCode') }}</label>
                                <input
                                    id="postal_code"
                                    v-model="form.postal_code"
                                    name="postal_code"
                                    type="text"
                                    autocomplete="postal-code"
                                    :aria-describedby="errors.postal_code ? 'postal_code_error' : null"
                                    :aria-invalid="errors.postal_code ? 'true' : 'false'"
                                    class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                    :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                    :placeholder="t('enterPostalCode')"
                                    required
                                >
                                <div
                                    v-if="errors.postal_code"
                                    id="postal_code_error"
                                    class="text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    {{ Array.isArray(errors.postal_code) ? errors.postal_code[0] : errors.postal_code }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label
                                for="country"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('country') }}</label>
                            <select
                                id="country"
                                v-model="form.country"
                                name="country"
                                autocomplete="country-name"
                                :aria-describedby="errors.country ? 'country_error' : null"
                                :aria-invalid="errors.country ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                required
                            >
                                <option value="">{{ t('selectCountry') }}</option>
                                <option
                                    v-for="country in countries"
                                    :key="country"
                                    :value="country"
                                >
                                    {{ country }}
                                </option>
                            </select>
                            <div
                                v-if="errors.country"
                                id="country_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.country) ? errors.country[0] : errors.country }}
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <legend class="sr-only">{{ t('documentInformation') }}</legend>

                        <div>
                            <label
                                for="document_type"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('documentType') }}</label>
                            <select
                                id="document_type"
                                v-model="form.document_type"
                                name="document_type"
                                :aria-describedby="errors.document_type ? 'document_type_error' : null"
                                :aria-invalid="errors.document_type ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                required
                            >
                                <option value="">{{ t('selectDocumentType') }}</option>
                                <option value="passport">{{ t('passport') }}</option>
                                <option value="driver_license">{{ t('driversLicense') }}</option>
                                <option value="national_id">{{ t('nationalId') }}</option>
                            </select>
                            <div
                                v-if="errors.document_type"
                                id="document_type_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.document_type) ? errors.document_type[0] : errors.document_type }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="document_number"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >{{ t('documentNumber') }}</label>
                            <input
                                id="document_number"
                                v-model="form.document_number"
                                name="document_number"
                                type="text"
                                :aria-describedby="errors.document_number ? 'document_number_error' : null"
                                :aria-invalid="errors.document_number ? 'true' : 'false'"
                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 placeholder-gray-400"
                                :style="{ ...inputStyle, ':focus': { borderColor: derivedColors.accent } }"
                                :placeholder="t('enterDocumentNumber')"
                                required
                            >
                            <div
                                v-if="errors.document_number"
                                id="document_number_error"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ errors.document_number }}
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-4">
                        <legend class="text-lg font-semibold mb-4 flex items-center" :style="{ color: derivedColors.textPrimary }">
                            <div class="w-2 h-2 rounded-full mr-3" :style="{ backgroundColor: derivedColors.accent }"></div>
                            {{ t('documentUpload') }}
                        </legend>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label
                                    for="document_front_input"
                                    class="block text-sm font-medium mb-2"
                                    :style="{ color: derivedColors.textSecondary }"
                                >
                                    {{ t('documentFrontSide') }} <span class="text-red-400">*</span>
                                </label>
                                <div class="border-2 border-dashed rounded-lg p-6 text-center hover:opacity-80 transition-colors relative" :style="uploadStyle">
                                    <div
                                        v-if="fileUploading.document_front"
                                        class="absolute inset-0 bg-black/50 flex items-center justify-center rounded-lg z-10"
                                    >
                                        <div class="flex flex-col items-center space-y-2">
                                            <div class="animate-spin w-8 h-8 border-2 border-white border-t-transparent rounded-full" />
                                            <span class="text-white text-sm">{{ t('processing') }}...</span>
                                        </div>
                                    </div>

                                    <input
                                        id="document_front_input"
                                        ref="documentFrontInput"
                                        type="file"
                                        accept="image/*"
                                        :disabled="fileUploading.document_front"
                                        class="hidden"
                                        @change="handleFileUpload('document_front', $event)"
                                    >
                                    <div
                                        v-if="!form.document_front"
                                        class="cursor-pointer"
                                        role="button"
                                        tabindex="0"
                                        :aria-label="t('uploadDocumentFrontSide')"
                                        @click="$refs.documentFrontInput.click()"
                                        @keydown.enter="$refs.documentFrontInput.click()"
                                        @keydown.space.prevent="$refs.documentFrontInput.click()"
                                    >
                                        <div class="h-12 w-12 rounded-full flex items-center justify-center mx-auto mb-4" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                            <svg
                                                class="w-6 h-6"
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
                                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                                />
                                            </svg>
                                        </div>
                                        <p class="font-medium" :style="{ color: derivedColors.textSecondary }">
                                            {{ t('clickToUploadFrontSide') }}
                                        </p>
                                        <p class="text-xs mt-1" :style="{ color: derivedColors.textMuted }">
                                            {{ t('pngJpgUpTo8MB') }}
                                        </p>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <img
                                            :src="getFilePreview('document_front')"
                                            :alt="t('documentFrontPreview')"
                                            class="max-h-32 mx-auto rounded border"
                                            :style="{ borderColor: derivedColors.accent + '20' }"
                                        >
                                        <p class="text-sm font-medium" :style="{ color: derivedColors.accent }">
                                            {{ form.document_front.name }}
                                        </p>
                                        <button
                                            type="button"
                                            :disabled="fileUploading.document_front"
                                            class="text-sm text-red-400 hover:text-red-300 transition-colors disabled:opacity-50"
                                            :aria-label="t('removeDocumentFrontImage')"
                                            @click="removeFile('document_front')"
                                        >
                                            {{ t('remove') }}
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-if="errors.document_front"
                                    class="text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    {{ Array.isArray(errors.document_front) ? errors.document_front[0] : errors.document_front }}
                                </div>
                            </div>

                            <div v-if="form.document_type !== 'passport'">
                                <label
                                    for="document_back_input"
                                    class="block text-sm font-medium mb-2"
                                    :style="{ color: derivedColors.textSecondary }"
                                >
                                    {{ t('documentBackSide') }}
                                </label>
                                <div class="border-2 border-dashed rounded-lg p-6 text-center hover:opacity-80 transition-colors relative" :style="uploadStyle">
                                    <div
                                        v-if="fileUploading.document_back"
                                        class="absolute inset-0 bg-black/50 flex items-center justify-center rounded-lg z-10"
                                    >
                                        <div class="flex flex-col items-center space-y-2">
                                            <div class="animate-spin w-8 h-8 border-2 border-white border-t-transparent rounded-full" />
                                            <span class="text-white text-sm">{{ t('processing') }}...</span>
                                        </div>
                                    </div>

                                    <input
                                        id="document_back_input"
                                        ref="documentBackInput"
                                        type="file"
                                        accept="image/*"
                                        :disabled="fileUploading.document_back"
                                        class="hidden"
                                        @change="handleFileUpload('document_back', $event)"
                                    >
                                    <div
                                        v-if="!form.document_back"
                                        class="cursor-pointer"
                                        role="button"
                                        tabindex="0"
                                        :aria-label="t('uploadDocumentBackSide')"
                                        @click="$refs.documentBackInput.click()"
                                        @keydown.enter="$refs.documentBackInput.click()"
                                        @keydown.space.prevent="$refs.documentBackInput.click()"
                                    >
                                        <div class="h-12 w-12 rounded-full flex items-center justify-center mx-auto mb-4" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                            <svg
                                                class="w-6 h-6"
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
                                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                                />
                                            </svg>
                                        </div>
                                        <p class="font-medium" :style="{ color: derivedColors.textSecondary }">
                                            {{ t('clickToUploadBackSide') }}
                                        </p>
                                        <p class="text-xs mt-1" :style="{ color: derivedColors.textMuted }">
                                            {{ t('pngJpgUpTo8MB') }}
                                        </p>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <img
                                            :src="getFilePreview('document_back')"
                                            :alt="t('documentBackPreview')"
                                            class="max-h-32 mx-auto rounded border"
                                            :style="{ borderColor: derivedColors.accent + '20' }"
                                        >
                                        <p class="text-sm font-medium" :style="{ color: derivedColors.accent }">
                                            {{ form.document_back.name }}
                                        </p>
                                        <button
                                            type="button"
                                            :disabled="fileUploading.document_back"
                                            class="text-sm text-red-400 hover:text-red-300 transition-colors disabled:opacity-50"
                                            :aria-label="t('removeDocumentBackImage')"
                                            @click="removeFile('document_back')"
                                        >
                                            {{ t('remove') }}
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-if="errors.document_back"
                                    class="text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    {{ Array.isArray(errors.document_back) ? errors.document_back[0] : errors.document_back }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label
                                for="selfie_input"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('selfieWithDocument') }} <span class="text-red-400">*</span>
                            </label>
                            <div class="border-2 border-dashed rounded-lg p-6 text-center hover:opacity-80 transition-colors max-w-sm mx-auto relative" :style="uploadStyle">
                                <div
                                    v-if="fileUploading.selfie"
                                    class="absolute inset-0 bg-black/50 flex items-center justify-center rounded-lg z-10"
                                >
                                    <div class="flex flex-col items-center space-y-2">
                                        <div class="animate-spin w-8 h-8 border-2 border-white border-t-transparent rounded-full" />
                                        <span class="text-white text-sm">{{ t('processing') }}...</span>
                                    </div>
                                </div>

                                <input
                                    id="selfie_input"
                                    ref="selfieInput"
                                    type="file"
                                    accept="image/*"
                                    :disabled="fileUploading.selfie"
                                    class="hidden"
                                    @change="handleFileUpload('selfie', $event)"
                                >
                                <div
                                    v-if="!form.selfie"
                                    class="cursor-pointer"
                                    role="button"
                                    tabindex="0"
                                    :aria-label="t('uploadSelfieWithDocument')"
                                    @click="$refs.selfieInput.click()"
                                    @keydown.enter="$refs.selfieInput.click()"
                                    @keydown.space.prevent="$refs.selfieInput.click()"
                                >
                                    <div class="h-12 w-12 rounded-full flex items-center justify-center mx-auto mb-4" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                        <svg
                                            class="w-6 h-6"
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
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                        </svg>
                                    </div>
                                    <p class="font-medium" :style="{ color: derivedColors.textSecondary }">
                                        {{ t('clickToUploadSelfie') }}
                                    </p>
                                    <p class="text-xs mt-1" :style="{ color: derivedColors.textMuted }">
                                        {{ t('holdDocumentNextToFace') }}
                                    </p>
                                </div>
                                <div v-else class="space-y-2">
                                    <img
                                        :src="getFilePreview('selfie')"
                                        :alt="t('selfiePreview')"
                                        class="max-h-32 mx-auto rounded border"
                                        :style="{ borderColor: derivedColors.accent + '20' }"
                                    >
                                    <p class="text-sm font-medium" :style="{ color: derivedColors.accent }">
                                        {{ form.selfie.name }}
                                    </p>
                                    <button
                                        type="button"
                                        :disabled="fileUploading.selfie"
                                        class="text-sm text-red-400 hover:text-red-300 transition-colors disabled:opacity-50"
                                        :aria-label="t('removeSelfieImage')"
                                        @click="removeFile('selfie')"
                                    >
                                        {{ t('remove') }}
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="errors.selfie"
                                class="text-red-400 text-sm mt-1"
                                role="alert"
                            >
                                {{ Array.isArray(errors.selfie) ? errors.selfie[0] : errors.selfie }}
                            </div>
                        </div>
                    </fieldset>

                    <div class="border rounded-lg p-4" :style="{ backgroundColor: derivedColors.accent + '10', borderColor: derivedColors.accent + '30' }">
                        <div class="flex items-start space-x-3">
                            <input
                                id="agree_terms"
                                v-model="form.agree_terms"
                                type="checkbox"
                                :aria-describedby="!form.agree_terms ? 'terms_required' : null"
                                class="mt-1 h-4 w-4 rounded focus:ring-2 focus:ring-offset-2"
                                :style="{
                                    backgroundColor: derivedColors.surface,
                                    borderColor: derivedColors.border,
                                    ':focus': { ringColor: derivedColors.accent }
                                }"
                            >
                            <div class="text-sm">
                                <label
                                    for="agree_terms"
                                    class="cursor-pointer"
                                    :style="{ color: derivedColors.textSecondary }"
                                >
                                    {{ t('agreeToTermsText') }}
                                </label>
                                <div
                                    v-if="!form.agree_terms"
                                    id="terms_required"
                                    class="sr-only"
                                >
                                    {{ t('agreeToTermsRequired') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="errors && Object.keys(errors).length > 0"
                        class="border rounded-lg p-4"
                        :style="{ backgroundColor: '#ef444420', borderColor: '#ef444450' }"
                        role="alert"
                    >
                        <h4 class="text-red-300 font-medium mb-2 flex items-center">
                            <div class="w-2 h-2 bg-red-400 rounded-full mr-3"></div>
                            {{ t('pleaseFixFollowingErrors') }}
                        </h4>
                        <ul class="text-red-200 text-sm space-y-1">
                            <li
                                v-for="(error, field) in errors"
                                :key="field"
                            >
                                <strong>{{ formatFieldName(field) }}:</strong> {{ Array.isArray(error) ? error[0] : error }}
                            </li>
                        </ul>
                    </div>

                    <div class="pt-6">
                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            :aria-label="submitting ? t('submittingKyc') : (kyc ? t('resubmitKyc') : t('submitKyc'))"
                            class="w-full py-3 px-6 rounded-lg font-bold disabled:opacity-50 transition-all duration-200 shadow-lg hover:scale-105 flex items-center justify-center space-x-2"
                            :style="{
                                backgroundColor: canSubmit ? derivedColors.accent : derivedColors.surface,
                                color: canSubmit ? derivedColors.background : derivedColors.textMuted
                            }"
                        >
                            <svg
                                v-if="submitting"
                                class="animate-spin -ml-1 mr-3 h-5 w-5"
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
                                    d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            <span>{{ submitting ? t('submitting') : (kyc ? t('resubmitKyc') : t('submitKyc')) }}...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </UserLayout>
</template>
