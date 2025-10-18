<script setup>
import {computed, onMounted, ref} from 'vue';
import {router, usePage} from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import {useToast} from "@/composables/useToast.js";
import {useTranslation} from '@/composables/useTranslation';

const props = defineProps({
    ticket: {
        type: Object,
        required: true
    }
});

const isProcessing = ref(false);
const replyMessage = ref('');
const attachments = ref([]);
const showReplyForm = ref(false);
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

const sortedReplies = computed(() => {
    return [...props.ticket.replies].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

const canReply = computed(() => {
    return ['open', 'in_progress'].includes(props.ticket.status);
});

const goBack = () => {
    router.get('/user/support-tickets/list');
};

const handleFileUpload = (event) => {
    attachments.value = Array.from(event.target.files);
};

const removeAttachment = (index) => {
    attachments.value.splice(index, 1);
};

const submitReply = () => {
    if (!replyMessage.value.trim()) {
        showToast(t('pleaseEnterReplyMessage'), 'error');
        return;
    }

    isProcessing.value = true;
    const formData = new FormData();
    formData.append('message', replyMessage.value);

    attachments.value.forEach((file, index) => {
        formData.append(`attachments[${index}]`, file);
    });

    router.post(`/user/support-tickets/${props.ticket.id}/reply`, formData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showToast(t('replySentSuccessfully'), 'success');
            replyMessage.value = '';
            attachments.value = [];
            showReplyForm.value = false;
        },
        onError: () => {
            showToast(t('failedToSendReply'), 'error');
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};

const downloadAttachment = (attachment) => {
    window.open(`/user/support-tickets/attachments/${attachment.id}/download`, '_blank');
};

const formatDate = (dateString) => {
    if (!dateString) return t('unknown');
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getTimeAgo = (dateString) => {
    if (!dateString) return t('unknown');
    const date = new Date(dateString);
    const now = new Date();
    const diffInMs = now - date;
    const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
    const diffInMinutes = Math.floor(diffInMs / (1000 * 60));

    if (diffInDays > 0) return `${diffInDays} ${diffInDays > 1 ? t('days') : t('day')} ${t('ago')}`;
    if (diffInHours > 0) return `${diffInHours} ${diffInHours > 1 ? t('hours') : t('hour')} ${t('ago')}`;
    if (diffInMinutes > 0) return `${diffInMinutes} ${diffInMinutes > 1 ? t('minutes') : t('minute')} ${t('ago')}`;
    return t('justNow');
};

const getStatusClass = (status) => {
    const classes = {
        'open': 'bg-blue-100 text-blue-800 border-blue-200',
        'in_progress': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'resolved': 'bg-green-100 text-green-800 border-green-200',
        'closed': 'bg-gray-100 text-gray-800 border-gray-200'
    };
    return classes[status] || classes.open;
};

const getPriorityClass = (priority) => {
    const classes = {
        'low': 'bg-green-100 text-green-800 border-green-200',
        'medium': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'high': 'bg-orange-100 text-orange-800 border-orange-200',
        'urgent': 'bg-red-100 text-red-800 border-red-200'
    };
    return classes[priority] || classes.medium;
};

const getFileIcon = (fileType) => {
    if (fileType?.includes('image/')) {
        return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;
    } else if (fileType?.includes('pdf')) {
        return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>`;
    } else {
        return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>`;
    }
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
        :page-title="ticket.ticket_number"
        :page-section="t('support')"
    >
        <div class="max-w-6xl mx-auto space-y-6 px-4">
            <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-2">
                            <h1 class="text-xl sm:text-2xl font-bold truncate" :style="{ color: derivedColors.textPrimary }">
                                {{ ticket.ticket_number }}
                            </h1>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="px-2.5 py-1 text-xs font-medium rounded-full border inline-block"
                                    :class="getStatusClass(ticket.status)"
                                >
                                    {{ ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1) }}
                                </span>
                                <span
                                    class="px-2.5 py-1 text-xs font-medium rounded-full border inline-block"
                                    :class="getPriorityClass(ticket.priority)"
                                >
                                    {{ ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1) }}
                                </span>
                            </div>
                        </div>
                        <h2 class="text-base sm:text-lg font-semibold mb-1" :style="{ color: derivedColors.textSecondary }">
                            {{ ticket.subject }}
                        </h2>
                        <p class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('createdOn') }} {{ formatDate(ticket.created_at) }}
                        </p>
                    </div>
                    <button
                        class="w-full sm:w-auto px-4 py-2 border rounded-lg font-medium transition-colors"
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <div class="flex flex-col sm:flex-row sm:items-start gap-4 mb-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent }">
                                <span class="text-sm font-medium" :style="{ color: derivedColors.background }">
                                    {{ ticket.user?.name?.charAt(0) || 'U' }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 mb-1">
                                    <h3 class="font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                        {{ ticket.user?.name || t('unknownUser') }}
                                    </h3>
                                    <span class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ formatDate(ticket.created_at) }}
                                    </span>
                                </div>
                                <div class="prose prose-sm max-w-none" :style="{ color: derivedColors.textSecondary }">
                                    <p class="whitespace-pre-wrap break-words">{{ ticket.description }}</p>
                                </div>

                                <div v-if="ticket.attachments && ticket.attachments.length > 0" class="mt-4">
                                    <h4 class="text-sm font-medium mb-2" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('attachments') }}:
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="attachment in ticket.attachments"
                                            :key="attachment.id"
                                            class="inline-flex items-center px-3 py-2 text-xs font-medium border rounded-lg transition-colors break-all"
                                            :style="{
                                                backgroundColor: derivedColors.accent + '20',
                                                borderColor: derivedColors.accent + '30',
                                                color: derivedColors.accent
                                            }"
                                            @click="downloadAttachment(attachment)"
                                        >
                                            <span v-html="getFileIcon(attachment.file_type)" class="mr-2 flex-shrink-0"></span>
                                            <span class="truncate">{{ attachment.original_name }}</span>
                                            <span class="ml-2 flex-shrink-0" :style="{ color: derivedColors.textMuted }">
                                                ({{ formatFileSize(attachment.file_size) }})
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border" :style="cardStyle">
                        <div class="p-4 sm:p-6 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <h3 class="text-lg font-semibold" :style="{ color: derivedColors.textPrimary }">
                                    {{ t('conversation') }}
                                </h3>
                                <button
                                    v-if="canReply && !showReplyForm"
                                    class="w-full sm:w-auto px-4 py-2 rounded-lg font-medium transition-colors"
                                    :style="{ backgroundColor: derivedColors.accent, color: derivedColors.background }"
                                    @click="showReplyForm = true"
                                >
                                    {{ t('reply') }}
                                </button>
                            </div>
                        </div>

                        <div class="p-4 sm:p-6">
                            <div class="space-y-6">
                                <div
                                    v-for="reply in sortedReplies"
                                    :key="reply.id"
                                    class="border rounded-lg p-4"
                                    :class="reply.is_admin_reply ? 'border-blue-200 bg-blue-50/50' : 'border-gray-200'"
                                    :style="reply.is_admin_reply ? {
                                        backgroundColor: derivedColors.accent + '10',
                                        borderColor: derivedColors.accent + '30'
                                    } : {
                                        backgroundColor: derivedColors.surface + '30',
                                        borderColor: derivedColors.border
                                    }"
                                >
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-3 mb-3">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium flex-shrink-0"
                                            :style="{
                                                backgroundColor: reply.is_admin_reply ? derivedColors.accent : derivedColors.secondary,
                                                color: derivedColors.background
                                            }"
                                        >
                                            {{ reply.user?.name?.charAt(0) || '?' }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 mb-1">
                                                <p class="text-sm font-medium truncate" :style="{ color: derivedColors.textPrimary }">
                                                    {{ reply.user?.name || t('unknownUser') }}
                                                    <span v-if="reply.is_admin_reply" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ t('support') }}
                                                    </span>
                                                </p>
                                                <span class="text-xs whitespace-nowrap" :style="{ color: derivedColors.textMuted }">
                                                    {{ formatDate(reply.created_at) }} • {{ getTimeAgo(reply.created_at) }}
                                                </span>
                                            </div>
                                            <div class="prose prose-sm max-w-none mb-3" :style="{ color: derivedColors.textSecondary }">
                                                <p class="whitespace-pre-wrap break-words">{{ reply.message }}</p>
                                            </div>

                                            <div v-if="reply.attachments && reply.attachments.length > 0" class="mt-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <button
                                                        v-for="attachment in reply.attachments"
                                                        :key="attachment.id"
                                                        class="inline-flex items-center px-2 py-1 text-xs font-medium border rounded transition-colors break-all"
                                                        :style="{
                                                            backgroundColor: derivedColors.accent + '10',
                                                            borderColor: derivedColors.accent + '20',
                                                            color: derivedColors.accent
                                                        }"
                                                        @click="downloadAttachment(attachment)"
                                                    >
                                                        <span v-html="getFileIcon(attachment.file_type)" class="mr-1 flex-shrink-0"></span>
                                                        <span class="truncate">{{ attachment.original_name }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="sortedReplies.length === 0" class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 mb-4" :style="{ color: derivedColors.textMuted }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                        {{ t('noRepliesYet') }}
                                    </p>
                                </div>

                                <div v-if="showReplyForm && canReply" class="border-t pt-6" :style="{ borderColor: derivedColors.border + '20' }">
                                    <h4 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('addReply') }}
                                    </h4>
                                    <form @submit.prevent="submitReply" class="space-y-4">
                                        <div>
                                            <textarea
                                                v-model="replyMessage"
                                                rows="4"
                                                class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-opacity-50 resize-none"
                                                :style="inputStyle"
                                                :placeholder="t('typeYourReply')"
                                                required
                                            ></textarea>
                                        </div>

                                        <div>
                                            <input
                                                type="file"
                                                multiple
                                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.zip"
                                                class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:transition-colors"
                                                :style="{
                                                    color: derivedColors.textSecondary,
                                                    'file:backgroundColor': derivedColors.accent,
                                                    'file:color': derivedColors.background
                                                }"
                                                @change="handleFileUpload"
                                            >
                                            <p class="text-xs mt-1" :style="{ color: derivedColors.textMuted }">
                                                {{ t('maxFileSize10MB') }}
                                            </p>

                                            <div v-if="attachments.length > 0" class="mt-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <div
                                                        v-for="(file, index) in attachments"
                                                        :key="index"
                                                        class="inline-flex items-center px-3 py-1 text-xs border rounded-lg break-all"
                                                        :style="{
                                                            backgroundColor: derivedColors.surface + '30',
                                                            borderColor: derivedColors.border,
                                                            color: derivedColors.textPrimary
                                                        }"
                                                    >
                                                        <span v-html="getFileIcon(file.type)" class="mr-1 flex-shrink-0"></span>
                                                        <span class="truncate">{{ file.name }}</span>
                                                        <button
                                                            type="button"
                                                            class="ml-2 text-red-400 hover:text-red-300 flex-shrink-0"
                                                            @click="removeAttachment(index)"
                                                        >
                                                            ×
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                                            <button
                                                type="button"
                                                class="w-full sm:w-auto px-4 py-2 border rounded-lg font-medium transition-colors"
                                                :style="{
                                                    backgroundColor: derivedColors.surface,
                                                    borderColor: derivedColors.border,
                                                    color: derivedColors.textPrimary
                                                }"
                                                @click="showReplyForm = false"
                                            >
                                                {{ t('cancel') }}
                                            </button>
                                            <button
                                                type="submit"
                                                :disabled="isProcessing || !replyMessage.trim()"
                                                class="w-full sm:w-auto px-4 py-2 rounded-lg font-medium transition-all duration-200 disabled:opacity-50"
                                                :style="{
                                                    backgroundColor: derivedColors.accent,
                                                    color: derivedColors.background
                                                }"
                                            >
                                                <span v-if="isProcessing" class="flex items-center justify-center">
                                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                    </svg>
                                                    {{ t('sending') }}
                                                </span>
                                                <span v-else>{{ t('sendReply') }}</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <div v-if="!canReply" class="border-t pt-6" :style="{ borderColor: derivedColors.border + '20' }">
                                    <div class="text-center py-4">
                                        <svg class="mx-auto h-8 w-8 mb-2" :style="{ color: derivedColors.textMuted }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <p class="text-sm" :style="{ color: derivedColors.textMuted }">
                                            {{ t('ticketClosedNoReply') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('ticketDetails') }}
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-medium" :style="{ color: derivedColors.textMuted }">
                                    {{ t('ticketNumber') }}
                                </p>
                                <p class="text-sm break-all" :style="{ color: derivedColors.textSecondary }">
                                    {{ formatDate(ticket.updated_at) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('quickActions') }}
                        </h3>
                        <div class="space-y-3">
                            <button
                                v-if="canReply && !showReplyForm"
                                class="w-full px-4 py-3 rounded-lg font-medium transition-colors text-left flex items-center gap-3"
                                :style="{
                                    backgroundColor: derivedColors.accent + '20',
                                    borderColor: derivedColors.accent + '30',
                                    color: derivedColors.accent
                                }"
                                @click="showReplyForm = true"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                                <span class="truncate">{{ t('replyToTicket') }}</span>
                            </button>

                            <button
                                class="w-full px-4 py-3 border rounded-lg font-medium transition-colors text-left flex items-center gap-3"
                                :style="{
                                    backgroundColor: derivedColors.surface + '30',
                                    borderColor: derivedColors.border,
                                    color: derivedColors.textPrimary
                                }"
                                @click="goBack"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span class="truncate">{{ t('viewAllTickets') }}</span>
                            </button>

                            <button
                                class="w-full px-4 py-3 border rounded-lg font-medium transition-colors text-left flex items-center gap-3"
                                :style="{
                                    backgroundColor: derivedColors.surface + '30',
                                    borderColor: derivedColors.border,
                                    color: derivedColors.textPrimary
                                }"
                                @click="router.get('/user/support-tickets/create')"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span class="truncate">{{ t('createNewTicket') }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('supportInfo') }}
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                    <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('responseTime') }}
                                    </p>
                                    <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('within24Hours') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                    <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('emergencySupport') }}
                                    </p>
                                    <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('forUrgentIssues') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: derivedColors.accent + '20' }">
                                    <svg class="w-4 h-4" :style="{ color: derivedColors.accent }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                        {{ t('helpCenter') }}
                                    </p>
                                    <p class="text-xs" :style="{ color: derivedColors.textMuted }">
                                        {{ t('selfServiceOptions') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="backdrop-blur-sm rounded-xl shadow-lg border p-4 sm:p-6" :style="cardStyle">
                        <h3 class="text-lg font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                            {{ t('ticketActivity') }}
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('totalReplies') }}
                                </span>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ sortedReplies.length }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('lastActivity') }}
                                </span>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ sortedReplies.length > 0 ? getTimeAgo(sortedReplies[sortedReplies.length - 1].created_at) : getTimeAgo(ticket.created_at) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm" :style="{ color: derivedColors.textSecondary }">
                                    {{ t('attachments') }}
                                </span>
                                <span class="text-sm font-medium" :style="{ color: derivedColors.textPrimary }">
                                    {{ (ticket.attachments?.length || 0) + sortedReplies.reduce((sum, reply) => sum + (reply.attachments?.length || 0), 0) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
