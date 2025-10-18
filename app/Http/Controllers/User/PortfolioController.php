<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\IcoPurchase;
use App\Models\IcoToken;
use App\Models\TokenSale;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    /**
     * @return Response
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $holdings = $this->getUserTokenHoldings($user->id);
            $portfolioStats = $this->calculatePortfolioStats($holdings);
            return Inertia::render('User/Portfolio/Index', [
                'holdings' => $holdings,
                'portfolioStats' => $portfolioStats
            ]);

        } catch (\Exception $e) {
            Log::error('Portfolio Error: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return Inertia::render('User/Portfolio/Index', [
                'holdings' => [],
                'portfolioStats' => [
                    'total_invested' => 0,
                    'current_value' => 0,
                    'total_profit_loss' => 0,
                    'total_profit_loss_percentage' => 0,
                    'total_tokens' => 0,
                    'profitable_holdings' => 0,
                    'losing_holdings' => 0
                ]
            ])->with('error', 'Unable to load portfolio. Please try again.');
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function sell(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $validated = $request->validate([
                'ico_token_id' => 'required|integer|exists:ico_tokens,id',
                'tokens_to_sell' => 'required|integer|min:1'
            ]);

            $icoToken = IcoToken::findOrFail($validated['ico_token_id']);
            $tokensToSell = $validated['tokens_to_sell'];
            $userTokens = IcoPurchase::where('user_id', $user->id)
                ->where('ico_token_id', $icoToken->id)
                ->where('status', 'completed')
                ->sum('tokens_purchased');

            $soldTokens = TokenSale::where('user_id', $user->id)
                ->where('ico_token_id', $icoToken->id)
                ->where('status', 'completed')
                ->sum('tokens_sold');

            $availableTokens = $userTokens - $soldTokens;

            if ($tokensToSell > $availableTokens) {
                return redirect()->back()->withErrors(['error' => 'Insufficient tokens to sell.']);
            }
            $salePrice = $icoToken->current_price ?? $icoToken->price;
            $totalAmount = $tokensToSell * $salePrice;

            DB::beginTransaction();

            try {
                $sale = TokenSale::create([
                    'user_id' => $user->id,
                    'ico_token_id' => $icoToken->id,
                    'sale_id' => TokenSale::generateSaleId(),
                    'tokens_sold' => $tokensToSell,
                    'sale_price' => $salePrice,
                    'total_amount' => $totalAmount,
                    'status' => 'completed',
                    'sold_at' => now()
                ]);

                $wallet = $user->mainWallet;
                $wallet->increment('balance', $totalAmount);
                Transaction::create([
                    'transaction_id' => $sale->sale_id,
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'wallet_type' => 'main_wallet',
                    'amount' => $totalAmount,
                    'post_balance' => $wallet->fresh()->balance,
                    'status' => 'completed',
                    'details' => "Token sale: {$icoToken->symbol} ({$tokensToSell} tokens)"
                ]);

                DB::commit();

                return redirect()->back()->with('success',
                    'Successfully sold ' . number_format($tokensToSell) . ' ' . $icoToken->symbol . ' tokens for $' . number_format($totalAmount, 2)
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Token Sale Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->only(['ico_token_id', 'tokens_to_sell'])
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to sell tokens. Please try again.']);
        }
    }

    /**
     * @param $userId
     * @return mixed
     */
    private function getUserTokenHoldings($userId): mixed
    {
        return IcoPurchase::select([
            'ico_token_id',
            DB::raw('SUM(tokens_purchased) as total_tokens'),
            DB::raw('SUM(amount_usd) as total_invested'),
            DB::raw('AVG(token_price) as average_price'),
            DB::raw('MIN(purchased_at) as first_purchase_date'),
            DB::raw('MAX(purchased_at) as last_purchase_date'),
            DB::raw('COUNT(*) as purchase_count')
        ])
            ->with(['icoToken' => function ($query) {
                $query->select('id', 'name', 'symbol', 'price', 'current_price', 'price_updated_at');
            }])
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->groupBy('ico_token_id')
            ->get()
            ->map(function ($holding) use ($userId) {
                $token = $holding->icoToken;
                $currentPrice = $token->current_price ?? $token->price;

                $soldTokensData = TokenSale::where('user_id', $userId)
                    ->where('ico_token_id', $token->id)
                    ->where('status', 'completed')
                    ->selectRaw('SUM(tokens_sold) as total_sold, SUM(total_amount) as total_revenue')
                    ->first();

                $soldTokens = $soldTokensData->total_sold ?? 0;
                $soldRevenue = $soldTokensData->total_revenue ?? 0;

                $availableTokens = $holding->total_tokens - $soldTokens;
                $soldTokensCost = $soldTokens * $holding->average_price;
                $adjustedInvestment = $holding->total_invested - $soldTokensCost;

                $currentValue = $availableTokens * $currentPrice;
                $profitLoss = $currentValue - $adjustedInvestment;
                $realizedPL = $soldRevenue - $soldTokensCost;

                $profitLossPercentage = $adjustedInvestment > 0
                    ? (($profitLoss / $adjustedInvestment) * 100)
                    : 0;

                $priceChangePercentage = 0;
                if ($token->current_price && $token->price && $token->price > 0) {
                    $priceChangePercentage = (($token->current_price - $token->price) / $token->price) * 100;
                }

                return [
                    'token' => [
                        'id' => $token->id,
                        'name' => $token->name,
                        'symbol' => $token->symbol,
                        'price' => (float) $token->price,
                        'current_price' => (float) $currentPrice,
                        'price_updated_at' => $token->price_updated_at ? $token->price_updated_at->format('Y-m-d H:i:s') : null,
                        'price_change_percentage' => (float) $priceChangePercentage
                    ],
                    'total_tokens' => (float) $holding->total_tokens,
                    'available_tokens' => (float) $availableTokens,
                    'sold_tokens' => (float) $soldTokens,
                    'total_invested' => (float) $holding->total_invested,
                    'adjusted_investment' => (float) $adjustedInvestment,
                    'average_price' => (float) $holding->average_price,
                    'current_value' => (float) $currentValue,
                    'profit_loss' => (float) $profitLoss,
                    'profit_loss_percentage' => (float) $profitLossPercentage,
                    'realized_pl' => (float) $realizedPL,
                    'is_profitable' => $profitLoss > 0,
                    'first_purchase_date' => $holding->first_purchase_date,
                    'last_purchase_date' => $holding->last_purchase_date,
                    'purchase_count' => (int) $holding->purchase_count,
                    'sold_revenue' => (float) $soldRevenue
                ];
            })
            ->filter(function ($holding) {
                return $holding['available_tokens'] > 0;
            })
            ->sortByDesc('current_value')
            ->values();
    }

    /**
     * @param $holdings
     * @return array
     */
    private function calculatePortfolioStats($holdings): array
    {
        $totalInvested = $holdings->sum('adjusted_investment');
        $currentValue = $holdings->sum('current_value');
        $totalProfitLoss = $currentValue - $totalInvested;
        $totalRealizedPL = $holdings->sum('realized_pl');
        $totalProfitLossPercentage = $totalInvested > 0
            ? (($totalProfitLoss / $totalInvested) * 100)
            : 0;

        return [
            'total_invested' => (float) $totalInvested,
            'current_value' => (float) $currentValue,
            'total_profit_loss' => (float) $totalProfitLoss,
            'total_profit_loss_percentage' => (float) $totalProfitLossPercentage,
            'total_realized_pl' => (float) $totalRealizedPL,
            'total_tokens' => (float) $holdings->sum('available_tokens'),
            'profitable_holdings' => $holdings->where('is_profitable', true)->count(),
            'losing_holdings' => $holdings->where('is_profitable', false)->count(),
            'total_holdings' => $holdings->count()
        ];
    }
}
