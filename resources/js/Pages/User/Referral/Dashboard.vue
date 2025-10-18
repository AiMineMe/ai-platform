<script setup>
import { computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import UserLayout from "@/Layouts/UserLayout/UserLayout.vue";
import { useToast } from "@/composables/useToast.js";
import { useTranslation } from '@/composables/useTranslation';

const props = defineProps({
    stats: {
        type: Object,
        required: true
    },
    recentReferrals: {
        type: Object,
        default: () => ({ data: [], links: [], meta: {} })
    },
    referralCode: {
        type: String,
        required: true
    },
    referralLink: {
        type: String,
        required: true
    },
});

const { showToast } = useToast();
const { t } = useTranslation();
const page = usePage();

const primaryColor = computed(() => page.props.primaryColor || '#1f2937');
const currencySymbol = computed(() => page.props.currencySymbol || '$');

const derivedColors = computed(() => {
    const primary = primaryColor.value;
    const adjustColor = (color, amount) => {
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        const newR = Math.min(255, Math.max(0, r + amount));
        const newG = Math.min(255, Math.max(0, g + amount));
        const newB = Math.min(255, Math.max(0, b + amount));
        return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
    };

    return {
        primary: primary,
        surface: adjustColor(primary, -10),
        background: adjustColor(primary, -200),
        textPrimary: '#ffffff',
        textSecondary: '#e2e8f0',
        textMuted: '#94a3b8',
        border: adjustColor(primary, 60),
        accent: adjustColor(primary, 120),
        success: '#10b981',
        warning: '#f59e0b'
    };
});

const cardStyle = computed(() => ({
    backgroundColor: derivedColors.value.surface + '50',
    borderColor: derivedColors.value.border + '20'
}));

const copyToClipboard = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            showToast(t('copied'), 'success');
            return;
        } catch (err) {
        }
    }

    try {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'absolute';
        textArea.style.left = '-999999px';

        document.body.appendChild(textArea);
        textArea.select();

        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);

        if (successful) {
            showToast(t('copied'), 'success');
        } else {
            showToast(t('copyFailed'), 'error');
        }
    } catch (err) {
        showToast(t('copyFailed'), 'error');
    }
};

const changePage = (url) => {
    if (url) {
        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
        });
    }
};

const getPaginationLabel = (link) => {
    if (link.label.includes('Previous')) return t('previous');
    if (link.label.includes('Next')) return t('next');
    return `${t('page')} ${link.label}`;
};

onMounted(() => {
    const flash = page.props.flash;
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
});
</script>

