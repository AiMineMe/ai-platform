<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    blog: {
        type: Object,
        default: null
    },
    errors: {
        type: Object,
        default: () => ({})
    }
});

const pageProps = usePage();
const { showToast } = useToast();
const isProcessing = ref(false);
const errors = ref({});
const imagePreview = ref(null);

const isEditing = computed(() => !!props.blog);
const flash = computed(() => pageProps.props.flash);
const form = ref({
    title: props.blog?.title || '',
    excerpt: props.blog?.excerpt || '',
    content: props.blog?.content || '',
    image: null,
    read_time: props.blog?.read_time || 5,
    is_published: props.blog?.is_published !== undefined ? Boolean(props.blog.is_published) : true
});

if (isEditing.value && props.blog?.image) {
    imagePreview.value = `${props.blog.image}`;
}

const currentWordCount = computed(() => {
    if (!form.value.content) return 0;
    return form.value.content.trim().split(/\s+/).filter(word => word.length > 0).length;
});

const estimatedReadTime = computed(() => {
    const wordsPerMinute = 225;
    const minutes = Math.ceil(currentWordCount.value / wordsPerMinute);
    return Math.max(minutes, 1);
});
const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US').format(num || 0);
};
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

const getStatusClass = (isPublished) => {
    return isPublished
        ? 'bg-green-100 text-green-800 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        : 'bg-gray-100 text-gray-800 border border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700';
};
const goBack = () => {
    router.get('/admin/blogs');
};
const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.value.image = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeImage = () => {
    form.value.image = null;
    imagePreview.value = null;
    const fileInput = document.getElementById('image');
    if (fileInput) {
        fileInput.value = '';
    }
};

const submitForm = () => {
    isProcessing.value = true;
    errors.value = {};
    const formData = new FormData();
    formData.append('title', form.value.title);
    formData.append('excerpt', form.value.excerpt);
    formData.append('content', form.value.content);
    formData.append('read_time', form.value.read_time);
    formData.append('is_published', form.value.is_published ? '1' : '0');

    if (form.value.image instanceof File) {
        formData.append('image', form.value.image);
    }

    const url = isEditing.value
        ? `/admin/blogs/${props.blog.id}`
        : '/admin/blogs';

    if (isEditing.value) {
        formData.append('_method', 'PATCH');
    }

    router.post(url, formData, {
        preserveState: false,
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {

        },
        onError: (formErrors) => {
            errors.value = formErrors;
            const errorMessages = Object.values(formErrors).flat();
            const errorMessage = errorMessages.length > 0
                ? errorMessages[0]
                : `Failed to ${isEditing.value ? 'update' : 'create'} blog. Please check the form for errors.`;
            showToast(errorMessage, 'error');
        },
        onFinish: () => isProcessing.value = false
    });
};

watch(() => form.value.content, () => {
    if (estimatedReadTime.value !== form.value.read_time) {
        form.value.read_time = estimatedReadTime.value;
    }
}, { debounce: 1000 });

watch(() => props.errors, (newErrors) => {
    errors.value = { ...errors.value, ...newErrors };
}, { immediate: true });

watch(flash, (newFlash) => {
    if (newFlash?.success) {
        showToast(newFlash.success, 'success');
    } else if (newFlash?.error) {
        showToast(newFlash.error, 'error');
    }
}, { immediate: true });

onMounted(() => {
    const page = usePage();
    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) {
        errors.value = { general: flash.error };
        showToast(flash.error, 'error');
    }
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
});
</script>

