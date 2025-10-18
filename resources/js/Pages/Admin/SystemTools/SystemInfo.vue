<script setup>
import { computed } from 'vue'
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";

const props = defineProps({
    systemInfo: Object
})

const diskUsagePercentage = computed(() => {
    const total = parseSize(props.systemInfo.disk_space.total)
    const used = parseSize(props.systemInfo.disk_space.used)
    return Math.round((used / total) * 100)
})

const diskUsageColor = computed(() => {
    const percentage = diskUsagePercentage.value
    if (percentage >= 90) return 'bg-red-500'
    if (percentage >= 70) return 'bg-yellow-500'
    return 'bg-green-500'
})

const parseSize = (sizeString) => {
    const units = {'B': 1, 'KB': 1024, 'MB': 1024 ** 2, 'GB': 1024 ** 3, 'TB': 1024 ** 4}
    const match = sizeString.match(/^([\d.]+)\s*(\w+)$/)
    if (!match) return 0
    return parseFloat(match[1]) * (units[match[2]] || 1)
}
</script>

<template>
    <AdminLayout title="System Information">
        <div class="py-8">
            <div class="max-w-6xl mx-auto">
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">System Information</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Overview of your system configuration
                            and environment</p>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div
                                class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div class="flex items-center">
                                    <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-lg">
                                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none"
                                             stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-blue-800 dark:text-blue-300">Environment
                                        </div>
                                        <div class="text-lg font-semibold text-blue-900 dark:text-blue-100 capitalize">
                                            {{ systemInfo.environment }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="bg-green-50 dark:bg-green-900/20 p-6 rounded-lg border border-green-200 dark:border-green-800">
                                <div class="flex items-center">
                                    <div class="p-2 bg-green-100 dark:bg-green-800/50 rounded-lg">
                                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none"
                                             stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-green-800 dark:text-green-300">Debug Mode
                                        </div>
                                        <div class="text-lg font-semibold text-green-900 dark:text-green-100">
                                            {{ systemInfo.debug_mode ? 'Enabled' : 'Disabled' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="bg-purple-50 dark:bg-purple-900/20 p-6 rounded-lg border border-purple-200 dark:border-purple-800">
                                <div class="flex items-center">
                                    <div class="p-2 bg-purple-100 dark:bg-purple-800/50 rounded-lg">
                                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none"
                                             stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-purple-800 dark:text-purple-300">Timezone
                                        </div>
                                        <div class="text-lg font-semibold text-purple-900 dark:text-purple-100">
                                            {{ systemInfo.timezone }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-slate-600 pb-2">
                                    Software Information</h3>

                                <div class="space-y-4">
                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span
                                            class="text-sm font-medium text-gray-600 dark:text-gray-400">PHP Version</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.php_version
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Laravel Version</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.laravel_version
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Server Software</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.server_software
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Database Version</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.database_version
                                            }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-slate-600 pb-2">
                                    System Configuration</h3>
                                <div class="space-y-4">
                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span
                                            class="text-sm font-medium text-gray-600 dark:text-gray-400">Memory Limit</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.memory_limit
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Max Execution Time</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.max_execution_time
                                            }}s</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Upload Max Filesize</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{
                                                systemInfo.upload_max_filesize
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span
                                            class="text-sm font-medium text-gray-600 dark:text-gray-400">Cache Driver</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100 capitalize">{{
                                                systemInfo.cache_driver
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Session Driver</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100 capitalize">{{
                                                systemInfo.session_driver
                                            }}</span>
                                    </div>

                                    <div
                                        class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span
                                            class="text-sm font-medium text-gray-600 dark:text-gray-400">Queue Driver</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100 capitalize">{{
                                                systemInfo.queue_driver
                                            }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-slate-600 pb-2 mb-6">
                                Disk Space Usage</h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div
                                    class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg border border-blue-200 dark:border-blue-800">
                                    <div class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Total Space
                                    </div>
                                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                                        {{ systemInfo.disk_space.total }}
                                    </div>
                                </div>

                                <div
                                    class="bg-green-50 dark:bg-green-900/20 p-6 rounded-lg border border-green-200 dark:border-green-800">
                                    <div class="text-sm font-medium text-green-800 dark:text-green-300 mb-2">Free
                                        Space
                                    </div>
                                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                                        {{ systemInfo.disk_space.free }}
                                    </div>
                                </div>

                                <div
                                    class="bg-red-50 dark:bg-red-900/20 p-6 rounded-lg border border-red-200 dark:border-red-800">
                                    <div class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">Used Space
                                    </div>
                                    <div class="text-2xl font-bold text-red-900 dark:text-red-100">
                                        {{ systemInfo.disk_space.used }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <span>Disk Usage</span>
                                    <span>{{ diskUsagePercentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-3">
                                    <div
                                        class="h-3 rounded-full transition-all duration-300"
                                        :class="diskUsageColor"
                                        :style="{ width: diskUsagePercentage + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
