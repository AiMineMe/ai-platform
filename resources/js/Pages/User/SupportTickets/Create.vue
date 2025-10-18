<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({})
    }
});

const isProcessing = ref(false);
const attachments = ref([]);
const form = ref({
    subject: '',
    description: '',
    category: '',
    priority: 'medium'
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

const priorityOptions = [
    { value: 'low', label: t('low') },
    { value: 'medium', label: t('medium') },
    { value: 'high', label: t('high') },
    { value: 'urgent', label: t('urgent') }
];

const categoryOptions = [
    { value: 'technical', label: t('technical') },
    { value: 'billing', label: t('billing') },
    { value: 'account', label: t('account') },
    { value: 'general', label: t('general') },
    { value: 'other', label: t('other') }
];

const canSubmit = computed(() => {
    return form.value.subject.trim() &&
        form.value.description.trim() &&
        form.value.priority &&
        !isProcessing.value;
});

const handleFileUpload = (event) => {
    const files = Array.from(event.target.files);
    attachments.value = files;
};

const removeAttachment = (index) => {
    attachments.value.splice(index, 1);
};

const goBack = () => {
    router.get('/user/support-tickets/list');
};

const submitTicket = () => {
    if (!canSubmit.value) {
        showToast(t('pleaseFillRequiredFields'), 'error');
        return;
    }

    isProcessing.value = true;

    const formData = new FormData();
    formData.append('subject', form.value.subject);
    formData.append('description', form.value.description);
    formData.append('category', form.value.category);
    formData.append('priority', form.value.priority);

    attachments.value.forEach((file, index) => {
        formData.append(`attachments[${index}]`, file);
    });

    router.post('/user/support-tickets', formData, {
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat()[0] || t('failedToCreateTicket');
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};

const formatFileSize = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }

    return `${size.toFixed(2)} ${units[unitIndex]}`;
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
        :page-title="t('createSupportTicket')"
        :page-section="t('support')"
    >
        <div class="max-w-4xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                            {{ t('createSupportTicket') }}
                        </h1>
                        <p class="mt-1 text-sm sm:text-base" :style="{ color: derivedColors.textMuted }">
                            {{ t('describeProblemGetHelp') }}
                        </p>
                    </div>
                    <button
                        class="px-4 py-2 border rounded-lg font-medium transition-colors flex-shrink-0 w-full sm:w-auto text-center"
                        :style="{
                            backgroundColor: derivedColors.surface,
                            borderColor: derivedColors.border,
                            color: derivedColors.textPrimary
                        }"
                        @click="goBack"
                    >
                        {{ t('back') }}
                    </button>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <form @submit.prevent="submitTicket" class="space-y-6">
                    <div>
                        <label
                            for="subject"
                            class="block text-sm font-medium mb-2"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('subject') }} *
                        </label>
                        <input
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            required
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                            :style="inputStyle"
                            :placeholder="t('enterTicketSubject')"
                        >
                        <p
                            v-if="errors.subject"
                            class="mt-1 text-sm text-red-400 break-words"
                        >
                            {{ Array.isArray(errors.subject) ? errors.subject[0] : errors.subject }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label
                                for="category"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('category') }}
                            </label>
                            <select
                                id="category"
                                v-model="form.category"
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                                :style="inputStyle"
                            >
                                <option value="">{{ t('selectCategory') }}</option>
                                <option
                                    v-for="category in categoryOptions"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ category.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                for="priority"
                                class="block text-sm font-medium mb-2"
                                :style="{ color: derivedColors.textSecondary }"
                            >
                                {{ t('priority') }} *
                            </label>
                            <select
                                id="priority"
                                v-model="form.priority"
                                required
                                class="w-full px-3 sm:px-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 text-sm sm:text-base"
                                :style="inputStyle"
                            >
                                <option
                                    v-for="priority in priorityOptions"
                                    :key="priority.value"
                                    :value="priority.value"
                                >
                                    {{ priority.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            for="description"
                            class="block text-sm font-medium mb-2"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('description') }} *
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="6"
                            required
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 resize-none text-sm sm:text-base"
                            :style="inputStyle"
                            :placeholder="t('describeYourProblem')"
                        ></textarea>
                        <p
                            v-if="errors.description"
                            class="mt-1 text-sm text-red-400 break-words"
                        >
                            {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium mb-2"
                            :style="{ color: derivedColors.textSecondary }"
                        >
                            {{ t('attachments') }} ({{ t('optional') }})
                        </label>
                        <div class="border-2 border-dashed rounded-lg p-4 text-center transition-colors hover:border-opacity-60"
                             :style="{ borderColor: derivedColors.border, backgroundColor: derivedColors.surface + '10' }">
                            <input
                                type="file"
                                multiple
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.zip"
                                class="hidden"
                                ref="fileInput"
                                @change="handleFileUpload"
                            >
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border rounded-lg font-medium transition-colors text-sm"
                                :style="{
                                    backgroundColor: derivedColors.accent,
                                    color: derivedColors.background,
                                    borderColor: derivedColors.accent
                                }"
                                @click="$refs.fileInput.click()"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                {{ t('chooseFiles') || 'Choose Files' }}
                            </button>
                            <p class="mt-2 text-xs" :style="{ color: derivedColors.textMuted }">
                                {{ attachments.length > 0 ? `${attachments.length} file(s) selected` : 'No files chosen' }}
                            </p>
                        </div>
                        <p class="text-xs mt-1 break-words" :style="{ color: derivedColors.textMuted }">
                            {{ t('maxFileSize10MB') }}. {{ t('supportedFormats') }}: JPG, PNG, PDF, DOC, DOCX, TXT, ZIP
                        </p>

                        <div v-if="attachments.length > 0" class="mt-4">
                            <h5 class="text-sm font-medium mb-2" :style="{ color: derivedColors.textSecondary }">
                                {{ t('selectedFiles') }}:
                            </h5>
                            <div class="space-y-2">
                                <div
                                    v-for="(file, index) in attachments"
                                    :key="index"
                                    class="flex items-center justify-between p-2 sm:p-3 border rounded-lg"
                                    :style="{
                                        backgroundColor: derivedColors.surface + '30',
                                        borderColor: derivedColors.border
                                    }"
                                >
                                    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0 flex-1">
                                        <svg
                                            class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0"
                                            :style="{ color: derivedColors.accent }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs sm:text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                                {{ file.name }}
                                            </p>
                                            <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                                {{ formatFileSize(file.size) }}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-red-400 hover:text-red-300 transition-colors flex-shrink-0 ml-2"
                                        @click="removeAttachment(index)"
                                    >
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3 pt-6 border-t" :style="{ borderColor: derivedColors.border + '20' }">
                        <button
                            type="button"
                            class="px-6 py-3 border rounded-lg font-medium transition-colors w-full sm:w-auto"
                            :style="{
                                backgroundColor: derivedColors.surface,
                                borderColor: derivedColors.border,
                                color: derivedColors.textPrimary
                            }"
                                                @click="goBack"
                                            >
                                                {{ t('cancel') }}
                                            </button>
                                            <button
                                                type="submit"
                                                :disabled="!canSubmit"
                                                class="px-6 py-3 rounded-lg font-medium transition-all duration-200 disabled:opacity-50 w-full sm:w-auto"
                                                :style="{
                                backgroundColor: canSubmit ? derivedColors.accent : derivedColors.surface,
                                color: canSubmit ? derivedColors.background : derivedColors.textMuted
                            }"
                                            >
                            <span v-if="isProcessing" class="flex items-center justify-center">
                                <svg
                                    class="animate-spin -ml-1 mr-2 h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ t('creating') }}
                            </span>
                            <span v-else>{{ t('createTicket') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <h3 class="text-base sm:text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('helpfulTips') }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('beSpecific') }}
                            </p>
                            <p class="text-xs break-words" :style="{ color: derivedColors.textMuted }">
                                {{ t('provideDetailedDescription') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('attachScreenshots') }}
                            </p>
                            <p class="text-xs break-words" :style="{ color: derivedColors.textMuted }">
                                {{ t('visualsHelpUsUnderstand') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('setPriority') }}
                            </p>
                            <p class="text-xs break-words" :style="{ color: derivedColors.textMuted }">
                                {{ t('urgentForCriticalIssues') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                            <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                {{ t('responseTime') }}
                            </p>
                            <p class="text-xs break-words" :style="{ color: derivedColors.textMuted }">
                                {{ t('weRespondWithin24Hours') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
