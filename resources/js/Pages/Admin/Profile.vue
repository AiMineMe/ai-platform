<script setup>
import {ref, reactive, computed, onMounted, onUnmounted} from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useToast } from "@/composables/useToast.js";

const { props } = usePage();
const user = computed(() => props.user || {});
const { showToast } = useToast();
const form = useForm({
    name: user.value.name || '',
    email: user.value.email || '',
    phone: user.value.phone || '',
    bio: user.value.bio || '',
    avatar: null
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: ''
});

const processing = ref(false);
const passwordProcessing = ref(false);
const errors = ref({});
const passwordErrors = ref({});
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const passwordValidation = reactive({
    length: false,
    uppercase: false,
    lowercase: false,
    number: false,
    special: false
});

const isValidName = computed(() => {
    if (!form.name) return false;
    const nameRegex = /^[a-zA-Z\s]+$/;
    return nameRegex.test(form.name) && form.name.length >= 2 && form.name.length <= 255;
});

const isValidEmail = computed(() => {
    if (!form.email) return false;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(form.email) && form.email.length <= 255;
});

const isValidPhone = computed(() => {
    if (!form.phone) return true;
    const phoneRegex = /^[+]?[0-9\s\-\(\)]+$/;
    return phoneRegex.test(form.phone) && form.phone.length <= 20;
});

const isValidPassword = computed(() => {
    return passwordValidation.length &&
        passwordValidation.uppercase &&
        passwordValidation.lowercase &&
        passwordValidation.number &&
        passwordValidation.special;
});

const passwordsMatch = computed(() => {
    if (!passwordForm.password_confirmation) return false;
    return passwordForm.password === passwordForm.password_confirmation;
});

const isProfileFormValid = computed(() => {
    return isValidName.value && isValidEmail.value && isValidPhone.value;
});

const isPasswordFormValid = computed(() => {
    return passwordForm.current_password &&
        isValidPassword.value &&
        passwordsMatch.value;
});
const validateName = () => {
    if (!isValidName.value && form.name) {
        errors.value.name = 'Name must contain only letters and spaces and be between 2-255 characters';
    } else {
        delete errors.value.name;
    }
};
const validateEmail = () => {
    if (!isValidEmail.value && form.email) {
        errors.value.email = 'Please enter a valid email address';
    } else {
        delete errors.value.email;
    }
};
const validatePhone = () => {
    if (!isValidPhone.value && form.phone) {
        errors.value.phone = 'Please enter a valid phone number';
    } else {
        delete errors.value.phone;
    }
};

const validatePassword = () => {
    const password = passwordForm.password;
    passwordValidation.length = password.length >= 8 && password.length <= 255;
    passwordValidation.uppercase = /[A-Z]/.test(password);
    passwordValidation.lowercase = /[a-z]/.test(password);
    passwordValidation.number = /\d/.test(password);
    passwordValidation.special = /[@$!%*?&]/.test(password);
};

const validatePasswordConfirmation = () => {
    if (passwordForm.password_confirmation && !passwordsMatch.value) {
        passwordErrors.value.password_confirmation = 'Passwords do not match';
    } else {
        delete passwordErrors.value.password_confirmation;
    }
};
const updateProfile = () => {
    validateName();
    validateEmail();
    validatePhone();

    if (!isProfileFormValid.value) {
        showToast('Please fix the validation errors before submitting', 'error');
        return;
    }

    processing.value = true;
    errors.value = {};

    const formData = new FormData();
    formData.append('name', form.name);
    formData.append('email', form.email);
    formData.append('_method', 'PUT');
    form.post('/admin/profile/update', {
        data: formData,
        forceFormData: true,
        onSuccess: (page) => {
            processing.value = false;
            errors.value = {};
            showToast('Profile updated successfully!', 'success');
        },
        onError: (formErrors) => {
            processing.value = false;
            errors.value = formErrors;
            console.error('Profile update errors:', formErrors);

            const errorMessage = formErrors.message || 'Failed to update profile. Please check the form and try again.';
            showToast(errorMessage, 'error');
        }
    });
};

