<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import AdminPagination from "@/Components/AdminPagination.vue";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    users: {
        type: Array,
        required: true,
        default: () => [],
        validator: (value) => Array.isArray(value)
    },
    meta: {
        type: Object,
        default: () => ({
            total: 0,
            current_page: 1,
            per_page: 10,
            last_page: 1
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                typeof value.total === 'number' &&
                typeof value.current_page === 'number' &&
                typeof value.per_page === 'number' &&
                typeof value.last_page === 'number';
        }
    },
    stats: {
        type: Object,
        default: () => ({
            totalUsers: 0,
            activeUsers: 0,
            emailVerifiedUsers: 0,
            pendingUsers: 0
        }),
        validator: (value) => {
            return value && typeof value === 'object' &&
                typeof value.totalUsers === 'number' &&
                typeof value.activeUsers === 'number' &&
                typeof value.emailVerifiedUsers === 'number' &&
                typeof value.pendingUsers === 'number';
        }
    },
    filters: {
        type: Object,
        default: () => ({
            sort_field: 'created_at',
            sort_direction: 'desc'
        }),
        validator: (value) => value && typeof value === 'object'
    },
    currentUser: {
        type: Object,
        default: () => ({}),
        validator: (value) => value && typeof value === 'object'
    }
});

const { showToast } = useToast();
const isLoading = ref(false);
const updatingUser = ref(null);
const error = ref(null);
const showMailModal = ref(false);
const selectedUser = ref(null);
const mailForm = ref({
    subject: '',
    content: ''
});
const sendingMail = ref(false);
const searchTerm = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const selectedKycStatus = ref(props.filters?.kyc_status || '');
const statusFilterOptions = [
    { value: '0', label: 'Inactive' },
    { value: '1', label: 'Active' },
    { value: '2', label: 'Pending' },
    { value: '3', label: 'Suspended' }
];

const kycStatusFilterOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'reviewing', label: 'Reviewing' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const statusOptions = [
    { value: 0, label: 'Inactive' },
    { value: 1, label: 'Active' },
    { value: 2, label: 'Pending' },
    { value: 3, label: 'Suspended' }
];

const isAdmin = computed(() => props.currentUser?.role === 'admin');
const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const sortField = computed(() => props.filters.sort_field || 'created_at');
const sortDirection = computed(() => props.filters.sort_direction || 'desc');
const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedStatus.value || selectedKycStatus.value);
});

const totalUsersCount = computed(() => props.stats?.totalUsers || 0);
const activeUsersCount = computed(() => props.stats?.activeUsers || 0);
const emailVerifiedUsersCount = computed(() => props.stats?.emailVerifiedUsers || 0);
const pendingUsersCount = computed(() => props.stats?.pendingUsers || 0);
const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const applyFilters = () => {
    if (isLoading.value) return;

    isLoading.value = true;
    error.value = null;

    const params = {
        page: 1,
        sort_field: sortField.value,
        sort_direction: sortDirection.value
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedKycStatus.value) params.kyc_status = selectedKycStatus.value;

    router.get('/admin/users', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Filter error:', errors);
            error.value = 'Failed to apply filters. Please try again.';
            showToast('Failed to apply filters', 'error');
        }
    });
};

const clearSearch = () => {
    searchTerm.value = '';
    applyFilters();
};

const clearRoleFilter = () => {
    applyFilters();
};

const clearStatusFilter = () => {
    selectedStatus.value = '';
    applyFilters();
};

const clearKycStatusFilter = () => {
    selectedKycStatus.value = '';
    applyFilters();
};

const clearAllFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = '';
    selectedKycStatus.value = '';

    isLoading.value = true;
    error.value = null;

    router.get('/admin/users', {
        sort_field: 'created_at',
        sort_direction: 'desc'
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
            showToast('Filters cleared successfully', 'success');
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Clear filters error:', errors);
            error.value = 'Failed to clear filters. Please try again.';
            showToast('Failed to clear filters', 'error');
        }
    });
};

