<template>
    <AdminLayout title="System Update" page-section="System Management">
        <div class="p-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-semibold text-gray-900">System Update</h1>
                <p class="text-gray-600 mt-1">Manage system migrations and version updates</p>
            </div>

            <!-- Main Update Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Header Section -->
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">System Version</h3>
                            <p class="text-sm text-gray-600">Current status and available updates</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div :class="[
                                'w-3 h-3 rounded-full',
                                needsUpdate ? 'bg-amber-400' : 'bg-green-500'
                            ]"></div>
                            <span class="text-sm font-medium" :class="needsUpdate ? 'text-amber-600' : 'text-green-600'">
                                {{ needsUpdate ? 'Update Available' : 'Up to Date' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Content Section -->
                <div class="p-6">
                    <!-- Version Display -->
                    <div class="grid grid-cols-2 gap-6 mb-6">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Current Version</label>
                            <div class="text-xl font-mono font-semibold text-gray-900 bg-gray-50 px-4 py-3 rounded-lg border">
                                {{ currentVersion || 'Unknown' }}
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Latest Version</label>
                            <div class="text-xl font-mono font-semibold bg-blue-50 px-4 py-3 rounded-lg border border-blue-200" :class="needsUpdate ? 'text-blue-700' : 'text-gray-900'">
                                {{ migrateVersion || 'No updates' }}
                            </div>
                        </div>
                    </div>

                    <!-- Update Path (when update available) -->
                    <div v-if="needsUpdate" class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800">System update required</p>
                                    <p class="text-xs text-amber-700">{{ currentVersion }} → {{ migrateVersion }}</p>
                                </div>
                            </div>
                            <button
                                @click="showUpdateModal = true"
                                :disabled="isUpdating"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center space-x-2',
                                    isUpdating
                                        ? 'bg-gray-400 cursor-not-allowed text-white'
                                        : 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm hover:shadow'
                                ]"
                            >
                                <svg
                                    v-if="isUpdating"
                                    class="animate-spin w-4 h-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isUpdating ? 'Updating...' : 'Update Now' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Up to Date Message -->
                    <div v-else class="p-4 bg-green-50 border border-green-200 rounded-lg text-center">
                        <svg class="mx-auto w-8 h-8 text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm font-medium text-green-800">System is up to date</p>
                        <p class="text-xs text-green-600 mt-1">No migrations required</p>
                    </div>

                    <!-- Warning Notice (only when update available) -->
                    <div v-if="needsUpdate" class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-xs text-yellow-800">
                            <span class="font-medium">Important:</span> Ensure you have a database backup before proceeding with the update.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Update Modal -->
        <div v-if="showUpdateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-6 pb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Confirm System Update</h3>
                    <button @click="showUpdateModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="px-6 pb-6 space-y-4">
                    <!-- Update Info -->
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-600">Version Update:</span>
                            <span class="font-mono font-medium">{{ currentVersion }} → {{ migrateVersion }}</span>
                        </div>
                    </div>

                    <!-- Warning -->
                    <div class="bg-red-50 border border-red-200 p-3 rounded-lg">
                        <div class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-red-800">Prerequisites</p>
                                <ul class="text-xs text-red-700 mt-1 space-y-0.5">
                                    <li>• Database backup completed</li>
                                    <li>• Process may take several minutes</li>
                                    <li>• System will be temporarily unavailable</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation -->
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input
                            v-model="confirmBackup"
                            type="checkbox"
                            class="mt-1 w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <span class="text-sm text-gray-700">
                            I confirm that I have completed a database backup and understand the risks involved.
                        </span>
                    </label>

                    <!-- Actions -->
                    <div class="flex space-x-3 pt-2">
                        <button
                            @click="showUpdateModal = false"
                            :disabled="isUpdating"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            @click="performSystemUpdate"
                            :disabled="!confirmBackup || isUpdating"
                            :class="[
                                'flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center justify-center space-x-2',
                                (!confirmBackup || isUpdating)
                                    ? 'bg-gray-400 cursor-not-allowed text-white'
                                    : 'bg-red-600 hover:bg-red-700 text-white'
                            ]"
                        >
                            <svg
                                v-if="isUpdating"
                                class="animate-spin w-4 h-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isUpdating ? 'Updating...' : 'Update System' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue"

const props = defineProps({
    currentVersion: String,
    migrateVersion: String,
})

const isUpdating = ref(false)
const showUpdateModal = ref(false)
const confirmBackup = ref(false)

// Check if system needs update by comparing versions
const needsUpdate = computed(() => {
    if (!props.currentVersion || !props.migrateVersion) return false
    return props.currentVersion !== props.migrateVersion
})

const performSystemUpdate = () => {
    if (isUpdating.value || !confirmBackup.value) return

    isUpdating.value = true
    showUpdateModal.value = false

    router.post('/admin/system/migrate', {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isUpdating.value = false
            confirmBackup.value = false
        },
        onSuccess: (page) => {
            console.log('System updated successfully')
            // Optionally reload the page to refresh version info
            if (page.props.flash?.success) {
                setTimeout(() => {
                    window.location.reload()
                }, 2000)
            }
        },
        onError: (errors) => {
            console.error('System update failed:', errors)
        }
    })
}
</script>

<style scoped>
/* Backdrop blur effect */
.fixed.inset-0 {
    backdrop-filter: blur(4px);
}
</style>
