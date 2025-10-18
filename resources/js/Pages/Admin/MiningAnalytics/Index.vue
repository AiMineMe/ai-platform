<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";

const props = defineProps({
    overview: {
        type: Object,
        default: () => ({})
    },
    miningTrends: {
        type: Array,
        default: () => []
    },
    userEngagement: {
        type: Object,
        default: () => ({})
    },
    achievementStats: {
        type: Object,
        default: () => ({})
    },
    competitionAnalytics: {
        type: Object,
        default: () => ({})
    },
    performanceMetrics: {
        type: Object,
        default: () => ({})
    },
    filters: {
        type: Object,
        default: () => ({
            date_range: '30'
        })
    }
});

const { currencySymbol } = useSettings();
const isLoading = ref(false);
const selectedDateRange = ref(props.filters.date_range || '30');
const dateRangeOptions = [
    { value: '7', label: 'Last 7 Days' },
    { value: '30', label: 'Last 30 Days' },
    { value: '90', label: 'Last 90 Days' },
    { value: '365', label: 'Last Year' }
];

const applyFilters = () => {
    isLoading.value = true;

    const params = {
        date_range: selectedDateRange.value
    };

    router.get('/admin/mining-analytics', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
    }).format(num);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        return 'Invalid Date';
    }
};

const getGrowthClass = (value) => {
    if (value > 0) return 'text-green-600 dark:text-green-400';
    if (value < 0) return 'text-red-600 dark:text-red-400';
    return 'text-gray-600 dark:text-gray-400';
};

const getGrowthIcon = (value) => {
    if (value > 0) return '↗️';
    if (value < 0) return '↘️';
    return '➡️';
};
</script>

<template>
    <AdminLayout
        title="Mining Analytics"
        page-section="Gamified Mining System"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Mining Analytics
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Comprehensive analytics and insights for the mining system
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <select
                        v-model="selectedDateRange"
                        class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                        @change="applyFilters"
                    >
                        <option v-for="option in dateRangeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Sessions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(overview.total_sessions) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ overview.active_sessions }} active ({{ overview.activity_rate }}%)
                        </p>
                    </div>
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Mined</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(overview.total_mined) }}</p>
                        <p class="text-sm" :class="getGrowthClass(overview.mining_growth)">
                            {{ getGrowthIcon(overview.mining_growth) }} {{ Math.abs(overview.mining_growth) }}% from last period
                        </p>
                    </div>
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Level</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ overview.average_level }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Across all users
                        </p>
                    </div>
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Achievement Statistics</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ achievementStats.total_achievements }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Achievements</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ achievementStats.claim_rate }}%
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Claim Rate</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Unlocked</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ achievementStats.unlocked_count }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Claimed</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ achievementStats.claimed_count }}</span>
                        </div>
                    </div>

                    <div v-if="achievementStats.popular_achievements && achievementStats.popular_achievements.length > 0" class="mt-6">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Popular Achievements</h4>
                        <div class="space-y-2">
                            <div
                                v-for="achievement in achievementStats.popular_achievements.slice(0, 3)"
                                :key="achievement.mining_achievement_id"
                                class="flex justify-between items-center text-sm"
                            >
                                <span class="text-gray-600 dark:text-gray-400">{{ achievement.achievement?.name || 'Unknown' }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ achievement.unlock_count }} unlocks</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Competition Analytics</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                {{ competitionAnalytics.total_competitions }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Competitions</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ competitionAnalytics.active_competitions }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Active Now</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Prize Pool</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ currencySymbol }}{{ formatNumber(competitionAnalytics.total_prize_pool) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Unique Participants</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ competitionAnalytics.unique_participants }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Total Participations</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ competitionAnalytics.total_participations }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Avg. Mined</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ formatNumber(competitionAnalytics.avg_mined_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Session Types</h3>
                </div>
                <div class="p-6">
                    <div v-if="userEngagement.session_types && userEngagement.session_types.length > 0" class="space-y-3">
                        <div
                            v-for="type in userEngagement.session_types"
                            :key="type.session_type"
                            class="flex items-center justify-between"
                        >
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">
                                {{ type.session_type }}
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div
                                        class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full"
                                        :style="{ width: `${Math.min((type.count / Math.max(...userEngagement.session_types.map(t => t.count))) * 100, 100)}%` }"
                                    />
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white min-w-[30px]">
                                    {{ type.count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-4 text-gray-400">No data available</div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Streak Analysis</h3>
                </div>
                <div class="p-6">
                    <div v-if="userEngagement.streak_analysis && userEngagement.streak_analysis.length > 0" class="space-y-3">
                        <div
                            v-for="streak in userEngagement.streak_analysis"
                            :key="streak.streak_range"
                            class="flex items-center justify-between"
                        >
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ streak.streak_range }}
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div
                                        class="bg-gradient-to-r from-orange-500 to-red-600 h-2 rounded-full"
                                        :style="{ width: `${Math.min((streak.count / Math.max(...userEngagement.streak_analysis.map(s => s.count))) * 100, 100)}%` }"
                                    />
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white min-w-[30px]">
                                    {{ streak.count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-4 text-gray-400">No data available</div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Mining Rates</h3>
                </div>
                <div class="p-6">
                    <div v-if="performanceMetrics.mining_rate_analysis" class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Average Rate</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(performanceMetrics.mining_rate_analysis.avg_rate) }}/sec
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Min Rate</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(performanceMetrics.mining_rate_analysis.min_rate) }}/sec
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Max Rate</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(performanceMetrics.mining_rate_analysis.max_rate) }}/sec
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Avg Multiplier</span>
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ formatNumber(performanceMetrics.mining_rate_analysis.avg_multiplier) }}x
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-center py-4 text-gray-400">No data available</div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Mining Trends</h3>
            </div>
            <div class="p-6">
                <div v-if="miningTrends.length > 0" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="text-center">
                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                {{ formatNumber(miningTrends.reduce((sum, day) => sum + parseFloat(day.daily_mined || 0), 0)) }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Mined</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600 dark:text-green-400">
                                {{ Math.max(...miningTrends.map(day => parseInt(day.active_users || 0))) }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Peak Active Users</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-purple-600 dark:text-purple-400">
                                {{ formatNumber(miningTrends.reduce((sum, day) => sum + parseFloat(day.avg_rate || 0), 0) / miningTrends.length) }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Avg Rate/sec</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(day, index) in miningTrends.slice(-7)" :key="day.date" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700 rounded-lg">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ formatDate(day.date) }}
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="text-right">
                                    <div class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                        {{ formatNumber(day.daily_mined) }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">tokens mined</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-green-600 dark:text-green-400">
                                        {{ day.active_users }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">active users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-400">
                    No mining trend data available for the selected period
                </div>
            </div>
        </div>

        <div v-if="performanceMetrics.top_performers && performanceMetrics.top_performers.length > 0" class="bg-white dark:bg-slate-800 rounded-xl mt-5 shadow-sm border border-gray-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Performers</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div
                        v-for="(performer, index) in performanceMetrics.top_performers"
                        :key="performer.user_id"
                        class="text-center p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
                    >
                        <div class="text-2xl mb-2">
                            {{ index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '🏅' }}
                        </div>
                        <div class="font-semibold text-gray-900 dark:text-white text-sm">
                            {{ performer.user?.name || 'Unknown' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ formatNumber(performer.total_mined) }} tokens
                        </div>
                        <div class="text-xs text-purple-600 dark:text-purple-400">
                            Level {{ performer.level }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