<template>
    <AdminLayout
        :title="isEditing ? 'Edit Blog Post' : 'Create Blog Post'"
        page-section="Content Management"
    >
        <div class="py-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ isEditing ? 'Edit Blog Post' : 'Create New Blog Post' }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ isEditing ? `Update "${blog?.title}" information and content` : 'Create a new blog post for your audience' }}
                            </p>
                        </div>
                        <button
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                            @click="goBack"
                        >
                            <svg
                                class="h-4 w-4 mr-2"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            Back to Blogs
                        </button>
                    </div>
                </div>

                <div
                    v-if="isEditing"
                    class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 mb-8 border border-blue-200 dark:border-blue-700"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                            Current Blog Status
                        </h3>
                        <span
                            class="px-3 py-1 rounded-full text-sm font-medium"
                            :class="getStatusClass(blog.is_published)"
                        >
              {{ blog.is_published ? 'PUBLISHED' : 'DRAFT' }}
            </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Created:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatDate(blog.created_at) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Last Updated:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatDate(blog.updated_at) }}
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Read Time:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ blog.read_time }} min
                            </div>
                        </div>
                        <div>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">Views:</span>
                            <div class="text-blue-900 dark:text-blue-100 font-bold">
                                {{ formatNumber(blog.views || 0) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md overflow-hidden border border-gray-200 dark:border-slate-700">
                    <form
                        class="p-6 space-y-6"
                        @submit.prevent="submitForm"
                    >
                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Basic Information
                            </h3>
                            <div class="space-y-6">
                                <div>
                                    <label
                                        for="title"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Blog Title *
                                    </label>
                                    <input
                                        id="title"
                                        v-model="form.title"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="Enter an engaging blog title..."
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.title }"
                                    >
                                    <p
                                        v-if="errors.title"
                                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{ Array.isArray(errors.title) ? errors.title[0] : errors.title }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="excerpt"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Excerpt *
                                    </label>
                                    <textarea
                                        id="excerpt"
                                        v-model="form.excerpt"
                                        rows="3"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="Brief description that will appear in blog listings..."
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.excerpt }"
                                    />
                                    <p
                                        v-if="errors.excerpt"
                                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{ Array.isArray(errors.excerpt) ? errors.excerpt[0] : errors.excerpt }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Keep it under 160 characters for best SEO results
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Content
                            </h3>
                            <div>
                                <label
                                    for="content"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Blog Content *
                                </label>
                                <textarea
                                    id="content"
                                    v-model="form.content"
                                    rows="15"
                                    required
                                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all font-mono"
                                    placeholder="Write your blog content here. You can use Markdown formatting..."
                                    :class="{ 'border-red-500 focus:ring-red-500': errors.content }"
                                />
                                <p
                                    v-if="errors.content"
                                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ Array.isArray(errors.content) ? errors.content[0] : errors.content }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    HTML or plain text allowed
                                </p>
                                <div class="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ formatNumber(currentWordCount) }} words</span>
                                    <span>Estimated read time: {{ estimatedReadTime }} minutes</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Featured Image
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <label
                                        for="image"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Upload Image
                                    </label>
                                    <input
                                        id="image"
                                        type="file"
                                        accept="image/*"
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.image }"
                                        @change="handleImageUpload"
                                    >
                                    <p
                                        v-if="errors.image"
                                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{ Array.isArray(errors.image) ? errors.image[0] : errors.image }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Recommended size: 1200x630px. Max file size: 2MB
                                    </p>
                                </div>

                                <div
                                    v-if="imagePreview"
                                    class="relative"
                                >
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Preview:</span>
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200 text-sm"
                                            @click="removeImage"
                                        >
                                            Remove Image
                                        </button>
                                    </div>
                                    <div class="w-full max-w-md">
                                        <img
                                            :src="imagePreview"
                                            alt="Preview"
                                            class="w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-slate-600"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Settings
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label
                                        for="read_time"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        Read Time (minutes) *
                                    </label>
                                    <input
                                        id="read_time"
                                        v-model="form.read_time"
                                        type="number"
                                        min="1"
                                        max="60"
                                        required
                                        class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                                        placeholder="5"
                                        :class="{ 'border-red-500 focus:ring-red-500': errors.read_time }"
                                    >
                                    <p
                                        v-if="errors.read_time"
                                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{ Array.isArray(errors.read_time) ? errors.read_time[0] : errors.read_time }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Auto-calculated based on content length
                                    </p>
                                </div>

                                <div class="flex items-center justify-start h-full pt-8">
                                    <label class="flex items-center">
                                        <input
                                            v-model="form.is_published"
                                            type="checkbox"
                                            class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-700"
                                        >
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Publish Immediately</span>
                                    </label>
                                    <p class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        Uncheck to save as draft
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="form.title || form.excerpt"
                            class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6 border border-blue-200 dark:border-blue-700"
                        >
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                                Blog Preview
                            </h4>
                            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                                <div class="flex justify-between">
                                    <span>Title:</span>
                                    <span class="font-medium">{{ form.title || 'Not set' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Excerpt Length:</span>
                                    <span class="font-medium">{{ form.excerpt.length }} characters</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Content Words:</span>
                                    <span class="font-medium">{{ formatNumber(currentWordCount) }} words</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Read Time:</span>
                                    <span class="font-medium">{{ form.read_time }} minutes</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Status:</span>
                                    <span class="font-medium font-bold">
                    {{ form.is_published ? 'Will be Published' : 'Will be Saved as Draft' }}
                  </span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Featured Image:</span>
                                    <span class="font-medium">{{ imagePreview ? 'Set' : 'Not set' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
                            <div
                                v-if="isEditing"
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Created: {{ formatDate(blog.created_at) }}
                            </div>
                            <div v-else />

                            <div class="flex space-x-4">
                                <button
                                    type="button"
                                    class="px-6 py-3 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                                    @click="goBack"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isProcessing"
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center"
                                >
                                    <svg
                                        v-if="isProcessing"
                                        class="animate-spin -ml-1 mr-3 h-4 w-4 text-white"
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
                                    {{ isProcessing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update Blog' : 'Create Blog') }}
                                </button>
                            </div>
                        </div>
                    </form>
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
</style>
