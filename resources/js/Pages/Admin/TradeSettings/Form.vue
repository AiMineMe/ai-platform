<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useToast } from "@/composables/useToast.js";
import {useSettings} from "@/composables/useSettings.js";

const props = defineProps({
    setting: {
        type: Object,
        default: null
    },
    currencies: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        required: true
    },
    errors: {
        type: Object,
        default: () => ({})
    }
});

const { showToast } = useToast();
const isFormSubmitting = ref(false);
const isEditMode = computed(() => !!props.setting && !!props.setting.id);
const defaultTradingHours = {
    monday: { enabled: true, start: '09:00', end: '17:00' },
    tuesday: { enabled: true, start: '09:00', end: '17:00' },
    wednesday: { enabled: true, start: '09:00', end: '17:00' },
    thursday: { enabled: true, start: '09:00', end: '17:00' },
    friday: { enabled: true, start: '09:00', end: '17:00' },
    saturday: { enabled: false, start: '09:00', end: '17:00' },
    sunday: { enabled: false, start: '09:00', end: '17:00' }
};

const predefinedDurations = [
    { label: '30 sec', value: 30 },
    { label: '1 min', value: 60 },
    { label: '2 min', value: 120 },
    { label: '5 min', value: 300 },
    { label: '10 min', value: 600 },
    { label: '15 min', value: 900 },
    { label: '30 min', value: 1800 },
    { label: '1 hour', value: 3600 },
    { label: '2 hours', value: 7200 },
    { label: '4 hours', value: 14400 }
];

const form = ref({
    symbol: isEditMode.value ? props.setting.symbol : '',
    is_active: isEditMode.value ? props.setting.is_active : true,
    min_amount: isEditMode.value ? props.setting.min_amount : 1,
    max_amount: isEditMode.value ? props.setting.max_amount : 10000,
    payout_rate: isEditMode.value ? props.setting.payout_rate : 85,
    durations: isEditMode.value ? [...(props.setting.durations || [])] : [60, 300, 900],
    trading_hours: isEditMode.value
        ? (props.setting.trading_hours || defaultTradingHours)
        : defaultTradingHours
});

const availableCurrencies = computed(() => {
    const grouped = {
        crypto: [],
        forex: [],
        stock: [],
        commodity: []
    };

    props.currencies.forEach(currency => {
        if (currency.type && grouped[currency.type]) {
            grouped[currency.type].push(currency);
        }
    });

    return grouped;
});

const isFormValid = computed(() => {
    return !!(
        form.value.symbol &&
        form.value.min_amount > 0 &&
        form.value.max_amount > form.value.min_amount &&
        form.value.payout_rate > 0 &&
        form.value.durations.length > 0
    );
});

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US').format(num);
};

const formatMoney = (value) => {
    const num = parseFloat(value) || 0;
    if (num >= 1000000000) {
        return (num / 1000000000).toFixed(2) + 'B';
    } else if (num >= 1000000) {
        return (num / 1000000).toFixed(2) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(2) + 'K';
    } else {
        return num.toFixed(2);
    }
};

const formatPercentage = (percentage) => {
    const num = parseFloat(percentage) || 0;
    return num.toFixed(1);
};

const goBack = () => {
    router.get('/admin/trade-settings');
};

const setAllTradingDays = (enabled) => {
    Object.keys(form.value.trading_hours).forEach(day => {
        form.value.trading_hours[day].enabled = enabled;
    });
    showToast(`All days ${enabled ? 'enabled' : 'disabled'}`, 'success');
};

const setWeekdaysOnly = () => {
    const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    const weekends = ['saturday', 'sunday'];

    weekdays.forEach(day => {
        if (form.value.trading_hours[day]) {
            form.value.trading_hours[day].enabled = true;
        }
    });

    weekends.forEach(day => {
        if (form.value.trading_hours[day]) {
            form.value.trading_hours[day].enabled = false;
        }
    });

    showToast('Weekdays only enabled', 'success');
};

