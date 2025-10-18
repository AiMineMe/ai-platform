<script setup>
import { computed, ref } from 'vue'
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";

const copied = ref(false)

const cronUrl = computed(() => {
    return `curl -s ${window.location.origin}/schedule/run`
})

const copyCron = async () => {
    try {
        await navigator.clipboard.writeText(cronUrl.value)
        copied.value = true
        setTimeout(() => {
            copied.value = false
        }, 2000)
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea')
        textArea.value = cronUrl.value
        document.body.appendChild(textArea)
        textArea.select()
        document.execCommand('copy')
        document.body.removeChild(textArea)
        copied.value = true
        setTimeout(() => {
            copied.value = false
        }, 2000)
    }
}
</script>

<template>
    <AdminLayout title="Cron Job Configuration">
        <div class="py-8">
            <div class="max-w-6xl mx-auto">
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Cron Job Configuration</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Set up automated task scheduling for your application</p>
                    </div>

                    <div class="p-6">
                        <!-- Main Cron Job Card -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg border border-blue-200 dark:border-blue-800 mb-6">
                            <div class="flex items-center mb-4">
                                <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-blue-800 dark:text-blue-300">Cron Command</div>
                                    <div class="text-lg font-semibold text-blue-900 dark:text-blue-100">Scheduler Endpoint</div>
                                </div>
                            </div>

                            <div class="flex items-center">
                                <input
                                    type="text"
                                    class="flex-1 px-4 py-3 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-l-lg text-sm text-gray-900 dark:text-gray-100 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    :value="cronUrl"
                                    id="cron"
                                    readonly
                                >
                                <button
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-r-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800"
                                    @click="copyCron"
                                    :class="{ 'bg-green-600 hover:bg-green-700': copied }"
                                >
                                    <span v-if="!copied">Copy</span>
                                    <span v-else class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Copied!
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Instructions Section -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-slate-600 pb-2">
                                    Setup Instructions</h3>

                                <div class="space-y-4">
                                    <div class="flex items-start py-3">
                                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-800/50 rounded-full flex items-center justify-center mr-3 mt-0.5">
                                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">1</span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Copy the command above</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Click the copy button to copy the cron command to your clipboard</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start py-3">
                                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-800/50 rounded-full flex items-center justify-center mr-3 mt-0.5">
                                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">2</span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Access your server's crontab</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Run <code class="bg-gray-100 dark:bg-slate-700 px-1 rounded">crontab -e</code> in your server terminal</div>
                                        </div>
                                    </div>

                                    <div class="flex items-start py-3">
                                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-800/50 rounded-full flex items-center justify-center mr-3 mt-0.5">
                                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">3</span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Add the cron job</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">Add a new line with the schedule and the copied command</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-slate-600 pb-2">
                                    Common Schedules</h3>

                                <div class="space-y-4">
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Every minute</span>
                                        <code class="text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">* * * * *</code>
                                    </div>

                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Every 5 minutes</span>
                                        <code class="text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">*/5 * * * *</code>
                                    </div>

                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Every hour</span>
                                        <code class="text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">0 * * * *</code>
                                    </div>

                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Daily at midnight</span>
                                        <code class="text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">0 0 * * *</code>
                                    </div>

                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Weekly (Sunday)</span>
                                        <code class="text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">0 0 * * 0</code>
                                    </div>

                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Monthly (1st day)</span>
                                        <code class="text-sm text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">0 0 1 * *</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Example Section -->
                        <div class="mt-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-slate-600 pb-2 mb-6">
                                Example Cron Entry</h3>

                            <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">For running every minute:</div>
                                <code class="text-sm font-mono text-gray-900 dark:text-gray-100 block">
                                    * * * * * {{ cronUrl }}
                                </code>
                            </div>

                            <div class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Important Note</h3>
                                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                            <p>Make sure your web server has proper permissions and the URL is accessible. Test the URL in your browser first to ensure it works correctly.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
