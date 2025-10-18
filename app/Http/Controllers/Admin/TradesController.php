<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TradesController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Trade::with('user');
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('trade_id', 'like', '%' . $request->search . '%')
                    ->orWhereHas('user', function($userQuery) use ($request) {
                        $userQuery->where('name', 'like', '%' . $request->search . '%')
                            ->orWhere('email', 'like', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->filled('symbol')) {
            $query->where('symbol', $request->symbol);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['trade_id', 'symbol', 'amount', 'status', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest();
        }

        $trades = $query->paginate(20)->appends($request->all());
        $tradesData = $trades->getCollection()->map(function ($trade) {
            return [
                'id' => $trade->id,
                'trade_id' => $trade->trade_id,
                'user' => [
                    'name' => $trade->user->name ?? 'Unknown',
                    'email' => $trade->user->email ?? 'N/A',
                ],
                'symbol' => $trade->symbol,
                'direction' => $trade->direction,
                'amount' => $trade->amount,
                'open_price' => $trade->open_price,
                'close_price' => $trade->close_price,
                'payout_rate' => $trade->payout_rate,
                'status' => $trade->status,
                'profit_loss' => $trade->profit_loss,
                'formatted_profit_loss' => $trade->formatted_profit_loss,
                'duration_formatted' => $trade->duration_formatted,
                'open_time' => $trade->open_time,
                'close_time' => $trade->close_time,
                'created_at' => $trade->created_at,
            ];
        });

        $symbols = Trade::select('symbol')->distinct()->pluck('symbol')->sort()->values();
        $stats = $this->getTradeStats();

        $tradeResultSetting = Setting::get('trade_result', 'automated');

        return Inertia::render('Admin/Trades/Index', [
            'trades' => $tradesData,
            'meta' => [
                'total' => $trades->total(),
                'current_page' => $trades->currentPage(),
                'per_page' => $trades->perPage(),
                'last_page' => $trades->lastPage(),
            ],
            'filters' => $request->only(['search', 'symbol', 'direction', 'status', 'sort_field', 'sort_direction']),
            'symbols' => $symbols,
            'stats' => $stats,
            'tradeResultSetting' => $tradeResultSetting
        ]);
    }

    /**
     * @param Trade $trade
     * @return Response
     */
    public function show(Trade $trade): Response
    {
        $trade->load('user');
        return Inertia::render('Admin/Trades/Show', [
            'trade' => $trade
        ]);
    }

    /**
     * @param Request $request
     * @param Trade $trade
     * @return RedirectResponse
     */
    public function settle(Request $request, Trade $trade): RedirectResponse
    {
        $request->validate([
            'close_price' => 'required|numeric|min:0'
        ]);

        if (!in_array($trade->status, ['active', 'expired'])) {
            return back()->withErrors(['error' => 'Trade cannot be settled. Only active or expired trades can be settled.']);
        }

        try {
            $trade->settleTrade($request->close_price);
            $trade->refresh();

            if($trade->status == 'won' || $trade->status == 'draw') {
                $this->updateUserBalance($trade);
            }

            return back()->with('success', 'Trade settled successfully');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Failed to settle trade: ' . $e->getMessage()]);
        }
    }

    /**
     * @param Trade $trade
     * @return RedirectResponse
     */
    public function cancel(Trade $trade): RedirectResponse
    {
        if (!in_array($trade->status, ['active', 'expired'])) {
            return back()->withErrors(['error' => 'Trade cannot be cancelled. Only active or expired trades can be cancelled.']);
        }

        try {
            $trade->status = 'cancelled';
            $trade->close_time = now();
            $trade->profit_loss = 0;
            $trade->save();

            $trade->refresh();
            if($trade->status == 'cancelled') {
                $this->updateUserBalance($trade);
            }

            return back()->with('success', 'Trade cancelled successfully');
        } catch (Exception $e) {
            return back()->withErrors(['error' =>  'Failed to cancel trade: ' . $e->getMessage()]);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:settle,cancel',
            'trade_ids' => 'required|array',
            'trade_ids.*' => 'exists:trades,id',
            'close_price' => 'required_if:action,settle|numeric|min:0'
        ]);

        $trades = Trade::whereIn('id', $request->trade_ids)
            ->whereIn('status', ['active', 'expired'])
            ->get();

        if ($trades->isEmpty()) {
            return back()->withErrors(['error' => 'No actionable trades found. Only active or expired trades can be processed.']);
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($trades as $trade) {
            try {
                switch ($request->action) {
                    case 'settle':
                        $trade->settleTrade($request->close_price);
                        $successCount++;
                        break;

                    case 'cancel':
                        $trade->cancelTrade();
                        $successCount++;
                        break;
                }

                $trade->refresh();
                if($trade->status == 'won' || $trade->status == 'draw' ||  $trade->status == 'cancelled') {
                    $this->updateUserBalance($trade);
                }

            } catch (Exception $e) {
                $errorCount++;
                Log::error("Failed to {$request->action} trade {$trade->trade_id}: " . $e->getMessage());
                continue;
            }
        }

        $message = "{$successCount} trades {$request->action}d successfully";
        if ($errorCount > 0) {
            $message .= ". {$errorCount} trades failed to process.";
        }

        return back()->with('success', $message);
    }

    /**
     * @return array
     */
    private function getTradeStats(): array
    {
        $trades = Trade::all();

        $totalTrades = $trades->count();
        $activeTrades = $trades->where('status', 'active')->count();
        $wonTrades = $trades->where('status', 'won')->count();
        $lostTrades = $trades->where('status', 'lost')->count();
        $totalProfitLoss = $trades->where('status', 'lost')->sum('amount') - $trades->where('status', 'won')->sum('profit_loss');

        return [
            'totalTrades' => $totalTrades,
            'activeTrades' => $activeTrades,
            'wonTrades' => $wonTrades,
            'lostTrades' => $lostTrades,
            'totalProfitLoss' => round($totalProfitLoss, 2)
        ];
    }


    /**
     * @param Trade $trade
     * @return void
     * @throws Exception
     */
    private function updateUserBalance(Trade $trade): void
    {
        $userId = $trade->user_id;
        $user = User::find($userId);

        if (!$user) {
            throw new Exception("User with ID {$userId} not found");
        }

        $wallet = $user->tradeWallet;
        if (!$wallet) {
            throw new Exception("Main wallet not found for user {$userId}");
        }

        $previousBalance = $wallet->balance;
        $tradeResult = $trade->status;

        switch ($tradeResult) {
            case 'won':
                $totalAmount = $trade->amount + $trade->profit_loss;
                $wallet->increment('balance', $totalAmount);
                $postBalance = $previousBalance + $totalAmount;

                $details = "Trade won for {$trade->symbol} ({$trade->direction}) - Investment return: " .
                    number_format($trade->amount, 2) . " + Profit: " .
                    number_format($trade->profit_loss, 2) . " = Total: " .
                    number_format($totalAmount, 2);

                Transaction::create([
                    'transaction_id' => Str::random(),
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'wallet_type' => 'trade_wallet',
                    'amount' => $totalAmount,
                    'post_balance' => $postBalance,
                    'status' => 'completed',
                    'details' => $details,
                ]);
                break;

            case 'draw':
            case 'cancelled':
                $refundAmount = $trade->amount;
                $wallet->increment('balance', $refundAmount);
                $postBalance = $previousBalance + $refundAmount;

                $actionText = $tradeResult === 'draw' ? 'draw' : 'cancelled';
                $details = "Trade {$actionText} for {$trade->symbol} ({$trade->direction}) - Investment refund: " .
                    number_format($refundAmount, 2);

                Transaction::create([
                    'transaction_id' => Str::random(),
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'wallet_type' => 'trade_wallet',
                    'amount' => $refundAmount,
                    'post_balance' => $postBalance,
                    'status' => 'completed',
                    'details' => $details,
                ]);
                break;

            default:
                throw new Exception("Unsupported trade status: {$tradeResult}. Only 'won', 'draw', and 'cancelled' are supported.");
        }
    }


    public function setResult(Request $request, Trade $trade): RedirectResponse
    {
        $request->validate([
            'result' => 'required|in:won,lost,automated'
        ]);

        Setting::set(
            'trade_result',
            $request->input('result'),
             'text',
                null,
            'trading'
        );

        return back()->with('success', 'Trade result status updated successfully');
    }
}
