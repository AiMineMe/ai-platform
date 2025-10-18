<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AdminLayout from '@/Layouts/AdminLayout/AdminLayout.vue'
import AdminPagination from '@/Components/AdminPagination.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    contacts: Object,
    stats: Object,
    filters: Object
})

const { showToast } = useToast()
const isLoading = ref(false)
const isProcessing = ref(false)
const isUpdatingStatus = ref(false)
const error = ref(null)
const searchTerm = ref(props.filters?.search || '')
const selectedStatus = ref(props.filters?.status || '')

const showDeleteModal = ref(false)
const showViewModal = ref(false)
const selectedContact = ref(null)
const selectedContactStatus = ref('')

const currentPage = computed(() => props.contacts.current_page || 1)
const sortField = computed(() => props.filters.sort_field || 'created_at')
const sortDirection = computed(() => props.filters.sort_direction || 'desc')

const getStatusLabel = (status) => {
    const labels = {
        'unread': 'Unread',
        'read': 'Read',
        'replied': 'Replied'
    }
    return labels[status] || status
}

const handleSort = (field) => {
    if (isLoading.value) return

    const currentSortField = props.filters.sort_field
    const currentSortDirection = props.filters.sort_direction

    let newDirection = 'asc'
    if (currentSortField === field && currentSortDirection === 'asc') {
        newDirection = 'desc'
    }

    isLoading.value = true
    error.value = null

    const params = {
        sort_field: field,
        sort_direction: newDirection,
        page: 1
    }

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim()
    if (selectedStatus.value) params.status = selectedStatus.value

    router.get('/admin/contacts', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false
        },
        onError: (errors) => {
            isLoading.value = false
            console.error('Sort error:', errors)
            error.value = 'Failed to sort contacts. Please try again.'
            showToast('Failed to sort contacts', 'error')
        }
    })
}

const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return

    isLoading.value = true
    error.value = null

    const params = { page }
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim()
    if (selectedStatus.value) params.status = selectedStatus.value
    if (sortField.value) params.sort_field = sortField.value
    if (sortDirection.value) params.sort_direction = sortDirection.value

    router.get('/admin/contacts', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false
        },
        onError: (errors) => {
            isLoading.value = false
            console.error('Pagination error:', errors)
            error.value = 'Failed to load page. Please try again.'
            showToast('Failed to load page', 'error')
        }
    })
}

const viewContact = (contact) => {
    selectedContact.value = contact
    selectedContactStatus.value = contact.status
    showViewModal.value = true
}

const closeViewModal = () => {
    showViewModal.value = false
    selectedContact.value = null
    selectedContactStatus.value = ''
}

const openDeleteModal = (contact) => {
    selectedContact.value = contact
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    showDeleteModal.value = false
    selectedContact.value = null
}

