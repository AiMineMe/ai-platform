<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Trade;
use App\Models\MiningSession;
use App\Models\IcoPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Carbon\Carbon;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        $stats = Cache::remember('dashboard_stats', 300, function () {
            return $this->getStats();
        });

        $recentActivity = $this->getRecentActivity();
        $chartData = $this->getChartData();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'chart_data' => $chartData,
        ]);
    }

    private function getStats(): array
    {
        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        $userStats = DB::table('users')
            ->selectRaw("
                COUNT(CASE WHEN role != 'admin' THEN 1 END) as total_users,
                COUNT(CASE WHEN status = 1 THEN 1 END) as active_users,
                COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as new_today,
                COUNT(CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN 1 END) as new_week,
                COUNT(CASE WHEN email_verified_at IS NOT NULL THEN 1 END) as verified_users,
                COUNT(CASE WHEN kyc_status = 'pending' THEN 1 END) as pending_kyc
            ", [$today, $weekStart, $weekEnd])
            ->first();

        $financialStats = $this->getFinancialStats($today);
        $tradingStats = $this->getTradingStats($today);
        $investmentStats = $this->getInvestmentStats();
        $miningStats = $this->getMiningStats($today);

        return [
            'users' => [
                'total' => $userStats->total_users,
                'active' => $userStats->active_users,
                'new_today' => $userStats->new_today,
                'new_week' => $userStats->new_week,
                'verified' => $userStats->verified_users,
                'pending_kyc' => $userStats->pending_kyc,
            ],
            'financial' => $financialStats,
            'trading' => $tradingStats,
            'investment' => $investmentStats,
            'mining' => $miningStats,
        ];
    }

    private function getFinancialStats(string $today): array
    {
        $depositStats = DB::table('deposits')
            ->selectRaw("
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN DATE(created_at) = ? AND status = 'approved' THEN amount ELSE 0 END) as today_approved
            ", [$today])
            ->first();

        $withdrawalStats = DB::table('withdrawals')
            ->selectRaw("
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN DATE(created_at) = ? AND status = 'approved' THEN amount ELSE 0 END) as today_approved
            ", [$today])
            ->first();

        $totalBalance = DB::table('wallets')->sum('balance');

        return [
            'total_deposits' => $depositStats->total_approved ?? 0,
            'total_withdrawals' => $withdrawalStats->total_approved ?? 0,
            'pending_deposits' => $depositStats->total_pending ?? 0,
            'pending_withdrawals' => $withdrawalStats->total_pending ?? 0,
            'deposits_today' => $depositStats->today_approved ?? 0,
            'withdrawals_today' => $withdrawalStats->today_approved ?? 0,
            'total_balance' => $totalBalance,
        ];
    }

    private function getTradingStats(string $today): array
    {
        $stats = DB::table('trades')
            ->selectRaw("
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_trades,
                COUNT(*) as total_trades,
                SUM(CASE WHEN DATE(created_at) = ? THEN amount ELSE 0 END) as daily_volume,
                SUM(amount) as total_volume,
                COUNT(CASE WHEN status = 'won' THEN 1 END) as won_trades,
                COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_trades
            ", [$today])
            ->first();

        return [
            'active_trades' => $stats->active_trades ?? 0,
            'total_trades' => $stats->total_trades ?? 0,
            'daily_volume' => $stats->daily_volume ?? 0,
            'total_volume' => $stats->total_volume ?? 0,
            'won_trades' => $stats->won_trades ?? 0,
            'lost_trades' => $stats->lost_trades ?? 0,
        ];
    }

    private function getInvestmentStats(): array
    {
        $stats = DB::table('ico_purchases')
            ->selectRaw("
                SUM(CASE WHEN status = 'completed' THEN amount_usd ELSE 0 END) as ico_sales,
                SUM(CASE WHEN status = 'pending' THEN amount_usd ELSE 0 END) as pending_investments,
                SUM(CASE WHEN status = 'completed' THEN tokens_purchased ELSE 0 END) as total_tokens_sold,
                COUNT(DISTINCT CASE WHEN status = 'completed' THEN user_id END) as total_investors
            ")
            ->first();

        return [
            'ico_sales' => $stats->ico_sales ?? 0,
            'pending_investments' => $stats->pending_investments ?? 0,
            'total_tokens_sold' => $stats->total_tokens_sold ?? 0,
            'total_investors' => $stats->total_investors ?? 0,
        ];
    }

    private function getMiningStats(string $today): array
    {
        $stats = DB::table('mining_sessions')
            ->selectRaw("
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_sessions,
                SUM(total_mined) as total_mined,
                COUNT(DISTINCT user_id) as total_miners,
                SUM(CASE WHEN DATE(session_started_at) = ? THEN total_mined ELSE 0 END) as mining_today
            ", [$today])
            ->first();

        return [
            'active_sessions' => $stats->active_sessions ?? 0,
            'total_mined' => $stats->total_mined ?? 0,
            'total_miners' => $stats->total_miners ?? 0,
            'mining_today' => $stats->mining_today ?? 0,
        ];
    }

    private function getRecentActivity(): array
    {
        return [
            'users' => User::select('id', 'name', 'email', 'avatar', 'created_at', 'status', 'kyc_status', 'last_login_at')
                ->latest()
                ->take(8)
                ->get(),

            'deposits' => Deposit::with('user:id,name,email,avatar')
                ->select('id', 'user_id', 'amount', 'currency', 'status', 'created_at', 'trx')
                ->latest()
                ->take(8)
                ->get(),

            'withdrawals' => Withdrawal::with('user:id,name,email,avatar')
                ->select('id', 'user_id', 'amount', 'currency', 'status', 'created_at', 'trx')
                ->latest()
                ->take(8)
                ->get(),

            'trades' => Trade::with('user:id,name,email,avatar')
                ->select('id', 'user_id', 'symbol', 'amount', 'direction', 'status', 'profit_loss', 'created_at')
                ->latest()
                ->take(8)
                ->get(),
        ];
    }

    private function getChartData(): array
    {
        return [
            'user_growth' => $this->getUserGrowthDataOptimized(),
            'revenue' => $this->getRevenueDataOptimized(),
            'trading' => $this->getTradingDataOptimized(),
        ];
    }

    private function getUserGrowthDataOptimized(): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays(29);

        $data = DB::table('users')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->toDateString();
            $result[] = [
                'date' => $dateStr,
                'count' => $data->get($dateStr)->count ?? 0
            ];
        }

        return $result;
    }

    private function getRevenueDataOptimized(): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays(29);
        $deposits = DB::table('deposits')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as amount')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        $result = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->toDateString();
            $result[] = [
                'date' => $dateStr,
                'deposits' => $deposits->get($dateStr)->amount ?? 0
            ];
        }

        return $result;
    }

    private function getTradingDataOptimized(): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays(29);
        $trades = DB::table('trades')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as volume')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        $result = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->toDateString();
            $result[] = [
                'date' => $dateStr,
                'volume' => $trades->get($dateStr)->volume ?? 0
            ];
        }

        return $result;
    }

    public function profile(): Response
    {
        $user = Auth::user();
        return Inertia::render('Admin/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'last_login_at' => $user->last_login_at,
                'role' => $user->role,
                'kyc_status' => $user->kyc_status,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar ? asset('assets/files/'.$user->avatar) : null,
            ]
        ]);
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();
            DB::transaction(function () use ($user, $validated) {
                $user->update($validated);
            });

            return back()->with('success', 'Profile updated successfully!');
        } catch (ValidationException $e) {
            return back()->withErrors($e->validator);
        }
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $validator = Validator::make($request->all(), [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', Password::defaults(), 'confirmed'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();
            DB::transaction(function () use ($user, $validated) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                    'password_updated_at' => now(),
                ]);

                Log::info('Password updated', ['user_id' => $user->id]);
            });

            return back()->with('success', 'Password updated successfully!');

        } catch (ValidationException $e) {
            return back()->withErrors($e->validator);
        }
    }
}
