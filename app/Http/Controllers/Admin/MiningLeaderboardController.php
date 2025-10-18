<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MiningSession;
use App\Models\MiningCompetition;
use App\Models\CompetitionParticipant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MiningLeaderboardController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $timeframe = $request->get('timeframe', 'all_time');
        $competitionId = $request->get('competition_id');
        $globalLeaderboard = $this->getGlobalLeaderboard($timeframe);
        $competitionLeaderboard = null;
        if ($competitionId) {
            $competitionLeaderboard = $this->getCompetitionLeaderboard($competitionId);
        }

        $activeCompetitions = MiningCompetition::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->select('id', 'name', 'type')
            ->get();

        $topPerformers = $this->getTopPerformersStats();
        $levelDistribution = $this->getLevelDistribution();

        return Inertia::render('Admin/MiningLeaderboards/Index', [
            'globalLeaderboard' => $globalLeaderboard,
            'competitionLeaderboard' => $competitionLeaderboard,
            'activeCompetitions' => $activeCompetitions,
            'topPerformers' => $topPerformers,
            'levelDistribution' => $levelDistribution,
            'filters' => [
                'timeframe' => $timeframe,
                'competition_id' => $competitionId
            ]
        ]);
    }

    /**
     * @param $timeframe
     * @return mixed
     */
    private function getGlobalLeaderboard($timeframe)
    {
        $cacheKey = "global_leaderboard_{$timeframe}";
        return Cache::remember($cacheKey, 300, function () use ($timeframe) {
            $query = MiningSession::with(['user:id,name,email'])
                ->where('total_mined', '>', 0);

            switch ($timeframe) {
                case 'today':
                    $query->whereDate('updated_at', today());
                    break;
                case 'week':
                    $query->where('updated_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('updated_at', '>=', now()->subMonth());
                    break;
                case 'all_time':
                default:
                    break;
            }

            return $query->orderBy('total_mined', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($session, $index) {
                    return [
                        'rank' => $index + 1,
                        'user' => $session->user,
                        'total_mined' => $session->total_mined,
                        'level' => $session->level,
                        'streak_days' => $session->streak_days,
                        'is_active' => $session->is_active,
                        'last_activity' => $session->updated_at
                    ];
                });
        });
    }

    /**
     * @param $competitionId
     * @return Collection|\Illuminate\Support\Collection
     */
    private function getCompetitionLeaderboard($competitionId): Collection|\Illuminate\Support\Collection
    {
        return CompetitionParticipant::with(['user:id,name,email', 'competition:id,name'])
            ->where('mining_competition_id', $competitionId)
            ->orderBy('mined_amount', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($participant, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $participant->user,
                    'mined_amount' => $participant->mined_amount,
                    'prize_won' => $participant->prize_won,
                    'prize_claimed' => $participant->prize_claimed
                ];
            });
    }

    /**
     * @return array
     */
    private function getTopPerformersStats(): array
    {
        return [
            'highest_miner' => MiningSession::with('user:id,name')
                ->orderBy('total_mined', 'desc')
                ->first(),
            'highest_level' => MiningSession::with('user:id,name')
                ->orderBy('level', 'desc')
                ->orderBy('experience_points', 'desc')
                ->first(),
            'longest_streak' => MiningSession::with('user:id,name')
                ->orderBy('streak_days', 'desc')
                ->first(),
            'most_active' => MiningSession::with('user:id,name')
                ->where('is_active', true)
                ->orderBy('updated_at', 'desc')
                ->first()
        ];
    }

    /**
     * @return mixed
     */
    private function getLevelDistribution(): mixed
    {
        return MiningSession::select(
            DB::raw('CASE
                    WHEN level BETWEEN 1 AND 5 THEN "1-5"
                    WHEN level BETWEEN 6 AND 10 THEN "6-10"
                    WHEN level BETWEEN 11 AND 20 THEN "11-20"
                    WHEN level BETWEEN 21 AND 50 THEN "21-50"
                    ELSE "50+"
                END as level_range'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('level_range')
            ->orderBy('level_range')
            ->get();
    }
}
