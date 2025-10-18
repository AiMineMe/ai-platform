<script setup>
import AdminLayout from "@/Layouts/AdminLayout/AdminLayout.vue";
import {computed} from "vue";
import {usePage} from "@inertiajs/vue3";

const props = defineProps({
    stats: {
        type: Object,
        required: true
    },
    recent_activity: {
        type: Object,
        required: true
    },
    chart_data: {
        type: Object,
        required: true
    }
});

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0';
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US').format(num);
};
const page = usePage();
const currencySymbol = computed(() => page.props.currencySymbol || '$');
const formatCurrency = (value) => {
    if (value === null || value === undefined) return `${currencySymbol.value}0.00`;
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(num).replace('$', currencySymbol.value);
};

const getStatusColor = (status) => {
    const colors = {
        'active': 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300',
        'pending': 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300',
        'approved': 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300',
        'rejected': 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-300',
        'completed': 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300',
        'failed': 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-300',
        'won': 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300',
        'lost': 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-300',
        1: 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-300',
        0: 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-300'
    };
    return colors[status] || 'text-gray-600 bg-gray-100 dark:bg-gray-900/30 dark:text-gray-300';
};

const getStatusText = (status) => {
    const statusMap = {
        1: 'Active',
        0: 'Inactive',
        'pending': 'Pending',
        'approved': 'Approved',
        'rejected': 'Rejected',
        'completed': 'Completed',
        'failed': 'Failed',
        'active': 'Active',
        'won': 'Won',
        'lost': 'Lost',
        'reviewing': 'Reviewing'
    };
    return statusMap[status] || status;
};

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().slice(0, 2);
};

const getRelativeTime = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        return `${Math.floor(diffInSeconds / 2592000)}mo ago`;
    } catch (error) {
        return '';
    }
};

const getTradeDirectionIcon = (direction) => {
    return direction === 'up' ? '↗️' : '↘️';
};

const getTradeDirectionColor = (direction) => {
    return direction === 'up' ? 'text-green-600' : 'text-red-600';
};
</script>