const confirmDelete = () => {
    if (!selectedContact.value || isProcessing.value) return

    isProcessing.value = true

    router.delete(`/admin/contacts/${selectedContact.value.id}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal()
            showToast('Contact message deleted successfully', 'success')
        },
        onError: (errors) => {
            console.error('Delete error:', errors)
            showToast('Failed to delete contact message', 'error')
        },
        onFinish: () => isProcessing.value = false
    })
}

const updateContactStatus = () => {
    if (!selectedContact.value || isUpdatingStatus.value) return

    isUpdatingStatus.value = true

    router.patch(`/admin/contacts/${selectedContact.value.id}/status`, {
        status: selectedContactStatus.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedContact.value.status = selectedContactStatus.value
            showToast('Contact status updated successfully', 'success')
        },
        onError: (errors) => {
            console.error('Status update error:', errors)
            showToast('Failed to update contact status', 'error')
        },
        onFinish: () => isUpdatingStatus.value = false
    })
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatStatus = (status) => {
    return status.charAt(0).toUpperCase() + status.slice(1)
}

const getStatusClass = (status) => {
    const classes = {
        unread: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
        read: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        replied: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
    }
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300'
}

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return '?'
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2)
}

onMounted(() => {
    const page = usePage()
    const flash = page.props.flash

    if (flash?.success) showToast(flash.success, 'success')
    if (flash?.error) {
        error.value = flash.error
        showToast(flash.error, 'error')
    }
    if (flash?.warning) showToast(flash.warning, 'warning')
    if (flash?.info) showToast(flash.info, 'info')
})
</script>

<template>
    <AdminLayout title="Contact Messages" page-section="Communication">
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Contact Messages</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Manage and respond to contact form submissions
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Messages</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Unread</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ stats.unread }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Read</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.read }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Replied</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ stats.replied }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="handleSort('name')">
                            <div class="flex items-center space-x-1">
                                <span>NAME</span>
                                <svg v-if="sortField === 'name'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="handleSort('email')">
                            <div class="flex items-center space-x-1">
                                <span>EMAIL</span>
                                <svg v-if="sortField === 'email'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="handleSort('subject')">
                            <div class="flex items-center space-x-1">
                                <span>SUBJECT</span>
                                <svg v-if="sortField === 'subject'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="handleSort('status')">
                            <div class="flex items-center space-x-1">
                                <span>STATUS</span>
                                <svg v-if="sortField === 'status'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer" @click="handleSort('created_at')">
                            <div class="flex items-center space-x-1">
                                <span>DATE</span>
                                <svg v-if="sortField === 'created_at'" class="w-4 h-4" :class="sortDirection === 'asc' ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            ACTIONS
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                    <tr v-if="!isLoading && contacts.data.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No contact messages found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No contact messages match your current filters. Try adjusting the search criteria.</p>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="isLoading">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">Loading contact messages...</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-gray-50 dark:hover:bg-slate-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div class="h-full w-full rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                        {{ getInitials(contact.name) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ contact.name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        ID: {{ contact.id }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                {{ contact.email }}
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900 dark:text-gray-100 truncate max-w-xs">
                                {{ contact.subject }}
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getStatusClass(contact.status)">
                                {{ formatStatus(contact.status) }}
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                            {{ formatDate(contact.created_at) }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-900/50 focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                                    @click="viewContact(contact)"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View
                                </button>

                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                                    @click="openDeleteModal(contact)"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
            v-if="contacts.total > 0"
            :current-page="contacts.current_page"
            :last-page="contacts.last_page"
            :total="contacts.total"
            :per-page="20"
            item-name="contacts"
            @page-change="changePage"
        />

        <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeDeleteModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 ease-out" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-2">Delete Contact Message</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-4">Are you sure you want to delete this contact message? This action cannot be undone.</p>

                    <div v-if="selectedContact" class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 mb-4">
                        <div class="text-sm space-y-2">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ selectedContact.name }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Email: {{ selectedContact.email }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Subject: {{ selectedContact.subject }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Status: {{ formatStatus(selectedContact.status) }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Date: {{ formatDate(selectedContact.created_at) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row-reverse sm:space-x-reverse sm:space-x-3 space-y-3 sm:space-y-0">
                        <button
                            :disabled="isProcessing"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            @click="confirmDelete"
                        >
                            <svg v-if="isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {{ isProcessing ? 'Deleting...' : 'Delete Message' }}
                        </button>
                        <button
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-white hover:bg-gray-50 dark:bg-slate-600 dark:hover:bg-slate-500 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-slate-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            @click="closeDeleteModal"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showViewModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeViewModal">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto transform transition-all duration-300 ease-out" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Contact Message Details</h3>
                        <button @click="closeViewModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="selectedContact" class="space-y-6">
                        <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ selectedContact.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ selectedContact.email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getStatusClass(selectedContact.status)">
                                        {{ formatStatus(selectedContact.status) }}
                                    </span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(selectedContact.created_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject</label>
                            <p class="text-sm text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-slate-700 rounded-lg p-3">
                                {{ selectedContact.subject }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</label>
                            <div class="text-sm text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-slate-700 rounded-lg p-4 max-h-60 overflow-y-auto whitespace-pre-wrap">
                                {{ selectedContact.message }}
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Update Status</label>
                            <div class="flex gap-2">
                                <select
                                    v-model="selectedContactStatus"
                                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm"
                                >
                                    <option value="unread">Unread</option>
                                    <option value="read">Read</option>
                                    <option value="replied">Replied</option>
                                </select>
                                <button
                                    @click="updateContactStatus"
                                    :disabled="isUpdatingStatus || selectedContactStatus === selectedContact.status"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors duration-200"
                                >
                                    <span v-if="!isUpdatingStatus">Update</span>
                                    <span v-else class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        Updating...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                        <button
                            @click="closeViewModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                        >
                            Close
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

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}
</style>
