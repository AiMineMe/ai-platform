<script setup>
import {useForm, usePage} from '@inertiajs/vue3'
import {computed, watch} from 'vue'
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import {useToast} from "@/composables/useToast.js";

const props = defineProps({
    cacheStats: Object
})

const form = useForm({
    cache_types: []
})

const page = usePage()

const results = computed(() => {
    return page.props.results || []
})

const {showToast, success, error} = useToast();

watch(results, (newResults) => {
    if (newResults && newResults.length > 0) {
        const allSuccess = newResults.every(result => result.status === 'success')
        const hasErrors = newResults.some(result => result.status === 'error')

        if (allSuccess) {
            showToast(`${newResults.length} cache type(s) cleared successfully`)
        } else if (hasErrors) {
            const successCount = newResults.filter(r => r.status === 'success').length
            const errorCount = newResults.filter(r => r.status === 'error').length

            if (successCount > 0) {
                showToast(`${successCount} successful, ${errorCount} failed`)
            } else {
                showToast('All cache operations failed. Please check your system.')
            }
        }
    }
}, {deep: true})

const clearCache = () => {
    form.post('/admin/cache-clear', {
        preserveScroll: true,
        onStart: () => {
            success('Cache clearing process has been initiated...')
        },
        onError: (errors) => {
            errors('Failed to process cache clear request')
        }
    })
}

const selectAll = () => {
    form.cache_types = ['application', 'config', 'route', 'view', 'compiled']
    showToast('All cache types have been selected')
}
</script>

<template>
    <AdminLayout title="Cache Clear">
        <div class="py-8">
            <div class="max-w-6xl mx-auto">
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Clear System Cache</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Select cache types to clear from your
                            system</p>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div
                                class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                                <div class="text-sm font-medium text-blue-800 dark:text-blue-300">Application Cache
                                </div>
                                <div class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                                    {{ cacheStats.application_cache_size }}
                                </div>
                            </div>
                            <div
                                class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                                <div class="text-sm font-medium text-green-800 dark:text-green-300">Config Cache</div>
                                <div class="text-lg font-semibold text-green-900 dark:text-green-100">
                                    {{ cacheStats.config_cached ? 'Cached' : 'Not Cached' }}
                                </div>
                            </div>
                            <div
                                class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                <div class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Routes Cache</div>
                                <div class="text-lg font-semibold text-yellow-900 dark:text-yellow-100">
                                    {{ cacheStats.routes_cached ? 'Cached' : 'Not Cached' }}
                                </div>
                            </div>
                            <div
                                class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                                <div class="text-sm font-medium text-purple-800 dark:text-purple-300">View Cache</div>
                                <div class="text-lg font-semibold text-purple-900 dark:text-purple-100">
                                    {{ cacheStats.views_cached }} files
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="clearCache" class="space-y-6">
                            <div>
                                <label class="text-base font-medium text-gray-900 dark:text-gray-100">Select Cache Types
                                    to Clear</label>
                                <div class="mt-4 space-y-4">
                                    <div class="flex items-center">
                                        <input
                                            id="application"
                                            v-model="form.cache_types"
                                            value="application"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-slate-700 rounded"
                                        >
                                        <label for="application"
                                               class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Application Cache
                                            <span class="text-gray-500 dark:text-gray-400 text-xs block">Clear all application cache data</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="config"
                                            v-model="form.cache_types"
                                            value="config"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-slate-700 rounded"
                                        >
                                        <label for="config"
                                               class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Configuration Cache
                                            <span class="text-gray-500 dark:text-gray-400 text-xs block">Clear cached configuration files</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="route"
                                            v-model="form.cache_types"
                                            value="route"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-slate-700 rounded"
                                        >
                                        <label for="route"
                                               class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Route Cache
                                            <span class="text-gray-500 dark:text-gray-400 text-xs block">Clear cached application routes</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="view"
                                            v-model="form.cache_types"
                                            value="view"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-slate-700 rounded"
                                        >
                                        <label for="view"
                                               class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            View Cache
                                            <span class="text-gray-500 dark:text-gray-400 text-xs block">Clear compiled view templates</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input
                                            id="compiled"
                                            v-model="form.cache_types"
                                            value="compiled"
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-slate-700 rounded"
                                        >
                                        <label for="compiled"
                                               class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Compiled Classes
                                            <span class="text-gray-500 dark:text-gray-400 text-xs block">Clear compiled class files</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="form.errors.cache_types" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.cache_types }}
                                </div>
                            </div>

                            <div class="flex justify-between items-center">
                                <button
                                    type="button"
                                    @click="selectAll"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300"
                                >
                                    Select All
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing || form.cache_types.length === 0"
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center"
                                >
                                    <span v-if="form.processing">Clearing...</span>
                                    <span v-else>Clear Selected Cache</span>
                                </button>
                            </div>
                        </form>

                        <div v-if="results && results.length > 0" class="mt-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Clear Results</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="result in results"
                                    :key="result.type"
                                    class="flex items-center justify-between p-3 rounded-lg"
                                    :class="result.status === 'success' ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300'"
                                >
                                    <span class="font-medium">{{ result.type }}</span>
                                    <span class="text-sm">{{ result.message }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