<template>
    <AdminLayout title="Dashboard" page-section="Overview">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Dashboard Overview
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Monitor your platform's performance and key metrics
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.users.total) }}</p>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="inline-flex items-center text-xs text-green-600 bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded-full">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-1"></div>
                                {{ formatNumber(stats.users.new_today) }} today
                            </span>
                            <span class="text-xs text-blue-600">{{ formatNumber(stats.users.new_week) }} this week</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-xl">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Users</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.users.active) }}</p>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="text-xs text-green-600">{{ formatNumber(stats.users.verified) }} verified</span>
                            <span class="text-xs text-amber-600">{{ formatNumber(stats.users.pending_kyc) }} pending KYC</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-xl">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Deposits</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(stats.financial.total_deposits) }}</p>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="text-xs text-green-600">{{ formatCurrency(stats.financial.deposits_today) }} today</span>
                            <span class="text-xs text-amber-600">{{ formatCurrency(stats.financial.pending_deposits) }} pending</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-xl">
                        <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Trades</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.trading.active_trades) }}</p>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="text-xs text-green-600">{{ formatCurrency(stats.trading.daily_volume) }} today</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatNumber(stats.trading.total_trades) }} total</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Investment</h3>
                        <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">ICO Sales</span>
                        <span class="text-sm font-semibold text-purple-600">{{ formatCurrency(stats.investment.ico_sales) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Pending Investments</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(stats.investment.pending_investments) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Tokens Sold</span>
                        <span class="text-sm font-semibold text-emerald-600">{{ formatNumber(stats.investment.total_tokens_sold) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Investors</span>
                        <span class="text-sm font-semibold text-blue-600">{{ formatNumber(stats.investment.total_investors) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Mining</h3>
                        <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Active Sessions</span>
                        <span class="text-sm font-semibold text-green-600">{{ formatNumber(stats.mining.active_sessions) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Miners</span>
                        <span class="text-sm font-semibold text-blue-600">{{ formatNumber(stats.mining.total_miners) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Mined</span>
                        <span class="text-sm font-semibold text-purple-600">{{ formatNumber(stats.mining.total_mined) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Mined Today</span>
                        <span class="text-sm font-semibold text-amber-600">{{ formatNumber(stats.mining.mining_today) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Trading</h3>
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Volume</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(stats.trading.total_volume) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Won Trades</span>
                        <span class="text-sm font-semibold text-green-600">{{ formatNumber(stats.trading.won_trades) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Lost Trades</span>
                        <span class="text-sm font-semibold text-red-600">{{ formatNumber(stats.trading.lost_trades) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Success Rate</span>
                        <span class="text-sm font-semibold text-blue-600">
                            {{ stats.trading.total_trades > 0 ? Math.round((stats.trading.won_trades / (stats.trading.won_trades + stats.trading.lost_trades)) * 100) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Financial</h3>
                        <div class="p-2 bg-rose-100 dark:bg-rose-900/30 rounded-lg">
                            <svg class="h-5 w-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Total Balance</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(stats.financial.total_balance) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Withdrawals Today</span>
                        <span class="text-sm font-semibold text-red-600">{{ formatCurrency(stats.financial.withdrawals_today) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Pending Withdrawals</span>
                        <span class="text-sm font-semibold text-amber-600">{{ formatCurrency(stats.financial.pending_withdrawals) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Net Flow</span>
                        <span class="text-sm font-semibold" :class="(stats.financial.total_deposits - stats.financial.total_withdrawals) >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ formatCurrency(stats.financial.total_deposits - stats.financial.total_withdrawals) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Users</h3>
                        <div class="p-2 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
                            <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div v-if="recent_activity.users && recent_activity.users.length > 0" class="space-y-4">
                        <div v-for="user in recent_activity.users" :key="user.id" class="flex items-center justify-between py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 rounded-lg px-2 -mx-2 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div v-if="user.avatar" class="h-full w-full rounded-full overflow-hidden border-2 border-white dark:border-slate-600 shadow-sm">
                                        <img :src="user.avatar_url" :alt="user.name" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else class="h-full w-full rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                        {{ getInitials(user.name) }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name || 'Unnamed User' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 text-xs rounded-full font-medium" :class="getStatusColor(user.status)">
                                    {{ getStatusText(user.status) }}
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ getRelativeTime(user.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12">
                        <div class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent users</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Deposits</h3>
                        <div class="p-2 bg-green-100 dark:bg-green-900/50 rounded-lg">
                            <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div v-if="recent_activity.deposits && recent_activity.deposits.length > 0" class="space-y-4">
                        <div v-for="deposit in recent_activity.deposits" :key="deposit.id" class="flex items-center justify-between py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 rounded-lg px-2 -mx-2 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div v-if="deposit.user?.avatar_url" class="h-full w-full rounded-full overflow-hidden border-2 border-white dark:border-slate-600 shadow-sm">
                                        <img :src="deposit.user.avatar_url" :alt="deposit.user.name" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else class="h-full w-full rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                        {{ getInitials(deposit.user?.name) }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ deposit.user?.name || 'Unknown' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(deposit.amount) }} {{ deposit.currency }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 text-xs rounded-full font-medium" :class="getStatusColor(deposit.status)">
                                    {{ getStatusText(deposit.status) }}
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ getRelativeTime(deposit.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12">
                        <div class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent deposits</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-r from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Withdrawals</h3>
                        <div class="p-2 bg-red-100 dark:bg-red-900/50 rounded-lg">
                            <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div v-if="recent_activity.withdrawals && recent_activity.withdrawals.length > 0" class="space-y-4">
                        <div v-for="withdrawal in recent_activity.withdrawals" :key="withdrawal.id" class="flex items-center justify-between py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 rounded-lg px-2 -mx-2 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div v-if="withdrawal.user?.avatar" class="h-full w-full rounded-full overflow-hidden border-2 border-white dark:border-slate-600 shadow-sm">
                                        <img :src="withdrawal.user.avatar_url" :alt="withdrawal.user.name" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else class="h-full w-full rounded-full bg-gradient-to-br from-red-500 to-pink-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                        {{ getInitials(withdrawal.user?.name) }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ withdrawal.user?.name || 'Unknown' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(withdrawal.amount) }} {{ withdrawal.currency }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 text-xs rounded-full font-medium" :class="getStatusColor(withdrawal.status)">
                                    {{ getStatusText(withdrawal.status) }}
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ getRelativeTime(withdrawal.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12">
                        <div class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 12H4" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent withdrawals</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Trades</h3>
                        <div class="p-2 bg-purple-100 dark:bg-purple-900/50 rounded-lg">
                            <svg class="h-4 w-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div v-if="recent_activity.trades && recent_activity.trades.length > 0" class="space-y-4">
                        <div v-for="trade in recent_activity.trades" :key="trade.id" class="flex items-center justify-between py-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 rounded-lg px-2 -mx-2 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div v-if="trade.user?.avatar" class="h-full w-full rounded-full overflow-hidden border-2 border-white dark:border-slate-600 shadow-sm">
                                        <img :src="trade.user.avatar_url" :alt="trade.user.name" class="h-full w-full object-cover" />
                                    </div>
                                    <div v-else class="h-full w-full rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                        {{ getInitials(trade.user?.name) }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center space-x-2">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ trade.symbol }}</p>
                                        <span class="text-sm font-medium" :class="getTradeDirectionColor(trade.direction)">
                                            {{ getTradeDirectionIcon(trade.direction) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatCurrency(trade.amount) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 text-xs rounded-full font-medium" :class="getStatusColor(trade.status)">
                                    {{ getStatusText(trade.status) }}
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ getRelativeTime(trade.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12">
                        <div class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent trades</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