const updatePassword = () => {
    validatePassword();
    validatePasswordConfirmation();

    if (!isPasswordFormValid.value) {
        showToast('Please ensure all password requirements are met', 'error');
        return;
    }

    passwordProcessing.value = true;
    passwordErrors.value = {};

    passwordForm.post('/admin/profile/password', {
        onSuccess: (page) => {
            passwordProcessing.value = false;
            passwordForm.reset();
            passwordErrors.value = {};

            Object.keys(passwordValidation).forEach(key => {
                passwordValidation[key] = false;
            });

            showToast('Password updated successfully!', 'success');
        },
        onError: (formErrors) => {
            passwordProcessing.value = false;
            passwordErrors.value = formErrors;
            console.error('Password update errors:', formErrors);

            const errorMessage = formErrors.message || 'Failed to update password. Please check the form and try again.';
            showToast(errorMessage, 'error');
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
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <AdminLayout
    page-title="Profile"
    page-section="User Profile"
  >
    <div class="profile-page space-y-8">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl p-6 border border-gray-200 dark:border-slate-700">
          <div class="flex items-center mb-6">
            <svg
              class="w-6 h-6 text-blue-500 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              Profile Information
            </h2>
          </div>

          <form
            class="space-y-6"
            novalidate
            @submit.prevent="updateProfile"
          >
            <div>
              <label
                for="name"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Full Name <span class="text-red-500">*</span>
              </label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                required
                maxlength="255"
                pattern="^[a-zA-Z\s]+$"
                class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                :class="{
                  'border-red-500 focus:ring-red-500': errors.name,
                  'border-green-500': form.name && !errors.name && isValidName
                }"
                placeholder="Enter your full name"
                aria-describedby="name-error name-help"
                @blur="validateName"
              >
              <p
                v-if="errors.name"
                id="name-error"
                class="text-red-500 text-sm mt-1"
                role="alert"
              >
                {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
              </p>
              <p
                v-else
                id="name-help"
                class="text-gray-500 text-sm mt-1"
              >
                Only letters and spaces allowed
              </p>
            </div>

            <div>
              <label
                for="email"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Email Address <span class="text-red-500">*</span>
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                maxlength="255"
                class="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                :class="{
                  'border-red-500 focus:ring-red-500': errors.email,
                  'border-green-500': form.email && !errors.email && isValidEmail
                }"
                placeholder="Enter your email address"
                aria-describedby="email-error email-help"
                @blur="validateEmail"
              >
              <p
                v-if="errors.email"
                id="email-error"
                class="text-red-500 text-sm mt-1"
                role="alert"
              >
                {{ Array.isArray(errors.email) ? errors.email[0] : errors.email }}
              </p>
              <p
                v-else
                id="email-help"
                class="text-gray-500 text-sm mt-1"
              >
                We'll never share your email with anyone else
              </p>
            </div>

            <button
              type="submit"
              :disabled="processing || !isProfileFormValid"
              class="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              :aria-label="processing ? 'Updating profile...' : 'Update profile information'"
            >
              <svg
                v-if="processing"
                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
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
              {{ processing ? 'Updating...' : 'Update Profile' }}
            </button>
          </form>
        </div>

        <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl p-6 border border-gray-200 dark:border-slate-700">
          <div class="flex items-center mb-6">
            <svg
              class="w-6 h-6 text-red-500 mr-3"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
              />
            </svg>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
              Change Password
            </h2>
          </div>

          <form
            class="space-y-6"
            novalidate
            @submit.prevent="updatePassword"
          >
            <div>
              <label
                for="current_password"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Current Password <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input
                  id="current_password"
                  v-model="passwordForm.current_password"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  required
                  class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                  :class="{ 'border-red-500 focus:ring-red-500': passwordErrors.current_password }"
                  placeholder="Enter current password"
                  aria-describedby="current-password-error"
                >
                <button
                  type="button"
                  class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                  :aria-label="showCurrentPassword ? 'Hide current password' : 'Show current password'"
                  @click="showCurrentPassword = !showCurrentPassword"
                >
                  <svg
                    v-if="showCurrentPassword"
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                    />
                  </svg>
                  <svg
                    v-else
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                  </svg>
                </button>
              </div>
              <p
                v-if="passwordErrors.current_password"
                id="current-password-error"
                class="text-red-500 text-sm mt-1"
                role="alert"
              >
                {{ Array.isArray(passwordErrors.current_password) ? passwordErrors.current_password[0] : passwordErrors.current_password }}
              </p>
            </div>

            <div>
              <label
                for="password"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                New Password <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input
                  id="password"
                  v-model="passwordForm.password"
                  :type="showNewPassword ? 'text' : 'password'"
                  required
                  minlength="8"
                  maxlength="255"
                  class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                  :class="{
                    'border-red-500 focus:ring-red-500': passwordErrors.password || !isValidPassword,
                    'border-green-500': passwordForm.password && isValidPassword
                  }"
                  placeholder="Enter new password"
                  aria-describedby="password-error password-requirements"
                  @input="validatePassword"
                >
                <button
                  type="button"
                  class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                  :aria-label="showNewPassword ? 'Hide new password' : 'Show new password'"
                  @click="showNewPassword = !showNewPassword"
                >
                  <svg
                    v-if="showNewPassword"
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                    />
                  </svg>
                  <svg
                    v-else
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                  </svg>
                </button>
              </div>

              <div
                id="password-requirements"
                class="mt-2 space-y-1"
              >
                <p
                  v-if="passwordErrors.password"
                  class="text-red-500 text-sm"
                  role="alert"
                >
                  {{ Array.isArray(passwordErrors.password) ? passwordErrors.password[0] : passwordErrors.password }}
                </p>
                <div class="text-sm space-y-1">
                  <div class="flex items-center space-x-2">
                    <svg
                      :class="passwordValidation.length ? 'text-green-500' : 'text-gray-400'"
                      class="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      aria-hidden="true"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <span :class="passwordValidation.length ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                      At least 8 characters
                    </span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <svg
                      :class="passwordValidation.uppercase ? 'text-green-500' : 'text-gray-400'"
                      class="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      aria-hidden="true"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <span :class="passwordValidation.uppercase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                      One uppercase letter
                    </span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <svg
                      :class="passwordValidation.lowercase ? 'text-green-500' : 'text-gray-400'"
                      class="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      aria-hidden="true"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <span :class="passwordValidation.lowercase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                      One lowercase letter
                    </span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <svg
                      :class="passwordValidation.number ? 'text-green-500' : 'text-gray-400'"
                      class="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      aria-hidden="true"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <span :class="passwordValidation.number ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                      One number
                    </span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <svg
                      :class="passwordValidation.special ? 'text-green-500' : 'text-gray-400'"
                      class="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                      aria-hidden="true"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <span :class="passwordValidation.special ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                      One special character (@$!%*?&)
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <label
                for="password_confirmation"
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Confirm New Password <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <input
                  id="password_confirmation"
                  v-model="passwordForm.password_confirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  required
                  class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                  :class="{
                    'border-red-500 focus:ring-red-500': passwordErrors.password_confirmation || !passwordsMatch,
                    'border-green-500': passwordForm.password_confirmation && passwordsMatch
                  }"
                  placeholder="Confirm new password"
                  aria-describedby="password-confirmation-error"
                  @input="validatePasswordConfirmation"
                >
                <button
                  type="button"
                  class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                  :aria-label="showConfirmPassword ? 'Hide password confirmation' : 'Show password confirmation'"
                  @click="showConfirmPassword = !showConfirmPassword"
                >
                  <svg
                    v-if="showConfirmPassword"
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                    />
                  </svg>
                  <svg
                    v-else
                    class="w-5 h-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                    />
                  </svg>
                </button>
              </div>
              <p
                v-if="passwordErrors.password_confirmation"
                id="password-confirmation-error"
                class="text-red-500 text-sm mt-1"
                role="alert"
              >
                {{ Array.isArray(passwordErrors.password_confirmation) ? passwordErrors.password_confirmation[0] : passwordErrors.password_confirmation }}
              </p>
              <p
                v-else-if="passwordForm.password_confirmation && !passwordsMatch"
                class="text-red-500 text-sm mt-1"
                role="alert"
              >
                Passwords do not match
              </p>
              <p
                v-else-if="passwordForm.password_confirmation && passwordsMatch"
                class="text-green-600 dark:text-green-400 text-sm mt-1"
              >
                Passwords match
              </p>
            </div>

            <button
              type="submit"
              :disabled="passwordProcessing || !isPasswordFormValid"
              class="w-full bg-red-500 hover:bg-red-600 disabled:bg-red-300 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
              :aria-label="passwordProcessing ? 'Updating password...' : 'Update password'"
            >
              <svg
                v-if="passwordProcessing"
                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
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
              {{ passwordProcessing ? 'Updating...' : 'Update Password' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

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

.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

.transition-colors {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

input:focus,
textarea:focus,
button:focus {
    outline: none;
}
@media (max-width: 640px) {
    .profile-page {
        padding: 1rem;
    }

    .grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .p-6 {
        padding: 1rem;
    }
}

@media print {
    .no-print,
    button,
    input[type="file"] {
        display: none !important;
    }

    .bg-white {
        background-color: white !important;
        border: 1px solid #000 !important;
    }

    .text-gray-900 {
        color: #000 !important;
    }
}

@media (prefers-contrast: more) {
    .border-gray-300 {
        border-color: #000;
    }

    .text-gray-500 {
        color: #333;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }

    .animate-spin {
        animation: none;
    }
}

.dark input:focus,
.dark textarea:focus {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}

.dark .error-border {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}

.dark .success-border {
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
</style>
