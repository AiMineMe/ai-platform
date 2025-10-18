<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";
import { useToast } from "@/composables/useToast.js";

const props = defineProps({
    icoToken: {
        type: Object,
        default: null
    },
    errors: {
        type: Object,
        default: () => ({})
    }
});

const pageProps = usePage();
const { currencySymbol } = useSettings();
const { success, error } = useToast();
const isProcessing = ref(false);
const errors = ref({});

const isEditing = computed(() => !!props.icoToken);
const today = new Date().toISOString().split('T')[0];

const flash = computed(() => pageProps.props.flash);

const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    return dateString.split(' ')[0].split('T')[0];
};

const form = ref({
    name: props.icoToken?.name || '',
    symbol: props.icoToken?.symbol || '',
    description: props.icoToken?.description || '',
    price: Number(props.icoToken?.price) || '',
    current_price: Number(props.icoToken?.current_price) || '',
    total_supply: Number(props.icoToken?.total_supply) || '',
    tokens_sold: Number(props.icoToken?.tokens_sold) || 0,
    sale_start_date: formatDateForInput(props.icoToken?.sale_start_date) || '',
    sale_end_date: formatDateForInput(props.icoToken?.sale_end_date) || '',
    status: props.icoToken?.status || 'active',
    is_featured: Boolean(props.icoToken?.is_featured) || false
});

if (!form.value.current_price && form.value.price) {
    form.value.current_price = form.value.price;
}

const currentProgress = computed(() => {
    if (!isEditing.value) return 0;
    const totalSupply = parseFloat(props.icoToken.total_supply) || 0;
    const tokensSold = parseFloat(props.icoToken.tokens_sold) || 0;

    if (totalSupply <= 0) return 0;
    if (tokensSold <= 0) return 0;
    if (tokensSold > totalSupply) return 100;

    const percentage = (tokensSold / totalSupply) * 100;
    return Math.min(Math.max(Math.round(percentage), 0), 100);
});

const currentTotalRaised = computed(() => {
    if (!isEditing.value) return 0;
    const tokensSold = parseFloat(props.icoToken.tokens_sold) || 0;
    const currentPrice = parseFloat(props.icoToken.current_price || props.icoToken.price) || 0;
    return tokensSold * currentPrice;
});

const priceChangeAmount = computed(() => {
    if (!form.value.current_price || !form.value.price) return 0;
    return parseFloat(form.value.current_price) - parseFloat(form.value.price);
});

const priceChangePercentage = computed(() => {
    if (!form.value.current_price || !form.value.price || parseFloat(form.value.price) === 0) return '0.00';
    const change = ((parseFloat(form.value.current_price) - parseFloat(form.value.price)) / parseFloat(form.value.price)) * 100;
    return change.toFixed(2);
});

const priceChangeText = computed(() => {
    const amount = priceChangeAmount.value;
    const symbol = currencySymbol.value;
    if (amount > 0) return `+${symbol}${amount.toFixed(4)}`;
    if (amount < 0) return `${symbol}${amount.toFixed(4)}`;
    return `${symbol}0.0000`;
});

const priceChangeClass = computed(() => {
    const amount = priceChangeAmount.value;
    if (amount > 0) return 'text-green-600 dark:text-green-400 font-semibold';
    if (amount < 0) return 'text-red-600 dark:text-red-400 font-semibold';
    return 'text-gray-600 dark:text-gray-400';
});

const progressPercentage = computed(() => {
    const totalSupply = parseFloat(form.value.total_supply) || 0;
    const tokensSold = parseFloat(form.value.tokens_sold) || 0;

    if (totalSupply <= 0) return 0;
    if (tokensSold <= 0) return 0;
    if (tokensSold > totalSupply) return 100;

    const percentage = (tokensSold / totalSupply) * 100;
    return Math.min(Math.max(Math.round(percentage), 0), 100);
});

const totalRaised = computed(() => {
    const tokensSold = parseFloat(form.value.tokens_sold) || 0;
    const currentPrice = parseFloat(form.value.current_price || form.value.price) || 0;
    return tokensSold * currentPrice;
});

const remainingTokens = computed(() => {
    const totalSupply = parseFloat(form.value.total_supply) || 0;
    const tokensSold = parseFloat(form.value.tokens_sold) || 0;
    return Math.max(totalSupply - tokensSold, 0);
});