const set24Hours = () => {
    Object.keys(form.value.trading_hours).forEach(day => {
        form.value.trading_hours[day].enabled = true;
        form.value.trading_hours[day].start = '00:00';
        form.value.trading_hours[day].end = '23:59';
    });
    showToast('24/7 trading enabled', 'success');
};

const submitForm = () => {
    if (isFormSubmitting.value || !isFormValid.value) return;

    isFormSubmitting.value = true;

    const formData = {
        symbol: form.value.symbol,
        is_active: form.value.is_active,
        min_amount: parseFloat(form.value.min_amount),
        max_amount: parseFloat(form.value.max_amount),
        payout_rate: parseFloat(form.value.payout_rate),
        durations: form.value.durations,
        trading_hours: form.value.trading_hours
    };

    const url = isEditMode.value
        ? `/admin/trade-settings/${props.setting.id}`
        : '/admin/trade-settings';

    const method = isEditMode.value ? 'put' : 'post';

    router[method](url, formData, {
        onSuccess: () => {
            const message = isEditMode.value
                ? 'Trade setting updated successfully'
                : 'Trade setting created successfully';
            showToast(message, 'success');
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors).flat().join(', ') || 'Submission failed';
            showToast(errorMessage, 'error');
        },
        onFinish: () => {
            isFormSubmitting.value = false;
        }
    });
};

onMounted(() => {
    const page = usePage();
    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
    if (flash?.warning) showToast(flash.warning, 'warning');
    if (flash?.info) showToast(flash.info, 'info');
});


const { currencySymbol } = useSettings();
</script>

