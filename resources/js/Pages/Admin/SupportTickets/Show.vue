<script setup>
import {computed, ref, watch} from 'vue';
import {router, usePage} from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import {useToast} from "@/composables/useToast.js";

const props = defineProps({
    ticket: {
        type: Object,
        required: true
    },
    adminUsers: {
        type: Array,
        default: () => []
    }
});

const { success, error } = useToast();
const pageProps = usePage();
const isProcessing = ref(false);
const replyMessage = ref('');
const attachments = ref([]);
const showReplyForm = ref(false);
const sortedReplies = computed(() => {
    return [...props.ticket.replies].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

const goBack = () => {
    router.get('/admin/support-tickets');
};

const updateStatus = (status) => {
    router.patch(`/admin/support-tickets/${props.ticket.id}/status`, {
        status: status
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
        },
        onError: () => {
        }
    });
};

const updatePriority = (priority) => {
    router.patch(`/admin/support-tickets/${props.ticket.id}/priority`, {
        priority: priority
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
        },
        onError: () => {
        }
    });
};

const assignTicket = (assigneeId) => {
    router.patch(`/admin/support-tickets/${props.ticket.id}/assign`, {
        assigned_to: assigneeId
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
        },
        onError: () => {
        }
    });
};

const handleFileUpload = (event) => {
    attachments.value = Array.from(event.target.files);
};

const removeAttachment = (index) => {
    attachments.value.splice(index, 1);
};

