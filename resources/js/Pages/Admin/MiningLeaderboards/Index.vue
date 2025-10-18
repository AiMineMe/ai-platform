<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import { useSettings } from "@/composables/useSettings.js";

const props = defineProps({
    globalLeaderboard: {
        type: Array,
        default: () => []
    },
    competitionLeaderboard: {
        type: Array,
        default: () => []
    },
    activeCompetitions: {
        type: Array,
        default: () => []
    },
    topPerformers: {
        type: Object,
        default: () => ({})
    },
    levelDistribution: {
        type: Array,
        default: () => []
    },
    filters: {
        type: Object,
        default: () => ({
            timeframe: 'all_time',
            competition_id: null
        })
    }
});


const isLoading = ref(false);
const selectedTimeframe = ref(props.filters.timeframe || 'all_time');
const selectedCompetition = ref(props.filters.competition_id || '');

const timeframeOptions = [
    { value: 'all_time', label: 'All Time' },
    { value: 'month', label: 'This Month' },
    { value: 'week', label: 'This Week' },
    { value: 'today', label: 'Today' }
];

const applyFilters = () => {
    isLoading.value = true;

    const params = {
        timeframe: selectedTimeframe.value
    };

    if (selectedCompetition.value) {
        params.competition_id = selectedCompetition.value;
    }

    router.get('/admin/mining-leaderboards', params, {
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
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return 'Invalid Date';
    }
};

const getRankClass = (rank) => {
    if (rank === 1) return 'text-yellow-500 font-bold';
    if (rank === 2) return 'text-gray-400 font-bold';
    if (rank === 3) return 'text-orange-500 font-bold';
    return 'text-gray-600 dark:text-gray-300';
};

const getRankIcon = (rank) => {
    if (rank === 1) return '#1';
    if (rank === 2) return '#2';
    if (rank === 3) return '#3';
    return `#${rank}`;
};
</script>

<template>
    <AdminLayout
        title="Mining Leaderboards"
        page-section="Gamified Mining System"
    >
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Mining Leaderboards
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Track top miners and competition rankings across different timeframes
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Top Miner</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ topPerformers.highest_miner?.user?.name || 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatNumber(topPerformers.highest_miner?.total_mined || 0) }} tokens
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Highest Level</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ topPerformers.highest_level?.user?.name || 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Level {{ topPerformers.highest_level?.level || 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Longest Streak</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ topPerformers.longest_streak?.user?.name || 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ topPerformers.longest_streak?.streak_days || 0 }} days
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Most Active</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ topPerformers.most_active?.user?.name || 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatDate(topPerformers.most_active?.last_activity) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 mb-6">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-[140px]">
                            <select
                                v-model="selectedTimeframe"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="applyFilters"
                            >
                                <option v-for="option in timeframeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="min-w-[200px]">
                            <select
                                v-model="selectedCompetition"
                                class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100"
                                @change="applyFilters"
                            >
                                <option value="">Global Leaderboard</option>
                                <option v-for="competition in activeCompetitions" :key="competition.id" :value="competition.id">
                                    {{ competition.name }} ({{ competition.type }})
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ selectedCompetition ? 'Competition Leaderboard' : 'Global Mining Leaderboard' }}
                        </h3>
                    </div>

                    <div class="p-6">
                        <div v-if="(selectedCompetition ? competitionLeaderboard : globalLeaderboard).length > 0" class="space-y-3">
                            <div
                                v-for="entry in (selectedCompetition ? competitionLeaderboard : globalLeaderboard)"
                                :key="entry.rank"
                                class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-700 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors"
                            >
                                <div class="flex items-center space-x-4">
                                    <div class="text-2xl min-w-[50px]" :class="getRankClass(entry.rank)">
                                        {{ getRankIcon(entry.rank) }}
                                    </div>
                                    <div class="h-12 w-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold text-sm">
                                            {{ entry.user?.name?.charAt(0).toUpperCase() || '?' }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ entry.user?.name || 'Unknown' }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ entry.user?.email || 'No email' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div class="font-bold text-gray-900 dark:text-white">
                                        {{ formatNumber(selectedCompetition ? entry.mined_amount : entry.total_mined) }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ selectedCompetition ? 'mined in competition' : 'total mined' }}
                                    </div>
                                    <div v-if="!selectedCompetition" class="text-xs text-purple-600 dark:text-purple-400">
                                        Level {{ entry.level }} • {{ entry.streak_days }} day streak
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8">
                            <div class="text-gray-400">No leaderboard data available</div>
                            <div class="text-sm text-gray-500">Check back later for rankings</div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Level Distribution
                        </h3>
                    </div>

                    <div class="p-6">
                        <div v-if="levelDistribution.length > 0" class="space-y-4">
                            <div
                                v-for="level in levelDistribution"
                                :key="level.level_range"
                                class="flex items-center justify-between"
                            >
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Level {{ level.level_range }}
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div
                                            class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full"
                                            :style="{ width: `${Math.min((level.count / Math.max(...levelDistribution.map(l => l.count))) * 100, 100)}%` }"
                                        />
                                    </div>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white min-w-[30px]">
                                        {{ level.count }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-4">
                            <div class="text-gray-400">No level data available</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
