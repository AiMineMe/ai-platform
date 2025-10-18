<?php

namespace App\Http\Controllers\User;

use App\Enums\Wallet\Type;
use App\Http\Controllers\Controller;
use App\Models\AdminRevenue;
use App\Models\MiningSession;
use App\Models\MiningAchievement;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\MiningCompetition;
use App\Models\CompetitionParticipant;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;
use Inertia\Response;

class MiningController extends Controller
{
    private const MAX_MINING_RATE = 0.001;
    private const MAX_SESSION_HOURS = 24;
    private const MAX_CLAIMABLE_AMOUNT = 1000;
    private const MAX_MINING_MULTIPLIER = 10.0;
    private const RATE_LIMIT_CLAIMS = 10;
    private const RATE_LIMIT_MINING_START = 5;

    /**
     * @return Response
     */
    public function index(): Response
    {
        $user = auth()->user();
        $miningSession = MiningSession::with('user')
            ->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'session_type' => 'standard',
                    'mining_rate' => 0.00001,
                    'total_mined' => 0,
                    'current_balance' => 0,
                    'level' => 1,
                    'experience_points' => 0,
                    'streak_days' => 0,
                    'multiplier' => 1.0,
                    'is_active' => false,
                    'last_update_at' => now()
                ]
            );

        if ($miningSession->is_active) {
            $this->updateMiningProgress($miningSession);
        }

        $this->updateMiningStreak($miningSession);
        $achievements = UserAchievement::with(['achievement' => function($query) {
            $query->select('id', 'name', 'description', 'icon', 'reward_amount', 'reward_type');
        }])
            ->where('user_id', $user->id)
            ->orderBy('earned_at', 'desc')
            ->limit(20)
            ->get();

        $availableAchievements = $this->getAvailableAchievements($user->id, $miningSession);
        $activeCompetitions = Cache::remember("active_competitions_{$user->id}", 300, function () use ($user) {
            return MiningCompetition::where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->select('id', 'name', 'type', 'prize_pool', 'description', 'max_participants')
                ->withCount('participants')
                ->with(['participants' => function($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->select('mining_competition_id', 'user_id', 'rank', 'mined_amount');
                }])
                ->get()
                ->map(function ($competition) {
                    $userParticipation = $competition->participants->first();
                    return [
                        'id' => $competition->id,
                        'name' => $competition->name,
                        'type' => $competition->type,
                        'prize_pool' => $competition->prize_pool,
                        'description' => $competition->description,
                        'participants_count' => $competition->participants_count,
                        'max_participants' => $competition->max_participants,
                        'is_full' => $competition->max_participants && $competition->participants_count >= $competition->max_participants,
                        'user_joined' => (bool)$userParticipation,
                        'user_rank' => $userParticipation ? $userParticipation->rank : null,
                        'user_mined_amount' => $userParticipation ? $userParticipation->mined_amount : 0
                    ];
                });
        });

        $leaderboard = $this->getLeaderboard();
        $userRank = $this->getUserRank($user->id);
        $currentSubscription = $user->subscriptionPlan ?? null;
        $miningFeeRate =  $user->getMiningFeeRate() ?? 10.0;
        $subscriptionMultiplier = $user->getMiningMultiplier() ?? 1.0;

        return Inertia::render('User/Mining/Dashboard', [
            'miningSession' => $miningSession->fresh(),
            'achievements' => $achievements,
            'availableAchievements' => $availableAchievements,
            'activeCompetitions' => $activeCompetitions,
            'leaderboard' => $leaderboard,
            'userRank' => $userRank,
            'currentSubscription' => $currentSubscription,
            'miningFeeRate' => $miningFeeRate,
            'subscriptionMultiplier' => $subscriptionMultiplier,
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function startMining(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $rateLimitKey = "mining_start_{$user->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MINING_START)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()->withErrors(['error' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        try {
            DB::beginTransaction();
            $miningSession = MiningSession::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$miningSession) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Mining session not found']);
            }

            if ($miningSession->is_active) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Mining session is already active']);
            }

            if ($miningSession->session_ends_at && $miningSession->session_ends_at->gt(now())) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Previous session still active']);
            }

            $this->updateMiningProgress($miningSession);
            $sessionDuration = min(self::MAX_SESSION_HOURS, 24);
            $miningRate = min($miningSession->mining_rate, self::MAX_MINING_RATE);

            $miningSession->update([
                'is_active' => true,
                'session_started_at' => now(),
                'session_ends_at' => now()->addHours($sessionDuration),
                'last_update_at' => now(),
                'mining_rate' => $miningRate
            ]);

            $this->updateMiningStreak($miningSession);
            RateLimiter::hit($rateLimitKey, 3600);

            DB::commit();
            return redirect()->back()->with('success', 'Mining started successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Start mining error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->withErrors(['error' => 'Failed to start mining. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function claimTokens(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $rateLimitKey = "mining_claim_{$user->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_CLAIMS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()->withErrors(['error' => "Too many claim attempts. Try again in {$seconds} seconds."]);
        }

        try {
            DB::beginTransaction();
            $miningSession = MiningSession::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$miningSession) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Mining session not found']);
            }

            $this->updateMiningProgress($miningSession);
            $miningSession->refresh();

            $grossAmount = $miningSession->current_balance;
            if ($grossAmount <= 1) {
                $currency = Setting::get('currency_symbol', '$');
                DB::rollBack();
                return redirect()->back()->withErrors([
                    'error' => "You need at least {$currency}1 to claim tokens."
                ]);

            }


            if ($grossAmount > self::MAX_CLAIMABLE_AMOUNT) {
                DB::rollBack();
                Log::warning('Excessive claim attempt', [
                    'user_id' => $user->id,
                    'amount' => $grossAmount,
                    'max_allowed' => self::MAX_CLAIMABLE_AMOUNT
                ]);
                return redirect()->back()->withErrors(['error' => 'Claim amount exceeds maximum limit']);
            }

            $feeRate = method_exists($user, 'getMiningFeeRate') ? $user->getMiningFeeRate() : 10.0;
            $feeAmount = ($grossAmount * $feeRate) / 100;
            $netAmount = $grossAmount - $feeAmount;

            if ($netAmount <= 0) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Net amount after fees is insufficient']);
            }

            $this->addBalanceToWallet($user, $netAmount);
            if ($feeAmount > 0) {
                $this->recordAdminRevenue($user, $feeAmount, $feeRate, $grossAmount);
                $this->recordFeeTransaction($user, $feeAmount, $feeRate);
            }

            $miningSession->update([
                'current_balance' => 0,
                'last_claim_at' => now(),
                'last_update_at' => now()
            ]);

            $xpToAdd = max(1, min(1000, (int)($netAmount * 100)));
            $this->addExperience($miningSession, $xpToAdd);

            if ($this->shouldCheckAchievements($user->id)) {
                $this->checkAchievements($user->id, $miningSession);
            }

            // Hit the rate limiter
            RateLimiter::hit($rateLimitKey, 3600);

            DB::commit();

            $message = "Claimed " . number_format($netAmount, 8) . " tokens!";
            if ($feeAmount > 0) {
                $message .= " (Fee: {$feeRate}%)";
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Claim tokens error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->withErrors(['error' => 'Failed to claim tokens. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param $achievementId
     * @return RedirectResponse
     */
    public function claimAchievement(Request $request, $achievementId): RedirectResponse
    {
        $user = auth()->user();
        if (!is_numeric($achievementId) || $achievementId <= 0) {
            return redirect()->back()->withErrors(['error' => 'Invalid achievement ID']);
        }

        try {
            DB::beginTransaction();

            $userAchievement = UserAchievement::with('achievement')
                ->where('user_id', $user->id)
                ->where('mining_achievement_id', $achievementId)
                ->where('claimed', false)
                ->lockForUpdate()
                ->first();

            if (!$userAchievement) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Achievement not found or already claimed']);
            }

            $achievement = $userAchievement->achievement;
            if (!$achievement || !$achievement->is_active) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Achievement is not valid or inactive']);
            }

            $userAchievement->update([
                'claimed' => true,
                'claimed_at' => now()
            ]);

            switch ($achievement->reward_type) {
                case 'tokens':
                    if ($achievement->reward_amount > 0 && $achievement->reward_amount <= 1000) {
                        $this->addBalanceToWallet($user, $achievement->reward_amount);
                    }
                    break;

                case 'multiplier':
                    $miningSession = MiningSession::where('user_id', $user->id)->lockForUpdate()->first();
                    if ($miningSession && $achievement->reward_amount > 0) {
                        $newMultiplier = min(self::MAX_MINING_MULTIPLIER, $miningSession->multiplier + $achievement->reward_amount);
                        $miningSession->update(['multiplier' => $newMultiplier]);
                    }
                    break;

                case 'boost':
                    $miningSession = MiningSession::where('user_id', $user->id)->lockForUpdate()->first();
                    if ($miningSession && $achievement->reward_amount > 0 && $achievement->reward_amount <= 24) {
                        $boosts = $miningSession->boosts ?: [];
                        $boosts[] = [
                            'type' => 'rate_boost',
                            'multiplier' => min(2.0, $achievement->reward_amount),
                            'expires_at' => now()->addHours(min(24, $achievement->reward_amount))->toISOString()
                        ];
                        $miningSession->update(['boosts' => $boosts]);
                    }
                    break;
            }

            DB::commit();
            return redirect()->back()->with('success', "Achievement '{$achievement->name}' claimed successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Claim achievement error', [
                'user_id' => $user->id,
                'achievement_id' => $achievementId,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->withErrors(['error' => 'Failed to claim achievement. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param $competitionId
     * @return RedirectResponse
     */
    public function joinCompetition(Request $request, $competitionId): RedirectResponse
    {
        $user = auth()->user();
        if (!is_numeric($competitionId) || $competitionId <= 0) {
            return redirect()->back()->withErrors(['error' => 'Invalid competition ID']);
        }

        try {
            DB::beginTransaction();

            $competition = MiningCompetition::where('id', $competitionId)
                ->lockForUpdate()
                ->first();

            if (!$competition) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Competition not found']);
            }

            if (!$competition->isActive()) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Competition is not active']);
            }

            if (!$competition->canJoin()) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Competition is not available for joining']);
            }

            $existingParticipation = CompetitionParticipant::where('mining_competition_id', $competitionId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existingParticipation) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'You have already joined this competition']);
            }

            if ($competition->entry_fee > 0) {
                $this->processEntryFee($user, $competition);
            }

            CompetitionParticipant::create([
                'mining_competition_id' => $competitionId,
                'user_id' => $user->id,
                'entry_fee_paid' => $competition->entry_fee,
                'joined_at' => now(),
                'mined_amount' => 0
            ]);

            Cache::forget("active_competitions_{$user->id}");

            Cache::flush();

            DB::commit();
            $message = "Successfully joined '{$competition->name}'!";
            if ($competition->entry_fee > 0) {
                $currencySymbol = Setting::get('currency_symbol', '$');
                $message .= " Entry fee:" . $currencySymbol. number_format($competition->entry_fee, 2);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Join competition error', [
                'user_id' => $user->id,
                'competition_id' => $competitionId,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function stopMining(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $rateLimitKey = "mining_stop_{$user->id}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MINING_START)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()->withErrors(['error' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        try {
            DB::beginTransaction();
            $miningSession = MiningSession::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$miningSession) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Mining session not found']);
            }

            if (!$miningSession->is_active) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Mining session is not active']);
            }

            $this->updateMiningProgress($miningSession);
            $miningSession->update([
                'is_active' => false,
                'session_ends_at' => now(),
                'last_update_at' => now()
            ]);

            RateLimiter::hit($rateLimitKey, 3600);

            DB::commit();
            return redirect()->back()->with('success', 'Mining stopped successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stop mining error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->withErrors(['error' => 'Failed to stop mining. Please try again.']);
        }
    }

    /**
     * @param MiningSession $miningSession
     * @return void
     */
    private function updateMiningProgress(MiningSession $miningSession): void
    {
        if (!$miningSession->is_active || !$miningSession->session_started_at) {
            return;
        }

        try {
            $user = $miningSession->user;
            $now = now();
            $lastUpdate = $miningSession->last_update_at ?: $miningSession->session_started_at;
            if ($miningSession->session_ends_at && $now->gt($miningSession->session_ends_at)) {
                $miningSession->update(['is_active' => false]);
                return;
            }

            $secondsElapsed = $lastUpdate->diffInSeconds($now, false);
            if ($secondsElapsed <= 0) return;
            if ($secondsElapsed > 3600) $secondsElapsed = 3600;

            $levelBonus = min(($miningSession->level - 1) * 0.000001, 0.001);
            $baseRate = min($miningSession->mining_rate + $levelBonus, self::MAX_MINING_RATE);

            $subscriptionMultiplier = method_exists($user, 'getMiningMultiplier') ?
                min($user->getMiningMultiplier(), 5.0) : 1.0;

            $sessionMultiplier = min($miningSession->multiplier, self::MAX_MINING_MULTIPLIER);
            $effectiveRate = $baseRate * $sessionMultiplier * $subscriptionMultiplier;
            $effectiveRate = $this->applyBoosts($miningSession, $effectiveRate);
            $effectiveRate = min($effectiveRate, self::MAX_MINING_RATE * 10);

            $minedAmount = round($effectiveRate * $secondsElapsed, 8);
            if ($minedAmount > 0 && $minedAmount <= self::MAX_CLAIMABLE_AMOUNT) {
                $miningSession->increment('current_balance', $minedAmount);
                $miningSession->increment('total_mined', $minedAmount);
            }

            $miningSession->update(['last_update_at' => $now]);

        } catch (\Exception $e) {
            Log::error('Mining progress update error', [
                'session_id' => $miningSession->id,
                'user_id' => $miningSession->user_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param MiningSession $miningSession
     * @param float $baseRate
     * @return float
     */
    private function applyBoosts(MiningSession $miningSession, float $baseRate): float
    {
        $boosts = $miningSession->boosts ?: [];
        $effectiveRate = $baseRate;
        $activeBoosts = [];

        foreach ($boosts as $boost) {
            if (!is_array($boost) || !isset($boost['expires_at'], $boost['type'])) {
                continue;
            }

            try {
                if (now()->lt(Carbon::parse($boost['expires_at']))) {
                    if ($boost['type'] === 'rate_boost' && isset($boost['multiplier'])) {
                        $multiplier = min(5.0, max(1.0, (float)$boost['multiplier']));
                        $effectiveRate *= $multiplier;
                    }
                    $activeBoosts[] = $boost;
                }
            } catch (\Exception $e) {
                Log::warning('Invalid boost data', ['boost' => $boost, 'error' => $e->getMessage()]);
            }
        }

        $effectiveRate = min($effectiveRate, self::MAX_MINING_RATE * 20);
        if (count($activeBoosts) !== count($boosts)) {
            $miningSession->update(['boosts' => $activeBoosts]);
        }

        return $effectiveRate;
    }

    /**
     * @param MiningSession $miningSession
     * @param int $xp
     * @return void
     */
    private function addExperience(MiningSession $miningSession, int $xp): void
    {
        if ($xp <= 0 || $xp > 1000) return;

        $miningSession->increment('experience_points', $xp);
        $currentLevel = $miningSession->level;
        $currentXp = $miningSession->experience_points;
        $requiredXp = $currentLevel * 100;
        $levelUps = 0;

        while ($currentXp >= $requiredXp && $levelUps < 5 && $currentLevel < 1000) {
            $miningSession->increment('level');
            $currentLevel = $miningSession->level;
            $requiredXp = $currentLevel * 100;
            $miningSession->increment('mining_rate', min(0.000001, 0.0001));
            $levelUps++;
        }
    }

    /**
     * @param $user
     * @param $amount
     * @return void
     * @throws Exception
     */
    private function addBalanceToWallet($user, $amount): void
    {
        if ($amount <= 0 || $amount > self::MAX_CLAIMABLE_AMOUNT) {
            throw new Exception('Invalid amount: must be between 0 and ' . self::MAX_CLAIMABLE_AMOUNT);
        }

        try {
            $wallet = $user->wallets()->where('type', Type::MAIN->value)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new Exception('Main wallet not found');
            }

            $previousBalance = (float) $wallet->balance;
            $newBalance = round($previousBalance + $amount, 8);
            if ($newBalance < 0 || $newBalance > 1000000) {
                throw new Exception('Invalid resulting balance');
            }

            $wallet->update([
                'balance' => $newBalance,
                'last_activity' => now()
            ]);

            Transaction::create([
                'transaction_id' => 'MINING_' . Str::random(12),
                'user_id' => $user->id,
                'type' => 'credit',
                'wallet_type' => 'main_wallet',
                'amount' => $amount,
                'post_balance' => $newBalance,
                'status' => 'completed',
                'details' => "Mining reward credited to main wallet"
            ]);

        } catch (Exception $e) {
            Log::error('Mining wallet addition error', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @param $user
     * @param $competition
     * @return void
     * @throws Exception
     */
    private function processEntryFee($user, $competition): void
    {
        $wallet = $user->wallets()->where('type', Type::MAIN->value)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            throw new \Exception('Wallet not found');
        }

        if ($wallet->balance < $competition->entry_fee) {
            $currencySymbol = Setting::get('currency_symbol', '$');
            throw new \Exception('Insufficient balance. Required: '.$currencySymbol.number_format($competition->entry_fee, 2));
        }

        $newBalance = $wallet->balance - $competition->entry_fee;
        if ($newBalance < 0) {
            throw new \Exception('Insufficient balance after deduction');
        }

        $wallet->update(['balance' => $newBalance]);

        $adminFee = ($competition->entry_fee * $competition->admin_fee_percentage) / 100;

        if ($adminFee > 0) {
            $this->recordAdminRevenue(
                $user,
                $adminFee,
                $competition->admin_fee_percentage,
                $competition->entry_fee,
                "Competition entry: {$competition->name}",
                [
                    'competition_id' => $competition->id,
                    'entry_fee' => $competition->entry_fee,
                    'admin_percentage' => $competition->admin_fee_percentage
                ]
            );
        }

        Transaction::create([
            'transaction_id' => 'COMP_' . Str::random(8),
            'user_id' => $user->id,
            'type' => 'debit',
            'wallet_type' => 'main_wallet',
            'amount' => $competition->entry_fee,
            'post_balance' => $newBalance,
            'status' => 'completed',
            'details' => "Competition entry: {$competition->name}"
        ]);
    }

    /**
     * @return mixed
     */
    private function getLeaderboard(): mixed
    {
        return Cache::remember('mining_leaderboard', 300, function () {
            return MiningSession::select('user_id', 'total_mined', 'level')
                ->with(['user' => function($query) {
                    $query->select('id', 'name');
                }])
                ->where('total_mined', '>', 0)
                ->orderBy('total_mined', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->user_id,
                        'name' => $session->user->name ?? 'Unknown',
                        'total_mined' => $session->total_mined,
                        'level' => $session->level
                    ];
                });
        });
    }

    /**
     * @param int $userId
     * @return int|null
     */
    private function getUserRank(int $userId): ?int
    {
        return Cache::remember("user_rank_{$userId}", 300, function () use ($userId) {
            $userSession = MiningSession::where('user_id', $userId)->first();
            if (!$userSession || $userSession->total_mined <= 0) {
                return null;
            }

            return MiningSession::where('total_mined', '>', $userSession->total_mined)->count() + 1;
        });
    }

    /**
     * @param int $userId
     * @param MiningSession $miningSession
     * @return mixed
     */
    private function getAvailableAchievements(int $userId, MiningSession $miningSession): mixed
    {
        $earnedAchievementIds = UserAchievement::where('user_id', $userId)
            ->pluck('mining_achievement_id')
            ->toArray();

        return MiningAchievement::where('is_active', true)
            ->whereNotIn('id', $earnedAchievementIds)
            ->orderBy('required_value')
            ->limit(5)
            ->get(['id', 'name', 'description', 'icon', 'type', 'condition', 'required_value', 'reward_amount', 'reward_type']);
    }

    /**
     * @param int $userId
     * @return bool
     */
    private function shouldCheckAchievements(int $userId): bool
    {
        $cacheKey = "achievement_check_{$userId}";
        return !Cache::has($cacheKey) || Cache::get($cacheKey) < now()->subMinutes(5);
    }

    /**
     * @param int $userId
     * @param MiningSession $miningSession
     * @return void
     */
    private function checkAchievements(int $userId, MiningSession $miningSession): void
    {
        try {
            Cache::put("achievement_check_{$userId}", now(), 300);

            $achievements = MiningAchievement::where('is_active', true)
                ->whereNotIn('id', function($query) use ($userId) {
                    $query->select('mining_achievement_id')
                        ->from('user_achievements')
                        ->where('user_id', $userId);
                })
                ->limit(20)
                ->get();

            foreach ($achievements as $achievement) {
                $shouldUnlock = false;

                switch ($achievement->type) {
                    case 'total':
                    case 'mining':
                        $shouldUnlock = $miningSession->total_mined >= $achievement->required_value;
                        break;
                    case 'streak':
                        $shouldUnlock = $miningSession->streak_days >= $achievement->required_value;
                        break;
                    case 'level':
                        $shouldUnlock = $miningSession->level >= $achievement->required_value;
                        break;
                }

                if ($shouldUnlock) {
                    UserAchievement::create([
                        'user_id' => $userId,
                        'mining_achievement_id' => $achievement->id,
                        'earned_at' => now(),
                        'claimed' => false
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Achievement check error', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param MiningSession $miningSession
     * @return void
     */
    private function updateMiningStreak(MiningSession $miningSession): void
    {
        try {
            $lastClaim = $miningSession->last_claim_at;
            if (!$lastClaim) {
                return;
            }

            $daysSinceLastClaim = now()->diffInDays($lastClaim);

            if ($daysSinceLastClaim === 1) {
                $newStreak = min($miningSession->streak_days + 1, 365);
                $miningSession->update(['streak_days' => $newStreak]);
            } elseif ($daysSinceLastClaim > 1) {
                $miningSession->update(['streak_days' => 1]);
            }
        } catch (\Exception $e) {
            Log::error('Mining streak update error', [
                'session_id' => $miningSession->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param $user
     * @param $feeAmount
     * @param $feeRate
     * @return void
     */
    private function recordFeeTransaction($user, $feeAmount, $feeRate): void
    {
        try {
            if ($feeAmount <= 0) return;

            Transaction::create([
                'transaction_id' => 'FEE_' . Str::random(8),
                'user_id' => $user->id,
                'type' => 'debit',
                'wallet_type' => 'main_wallet',
                'amount' => $feeAmount,
                'post_balance' => 0,
                'status' => 'completed',
                'details' => "Mining fee ({$feeRate}%)"
            ]);
        } catch (\Exception $e) {
            Log::warning('Fee transaction recording failed', [
                'user_id' => $user->id,
                'fee_amount' => $feeAmount,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param $user
     * @param $feeAmount
     * @param $feeRate
     * @param $grossAmount
     * @param string|null $description
     * @param array $metadata
     * @return void
     */
    private function recordAdminRevenue($user, $feeAmount, $feeRate, $grossAmount, string $description = null, array $metadata = []): void
    {
        try {
            if ($feeAmount <= 0) return;

            if (class_exists(AdminRevenue::class) && method_exists(AdminRevenue::class, 'recordRevenue')) {
                $defaultDescription = "Mining fee from {$user->name}";
                $defaultMetadata = [
                    'fee_rate' => $feeRate,
                    'gross_amount' => $grossAmount,
                    'plan' => method_exists($user, 'getCurrentPlanName') ? $user->getCurrentPlanName() : 'Free'
                ];

                AdminRevenue::recordRevenue(
                    'mining_fee',
                    $feeAmount,
                    $user->id,
                    $description ?: $defaultDescription,
                    array_merge($defaultMetadata, $metadata)
                );
            }
        } catch (\Exception $e) {
            Log::warning('Admin revenue recording failed', [
                'user_id' => $user->id,
                'fee_amount' => $feeAmount,
                'error' => $e->getMessage()
            ]);
        }
    }
}