const submitReply = () => {
    if (!replyMessage.value.trim()) {
        return;
    }

    isProcessing.value = true;

    const formData = new FormData();
    formData.append('message', replyMessage.value);

    attachments.value.forEach((file, index) => {
        formData.append(`attachments[${index}]`, file);
    });

    router.post(`/admin/support-tickets/${props.ticket.id}/reply`, formData, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            replyMessage.value = '';
            attachments.value = [];
            showReplyForm.value = false;
        },
        onError: () => {
            error('Failed to send reply');
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};

const downloadAttachment = (attachment) => {
    window.open(`/admin/support-tickets/attachments/${attachment.id}/download`, '_blank');
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getTimeAgo = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const now = new Date();
    const diffInMs = now - date;
    const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
    const diffInMinutes = Math.floor(diffInMs / (1000 * 60));

    if (diffInDays > 0) return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
    if (diffInHours > 0) return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
    if (diffInMinutes > 0) return `${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''} ago`;
    return 'Just now';
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

watch(() => pageProps.props.flash, (flash) => {
    if (flash?.success) success(flash.success);
    if (flash?.error) error(flash.error);
}, { immediate: true });
</script>

<template>
    <AdminLayout
        title="Support Ticket Details"
        page-section="Customer Support"
    >
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ ticket.ticket_number }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Support ticket details and conversation
                    </p>
                </div>
                <button
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600"
                    @click="goBack"
                >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Tickets
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                    {{ ticket.subject }}
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Submitted by {{ ticket.user?.name || 'Unknown User' }} on {{ formatDate(ticket.created_at) }}
                                </p>
                            </div>
                        </div>

                        <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300">
                            <p class="whitespace-pre-wrap">{{ ticket.description }}</p>
                        </div>

                        <div v-if="ticket.attachments && ticket.attachments.length > 0" class="mt-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Attachments:</h4>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="attachment in ticket.attachments"
                                    :key="attachment.id"
                                    class="inline-flex items-center px-3 py-2 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50"
                                    @click="downloadAttachment(attachment)"
                                >
                                    <span v-html="getFileIcon(attachment.file_type)" class="mr-2"></span>
                                    {{ attachment.original_name }}
                                    <span class="ml-2 text-blue-600 dark:text-blue-400">
                                        ({{ formatFileSize(attachment.file_size) }})
                                      </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Conversation
                            </h3>
                            <button
                                v-if="!showReplyForm"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg"
                                @click="showReplyForm = true"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                Reply
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="reply in sortedReplies"
                                :key="reply.id"
                                class="border border-gray-200 dark:border-slate-700 rounded-lg p-4"
                                :class="reply.is_admin_reply ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-slate-700'"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium"
                                             :class="reply.is_admin_reply ? 'bg-blue-600' : 'bg-gray-600'">
                                            {{ reply.user?.name?.charAt(0) || '?' }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ reply.user?.name || 'Unknown User' }}
                                                <span v-if="reply.is_admin_reply" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                  Admin
                                                </span>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ formatDate(reply.created_at) }} • {{ getTimeAgo(reply.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 mb-3">
                                    <p class="whitespace-pre-wrap">{{ reply.message }}</p>
                                </div>

                                <div v-if="reply.attachments && reply.attachments.length > 0" class="mt-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="attachment in reply.attachments"
                                            :key="attachment.id"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded hover:bg-blue-200 dark:hover:bg-blue-900/50"
                                            @click="downloadAttachment(attachment)"
                                        >
                                            <span v-html="getFileIcon(attachment.file_type)" class="mr-1"></span>
                                            {{ attachment.original_name }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="sortedReplies.length === 0" class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    No replies yet
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Be the first to reply to this ticket.
                                </p>
                            </div>
                        </div>

                        <div v-if="showReplyForm" class="mt-6 border-t border-gray-200 dark:border-slate-700 pt-6">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Add Reply</h4>
                            <form @submit.prevent="submitReply">
                                <div class="mb-4">
                                    <label for="reply-message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Reply Message *
                                    </label>
                                    <textarea
                                        id="reply-message"
                                        v-model="replyMessage"
                                        rows="4"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                        placeholder="Type your reply here..."
                                    ></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Attachments (Optional)
                                    </label>
                                    <input
                                        type="file"
                                        multiple
                                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.zip"
                                        class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300"
                                        @change="handleFileUpload"
                                    >
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Max 10MB per file. Supported: JPG, PNG, PDF, DOC, DOCX, TXT, ZIP
                                    </p>
                                </div>

                                <div v-if="attachments.length > 0" class="mb-4">
                                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Selected Files:</h5>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(file, index) in attachments"
                                            :key="index"
                                            class="flex items-center justify-between p-2 bg-gray-50 dark:bg-slate-700 rounded border"
                                        >
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ file.name }}</span>
                                            <button
                                                type="button"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                @click="removeAttachment(index)"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end space-x-3">
                                    <button
                                        type="button"
                                        class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600"
                                        @click="showReplyForm = false; replyMessage = ''; attachments = []"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="isProcessing || !replyMessage.trim()"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg flex items-center"
                                    >
                                        <svg
                                            v-if="isProcessing"
                                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        {{ isProcessing ? 'Sending...' : 'Send Reply' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Ticket Information
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Status
                                </label>
                                <select
                                    :value="ticket.status"
                                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 p-2"
                                    @change="updateStatus($event.target.value)"
                                >
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Priority
                                </label>
                                <select
                                    :value="ticket.priority"
                                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 p-2"
                                    @change="updatePriority($event.target.value)"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            User Information
                        </h3>

                        <div class="space-y-3">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Name:</span>
                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ ticket.user?.name || 'Unknown User' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Email:</span>
                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ ticket.user?.email || 'No email provided' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Created:</span>
                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ formatDate(ticket.created_at) }}
                                </p>
                            </div>
                            <div v-if="ticket.resolved_at">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Resolved:</span>
                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ formatDate(ticket.resolved_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Quick Actions
                        </h3>

                        <div class="space-y-3">
                            <button
                                class="w-full text-left px-4 py-2 text-sm font-medium text-green-700 dark:text-green-300 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50"
                                @click="updateStatus('resolved')"
                            >
                                Mark as Resolved
                            </button>
                            <button
                                class="w-full text-left px-4 py-2 text-sm font-medium text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50"
                                @click="updateStatus('in_progress')"
                            >
                                Mark In Progress
                            </button>
                            <button
                                class="w-full text-left px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-900/50"
                                @click="updateStatus('closed')"
                            >
                                Close Ticket
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.prose {
    max-width: none;
}

.prose p {
    margin-bottom: 1em;
}

.prose p:last-child {
    margin-bottom: 0;
}
</style>
