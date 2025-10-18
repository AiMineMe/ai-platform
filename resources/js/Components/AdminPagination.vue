<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true
    },
    lastPage: {
        type: Number,
        required: true
    },
    total: {
        type: Number,
        required: true
    },
    perPage: {
        type: Number,
        required: true
    },
    itemName: {
        type: String,
        default: 'items'
    },
    showPerPageSelector: {
        type: Boolean,
        default: false
    },
    perPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100]
    },
    showQuickJump: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['page-change', 'per-page-change']);
const jumpToPage = ref('');

const from = computed(() => {
    if (props.total === 0) return 0;
    return (props.currentPage - 1) * props.perPage + 1;
});

const to = computed(() => {
    if (props.total === 0) return 0;
    return Math.min(props.currentPage * props.perPage, props.total);
});

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    const current = props.currentPage;
    const last = props.lastPage;
    let start = Math.max(1, current - Math.floor(maxVisible / 2));
    let end = Math.min(last, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    if (start <= 2) start = 2;
    if (end >= last - 1) end = last - 1;

    for (let i = start; i <= end; i++) {
        if (i > 1 && i < last) {
            pages.push(i);
        }
    }

    return pages;
});

const showFirstPage = computed(() => {
    return props.lastPage > 1 && !visiblePages.value.includes(1);
});

const showLastPage = computed(() => {
    return props.lastPage > 1 && !visiblePages.value.includes(props.lastPage);
});

const showFirstEllipsis = computed(() => {
    return showFirstPage.value && visiblePages.value.length > 0 && visiblePages.value[0] > 2;
});

const showLastEllipsis = computed(() => {
    return showLastPage.value && visiblePages.value.length > 0 && visiblePages.value[visiblePages.value.length - 1] < props.lastPage - 1;
});

const isValidJumpPage = computed(() => {
    const page = parseInt(jumpToPage.value);
    return page >= 1 && page <= props.lastPage && page !== props.currentPage;
});

const pageButtonClass = (pageNum) => {
    const isActive = props.currentPage === pageNum;
    return [
        'px-3 py-2 rounded-lg text-sm font-medium border transition-all duration-200 min-w-[40px]',
        isActive
            ? 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700 shadow-md'
            : 'bg-white dark:bg-slate-600 border-gray-300 dark:border-slate-500 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-500 hover:shadow-md'
    ];
};

const goToPage = (page) => {
    if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
        emit('page-change', page);
    }
};

const changePerPage = (newPerPage) => {
    emit('per-page-change', parseInt(newPerPage));
};

const handleQuickJump = () => {
    if (isValidJumpPage.value) {
        goToPage(parseInt(jumpToPage.value));
        jumpToPage.value = '';
    }
};
</script>

<template>
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 px-6 py-4 mt-6">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="text-sm text-gray-700 dark:text-gray-300 order-2 sm:order-1">
        <span v-if="total > 0">
          Showing <span class="font-semibold text-gray-900 dark:text-gray-100">{{ from }}</span> to
          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ to }}</span> of
          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ total }}</span> {{ itemName }}
        </span>
        <span
          v-else
          class="text-gray-500 dark:text-gray-400"
        >
          No {{ itemName }} found
        </span>
      </div>

      <div
        v-if="lastPage > 1"
        class="flex justify-center items-center space-x-1 order-1 sm:order-2"
      >
        <button
          :disabled="currentPage <= 1"
          :class="[
            'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-1',
            currentPage <= 1
              ? 'opacity-50 cursor-not-allowed bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-gray-500'
              : 'bg-white dark:bg-slate-600 border border-gray-300 dark:border-slate-500 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-500 hover:shadow-md'
          ]"
          @click="goToPage(currentPage - 1)"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15 19l-7-7 7-7"
            />
          </svg>
          <span class="hidden sm:inline">Previous</span>
        </button>

        <button
          v-if="showFirstPage"
          :class="pageButtonClass(1)"
          @click="goToPage(1)"
        >
          1
        </button>

        <span
          v-if="showFirstEllipsis"
          class="px-2 py-2 text-gray-500 dark:text-gray-400"
        >... </span>

        <button
          v-for="pageNum in visiblePages"
          :key="pageNum"
          :class="pageButtonClass(pageNum)"
          @click="goToPage(pageNum)"
        >
          {{ pageNum }}
        </button>

        <span
          v-if="showLastEllipsis"
          class="px-2 py-2 text-gray-500 dark:text-gray-400"
        >...</span>

        <button
          v-if="showLastPage"
          :class="pageButtonClass(lastPage)"
          @click="goToPage(lastPage)"
        >
          {{ lastPage }}
        </button>

        <div class="flex sm:hidden items-center px-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg mx-2">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ currentPage }} / {{ lastPage }}
          </span>
        </div>

        <button
          :disabled="currentPage >= lastPage"
          :class="[
            'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-1',
            currentPage >= lastPage
              ? 'opacity-50 cursor-not-allowed bg-gray-100 dark:bg-slate-700 text-gray-400 dark:text-gray-500'
              : 'bg-white dark:bg-slate-600 border border-gray-300 dark:border-slate-500 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-500 hover:shadow-md'
          ]"
          @click="goToPage(currentPage + 1)"
        >
          <span class="hidden sm:inline">Next</span>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            />
          </svg>
        </button>
      </div>

      <div
        v-if="showPerPageSelector"
        class="flex items-center space-x-2 order-3 sm:order-3"
      >
        <label class="text-sm text-gray-700 dark:text-gray-300">Show:</label>
        <select
          :value="perPage"
          class="rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm px-3 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          @change="changePerPage($event.target.value)"
        >
          <option
            v-for="option in perPageOptions"
            :key="option"
            :value="option"
          >
            {{ option }}
          </option>
        </select>
        <span class="text-sm text-gray-700 dark:text-gray-300">per page</span>
      </div>
    </div>

    <div
      v-if="showQuickJump && lastPage > 10"
      class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-600"
    >
      <div class="flex items-center justify-center space-x-2">
        <label class="text-sm text-gray-600 dark:text-gray-400">Jump to page:</label>
        <input
          v-model="jumpToPage"
          type="number"
          :min="1"
          :max="lastPage"
          class="w-20 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 text-sm px-2 py-1 text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          @keyup.enter="handleQuickJump"
        >
        <button
          :disabled="!isValidJumpPage"
          class="px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white text-sm rounded-lg transition-colors duration-200"
          @click="handleQuickJump"
        >
          Go
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
button:not(:disabled):hover {
    transform: translateY(-1px);
}

button:not(:disabled):active {
    transform: translateY(0);
}

button:focus,
select:focus,
input:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

@media (max-width: 640px) {
    .min-w-\[40px\] {
        min-width: 36px;
    }
}

@media (prefers-color-scheme: dark) {
    .shadow-md {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    }
}
</style>