const potentialRaise = computed(() => {
    const currentPrice = parseFloat(form.value.current_price || form.value.price) || 0;
    return remainingTokens.value * currentPrice;
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

const getStatusClass = (status) => {
    switch (status?.toLowerCase()) {
        case 'active':
            return 'bg-green-100 text-green-800 border border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-700';
        case 'paused':
            return 'bg-yellow-100 text-yellow-800 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700';
        case 'completed':
            return 'bg-blue-100 text-blue-800 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700';
        case 'cancelled':
            return 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700';
        default:
            return 'bg-gray-100 text-gray-800 border border-gray-200 dark:bg-gray-900/30 dark:text-gray-300 dark:border-gray-700';
    }
};
const goBack = () => {
    router.get('/admin/ico-tokens');
};

const submitForm = () => {
    isProcessing.value = true;
    errors.value = {};

    const formData = {
        name: form.value.name,
        symbol: form.value.symbol,
        description: form.value.description,
        price: parseFloat(form.value.price),
        current_price: form.value.current_price ? parseFloat(form.value.current_price) : null,
        total_supply: parseInt(form.value.total_supply),
        sale_start_date: form.value.sale_start_date,
        sale_end_date: form.value.sale_end_date,
        status: form.value.status,
        is_featured: form.value.is_featured
    };

    if (isEditing.value) {
        formData.tokens_sold = parseInt(form.value.tokens_sold);
    }

    const url = isEditing.value
        ? `/admin/ico-tokens/${props.icoToken.id}`
        : '/admin/ico-tokens';

    const method = isEditing.value ? 'patch' : 'post';

    router[method](url, formData, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (formErrors) => {
            errors.value = formErrors;
            const errorMessages = Object.values(formErrors).flat();
            const errorMessage = errorMessages.length > 0
                ? errorMessages[0]
                : `Failed to ${isEditing.value ? 'update' : 'create'} token. Please check the form for errors.`;
            error(errorMessage);
        },
        onFinish: () => isProcessing.value = false
    });
};

watch(() => form.value.price, (newPrice) => {
    if (!isEditing.value && newPrice && !form.value.current_price) {
        form.value.current_price = newPrice;
    }
});

watch(() => form.value.sale_start_date, (newDate) => {
    if (newDate && form.value.sale_end_date && form.value.sale_end_date < newDate) {
        form.value.sale_end_date = newDate;
    }
});

watch(() => props.errors, (newErrors) => {
    errors.value = { ...errors.value, ...newErrors };
}, { immediate: true });

watch(flash, (newFlash) => {
    if (newFlash?.success) {
        success(newFlash.success);
    } else if (newFlash?.error) {
        error(newFlash.error);
    }
}, { immediate: true });
</script>

<template>
  <AdminLayout
    :title="isEditing ? 'Edit ICO Token' : 'Create ICO Token'"
    page-section="Token Sales & ICO"
  >
    <div class="py-8">
      <div class="max-w-4xl mx-auto">
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ isEditing ? 'Edit ICO Token' : 'Create New ICO Token' }}
              </h1>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ isEditing ? `Update ${icoToken?.name} (${icoToken?.symbol}) information and settings` : 'Set up a new ICO token for sale' }}
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
              Back to Tokens
            </button>
          </div>
        </div>

        <div
          v-if="isEditing"
          class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 mb-8 border border-blue-200 dark:border-blue-700"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">
              Current Token Status
            </h3>
            <span
              class="px-3 py-1 rounded-full text-sm font-medium"
              :class="getStatusClass(icoToken.status)"
            >
              {{ icoToken.status.toUpperCase() }}
            </span>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div>
              <span class="text-blue-600 dark:text-blue-400 font-medium">Tokens Sold:</span>
              <div class="text-blue-900 dark:text-blue-100 font-bold">
                {{ formatNumber(icoToken.tokens_sold) }}
              </div>
            </div>
            <div>
              <span class="text-blue-600 dark:text-blue-400 font-medium">Total Supply:</span>
              <div class="text-blue-900 dark:text-blue-100 font-bold">
                {{ formatNumber(icoToken.total_supply) }}
              </div>
            </div>
            <div>
              <span class="text-blue-600 dark:text-blue-400 font-medium">Progress:</span>
              <div class="text-blue-900 dark:text-blue-100 font-bold">
                {{ currentProgress }}%
              </div>
            </div>
            <div>
              <span class="text-blue-600 dark:text-blue-400 font-medium">Total Raised:</span>
              <div class="text-blue-900 dark:text-blue-100 font-bold">
                {{ currencySymbol }}{{ formatNumber(currentTotalRaised) }}
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
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label
                    for="name"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Token Name *
                  </label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    placeholder="e.g., Bitcoin Cash"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.name }"
                  >
                  <p
                    v-if="errors.name"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
                  </p>
                </div>

                <div>
                  <label
                    for="symbol"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Token Symbol *
                  </label>
                  <input
                    id="symbol"
                    v-model="form.symbol"
                    type="text"
                    required
                    maxlength="10"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm uppercase bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    placeholder="e.g., BCH"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.symbol }"
                    @input="form.symbol = $event.target.value.toUpperCase()"
                  >
                  <p
                    v-if="errors.symbol"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.symbol) ? errors.symbol[0] : errors.symbol }}
                  </p>
                </div>

                <div class="md:col-span-2">
                  <label
                    for="description"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Description
                  </label>
                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    placeholder="Brief description of the token and its purpose..."
                    :class="{ 'border-red-500 focus:ring-red-500': errors.description }"
                  />
                  <p
                    v-if="errors.description"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
                  </p>
                </div>
              </div>
            </div>

            <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Token Economics
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                  <label
                    for="price"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Initial Price *
                  </label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 dark:text-gray-400 text-sm">{{ currencySymbol }}</span>
                    </div>
                    <input
                      id="price"
                      v-model="form.price"
                      type="number"
                      step="0.0001"
                      min="0.0001"
                      required
                      class="pl-8 w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                      placeholder="0.0000"
                      :class="{ 'border-red-500 focus:ring-red-500': errors.price }"
                    >
                  </div>
                  <p
                    v-if="errors.price"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.price) ? errors.price[0] : errors.price }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Base launch price
                  </p>
                </div>

                <div>
                  <label
                    for="current_price"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Current Price
                  </label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 dark:text-gray-400 text-sm">{{ currencySymbol }}</span>
                    </div>
                    <input
                      id="current_price"
                      v-model="form.current_price"
                      type="number"
                      step="0.0001"
                      min="0.0001"
                      class="pl-8 w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                      placeholder="0.0000"
                      :class="{ 'border-red-500 focus:ring-red-500': errors.current_price }"
                    >
                  </div>
                  <p
                    v-if="errors.current_price"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.current_price) ? errors.current_price[0] : errors.current_price }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ isEditing ? 'Updated sale price' : 'Leave empty to use initial price' }}
                  </p>
                </div>

                <div>
                  <label
                    for="total_supply"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Total Supply *
                  </label>
                  <input
                    id="total_supply"
                    v-model="form.total_supply"
                    type="number"
                    min="1"
                    required
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    placeholder="1000000"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.total_supply }"
                  >
                  <p
                    v-if="errors.total_supply"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.total_supply) ? errors.total_supply[0] : errors.total_supply }}
                  </p>
                  <p
                    v-if="isEditing"
                    class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                  >
                    ⚠️ Be careful when changing total supply
                  </p>
                </div>

                <div v-if="isEditing">
                  <label
                    for="tokens_sold"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Tokens Sold
                  </label>
                  <input
                    id="tokens_sold"
                    v-model="form.tokens_sold"
                    type="number"
                    min="0"
                    :max="form.total_supply"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    placeholder="0"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.tokens_sold }"
                  >
                  <p
                    v-if="errors.tokens_sold"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.tokens_sold) ? errors.tokens_sold[0] : errors.tokens_sold }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Current: {{ formatNumber(icoToken.tokens_sold) }}
                  </p>
                </div>
              </div>

              <div
                v-if="isEditing && form.current_price && form.price"
                class="mt-4 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
              >
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">Price Change:</span>
                  <span :class="priceChangeClass">
                    {{ priceChangeText }}
                    <span class="ml-1">({{ priceChangePercentage }}%)</span>
                  </span>
                </div>
              </div>
            </div>

            <div class="border-b border-gray-200 dark:border-slate-700 pb-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Sale Period
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label
                    for="sale_start_date"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Sale Start Date *
                  </label>
                  <input
                    id="sale_start_date"
                    v-model="form.sale_start_date"
                    type="date"
                    required
                    :min="!isEditing ? today : null"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.sale_start_date }"
                  >
                  <p
                    v-if="errors.sale_start_date"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.sale_start_date) ? errors.sale_start_date[0] : errors.sale_start_date }}
                  </p>
                </div>

                <div>
                  <label
                    for="sale_end_date"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    Sale End Date *
                  </label>
                  <input
                    id="sale_end_date"
                    v-model="form.sale_end_date"
                    type="date"
                    required
                    :min="form.sale_start_date || (!isEditing ? today : null)"
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.sale_end_date }"
                  >
                  <p
                    v-if="errors.sale_end_date"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.sale_end_date) ? errors.sale_end_date[0] : errors.sale_end_date }}
                  </p>
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
                    for="status"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                  >
                    {{ isEditing ? 'Status *' : 'Initial Status *' }}
                  </label>
                  <select
                    id="status"
                    v-model="form.status"
                    required
                    class="w-full rounded-lg border border-gray-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 p-3 text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-all"
                    :class="{ 'border-red-500 focus:ring-red-500': errors.status }"
                  >
                    <option
                      v-if="!isEditing"
                      value="active"
                    >
                      Active (Ready for sale)
                    </option>
                    <option
                      v-if="!isEditing"
                      value="paused"
                    >
                      Paused (Created but not active)
                    </option>
                    <option
                      v-if="isEditing"
                      value="active"
                    >
                      Active
                    </option>
                    <option
                      v-if="isEditing"
                      value="paused"
                    >
                      Paused
                    </option>
                    <option
                      v-if="isEditing"
                      value="completed"
                    >
                      Completed
                    </option>
                    <option
                      v-if="isEditing"
                      value="cancelled"
                    >
                      Cancelled
                    </option>
                  </select>
                  <p
                    v-if="errors.status"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                  >
                    {{ Array.isArray(errors.status) ? errors.status[0] : errors.status }}
                  </p>
                  <p
                    v-if="isEditing"
                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                  >
                    Current: {{ icoToken.status }}
                  </p>
                </div>

                <div class="flex items-center justify-start h-full pt-8">
                  <label class="flex items-center">
                    <input
                      v-model="form.is_featured"
                      type="checkbox"
                      class="rounded border-gray-300 dark:border-slate-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-700"
                    >
                    <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Featured Token</span>
                  </label>
                  <p class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                    Display prominently on homepage
                  </p>
                </div>
              </div>
            </div>

            <div
              v-if="form.total_supply && (form.price || form.current_price)"
              class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6 border border-blue-200 dark:border-blue-700"
            >
              <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-3">
                {{ isEditing ? 'Updated Sale Progress Preview' : 'Token Preview' }}
              </h4>
              <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                <div class="flex justify-between">
                  <span>Token Name:</span>
                  <span class="font-medium">{{ form.name || 'Not set' }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Symbol:</span>
                  <span class="font-medium">{{ form.symbol || 'Not set' }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Total Supply:</span>
                  <span class="font-medium">{{ formatNumber(form.total_supply) }} tokens</span>
                </div>
                <div class="flex justify-between">
                  <span>{{ isEditing ? 'Initial Price:' : 'Price per Token:' }}</span>
                  <span class="font-medium">{{ currencySymbol }}{{ form.price }}</span>
                </div>
                <div
                  v-if="form.current_price && form.current_price !== form.price"
                  class="flex justify-between"
                >
                  <span>Current Price:</span>
                  <span class="font-medium">{{ currencySymbol }}{{ form.current_price }}</span>
                </div>
                <div class="flex justify-between">
                  <span>{{ isEditing ? 'Tokens Sold:' : 'Maximum Possible Raise:' }}</span>
                  <span class="font-medium font-bold">
                    {{ isEditing ? `${formatNumber(form.tokens_sold || 0)} (${progressPercentage}%)` : `${currencySymbol}${formatNumber(form.total_supply * (form.current_price || form.price))}` }}
                  </span>
                </div>
                <div
                  v-if="isEditing"
                  class="flex justify-between"
                >
                  <span>Total Raised:</span>
                  <span class="font-medium">{{ currencySymbol }}{{ formatNumber(totalRaised) }}</span>
                </div>
                <div
                  v-if="isEditing"
                  class="flex justify-between"
                >
                  <span>Remaining Tokens:</span>
                  <span class="font-medium">{{ formatNumber(remainingTokens) }}</span>
                </div>
                <div
                  v-if="isEditing"
                  class="flex justify-between"
                >
                  <span>Potential Additional Raise:</span>
                  <span class="font-medium">{{ currencySymbol }}{{ formatNumber(potentialRaise) }}</span>
                </div>
              </div>
              <div
                v-if="isEditing"
                class="mt-3"
              >
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                  <div
                    class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-300"
                    :style="{ width: progressPercentage + '%' }"
                  />
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
              <div
                v-if="isEditing"
                class="text-sm text-gray-500 dark:text-gray-400"
              >
                Last updated: {{ formatDate(icoToken.updated_at) }}
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
                  {{ isProcessing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update Token' : 'Create Token') }}
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