<template>
    <UserLayout :page-title="t('referralDashboard')" :page-section="t('referral')">
        <div class="max-w-none mx-auto space-y-4 sm:space-y-6 px-3 sm:px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-5 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="text-xl sm:text-2xl lg:text-3xl font-bold" :style="{ color: derivedColors.textPrimary }">
                        {{ stats.total_referrals || 0 }}
                    </div>
                    <div class="text-sm sm:text-base mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalReferrals') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-5 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="text-xl sm:text-2xl lg:text-3xl font-bold break-words" :style="{ color: derivedColors.success }">
                        {{ currencySymbol }}{{ stats.total_commission || 0 }}
                    </div>
                    <div class="text-sm sm:text-base mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('totalCommission') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-5 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="text-xl sm:text-2xl lg:text-3xl font-bold" :style="{ color: derivedColors.warning }">
                        {{ stats.commission_rate || 0 }}%
                    </div>
                    <div class="text-sm sm:text-base mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('commissionRate') }}
                    </div>
                </div>
                <div class="backdrop-blur-sm rounded-lg border p-4 sm:p-5 lg:p-6 hover:scale-105 transition-all duration-200" :style="cardStyle">
                    <div class="text-xl sm:text-2xl lg:text-3xl font-bold" :style="{ color: derivedColors.textPrimary }">
                        {{ stats.this_month_referrals || 0 }}
                    </div>
                    <div class="text-sm sm:text-base mt-1" :style="{ color: derivedColors.textMuted }">
                        {{ t('thisMonth') }}
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <h2 class="text-lg sm:text-xl font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('yourReferralCode') }}
                </h2>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                    <input
                        :value="referralCode"
                        readonly
                        class="flex-1 px-3 py-3 sm:py-2 border rounded-lg font-mono text-sm sm:text-base"
                        :style="{
                            backgroundColor: derivedColors.surface + '50',
                            borderColor: derivedColors.border,
                            color: derivedColors.textPrimary
                        }"
                    >
                    <button
                        @click="copyToClipboard(referralCode)"
                        class="px-4 py-3 sm:py-2 rounded-lg transition-colors whitespace-nowrap text-sm sm:text-base"
                        :style="{
                            backgroundColor: derivedColors.accent,
                            color: derivedColors.surface
                        }"
                    >
                        {{ t('copy') }}
                    </button>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border p-4 sm:p-6" :style="cardStyle">
                <h2 class="text-lg sm:text-xl font-semibold mb-4" :style="{ color: derivedColors.textPrimary }">
                    {{ t('referralLink') }}
                </h2>
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                        <input
                            :value="referralLink"
                            readonly
                            class="flex-1 px-3 py-3 sm:py-2 border rounded-lg text-sm break-all"
                            :style="{
                                backgroundColor: derivedColors.surface + '50',
                                borderColor: derivedColors.border,
                                color: derivedColors.textPrimary
                            }"
                        >
                        <button
                            @click="copyToClipboard(referralLink)"
                            class="px-4 py-3 sm:py-2 rounded-lg transition-colors whitespace-nowrap text-sm sm:text-base"
                            :style="{
                                backgroundColor: derivedColors.accent,
                                color: derivedColors.surface
                            }"
                        >
                            {{ t('copy') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="backdrop-blur-sm rounded-xl border overflow-hidden" :style="cardStyle">
                <div class="px-4 sm:px-6 py-4 border-b" :style="{ borderColor: derivedColors.border + '20' }">
                    <h2 class="text-lg sm:text-xl font-semibold" :style="{ color: derivedColors.textPrimary }">
                        {{ t('recentReferrals') }}
                    </h2>
                </div>

                <div v-if="recentReferrals.data && recentReferrals.data.length > 0" class="overflow-x-auto">
                    <div class="block sm:hidden">
                        <div
                            v-for="referral in recentReferrals.data"
                            :key="referral.id"
                            class="border-b p-4 space-y-2"
                            :style="{ borderColor: derivedColors.border + '50' }"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-white text-sm">{{ referral.referred_user }}</div>
                                    <div class="text-xs text-white opacity-75">{{ referral.referred_email }}</div>
                                </div>
                                <span class="px-2 py-1 rounded-full text-xs font-medium ml-2 flex-shrink-0"
                                      :class="{
                                          'bg-green-100 text-green-800': referral.status === 'Active',
                                          'bg-yellow-100 text-yellow-800': referral.status === 'Pending',
                                          'bg-red-100 text-red-800': referral.status === 'Inactive',
                                          'bg-gray-100 text-gray-800': !['Active', 'Pending', 'Inactive'].includes(referral.status)
                                      }">
                                    {{ referral.status }}
                                </span>
                            </div>
                            <div class="flex justify-between text-xs text-white opacity-75">
                                <span>{{ referral.date }}</span>
                                <span>{{ referral.time }}</span>
                            </div>
                        </div>
                    </div>

                    <table class="w-full hidden sm:table">
                        <thead :style="{ backgroundColor: derivedColors.surface + '50' }">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase text-white">
                                {{ t('name') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase text-white hidden md:table-cell">
                                {{ t('email') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase text-white">
                                {{ t('status') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase text-white hidden lg:table-cell">
                                {{ t('joined_date') }}
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium uppercase text-white hidden lg:table-cell">
                                {{ t('time') }}
                            </th>
                        </tr>
                        </thead>
                        <tbody class="divide-y" :style="{ borderColor: derivedColors.border + '50' }">
                        <tr v-for="referral in recentReferrals.data" :key="referral.id">
                            <td class="px-4 sm:px-6 py-4 text-sm text-white">
                                <div>{{ referral.referred_user }}</div>
                                <div class="text-xs opacity-75 md:hidden">{{ referral.referred_email }}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm text-white hidden md:table-cell">
                                {{ referral.referred_email }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                      :class="{
                                          'bg-green-100 text-green-800': referral.status === 'Active',
                                          'bg-yellow-100 text-yellow-800': referral.status === 'Pending',
                                          'bg-red-100 text-red-800': referral.status === 'Inactive',
                                          'bg-gray-100 text-gray-800': !['Active', 'Pending', 'Inactive'].includes(referral.status)
                                      }">
                                    {{ referral.status }}
                                </span>
                                <div class="text-xs text-white opacity-75 mt-1 lg:hidden">
                                    {{ referral.date }} {{ referral.time }}
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm text-white hidden lg:table-cell">
                                {{ referral.date }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm text-white hidden lg:table-cell">
                                {{ referral.time }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-8">
                    <p :style="{ color: derivedColors.textMuted }">{{ t('noReferralsYet') }}</p>
                </div>

                <div
                    v-if="recentReferrals.data && recentReferrals.data.length > 0 && recentReferrals.links"
                    class="px-4 sm:px-6 py-4 border-t"
                    :style="{ borderColor: derivedColors.border + '20', backgroundColor: derivedColors.surface + '30' }"
                >
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-3 sm:space-y-0">
                        <div class="text-xs sm:text-sm" :style="{ color: derivedColors.textMuted }">
                            {{ t('showingResults', {
                            from: recentReferrals.from || 0,
                            to: recentReferrals.to || 0,
                            total: recentReferrals.total || 0
                        }) }}
                        </div>
                        <nav
                            class="flex flex-wrap justify-center sm:justify-end space-x-1"
                            role="navigation"
                            :aria-label="t('pagination')"
                        >
                            <button
                                v-for="(link, index) in recentReferrals.links"
                                :key="index"
                                :disabled="!link.url"
                                :aria-current="link.active ? 'page' : null"
                                :aria-label="getPaginationLabel(link)"
                                :class="[
                                    'px-2 sm:px-3 py-2 text-xs sm:text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-opacity-50',
                                    link.active ? 'cursor-default' : link.url ? 'hover:opacity-80' : 'cursor-not-allowed opacity-50'
                                ]"
                                :style="{
                                    backgroundColor: link.active ? derivedColors.accent : link.url ? derivedColors.surface : derivedColors.surface + '50',
                                    color: link.active ? derivedColors.background : link.url ? derivedColors.textSecondary : derivedColors.textMuted,
                                    ':focus': { ringColor: derivedColors.accent }
                                }"
                                @click="changePage(link.url)"
                                v-html="link.label"
                            />
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
