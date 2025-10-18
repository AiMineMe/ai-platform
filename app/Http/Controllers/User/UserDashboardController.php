<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\MiningSession;
use App\Models\Trade;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class UserDashboardController extends Controller
{
    /**
     * @return Response
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(): Response
    {
        try {
            $user = Auth::user();
            $period = request()->get('period', '7d');
            $days = $this->getPeriodDays($period);

            $key = 'dashboard:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 60)) {
                $seconds = RateLimiter::availableIn($key);
                return $this->getErrorDashboard("Too many requests. Please try again in {$seconds} seconds.");
            }

            $dashboardData = $this->getDashboardData($user, $days);
            RateLimiter::hit($key, 60);

            return Inertia::render('User/Dashboard', array_merge($dashboardData, [
                'currentPeriod' => $period,
            ]));

        } catch (\Exception $e) {
            Log::error('Dashboard Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->getErrorDashboard('Unable to load dashboard data. Please try again.');
        }
    }

    /**
     * Get all dashboard data with optimized queries
     */
    private function getDashboardData($user, int $days): array
    {
        // 1. Get wallet balances with single query
        $wallets = $user->wallets()
            ->whereIn('type', ['main', 'trade'])
            ->pluck('balance', 'type');

        $mainBalance = (float) ($wallets['main'] ?? 0);
        $tradeBalance = (float) ($wallets['trade'] ?? 0);

        // 2. Get comprehensive trade statistics with single query
        $tradeStats = $this->getOptimizedTradeStats($user, $days);

        // 3. Build portfolio stats
        $portfolioStats = [
            'totalValue' => $mainBalance + $tradeBalance,
            'mainWalletBalance' => $mainBalance,
            'tradeWalletBalance' => $tradeBalance,
            'availableBalance' => $mainBalance,
            'activeTrades' => $tradeStats['activeTrades'],
            'todayPnL' => $tradeStats['todayPnL'],
            'todayPnLPercent' => $tradeStats['todayPnLPercent'],
            'portfolioChange' => $tradeStats['portfolioChange'],
        ];

        // 4. Build trading stats
        $tradingStats = [
            'totalTrades' => $tradeStats['totalTrades'],
            'winningTrades' => $tradeStats['winningTrades'],
            'losingTrades' => $tradeStats['losingTrades'],
            'totalProfit' => $tradeStats['totalProfit'],
            'totalLoss' => $tradeStats['totalLoss'],
            'winRate' => $tradeStats['winRate'],
        ];

        // 5. Get other dashboard components with optimized queries
        $portfolioChartData = $this->getOptimizedPortfolioChartData($user, $days);
        $tradingActivityData = $this->getTradingActivityData($user, $days);
        $recentActivities = $this->getOptimizedRecentActivities($user);
        $marketOverview = $this->getCachedMarketOverview();
        $quickStats = $this->getOptimizedQuickStats($user, $tradeStats['monthlyProfit']);

        return [
            'portfolioStats' => $portfolioStats,
            'tradingStats' => $tradingStats,
            'quickStats' => $quickStats,
            'recentActivities' => $recentActivities,
            'portfolioChartData' => $portfolioChartData,
            'tradingActivityData' => $tradingActivityData,
            'marketOverview' => $marketOverview,
        ];
    }

    /**
     * Get optimized trade statistics with single comprehensive query
     */
    private function getOptimizedTradeStats($user, int $days): array
    {
        // Single query to get all trade statistics at once
        $stats = DB::table('trades')
            ->where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total_trades,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_trades,
                COUNT(CASE WHEN status = "won" THEN 1 END) as winning_trades,
                COUNT(CASE WHEN status = "lost" THEN 1 END) as losing_trades,
                COALESCE(SUM(CASE WHEN status = "won" THEN profit_loss END), 0) as total_profit,
                COALESCE(SUM(CASE WHEN status = "lost" THEN profit_loss END), 0) as total_loss,
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status IN ("won", "lost") THEN profit_loss END), 0) as today_pnl,
                COALESCE(SUM(CASE WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status IN ("won", "lost") THEN profit_loss END), 0) as yesterday_pnl,
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND status IN ("won", "lost") THEN profit_loss END), 0) as monthly_profit
            ')
            ->first();

        $totalValue = ($user->wallets()->where('type', 'main')->value('balance') ?? 0) +
            ($user->wallets()->where('type', 'trade')->value('balance') ?? 0);

        $todayPnLPercent = $totalValue > 0 ? ($stats->today_pnl / $totalValue) * 100 : 0;
        $portfolioChange = $stats->yesterday_pnl != 0 ?
            (($stats->today_pnl - $stats->yesterday_pnl) / abs($stats->yesterday_pnl)) * 100 : 0;

        $winRate = $stats->total_trades > 0 ?
            round(($stats->winning_trades / $stats->total_trades) * 100, 2) : 0;

        return [
            'activeTrades' => (int) $stats->active_trades,
            'totalTrades' => (int) $stats->total_trades,
            'winningTrades' => (int) $stats->winning_trades,
            'losingTrades' => (int) $stats->losing_trades,
            'totalProfit' => (float) $stats->total_profit,
            'totalLoss' => (float) $stats->total_loss,
            'todayPnL' => (float) $stats->today_pnl,
            'todayPnLPercent' => round($todayPnLPercent, 2),
            'portfolioChange' => round($portfolioChange, 2),
            'winRate' => $winRate,
            'monthlyProfit' => (float) $stats->monthly_profit,
        ];
    }

    /**
     * Get trading activity data with optimized query
     */
    private function getTradingActivityData($user, int $days): array
    {
        return Trade::where('user_id', $user->id)
            ->selectRaw('symbol, COUNT(*) as trade_count, SUM(CASE WHEN status = "won" THEN 1 ELSE 0 END) as wins')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('status', ['won', 'lost'])
            ->groupBy('symbol')
            ->orderByDesc('trade_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'label' => e($item->symbol),
                    'trades' => (int) $item->trade_count,
                    'wins' => (int) $item->wins,
                    'winRate' => $item->trade_count > 0 ? round(($item->wins / $item->trade_count) * 100, 1) : 0
                ];
            })
            ->toArray();
    }

    /**
     * Get optimized recent activities with eager loading to fix N+1 queries
     */
    private function getOptimizedRecentActivities($user): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Fix N+1: Eager load paymentGateway relationship
        $recentDeposits = Deposit::where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->with('paymentGateway:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'final_amount', 'status', 'created_at', 'payment_gateway_id'])
            ->map(function ($deposit) {
                return [
                    'id' => 'deposit_' . $deposit->id,
                    'type' => 'deposit',
                    'description' => 'Deposit via ' . ucfirst($deposit->paymentGateway->name ?? 'Unknown'),
                    'amount' => (float) $deposit->final_amount,
                    'status' => ucfirst($deposit->status),
                    'timestamp' => $deposit->created_at->toISOString(),
                ];
            });

        // Fix N+1: Eager load withdrawalGateway relationship
        $recentWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->with('withdrawalGateway:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'final_amount', 'status', 'created_at', 'withdrawal_gateway_id'])
            ->map(function ($withdrawal) {
                return [
                    'id' => 'withdrawal_' . $withdrawal->id,
                    'type' => 'withdrawal',
                    'description' => 'Withdrawal to ' . ucfirst($withdrawal->withdrawalGateway->name ?? 'Unknown'),
                    'amount' => -(float) $withdrawal->final_amount,
                    'status' => ucfirst($withdrawal->status),
                    'timestamp' => $withdrawal->created_at->toISOString(),
                ];
            });

        $recentTrades = Trade::where('user_id', $user->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereIn('status', ['won', 'lost', 'active'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'symbol', 'direction', 'amount', 'profit_loss', 'status', 'created_at'])
            ->map(function ($trade) {
                return [
                    'id' => 'trade_' . $trade->id,
                    'type' => 'trade',
                    'description' => e($trade->symbol) . ' ' . e(ucfirst($trade->direction ?? 'Unknown')) . ' - $' . number_format($trade->amount, 2),
                    'amount' => $trade->status === 'active' ? 0 : (float) $trade->profit_loss,
                    'status' => e(ucfirst($trade->status)),
                    'timestamp' => $trade->created_at->toISOString(),
                    'symbol' => e($trade->symbol),
                    'direction' => e($trade->direction ?? 'Unknown')
                ];
            });

        return $recentDeposits
            ->concat($recentWithdrawals)
            ->concat($recentTrades)
            ->sortByDesc('timestamp')
            ->take(8)
            ->values()
            ->toArray();
    }

    /**
     * Get cached market overview
     */
    private function getCachedMarketOverview(): array
    {
        return Cache::remember('market_overview', 300, function () {
            return Currency::select(['symbol', 'name', 'current_price', 'image_url', 'change_percent'])
                ->whereNotNull('current_price')
                ->where('current_price', '>', 0)
                ->orderBy('current_price', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($currency) {
                    return [
                        'symbol' => $currency->symbol,
                        'name' => $currency->name,
                        'image_url' => $currency->image_url,
                        'price' => (float) $currency->current_price,
                        'change' => (float) ($currency->change_percent ?? 0),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get optimized quick stats with fewer queries
     */
    private function getOptimizedQuickStats($user, float $monthlyProfit): array
    {
        // Single query for both pending deposits and withdrawals
        $pendingCounts = DB::table('deposits')
            ->selectRaw('
                COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_deposits,
                (SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = "pending") as pending_withdrawals
            ', [$user->id])
            ->where('user_id', $user->id)
            ->first();

        $miningBalance = MiningSession::where('user_id', $user->id)
            ->value('current_balance') ?? 0;

        return [
            'pendingDeposits' => (int) $pendingCounts->pending_deposits,
            'pendingWithdrawals' => (int) $pendingCounts->pending_withdrawals,
            'monthlyProfit' => $monthlyProfit,
            'miningBalance' => (float) $miningBalance
        ];
    }

    /**
     * Get optimized portfolio chart data with single grouped query
     */
    private function getOptimizedPortfolioChartData($user, int $days): array
    {
        // Get current balances (fixed the typo: 'tradey' -> 'trade')
        $wallets = $user->wallets()->whereIn('type', ['main', 'trade'])->pluck('balance', 'type');
        $currentBalance = ($wallets['main'] ?? 0) + ($wallets['trade'] ?? 0);

        // Single grouped query to get daily P&L data
        $dailyPnL = Trade::where('user_id', $user->id)
            ->selectRaw('DATE(created_at) as date, SUM(profit_loss) as pnl')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereIn('status', ['won', 'lost'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $portfolioChartData = [];
        $totalPnLPeriod = $dailyPnL->sum('pnl');
        $runningBalance = $currentBalance - $totalPnLPeriod;

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();
            $dayPnL = (float) ($dailyPnL->get($dateStr)->pnl ?? 0);

            $runningBalance += $dayPnL;
            $label = $days <= 7 ? $date->format('M d') : $date->format('m/d');

            $portfolioChartData[] = [
                'label' => e($label),
                'value' => round($runningBalance, 2),
                'pnl' => round($dayPnL, 2)
            ];
        }

        return $portfolioChartData;
    }

    /**
     * @param string $period
     * @return int
     */
    private function getPeriodDays(string $period): int
    {
        return match($period) {
            '7d' => 7,
            '1m' => 30,
            '3m' => 90,
            default => 7
        };
    }

    /**
     * @param string $message
     * @return Response
     */
    private function getErrorDashboard(string $message): Response
    {
        return Inertia::render('User/Dashboard', [
            'portfolioStats' => [
                'totalValue' => 0,
                'mainWalletBalance' => 0,
                'tradeWalletBalance' => 0,
                'availableBalance' => 0,
                'activeTrades' => 0,
                'todayPnL' => 0,
                'todayPnLPercent' => 0,
                'portfolioChange' => 0,
            ],
            'tradingStats' => [
                'totalTrades' => 0,
                'winningTrades' => 0,
                'losingTrades' => 0,
                'totalProfit' => 0,
                'totalLoss' => 0,
                'winRate' => 0,
            ],
            'quickStats' => [
                'pendingDeposits' => 0,
                'pendingWithdrawals' => 0,
                'monthlyProfit' => 0,
                'miningBalance' => 0,
            ],
            'recentActivities' => [],
            'portfolioChartData' => [],
            'tradingActivityData' => [],
            'marketOverview' => [],
            'currentPeriod' => '7d',
            'error' => $message
        ]);
    }
}
