<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\IcoToken;
use App\Models\IcoPurchase;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
use Inertia\Response;

class IcoController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        try {
            $user = Auth::user();
            $icoTokens = IcoToken::where('status', 'active')
                ->where('sale_start_date', '<=', now())
                ->where('sale_end_date', '>=', now())
                ->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'name' => e($token->name),
                        'symbol' => e($token->symbol),
                        'description' => e($token->description),
                        'price' => (float) $token->price,
                        'current_price' => (float) $token->current_price,
                        'total_supply' => (float) $token->total_supply,
                        'tokens_sold' => (float) $token->tokens_sold,
                        'tokens_remaining' => (float) $token->tokens_remaining,
                        'progress_percentage' => (float) $token->progress_percentage,
                        'total_raised' => (float) $token->total_raised,
                        'sale_start_date' => $token->sale_start_date->format('Y-m-d'),
                        'sale_end_date' => $token->sale_end_date->format('Y-m-d'),
                        'days_remaining' => (int) $token->days_remaining,
                        'is_featured' => (bool) $token->is_featured,
                        'status' => e($token->status),
                    ];
                });

            $myPurchases = IcoPurchase::with('icoToken')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($purchase) {
                    return [
                        'id' => $purchase->id,
                        'purchase_id' => e($purchase->purchase_id),
                        'ico_token' => [
                            'id' => $purchase->icoToken->id,
                            'name' => e($purchase->icoToken->name),
                            'symbol' => e($purchase->icoToken->symbol),
                        ],
                        'amount_usd' => (float) $purchase->amount_usd,
                        'tokens_purchased' => (float) $purchase->tokens_purchased,
                        'token_price' => (float) $purchase->token_price,
                        'status' => e($purchase->status),
                        'transaction_id' => e($purchase->transaction_id ?? ''),
                        'admin_notes' => e($purchase->admin_notes ?? ''),
                        'confirmed_at' => $purchase->confirmed_at?->format('Y-m-d H:i:s'),
                        'completed_at' => $purchase->completed_at?->format('Y-m-d H:i:s'),
                        'purchased_at' => $purchase->purchased_at?->format('Y-m-d H:i:s'),
                        'created_at' => $purchase->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $statistics = [
                'total_invested' => (float) IcoPurchase::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->sum('amount_usd'),
                'total_tokens_purchased' => (float) IcoPurchase::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->sum('tokens_purchased'),
                'successful_purchases' => (int) IcoPurchase::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->count(),
                'pending_purchases' => (int) IcoPurchase::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
            ];

            return Inertia::render('User/Ico/Index', [
                'icoTokens' => $icoTokens,
                'myPurchases' => $myPurchases,
                'userBalance' => 500,
                'statistics' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('ICO Index Error: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return Inertia::render('User/Ico/Index', [
                'icoTokens' => [],
                'myPurchases' => [],
                'userBalance' => 0,
                'statistics' => [
                    'total_invested' => 0,
                    'total_tokens_purchased' => 0,
                    'successful_purchases' => 0,
                    'pending_purchases' => 0,
                ]
            ])->with('error', 'Unable to load ICO data. Please try again.');
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function purchase(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'ico-purchase:' . $user->id;
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many purchase attempts. Please try again in {$seconds} seconds."]);
            }

            $validated = $request->validate([
                'ico_token_id' => 'required|integer|min:1|exists:ico_tokens,id',
                'amount_usd' => 'required|numeric|min:1|max:999999999',
            ]);

            $icoToken = IcoToken::where('id', $validated['ico_token_id'])
                ->where('status', 'active')
                ->firstOrFail();

            if ($icoToken->status !== 'active') {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => 'This ICO token is not available for purchase.']);
            }

            if (now()->lt($icoToken->sale_start_date) || now()->gt($icoToken->sale_end_date)) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => 'This ICO sale is not currently active.']);
            }

            $amountUsd = (float) $validated['amount_usd'];
            if (isset($icoToken->min_purchase) && $amountUsd < $icoToken->min_purchase) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => "Minimum purchase amount is $" . number_format($icoToken->min_purchase, 2)]);
            }

            if (isset($icoToken->max_purchase) && $amountUsd > $icoToken->max_purchase) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => "Maximum purchase amount is $" . number_format($icoToken->max_purchase, 2)]);
            }

            $wallet = $user->mainWallet;
            if ($amountUsd > $wallet->balance) {
                return redirect()->back()->withErrors(['error' => 'Insufficient balance.']);
            }

            $tokenPrice = (float) $icoToken->price;
            if ($tokenPrice <= 0) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => 'Invalid token price.']);
            }

            $tokensToPurchase = floor($amountUsd / $tokenPrice);
            if ($tokensToPurchase <= 0) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => 'Amount too small to purchase any tokens.']);
            }

            $tokensRemaining = (float) $icoToken->tokens_remaining;
            if ($tokensToPurchase > $tokensRemaining) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => 'Not enough tokens remaining in this ICO.']);
            }

            $actualAmountUsd = $tokensToPurchase * $tokenPrice;
            if ($actualAmountUsd > 999999999 || $tokensToPurchase > 999999999) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['error' => 'Purchase amount is too large.']);
            }

            DB::beginTransaction();
            try {
                $wallet->decrement('balance', $actualAmountUsd);
                $wallet->fresh();

                IcoPurchase::create([
                    'user_id' => $user->id,
                    'ico_token_id' => $icoToken->id,
                    'purchase_id' => IcoPurchase::generatePurchaseId(),
                    'amount_usd' => round($actualAmountUsd, 2),
                    'tokens_purchased' => $tokensToPurchase,
                    'token_price' => $tokenPrice,
                    'status' => 'completed',
                    'purchased_at' => now(),
                ]);

                $icoToken->increment('tokens_sold', $tokensToPurchase);
                Transaction::create([
                    'transaction_id' => Str::random(16),
                    'user_id' => $user->id,
                    'type' => 'payment',
                    'wallet_type' => 'main_wallet',
                    'amount' => $actualAmountUsd,
                    'post_balance' => $wallet->balance,
                    'status' => 'completed',
                    'details' => "ICO purchase: {$icoToken->symbol} ({$tokensToPurchase} tokens)",
                ]);

                DB::commit();
                RateLimiter::clear($key);

                return redirect()->back()->with('success',
                    'Successfully purchased ' . number_format($tokensToPurchase) . ' ' . e($icoToken->symbol) . ' tokens for $' . number_format($actualAmountUsd, 2)
                );
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            RateLimiter::hit($key ?? 'ico-purchase:' . Auth::id(), 60);
            return redirect()->back()->withErrors(['error' => 'ICO token not found or not available.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('ICO Purchase Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->only(['ico_token_id', 'amount_usd'])
            ]);

            RateLimiter::hit($key ?? 'ico-purchase:' . Auth::id(), 60);
            return redirect()->back()->withErrors(['error' => 'Failed to complete purchase. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function history(Request $request): Response|RedirectResponse
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'search' => ['nullable', 'string', 'max:100'],
                'token' => ['nullable', 'integer', 'exists:ico_tokens,id'],
                'status' => ['nullable', Rule::in(['completed', 'pending', 'failed', 'cancelled'])],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:100']
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $query = IcoPurchase::with('icoToken')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($request->filled('search')) {
                $search = strip_tags(trim($request->search));
                $query->where(function ($q) use ($search) {
                    $q->where('purchase_id', 'like', '%' . $search . '%')
                        ->orWhereHas('icoToken', function ($tokenQuery) use ($search) {
                            $tokenQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('symbol', 'like', '%' . $search . '%');
                        });
                });
            }

            if ($request->filled('token')) {
                $query->where('ico_token_id', $request->token);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('start_date')) {
                try {
                    $startDate = Carbon::parse($request->start_date)->startOfDay();
                    $query->where('created_at', '>=', $startDate);
                } catch (\Exception $e) {
                    Log::warning('Invalid start_date format', ['start_date' => $request->start_date]);
                }
            }

            if ($request->filled('end_date')) {
                try {
                    $endDate = Carbon::parse($request->end_date)->endOfDay();
                    $query->where('created_at', '<=', $endDate);
                } catch (\Exception $e) {
                    Log::warning('Invalid end_date format', ['end_date' => $request->end_date]);
                }
            }

            $perPage = min($request->get('per_page', 10), 100);
            $purchases = $query->paginate($perPage);
            $purchases->getCollection()->transform(function ($purchase) {
                return [
                    'id' => $purchase->id,
                    'purchase_id' => e($purchase->purchase_id),
                    'token' => [
                        'id' => $purchase->icoToken->id,
                        'name' => e($purchase->icoToken->name),
                        'symbol' => e($purchase->icoToken->symbol),
                    ],
                    'amount_usd' => (float) $purchase->amount_usd,
                    'tokens_purchased' => (float) $purchase->tokens_purchased,
                    'token_price' => (float) $purchase->token_price,
                    'status' => e($purchase->status),
                    'transaction_hash' => e($purchase->transaction_hash ?? ''),
                    'notes' => e($purchase->notes ?? ''),
                    'purchased_at' => $purchase->purchased_at?->format('Y-m-d H:i:s'),
                    'created_at' => $purchase->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $tokens = IcoToken::select('id', 'name', 'symbol')
                ->whereIn('id', function ($query) use ($user) {
                    $query->select('ico_token_id')
                        ->from('ico_purchases')
                        ->where('user_id', $user->id)
                        ->distinct();
                })
                ->orderBy('name')
                ->get();

            $stats = [
                'total_purchases' => (int) IcoPurchase::where('user_id', $user->id)->count(),
                'unique_tokens' => (int) IcoPurchase::where('user_id', $user->id)
                    ->distinct('ico_token_id')
                    ->count('ico_token_id'),
                'total_invested' => (float) IcoPurchase::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->sum('amount_usd'),
                'total_tokens' => (float) IcoPurchase::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->sum('tokens_purchased'),
            ];

            $filters = $request->only(['search', 'token', 'status', 'start_date', 'end_date', 'per_page']);
            foreach ($filters as $key => $value) {
                if (is_string($value)) {
                    $filters[$key] = e($value);
                }
            }

            return Inertia::render('User/Ico/History', [
                'purchases' => $purchases,
                'filters' => $filters,
                'tokens' => $tokens,
                'statuses' => ['completed', 'pending', 'failed', 'cancelled'],
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('ICO History Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->only(['search', 'token', 'status', 'start_date', 'end_date'])
            ]);

            return Inertia::render('User/Ico/History', [
                'purchases' => ['data' => [], 'total' => 0],
                'filters' => [],
                'tokens' => [],
                'statuses' => ['completed', 'pending', 'failed', 'cancelled'],
                'stats' => [
                    'total_purchases' => 0,
                    'unique_tokens' => 0,
                    'total_invested' => 0,
                    'total_tokens' => 0,
                ],
            ])->with('error', 'Unable to load purchase history. Please try again.');
        }
    }
}
