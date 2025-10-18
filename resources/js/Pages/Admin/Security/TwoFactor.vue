<script setup>
import { ref, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue"
import { useToast } from "@/composables/useToast.js"

const props = defineProps({
    is_enabled: {
        type: Boolean,
        default: false
    },
    qr_code: {
        type: String,
        default: null
    },
    secret: {
        type: String,
        default: null
    },
    recovery_codes: {
        type: Array,
        default: () => []
    }
})

const pageProps = usePage()
const { success, error, info } = useToast()

const isProcessing = ref(false)
const enableForm = ref({
    code: '',
    secret: props.secret || ''
})

const verifyForm = ref({
    code: ''
})

const disableForm = ref({
    password: ''
})

const flash = ref(pageProps.props.flash)
const enableTwoFactor = () => {
    if (!enableForm.value.code || enableForm.value.code.length !== 6) return

    isProcessing.value = true

    router.post('/admin/security/2fa/enable', enableForm.value, {
        onSuccess: () => {
            enableForm.value.code = ''
            success('Two-factor authentication enabled successfully!')
        },
        onError: () => {
            error('Failed to enable 2FA. Please check your code.')
        },
        onFinish: () => {
            isProcessing.value = false
        },
        preserveScroll: true
    })
}

const verifyCode = () => {
    if (!verifyForm.value.code || verifyForm.value.code.length !== 6) return

    isProcessing.value = true

    router.post('/admin/security/2fa/verify', verifyForm.value, {
        onSuccess: () => {
            verifyForm.value.code = ''
            success('Code verified successfully!')
        },
        onError: () => {
            error('Invalid verification code.')
        },
        onFinish: () => {
            isProcessing.value = false
        },
        preserveScroll: true
    })
}

const disableTwoFactor = () => {
    if (!disableForm.value.password) return

    isProcessing.value = true

    router.post('/admin/security/2fa/disable', disableForm.value, {
        onSuccess: () => {
            disableForm.value.password = ''
            success('Two-factor authentication disabled.')
        },
        onError: () => {
            error('Failed to disable 2FA. Check your password.')
        },
        onFinish: () => {
            isProcessing.value = false
        },
        preserveScroll: true
    })
}

const downloadRecoveryCodes = () => {
    try {
        const codes = props.recovery_codes.join('\n')
        const blob = new Blob([codes], { type: 'text/plain' })
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = 'admin-recovery-codes.txt'
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        window.URL.revokeObjectURL(url)

        info('Recovery codes downloaded')
    } catch (err) {
        console.error('Error downloading recovery codes:', err)
        error('Failed to download recovery codes')
    }
}

onMounted(() => {
    if (flash.value?.success) {
        success(flash.value.success)
    } else if (flash.value?.error) {
        error(flash.value.error)
    } else if (flash.value?.info) {
        info(flash.value.info)
    }
})
</script>

<template>
  <AdminLayout
    title="Two-Factor Authentication"
    page-section="Security"
  >
    <div class="max-w-6xl mx-auto space-y-6 px-4">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
              Two-Factor Authentication
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
              Secure your admin account with 2FA
            </p>
          </div>
          <div class="flex items-center space-x-2">
            <div
              class="h-3 w-3 rounded-full"
              :class="is_enabled ? 'bg-green-500' : 'bg-red-500'"
            />
            <span
              class="text-sm font-medium"
              :class="is_enabled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
            >
              {{ is_enabled ? 'Enabled' : 'Disabled' }}
            </span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
          <div class="flex items-center space-x-3 mb-6">
            <div class="h-12 w-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
              <svg
                class="w-6 h-6 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ is_enabled ? 'Manage 2FA' : 'Setup 2FA' }}
              </h3>
              <p class="text-gray-600 dark:text-gray-400 text-sm">
                {{ is_enabled ? 'Your admin account is protected' : 'Secure your admin account' }}
              </p>
            </div>
          </div>

          <div v-if="!is_enabled">
            <div class="space-y-4">
              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h4 class="text-blue-800 dark:text-blue-300 font-medium mb-2">
                  Setup Instructions:
                </h4>
                <ol class="text-sm text-blue-700 dark:text-blue-400 space-y-1 list-decimal list-inside">
                  <li>Install Google Authenticator or Authy on your phone</li>
                  <li>Scan the QR code below</li>
                  <li>Enter the 6-digit code from your app</li>
                  <li>Save your recovery codes safely</li>
                </ol>
              </div>

              <div
                v-if="qr_code"
                class="text-center"
              >
                <div class="bg-white dark:bg-slate-900 p-4 rounded-lg inline-block mb-4 border-2 border-gray-200 dark:border-slate-600">
                  <img
                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qr_code)}`"
                    alt="QR Code"
                    class="w-48 h-48"
                  >
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Scan this QR code with your authenticator app
                </p>
              </div>

              <div
                v-if="secret"
                class="bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg p-4"
              >
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                  Or enter this code manually:
                </p>
                <code class="text-green-600 dark:text-green-400 font-mono text-sm break-all bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded">{{ secret }}</code>
              </div>

              <form
                class="space-y-4"
                @submit.prevent="enableTwoFactor"
              >
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Enter verification code from your app
                  </label>
                  <input
                    v-model="enableForm.code"
                    type="text"
                    maxlength="6"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 text-center text-2xl tracking-wider font-mono"
                    placeholder="000000"
                    required
                  >
                </div>

                <input
                  v-model="enableForm.secret"
                  type="hidden"
                  :value="secret"
                >

                <button
                  type="submit"
                  :disabled="enableForm.code.length !== 6 || isProcessing"
                  class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                >
                  <span v-if="isProcessing">Enabling...</span>
                  <span v-else>Enable Two-Factor Authentication</span>
                </button>
              </form>
            </div>
          </div>

          <div v-else>
            <div class="space-y-4">
              <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex items-center space-x-2 mb-2">
                  <svg
                    class="w-5 h-5 text-green-600 dark:text-green-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  <span class="text-green-800 dark:text-green-300 font-medium">Two-Factor Authentication is Active</span>
                </div>
                <p class="text-sm text-green-700 dark:text-green-400">
                  Your admin account is protected with 2FA. You'll need your authenticator app to sign in.
                </p>
              </div>

              <div class="border border-gray-200 dark:border-slate-600 rounded-lg p-4 bg-gray-50 dark:bg-slate-700">
                <h4 class="text-gray-900 dark:text-gray-100 font-medium mb-3">
                  Test Your 2FA
                </h4>
                <form
                  class="space-y-3"
                  @submit.prevent="verifyCode"
                >
                  <div>
                    <input
                      v-model="verifyForm.code"
                      type="text"
                      maxlength="6"
                      class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 text-center text-xl tracking-wider font-mono"
                      placeholder="Enter 6-digit code"
                    >
                  </div>
                  <button
                    type="submit"
                    :disabled="verifyForm.code.length !== 6 || isProcessing"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                  >
                    Verify Code
                  </button>
                </form>
              </div>

              <div class="border border-red-200 dark:border-red-800 rounded-lg p-4 bg-red-50 dark:bg-red-900/20">
                <h4 class="text-red-800 dark:text-red-300 font-medium mb-3">
                  Disable Two-Factor Authentication
                </h4>
                <p class="text-sm text-red-700 dark:text-red-400 mb-3">
                  This will remove the extra security layer from your admin account.
                </p>

                <form
                  class="space-y-3"
                  @submit.prevent="disableTwoFactor"
                >
                  <div>
                    <input
                      v-model="disableForm.password"
                      type="password"
                      class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                      placeholder="Enter your password to confirm"
                      required
                    >
                  </div>
                  <button
                    type="submit"
                    :disabled="!disableForm.password || isProcessing"
                    class="w-full bg-red-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 shadow-sm"
                  >
                    <span v-if="isProcessing">Disabling...</span>
                    <span v-else>Disable 2FA</span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
          <div class="flex items-center space-x-3 mb-6">
            <div class="h-12 w-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center">
              <svg
                class="w-6 h-6 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1721 9z"
                />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Recovery Codes
              </h3>
              <p class="text-gray-600 dark:text-gray-400 text-sm">
                Use these if you lose access to your device
              </p>
            </div>
          </div>

          <div v-if="is_enabled && recovery_codes">
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
              <div class="flex items-start space-x-2">
                <svg
                  class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0"
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
                <div>
                  <p class="text-yellow-800 dark:text-yellow-300 font-medium text-sm">
                    Important!
                  </p>
                  <p class="text-yellow-700 dark:text-yellow-400 text-xs mt-1">
                    Save these codes safely. Each code can only be used once.
                  </p>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-4">
              <div
                v-for="(code, index) in recovery_codes"
                :key="index"
                class="bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded p-3 text-center"
              >
                <code class="text-green-600 dark:text-green-400 font-mono text-sm">{{ code }}</code>
              </div>
            </div>

            <button
              class="w-full bg-gray-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-gray-700 transition-colors duration-200 shadow-sm"
              @click="downloadRecoveryCodes"
            >
              Download Recovery Codes
            </button>
          </div>

          <div
            v-else
            class="text-center py-8 text-gray-500 dark:text-gray-400"
          >
            <svg
              class="w-12 h-12 mx-auto mb-4 text-gray-400 dark:text-gray-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1721 9z"
              />
            </svg>
            <p>Enable 2FA to see recovery codes</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
button:focus,
input:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

button:disabled:hover {
    transform: none;
    box-shadow: none;
}

.transition-colors {
    transition-property: background-color, border-color, color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}
</style>