const openMailModal = (user) => {
    selectedUser.value = user;
    mailForm.value = {
        subject: '',
        content: ''
    };
    showMailModal.value = true;
};

const closeMailModal = () => {
    showMailModal.value = false;
    selectedUser.value = null;
    mailForm.value = {
        subject: '',
        content: ''
    };
};

const sendUserMail = async () => {
    if (!selectedUser.value || !mailForm.value.subject.trim() || !mailForm.value.content.trim()) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    sendingMail.value = true;

    try {
        router.post(`/admin/users/${selectedUser.value.id}/send-mail`, {
            subject: mailForm.value.subject.trim(),
            content: mailForm.value.content.trim()
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Mail sent successfully to ${selectedUser.value.email}`, 'success');
                closeMailModal();
            },
            onError: (errors) => {
                console.error('Mail send error:', errors);
                const errorMessage = errors.message || 'Failed to send mail';
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                sendingMail.value = false;
            }
        });
    } catch (error) {
        console.error('Error sending mail:', error);
        showToast('Failed to send mail', 'error');
        sendingMail.value = false;
    }
};

const getStatusLabel = (status) => {
    const option = statusFilterOptions.find(s => s.value === status);
    return option ? option.label : status;
};

const getKycStatusLabel = (kycStatus) => {
    const option = kycStatusFilterOptions.find(k => k.value === kycStatus);
    return option ? option.label : kycStatus;
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US').format(num);
};

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2);
};

const getAvatarUrl = (avatar) => {
    if (!avatar) return null;
    return avatar.startsWith('http') ? avatar : `/storage/${avatar}`;
};

const handleAvatarError = (event) => {
    event.target.style.display = 'none';
    const fallback = document.createElement('div');
    fallback.className = 'h-full w-full rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm';
    fallback.textContent = '?';
    event.target.parentElement.appendChild(fallback);
};

const formatRole = (role) => {
    if (!role) return 'User';
    return role.charAt(0).toUpperCase() + role.slice(1);
};

const formatStatus = (status) => {
    const statusMap = {
        0: 'Inactive',
        1: 'Active',
        2: 'Pending',
        3: 'Suspended'
    };
    return statusMap[status] || 'Unknown';
};

const formatKycStatus = (kycStatus) => {
    const kycStatusMap = {
        'pending': 'Pending',
        'reviewing': 'Reviewing',
        'approved': 'Approved',
        'rejected': 'Rejected',
    };
    return kycStatusMap[kycStatus] || 'Unknown';
};

const getRoleClass = (role) => {
    const classes = {
        'admin': 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700',
        'moderator': 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
        'user': 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700'
    };
    return classes[role] || classes.user;
};

const getStatusClass = (status) => {
    const classes = {
        0: 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700',
        1: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        2: 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700',
        3: 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
    };
    return classes[status] || classes[0];
};

const getKycStatusClass = (kycStatus) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700',
        'reviewing': 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700',
        'approved': 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700',
        'rejected': 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
    };
    return classes[kycStatus] || classes['pending'];
};

const getStatusDotClass = (status) => {
    const classes = {
        0: 'bg-gray-500',
        1: 'bg-green-500',
        2: 'bg-yellow-500',
        3: 'bg-red-500'
    };
    return classes[status] || classes[0];
};

const getKycStatusDotClass = (kycStatus) => {
    const classes = {
        'pending': 'bg-yellow-500',
        'reviewing': 'bg-blue-500',
        'approved': 'bg-green-500',
        'rejected': 'bg-red-500',
    };
    return classes[kycStatus] || 'bg-gray-500';
};

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

const formatLastLogin = (dateString) => {
    if (!dateString) return 'Never';
    return formatDate(dateString);
};

const getRelativeTime = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
        return `${Math.floor(diffInSeconds / 31536000)}y ago`;
    } catch (error) {
        console.error('Relative time error:', error);
        return '';
    }
};

const handleSort = (field) => {
    if (isLoading.value) return;

    const currentSortField = props.filters.sort_field;
    const currentSortDirection = props.filters.sort_direction;

    let newDirection = 'asc';
    if (currentSortField === field && currentSortDirection === 'asc') {
        newDirection = 'desc';
    }

    isLoading.value = true;
    error.value = null;

    const params = {
        sort_field: field,
        sort_direction: newDirection,
        page: 1
    };

    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedKycStatus.value) params.kyc_status = selectedKycStatus.value;

    router.get('/admin/users', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Sort error:', errors);
            error.value = 'Failed to sort users. Please try again.';
            showToast('Failed to sort users', 'error');
        }
    });
};

const changePage = (page) => {
    if (isLoading.value || page === currentPage.value) return;

    isLoading.value = true;
    error.value = null;

    const params = { page };

    // Preserve current filters and sorting
    if (searchTerm.value?.trim()) params.search = searchTerm.value.trim();
    if (selectedStatus.value) params.status = selectedStatus.value;
    if (selectedKycStatus.value) params.kyc_status = selectedKycStatus.value;
    if (sortField.value) params.sort_field = sortField.value;
    if (sortDirection.value) params.sort_direction = sortDirection.value;

    router.get('/admin/users', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        },
        onError: (errors) => {
            isLoading.value = false;
            console.error('Pagination error:', errors);
            error.value = 'Failed to load page. Please try again.';
            showToast('Failed to load page', 'error');
        }
    });
};

const canUpdateStatus = (user) => {
    if (!isAdmin.value) return false;
    if (user.id === props.currentUser?.id) return false;
    return true;
};

const updateUserStatus = async (userId, newStatus) => {
    if (!isAdmin.value || updatingUser.value === userId) return;

    const user = props.users.find(u => u.id === userId);
    if (!user || !canUpdateStatus(user)) {
        showToast('You do not have permission to update this user\'s status', 'error');
        return;
    }

    updatingUser.value = userId;
    error.value = null;

    try {
        router.put(`/admin/users/${userId}/status`, {
            status: newStatus
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast(`User status updated to ${formatStatus(newStatus)}`, 'success');
            },
            onError: (errors) => {
                console.error('Status update error:', errors);
                const errorMessage = errors.message || 'This is a demo version. You cannot change anything';
                error.value = errorMessage;
                showToast(errorMessage, 'error');
            },
            onFinish: () => {
                updatingUser.value = null;
            }
        });
    } catch (error) {
        console.error('Error updating user status:', error);
        showToast('This is a demo version. You cannot change anything', 'error');
        updatingUser.value = null;
    }
};


const loginAsUser = (user) => {
    if (!isAdmin.value) return;
    const url = `/admin/users/${user.id}/login-as`;
    window.open(url, '_blank');
};

onMounted(() => {
    const page = usePage();
    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) {
        error.value = flash.error;
        showToast(flash.error, 'error');
    }
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
});
</script>

<template>
  <AdminLayout
    title="User Management"
    page-section="Users & Accounts"
  >
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            User Management
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage and monitor all user accounts in your system
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-blue-600 dark:text-blue-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Users
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(totalUsersCount) }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-green-600 dark:text-green-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Active Users
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(activeUsersCount) }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-purple-600 dark:text-purple-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Email Verified
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(emailVerifiedUsersCount) }}
            </p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center">
          <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
            <svg
              class="h-6 w-6 text-yellow-600 dark:text-yellow-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Pending Users
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(pendingUsersCount) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 mb-6">
      <div class="p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div class="flex-1 max-w-md">
            <div class="relative">
              <svg
                class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>
              <input
                id="search-users"
                v-model="searchTerm"
                type="text"
                placeholder="Search by name, email, or ID..."
                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                @input="debouncedSearch"
              >
              <button
                v-if="searchTerm"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="clearSearch"
              >
                <svg
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
              <div class="min-w-[140px]">
              <select
                v-model="selectedStatus"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All Status
                </option>
                <option
                  v-for="status in statusFilterOptions"
                  :key="status.value"
                  :value="status.value"
                >
                  {{ status.label }}
                </option>
              </select>
            </div>

            <div class="min-w-[140px]">
              <select
                v-model="selectedKycStatus"
                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                @change="applyFilters"
              >
                <option value="">
                  All KYC Status
                </option>
                <option
                  v-for="kyc in kycStatusFilterOptions"
                  :key="kyc.value"
                  :value="kyc.value"
                >
                  {{ kyc.label }}
                </option>
              </select>
            </div>

            <button
              v-if="hasActiveFilters"
              class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
              @click="clearAllFilters"
            >
              Clear Filters
            </button>
          </div>
        </div>

        <div
          v-if="hasActiveFilters"
          class="mt-4 flex flex-wrap items-center gap-2"
        >
          <span class="text-sm text-gray-600 dark:text-gray-400">Active filters:</span>

          <span
            v-if="searchTerm"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
          >
            Search: "{{ searchTerm }}"
            <button
              class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
              @click="clearSearch"
            >
              <svg
                class="h-3 w-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </span>


          <span
            v-if="selectedStatus"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
          >
            Status: {{ getStatusLabel(selectedStatus) }}
            <button
              class="ml-2 text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200"
              @click="clearStatusFilter"
            >
              <svg
                class="h-3 w-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </span>

          <span
            v-if="selectedKycStatus"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
          >
            KYC: {{ getKycStatusLabel(selectedKycStatus) }}
            <button
              class="ml-2 text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200"
              @click="clearKycStatusFilter"
            >
              <svg
                class="h-3 w-3"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </span>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
          <thead class="bg-gray-50 dark:bg-slate-900">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('name')"
              >
                <div class="flex items-center space-x-1">
                  <span>USER</span>
                  <svg
                    v-if="sortField === 'name'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('email')"
              >
                <div class="flex items-center space-x-1">
                  <span>EMAIL</span>
                  <svg
                    v-if="sortField === 'email'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Role
                </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300"
                @click="handleSort('status')"
              >
                <div class="flex items-center space-x-1">
                  <span>STATUS</span>
                  <svg
                    v-if="sortField === 'status'"
                    class="w-4 h-4"
                    :class="sortDirection === 'asc' ? 'transform rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                KYC STATUS
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                LAST LOGIN
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                ACTIONS
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-if="!isLoading && users.length === 0">
              <td
                colspan="6"
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
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No users found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No users match your current filters. Try adjusting the search criteria.
                  </p>
                </div>
              </td>
            </tr>

            <tr v-if="isLoading">
              <td
                colspan="6"
                class="px-6 py-12 text-center"
              >
                <div class="flex items-center justify-center">
                  <svg
                    class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600"
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
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400">Loading users...</span>
                </div>
              </td>
            </tr>

            <tr
              v-for="user in users"
              :key="user.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-3">
                  <div class="h-10 w-10 flex-shrink-0">
                    <div
                      v-if="user.avatar"
                      class="h-full w-full rounded-full overflow-hidden border-2 border-gray-200 dark:border-slate-600 shadow-sm"
                    >
                      <img
                        :src="getAvatarUrl(user.avatar)"
                        :alt="`${user.name || 'User'} avatar`"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        @error="handleAvatarError"
                      >
                    </div>
                    <div
                      v-else
                      class="h-full w-full rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-md"
                    >
                      {{ getInitials(user.name) }}
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                      {{ user.name || 'Unnamed User' }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <div class="text-sm text-gray-600 dark:text-gray-300 truncate max-w-[200px]">
                    {{ user.email || 'No Email' }}
                  </div>
                  <div
                    v-if="user.email_verified_at"
                    class="flex-shrink-0"
                  >
                    <svg
                      class="h-4 w-4 text-green-500"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                  :class="getRoleClass(user.role)"
                >
                  {{ formatRole(user.role) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="min-w-[140px]">
                  <select
                    v-if="isAdmin && canUpdateStatus(user)"
                    :value="user.status"
                    :disabled="updatingUser === user.id"
                    class="w-full text-xs rounded-lg border-gray-300 dark:border-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 py-2 px-3 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-colors duration-200"
                    @change="updateUserStatus(user.id, parseInt($event.target.value))"
                  >
                    <option
                      v-for="status in statusOptions"
                      :key="status.value"
                      :value="status.value"
                    >
                      {{ status.label }}
                    </option>
                  </select>
                  <span
                    v-else
                    class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                    :class="getStatusClass(user.status)"
                  >
                    <span
                      class="w-2 h-2 rounded-full mr-1"
                      :class="getStatusDotClass(user.status)"
                    />
                    {{ formatStatus(user.status) }}
                  </span>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full items-center border"
                  :class="getKycStatusClass(user.kyc_status)"
                >
                  <span
                    class="w-2 h-2 rounded-full mr-1"
                    :class="getKycStatusDotClass(user.kyc_status)"
                  />
                  {{ formatKycStatus(user.kyc_status) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">
                  {{ formatLastLogin(user.last_login_at) }}
                </div>
                <div
                  v-if="user.last_login_at"
                  class="text-xs text-gray-500 dark:text-gray-400"
                >
                  {{ getRelativeTime(user.last_login_at) }}
                </div>
              </td>

                <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    <button
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-sm"
                        @click="openMailModal(user)"
                        :disabled="!user.email"
                    >
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Send Mail
                    </button>

                    <button
                        v-if="isAdmin"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1 transition-all duration-200 shadow-sm"
                        @click="loginAsUser(user)"
                        :title="'Login as this user'"
                    >
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013 3v1" />
                        </svg>
                        Login As
                    </button>
                </div>
                </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AdminPagination
      v-if="meta.total > 0"
      :current-page="currentPage"
      :last-page="lastPage"
      :total="meta.total"
      :per-page="20"
      item-name="users"
      @page-change="changePage"
    />

      <div v-if="showMailModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50" @click="closeMailModal">
          <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-2xl w-full mx-4 transform transition-all duration-300 ease-out max-h-[90vh] overflow-y-auto" @click.stop>
              <div class="p-6">
                  <div class="flex items-center justify-between mb-6">
                      <div class="flex items-center space-x-3">
                          <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                              <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                              </svg>
                          </div>
                          <div>
                              <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Send Mail</h3>
                              <p class="text-sm text-gray-500 dark:text-gray-400">
                                  Send email to: {{ selectedUser?.email }}
                              </p>
                          </div>
                      </div>
                      <button @click="closeMailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200">
                          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                      </button>
                  </div>

                  <form @submit.prevent="sendUserMail">
                      <div class="space-y-4">
                          <div>
                              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject *</label>
                              <input
                                  v-model="mailForm.subject"
                                  type="text"
                                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                  placeholder="Enter email subject..."
                                  :disabled="sendingMail"
                                  required
                              />
                          </div>

                          <div>
                              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message *</label>
                              <textarea
                                  v-model="mailForm.content"
                                  rows="8"
                                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 resize-none"
                                  placeholder="Enter your message here..."
                                  :disabled="sendingMail"
                                  required
                              />
                              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">HTML content is supported</p>
                          </div>
                      </div>

                      <div class="flex justify-end gap-3 mt-6">
                          <button
                              type="button"
                              @click="closeMailModal"
                              class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
                              :disabled="sendingMail"
                          >
                              Cancel
                          </button>
                          <button
                              type="submit"
                              :disabled="sendingMail || !mailForm.subject.trim() || !mailForm.content.trim()"
                              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-lg transition-colors duration-200 flex items-center"
                          >
                              <svg
                                  v-if="sendingMail"
                                  class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                  fill="none"
                                  viewBox="0 0 24 24"
                              >
                                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                              </svg>
                              {{ sendingMail ? 'Sending...' : 'Send Mail' }}
                          </button>
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

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}</style>
