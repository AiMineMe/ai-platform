<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";

const props = defineProps({
    purchases: {
        type: Array,
        default: () => []
    },
    statistics: {
        type: Object,
        default: () => ({
            total_sales: 0,
            total_purchases: 0,
            unique_buyers: 0,
            pending_purchases: 0
        })
    },
    meta: {
        type: Object,
        default: () => ({
            total: 0,
            current_page: 1,
            per_page: 20,
            last_page: 1
        })
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

const isLoading = ref(false);
const search = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || '');
const page = ref(props.meta?.current_page || 1);
const perPage = ref(props.meta?.per_page || 20);

const hasActiveFilters = computed(() => {
    return !!(search.value || selectedStatus.value);
});

const formatNumber = (num) => {
    const number = parseFloat(num) || 0;
    return new Intl.NumberFormat('en-US').format(number);
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '' || isNaN(value)) {
        return '0.00';
    }

    const numValue = parseFloat(value);
    if (isNaN(numValue)) {
        return '0.00';
    }

    const absValue = Math.abs(numValue);

    if (absValue >= 1000000000) {
        return (numValue / 1000000000).toFixed(2) + 'B';
    } else if (absValue >= 1000000) {
        return (numValue / 1000000).toFixed(2) + 'M';
    } else if (absValue >= 1000) {
        return (numValue / 1000).toFixed(2) + 'K';
    } else {
        return numValue.toFixed(2);
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    const options = { hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleTimeString(undefined, options);
};

const getUserInitials = (name) => {
    if (!name || typeof name !== 'string') return '??';
    const names = name.split(' ');
    return names.length > 1
        ? (names[0][0] + names[1][0]).toUpperCase()
        : name.substring(0, 2).toUpperCase();
};

const capitalize = (str) => {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
};

const getStatusClass = (status) => {
    switch (status?.toLowerCase()) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
    }
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const clearAllFilters = () => {
    search.value = '';
    selectedStatus.value = '';
    applyFilters();
};

const applyFilters = () => {
    router.get('/admin/purchase-history', {
        search: search.value,
        status: selectedStatus.value,
        page: 1
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
};

const refreshData = () => {
    isLoading.value = true;
    router.reload({
        onSuccess: () => {
            isLoading.value = false;
        },
        onError: () => {
            isLoading.value = false;
        }
    });
};

const goToPage = (newPage) => {
    if (newPage < 1 || newPage > (props.meta?.last_page || 1)) return;
    page.value = newPage;

    router.get('/admin/purchase-history', {
        search: search.value,
        status: selectedStatus.value,
        page: page.value
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
};
</script>

<template>
  <AdminLayout
    title="Purchase History"
    page-section="ICO Management"
  >
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Purchase History
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Monitor all ICO token purchases and transactions
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
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
                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Sales
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              ${{ formatMoney(statistics.total_sales) }}
            </p>
          </div>
        </div>
      </div>

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
                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Total Purchases
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(statistics.total_purchases) }}
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
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
              Unique Buyers
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(statistics.unique_buyers) }}
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
              Pending
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ formatNumber(statistics.pending_purchases) }}
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
                v-model="search"
                type="text"
                placeholder="Search by purchase ID, user email, or token..."
                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                @input="debouncedSearch"
              >
              <button
                v-if="search"
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
                  All Statuses
                </option>
                <option value="completed">
                  Completed
                </option>
                <option value="pending">
                  Pending
                </option>
                <option value="failed">
                  Failed
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
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
          <thead class="bg-gray-50 dark:bg-slate-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                PURCHASE ID
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                USER
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                TOKEN
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                AMOUNT
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                TOKENS
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                STATUS
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                PAYMENT METHOD
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                DATE
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
            <tr v-if="!isLoading && purchases.length === 0">
              <td
                colspan="7"
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
                      d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                    No purchases found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No purchase history matches your current filters.
                  </p>
                </div>
              </td>
            </tr>

            <tr v-if="isLoading">
              <td
                colspan="7"
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
                  <span class="text-gray-600 dark:text-gray-400">Loading purchase history...</span>
                </div>
              </td>
            </tr>

            <tr
              v-for="purchase in purchases"
              :key="purchase.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-mono text-gray-900 dark:text-gray-100">
                  {{ purchase.purchase_id }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-xs">
                    {{ getUserInitials(purchase.user.name) }}
                  </div>
                  <div class="ml-3">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ purchase.user.name }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      {{ purchase.user.email }}
                    </div>
                  </div>
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                  {{ purchase.ico_token.name }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                  {{ purchase.ico_token.symbol }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                  ${{ formatNumber(purchase.amount_usd) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  @ ${{ formatNumber(purchase.token_price) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                  {{ formatNumber(purchase.tokens_purchased) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  tokens
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full"
                  :class="getStatusClass(purchase.status)"
                >
                  {{ capitalize(purchase.status) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 rounded">
                  {{ purchase.metadata?.payment_method || 'N/A' }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">
                  {{ formatDate(purchase.purchased_at || purchase.created_at) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatTime(purchase.purchased_at || purchase.created_at) }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="meta.last_page > 1"
        class="px-6 py-4 border-t border-gray-200 dark:border-slate-700"
      >
        <div class="flex justify-between items-center">
          <div class="text-sm text-gray-700 dark:text-gray-300">
            Showing {{ (page - 1) * perPage + 1 }} to {{ Math.min(page * perPage, meta.total) }} of {{ meta.total }} results
          </div>
          <div class="flex space-x-2">
            <button
              v-for="pageNum in Math.min(5, meta.last_page || 1)"
              :key="pageNum"
              :class="[
                'px-3 py-1 border rounded text-sm transition-colors duration-200',
                page === pageNum ? 'bg-blue-600 text-white border-blue-600 dark:bg-blue-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-slate-700 dark:text-gray-300 dark:border-slate-600 dark:hover:bg-slate-600'
              ]"
              @click="goToPage(pageNum)"
            >
              {{ pageNum }}
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
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