<template>
  <AdminLayout
    :title="isEditMode ? 'Edit Trade Setting' : 'Add Trade Setting'"
    page-section="Trading & Markets"
  >
    <div class="max-w-4xl mx-auto">
      <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ isEditMode ? 'Edit Trade Setting' : 'Add Trade Setting' }}
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ isEditMode ? `Update settings for ${form.symbol}` : 'Configure a new symbol for trade trading' }}
            </p>
          </div>
          <button
            class="mt-4 sm:mt-0 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
            @click="goBack"
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
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
            Back to Trade Settings
          </button>
        </div>
      </div>

      <div
        v-if="isEditMode && setting"
        class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6"
      >
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
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Trades
              </p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ stats.total_trades }}
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
                  d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Active Trades
              </p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ stats.active_trades }}
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
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total Volume
              </p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ currencySymbol }}{{ formatMoney(stats.total_volume || 0) }}
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
          <div class="flex items-center">
            <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
              <svg
                class="h-6 w-6 text-orange-600 dark:text-orange-400"
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
                Payout Rate
              </p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ formatPercentage(setting.payout_rate || 0) }}%
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
        <form
          class="p-6 space-y-6"
          @submit.prevent="submitForm"
        >
          <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
              Symbol Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div
                v-if="!isEditMode"
                class="md:col-span-2"
              >
                <label
                  for="symbol-select"
                  class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                >Trading Symbol *</label>
                <select
                  id="symbol-select"
                  v-model="form.symbol"
                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  :class="{ 'border-red-300 dark:border-red-600': errors.symbol }"
                >
                  <option value="">
                    Select Currency
                  </option>
                  <optgroup
                    v-if="availableCurrencies.crypto.length > 0"
                    label="Cryptocurrencies"
                  >
                    <option
                      v-for="currency in availableCurrencies.crypto"
                      :key="currency.symbol"
                      :value="currency.symbol"
                    >
                      {{ currency.symbol }} - {{ currency.name }}
                    </option>
                  </optgroup>
                </select>
                <div
                  v-if="errors.symbol"
                  class="text-red-600 dark:text-red-400 text-sm mt-1"
                >
                  {{ errors.symbol }}
                </div>
              </div>

              <div v-if="isEditMode">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trading Symbol</label>
                <input
                  :value="form.symbol"
                  type="text"
                  readonly
                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-gray-50 dark:bg-slate-600 text-gray-700 dark:text-gray-300 font-mono cursor-not-allowed"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Symbol cannot be changed after creation
                </p>
              </div>

              <div v-if="isEditMode">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <div class="flex items-center space-x-4">
                  <button
                    type="button"
                    :class="[
                      'relative inline-flex h-8 w-14 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                      form.is_active ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600'
                    ]"
                    @click="form.is_active = !form.is_active"
                  >
                    <span
                      :class="[
                        'inline-block h-6 w-6 transform rounded-full bg-white transition-transform shadow-lg',
                        form.is_active ? 'translate-x-7' : 'translate-x-1'
                      ]"
                    />
                  </button>
                  <div>
                    <div
                      class="text-sm font-medium"
                      :class="form.is_active ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                    >
                      {{ form.is_active ? 'Active' : 'Inactive' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
              Trading Configuration
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label
                  for="min-amount"
                  class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >Minimum Amount *</label>
                <input
                  id="min-amount"
                  v-model.number="form.min_amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  :class="{ 'border-red-300 dark:border-red-600': errors.min_amount }"
                  placeholder="1.00"
                >
                <div
                  v-if="errors.min_amount"
                  class="text-red-600 dark:text-red-400 text-sm mt-1"
                >
                  {{ errors.min_amount }}
                </div>
              </div>

              <!-- Max Amount -->
              <div>
                <label
                  for="max-amount"
                  class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >Maximum Amount *</label>
                <input
                  id="max-amount"
                  v-model.number="form.max_amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  :class="{ 'border-red-300 dark:border-red-600': errors.max_amount }"
                  placeholder="10000.00"
                >
                <div
                  v-if="errors.max_amount"
                  class="text-red-600 dark:text-red-400 text-sm mt-1"
                >
                  {{ errors.max_amount }}
                </div>
              </div>

              <!-- Payout Rate -->
              <div>
                <label
                  for="payout-rate"
                  class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >Payout Rate (%) *</label>
                <input
                  id="payout-rate"
                  v-model.number="form.payout_rate"
                  type="number"
                  step="0.01"
                  min="1"
                  max="1000"
                  required
                  class="w-full rounded-lg border border-gray-300 dark:border-slate-600 p-3 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  :class="{ 'border-red-300 dark:border-red-600': errors.payout_rate }"
                  placeholder="85.00"
                >
                <div
                  v-if="errors.payout_rate"
                  class="text-red-600 dark:text-red-400 text-sm mt-1"
                >
                  {{ errors.payout_rate }}
                </div>
              </div>

              <div v-if="!isEditMode">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                <div class="flex items-center space-x-4">
                  <button
                    type="button"
                    :class="[
                      'relative inline-flex h-8 w-14 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                      form.is_active ? 'bg-green-600' : 'bg-gray-300 dark:bg-gray-600'
                    ]"
                    @click="form.is_active = !form.is_active"
                  >
                    <span
                      :class="[
                        'inline-block h-6 w-6 transform rounded-full bg-white transition-transform shadow-lg',
                        form.is_active ? 'translate-x-7' : 'translate-x-1'
                      ]"
                    />
                  </button>
                  <div>
                    <div
                      class="text-sm font-medium"
                      :class="form.is_active ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                    >
                      {{ form.is_active ? 'Active' : 'Inactive' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="form.min_amount && form.max_amount && form.payout_rate">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
              Financial Preview
            </h2>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-700">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                  <span class="text-gray-600 dark:text-gray-400">Investment Range:</span>
                  <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{currencySymbol}}{{ formatNumber(form.min_amount) }} - {{currencySymbol}}{{ formatNumber(form.max_amount) }}
                  </div>
                </div>
                <div>
                  <span class="text-gray-600 dark:text-gray-400">Example ({{currencySymbol}}100 investment):</span>
                  <div class="font-medium text-green-600 dark:text-green-400">
                      {{currencySymbol}}{{ formatNumber((100 * form.payout_rate) / 100) }} profit potential
                  </div>
                </div>
                <div>
                  <span class="text-gray-600 dark:text-gray-400">Payout Rate:</span>
                  <div class="font-medium text-blue-600 dark:text-blue-400">
                    {{ formatPercentage(form.payout_rate) }}%
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
              Duration Options
            </h2>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
              <div
                v-for="(duration, index) in predefinedDurations"
                :key="index"
                class="relative"
              >
                <input
                  :id="`duration_${index}`"
                  v-model="form.durations"
                  :value="duration.value"
                  type="checkbox"
                  class="peer sr-only"
                >
                <label
                  :for="`duration_${index}`"
                  class="flex items-center justify-center p-3 bg-white dark:bg-slate-700 rounded-lg border-2 border-gray-200 dark:border-slate-600 cursor-pointer transition-all hover:border-blue-300 dark:hover:border-blue-500 peer-checked:border-blue-500 dark:peer-checked:border-blue-400 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/30 peer-checked:text-blue-700 dark:peer-checked:text-blue-300"
                >
                  <span class="text-sm font-medium">{{ duration.label }}</span>
                </label>
              </div>
            </div>

            <div
              v-if="errors.durations"
              class="text-red-600 dark:text-red-400 text-sm mt-2"
            >
              {{ errors.durations }}
            </div>
          </div>

          <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
              Trading Schedule
            </h2>

            <div class="mb-4 flex flex-wrap gap-2">
              <button
                type="button"
                class="px-3 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded transition-colors"
                @click="setAllTradingDays(true)"
              >
                Enable All Days
              </button>
              <button
                type="button"
                class="px-3 py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors"
                @click="setAllTradingDays(false)"
              >
                Disable All Days
              </button>
              <button
                type="button"
                class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors"
                @click="setWeekdaysOnly"
              >
                Weekdays Only
              </button>
              <button
                type="button"
                class="px-3 py-1 text-xs bg-purple-600 hover:bg-purple-700 text-white rounded transition-colors"
                @click="set24Hours"
              >
                24/7 Trading
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div
                v-for="(day, dayName) in form.trading_hours"
                :key="dayName"
                class="flex items-center space-x-4 p-4 bg-white dark:bg-slate-700 rounded-lg border border-gray-200 dark:border-slate-600"
              >
                <div class="w-20">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ dayName }}</span>
                </div>
                <div class="flex items-center space-x-3">
                  <input
                    :id="`trading_${dayName}`"
                    v-model="day.enabled"
                    type="checkbox"
                    class="rounded border-gray-300 dark:border-slate-600 text-blue-600"
                  >
                  <label
                    :for="`trading_${dayName}`"
                    class="text-sm text-gray-600 dark:text-gray-400 cursor-pointer"
                  >Active</label>
                </div>
                <div
                  v-if="day.enabled"
                  class="flex items-center space-x-2 flex-1"
                >
                  <input
                    v-model="day.start"
                    type="time"
                    class="rounded border border-gray-300 dark:border-slate-600 p-1 text-xs bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                  >
                  <span class="text-gray-400 dark:text-gray-500 text-xs">to</span>
                  <input
                    v-model="day.end"
                    type="time"
                    class="rounded border border-gray-300 dark:border-slate-600 p-1 text-xs bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                  >
                </div>
                <div
                  v-else
                  class="text-xs text-gray-400 dark:text-gray-500 italic flex-1"
                >
                  Closed
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-between pt-6 border-t border-gray-200 dark:border-slate-700">
            <button
              type="button"
              class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors flex items-center"
              @click="goBack"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isFormSubmitting || !isFormValid"
              class="px-6 py-3 bg-lime-500 hover:bg-lime-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-medium transition-colors flex items-center"
            >
              <svg
                v-if="isFormSubmitting"
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
              {{ isFormSubmitting ? (isEditMode ? 'Updating...' : 'Creating...') : (isEditMode ? 'Update Setting' : 'Create Setting') }}
            </button>
          </div>
        </form>
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
    .grid-cols-2.md\:grid-cols-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid-cols-2.md\:grid-cols-5 {
        grid-template-columns: 1fr;
    }
}
</style>
