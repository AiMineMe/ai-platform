<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    blogs: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            total: 0,
            current_page: 1,
            per_page: 10,
            last_page: 1
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                Array.isArray(value.data);
        }
    },
});

const { showToast } = useToast();
const isLoading = ref(false);
const isProcessing = ref(false);
const showDeleteModal = ref(false);
const selectedBlog = ref(null);
const currentPage = computed(() => props.blogs.current_page || 1);
const lastPage = computed(() => props.blogs.last_page || 1);
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        console.error('Date formatting error:', error);
        return 'Invalid Date';
    }
};

const getBlogImage = (image) => {
    if (!image) return null;
    return image.startsWith('http') ? image : `/storage/${image}`;
};

const handleImageError = (event) => {
    event.target.style.display = 'none';
    const fallback = document.createElement('div');
    fallback.className = 'h-full w-full rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm';
    fallback.textContent = 'IMG';
    event.target.parentElement.appendChild(fallback);
};
const getStatusClass = (isPublished) => {
    return isPublished
        ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700'
        : 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700';
};
const getStatusIcon = (isPublished) => {
    return isPublished
        ? `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`
        : `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>`;
};
const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return;
    isLoading.value = true;
    router.get('/admin/blogs', { page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Pagination error:', errors);
            showToast('Failed to load page', 'error');
        }
    });
};
const createBlog = () => {
    router.get('/admin/blogs/create');
};
const editBlog = (blog) => {
    router.get(`/admin/blogs/${blog.id}/edit`);
};
const openDeleteModal = (blog) => {
    selectedBlog.value = blog;
    showDeleteModal.value = true;
};
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedBlog.value = null;
};

const confirmDelete = () => {
    if (!selectedBlog.value || isProcessing.value) return;

    isProcessing.value = true;
    router.delete(`/admin/blogs/${selectedBlog.value.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast('Blog deleted successfully', 'success');
        },
        onError: () => {
            showToast('Failed to delete blog', 'error');
        },
        onFinish: () => isProcessing.value = false
    });
};

onMounted(() => {
    const page = usePage();
    const flash = page.props.flash;

    // Show flash messages
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
});
</script>

<template>
    <AdminLayout
        title="Blog Management"
        page-section="Content Management"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Blog Management
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage and monitor all blog posts and drafts
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <button
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        @click="createBlog"
                    >
                        <svg
                            class="w-4 h-4 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                            />
                        </svg>
                        Create Blog
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            BLOG
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            STATUS
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            READ TIME
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            DATE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            ACTIONS
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="!isLoading && blogs.data.length === 0">
                        <td
                            colspan="5"
                            class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                        >
                            <div class="text-center">
                                <svg
                                    class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    No blogs found
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Get started by creating your first blog post.
                                </p>
                                <div class="mt-6">
                                    <button
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-lime-600 hover:bg-lime-700"
                                        @click="createBlog"
                                    >
                                        Create Blog
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="isLoading">
                        <td
                            colspan="5"
                            class="px-6 py-12 text-center"
                        >
                            <div class="flex items-center justify-center">
                                <svg
                                    class="animate-spin -ml-1 mr-3 h-5 w-5 text-lime-600"
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
                                <span class="text-gray-600 dark:text-gray-400">Loading blogs...</span>
                            </div>
                        </td>
                    </tr>

                    <tr
                        v-for="blog in blogs.data"
                        :key="blog.id"
                        class="hover:bg-gray-50 dark:hover:bg-slate-700"
                    >
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-12 w-12 flex-shrink-0">
                                    <div
                                        v-if="blog.image"
                                        class="h-full w-full rounded-lg overflow-hidden border-2 border-gray-200 dark:border-slate-600 shadow-sm"
                                    >
                                        <img
                                            :src="blog.image"
                                            :alt="`${blog.title || 'Blog'} image`"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                            @error="handleImageError"
                                        >
                                    </div>
                                    <div
                                        v-else
                                        class="h-full w-full rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm shadow-md"
                                    >
                                        IMG
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ blog.title || 'Untitled Blog' }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                        {{ blog.excerpt ? blog.excerpt.substring(0, 60) + '...' : 'No excerpt available' }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border items-center"
                                :class="getStatusClass(blog.is_published)"
                            >
                                <span
                                    class="mr-1"
                                    v-html="getStatusIcon(blog.is_published)"
                                />
                                {{ blog.is_published ? 'Published' : 'Draft' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ blog.read_time || 0 }} min
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                read time
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                {{ formatDate(blog.created_at) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ blog.updated_at !== blog.created_at ? 'Updated: ' + formatDate(blog.updated_at) : 'Created' }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-900/50 focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                                    @click="editBlog(blog)"
                                >
                                    <svg
                                        class="h-3 w-3 mr-1"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                        />
                                    </svg>
                                    Edit
                                </button>

                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                                    @click="openDeleteModal(blog)"
                                >
                                    <svg
                                        class="h-3 w-3 mr-1"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminPagination
            v-if="blogs.total > 0"
            :current-page="currentPage"
            :last-page="lastPage"
            :total="blogs.total"
            :per-page="blogs.per_page || 20"
            item-name="blogs"
            @page-change="changePage"
        />

        <div
            v-if="showDeleteModal"
            class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50"
            @click="closeDeleteModal"
        >
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 ease-out"
                @click.stop
            >
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                        <svg
                            class="w-6 h-6 text-red-600 dark:text-red-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                            />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">
                        Delete Blog Post
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-2">
                        Are you sure you want to delete "<strong>{{ selectedBlog?.title }}</strong>"?
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 text-center text-xs mb-4">
                        This action cannot be undone and will permanently remove the blog post and all associated data.
                    </p>
                    <div class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
                        <button
                            :disabled="isProcessing"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            @click="confirmDelete"
                        >
                            <svg
                                v-if="isProcessing"
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
                            {{ isProcessing ? 'Deleting...' : 'Delete Blog' }}
                        </button>
                        <button
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-white hover:bg-gray-50 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-slate-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2"
                            @click="closeDeleteModal"
                        >
                            Cancel
                        </button>
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
</style>
