<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MiningSession;
use App\Models\MiningCompetition;
use App\Models\MiningAchievement;
use App\Models\UserAchievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MiningAnalyticsController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $dateRange = $request->get('date_range', '30');
        $startDate = now()->subDays((int)$dateRange);
        $overview = $this->getOverviewMetrics($startDate);
        $miningTrends = $this->getMiningTrends($startDate);
        $userEngagement = $this->getUserEngagement($startDate);
        $achievementStats = $this->getAchievementStats($startDate);
        $competitionAnalytics = $this->getCompetitionAnalytics($startDate);
        $performanceMetrics = $this->getPerformanceMetrics($startDate);
        return Inertia::render('Admin/MiningAnalytics/Index', [
            'overview' => $overview,
            'miningTrends' => $miningTrends,
            'userEngagement' => $userEngagement,
            'achievementStats' => $achievementStats,
            'competitionAnalytics' => $competitionAnalytics,
            'performanceMetrics' => $performanceMetrics,
            'filters' => [
                'date_range' => $dateRange
            ]
        ]);
    }

    /**
     * @param $startDate
     * @return array
     */
    private function getOverviewMetrics($startDate): array
    {
        $totalSessions = MiningSession::count();
        $activeSessions = MiningSession::where('is_active', true)->count();
        $totalMined = MiningSession::sum('total_mined');
        $avgLevel = MiningSession::avg('level');

        $previousStart = $startDate->copy()->subDays($startDate->diffInDays(now()));
        $previousEnd = $startDate->copy();
        $previousMined = MiningSession::whereBetween('updated_at', [$previousStart, $previousEnd])->sum('total_mined');
        $currentMined = MiningSession::where('updated_at', '>=', $startDate)->sum('total_mined');
        $miningGrowth = $previousMined > 0 ? (($currentMined - $previousMined) / $previousMined) * 100 : 0;

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'total_mined' => $totalMined,
            'average_level' => round($avgLevel, 2),
            'mining_growth' => round($miningGrowth, 2),
            'activity_rate' => $totalSessions > 0 ? round(($activeSessions / $totalSessions) * 100, 2) : 0
        ];
    }

    /**
     * @param $startDate
     * @return mixed
     */
    private function getMiningTrends($startDate)
    {
        return MiningSession::select(
            DB::raw('DATE(updated_at) as date'),
            DB::raw('SUM(total_mined) as daily_mined'),
            DB::raw('COUNT(DISTINCT user_id) as active_users'),
            DB::raw('AVG(mining_rate) as avg_rate')
        )
            ->where('updated_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * @param $startDate
     * @return array
     */
    private function getUserEngagement($startDate): array
    {
        $sessionTypes = MiningSession::select('session_type', DB::raw('COUNT(*) as count'))
            ->where('updated_at', '>=', $startDate)
            ->groupBy('session_type')
            ->get();

        $levelDistribution = MiningSession::select('level', DB::raw('COUNT(*) as count'))
            ->where('updated_at', '>=', $startDate)
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        $streakAnalysis = MiningSession::select(
            DB::raw('CASE
                    WHEN streak_days = 0 THEN "No Streak"
                    WHEN streak_days BETWEEN 1 AND 7 THEN "1-7 days"
                    WHEN streak_days BETWEEN 8 AND 30 THEN "8-30 days"
                    ELSE "30+ days"
                END as streak_range'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('streak_range')
            ->get();

        return [
            'session_types' => $sessionTypes,
            'level_distribution' => $levelDistribution,
            'streak_analysis' => $streakAnalysis
        ];
    }

    /**
     * @param $startDate
     * @return array
     */
    private function getAchievementStats($startDate): array
    {
        $totalAchievements = MiningAchievement::where('is_active', true)->count();
        $unlockedAchievements = UserAchievement::where('earned_at', '>=', $startDate)->count();
        $claimedAchievements = UserAchievement::where('claimed_at', '>=', $startDate)->count();

        $popularAchievements = UserAchievement::with('achievement:id,name,type')
            ->select('mining_achievement_id', DB::raw('COUNT(*) as unlock_count'))
            ->where('earned_at', '>=', $startDate)
            ->groupBy('mining_achievement_id')
            ->orderBy('unlock_count', 'desc')
            ->limit(10)
            ->get();

        $achievementTypes = UserAchievement::with('achievement:id,type')
            ->where('earned_at', '>=', $startDate)
            ->get()
            ->groupBy('achievement.type')
            ->map(function ($group) {
                return $group->count();
            });

        return [
            'total_achievements' => $totalAchievements,
            'unlocked_count' => $unlockedAchievements,
            'claimed_count' => $claimedAchievements,
            'claim_rate' => $unlockedAchievements > 0 ? round(($claimedAchievements / $unlockedAchievements) * 100, 2) : 0,
            'popular_achievements' => $popularAchievements,
            'achievement_types' => $achievementTypes
        ];
    }

    /**
     * @param $startDate
     * @return array
     */
    private function getCompetitionAnalytics($startDate): array
    {
        $totalCompetitions = MiningCompetition::where('created_at', '>=', $startDate)->count();
        $activeCompetitions = MiningCompetition::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->count();

        $totalPrizePool = MiningCompetition::where('created_at', '>=', $startDate)
            ->sum('prize_pool');

        $participantStats = DB::table('competition_participants')
            ->join('mining_competitions', 'competition_participants.mining_competition_id', '=', 'mining_competitions.id')
            ->where('mining_competitions.created_at', '>=', $startDate)
            ->select(
                DB::raw('COUNT(DISTINCT competition_participants.user_id) as unique_participants'),
                DB::raw('COUNT(*) as total_participations'),
                DB::raw('AVG(competition_participants.mined_amount) as avg_mined_amount')
            )
            ->first();

        return [
            'total_competitions' => $totalCompetitions,
            'active_competitions' => $activeCompetitions,
            'total_prize_pool' => $totalPrizePool,
            'unique_participants' => $participantStats->unique_participants ?? 0,
            'total_participations' => $participantStats->total_participations ?? 0,
            'avg_mined_amount' => round($participantStats->avg_mined_amount ?? 0, 8)
        ];
    }

    /**
     * @param $startDate
     * @return array
     */
    private function getPerformanceMetrics($startDate): array
    {
        $topPerformers = MiningSession::with('user:id,name')
            ->where('updated_at', '>=', $startDate)
            ->orderBy('total_mined', 'desc')
            ->limit(5)
            ->get();

        $levelProgression = MiningSession::select(
            DB::raw('DATE(updated_at) as date'),
            DB::raw('AVG(level) as avg_level'),
            DB::raw('MAX(level) as max_level')
        )
            ->where('updated_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $miningRateAnalysis = MiningSession::select(
            DB::raw('AVG(mining_rate) as avg_rate'),
            DB::raw('MIN(mining_rate) as min_rate'),
            DB::raw('MAX(mining_rate) as max_rate'),
            DB::raw('AVG(multiplier) as avg_multiplier')
        )
            ->where('updated_at', '>=', $startDate)
            ->first();

        return [
            'top_performers' => $topPerformers,
            'level_progression' => $levelProgression,
            'mining_rate_analysis' => $miningRateAnalysis
        ];
    }
}
