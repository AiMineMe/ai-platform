<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'transaction-history:' . $user->id . ':' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 100)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many requests. Please try again in {$seconds} seconds."]);
            }

            $validator = Validator::make($request->all(), [
                'type' => ['nullable', Rule::in(['deposit', 'withdrawal', 'transfer', 'payment', 'refund', 'debit', 'credit'])],
                'status' => ['nullable', Rule::in(['completed', 'pending', 'failed', 'cancelled'])],
                'wallet_type' => ['nullable', 'string', 'max:50'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after:start_date'],
                'search' => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:100']
            ]);

            if ($validator->fails()) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $query = Transaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('wallet_type')) {
                $walletType = strip_tags(trim($request->wallet_type));
                $query->where('wallet_type', 'like', '%' . $walletType . '%');
            }

            if ($request->filled('search')) {
                $search = strip_tags(trim($request->search));
                $query->where('transaction_id', 'like', '%' . $search . '%');
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

            $perPage = min($request->get('per_page', 20), 100);
            $transactions = $query->paginate($perPage);

            $transactions->getCollection()->transform(function ($transaction) {
                $transaction->transaction_id = e($transaction->transaction_id);
                $transaction->type = e($transaction->type);
                $transaction->status = e($transaction->status);
                $transaction->wallet_type = e($transaction->wallet_type);
                $transaction->details = e($transaction->details ?? '');
                return $transaction;
            });

            $stats = [
                'total_transactions' => (int) Transaction::where('user_id', $user->id)->count(),
                'completed_transactions' => (int) Transaction::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->count(),
                'total_deposits' => (float) Transaction::where('user_id', $user->id)
                    ->where('type', 'deposit')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'total_withdrawals' => (float) Transaction::where('user_id', $user->id)
                    ->where('type', 'withdrawal')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'pending_transactions' => (int) Transaction::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
                'failed_transactions' => (int) Transaction::where('user_id', $user->id)
                    ->where('status', 'failed')
                    ->count(),
            ];

            RateLimiter::hit($key, 60);
            $filters = $request->only(['type', 'status', 'wallet_type', 'start_date', 'end_date', 'search', 'per_page']);
            foreach ($filters as $key => $value) {
                if (is_string($value)) {
                    $filters[$key] = e($value);
                }
            }

            return Inertia::render('User/Wallet/Transaction', [
                'transactions' => $transactions,
                'filters' => $filters,
                'stats' => $stats,
                'statuses' => ['completed', 'pending', 'failed', 'cancelled'],
                'transaction_types' => ['deposit', 'withdrawal', 'transfer', 'payment', 'refund', 'credit', 'debit'],
            ]);

        } catch (\Exception $e) {
            Log::error('Transaction History Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->only(['type', 'status', 'wallet_type', 'start_date', 'end_date', 'search'])
            ]);

            return Inertia::render('User/Wallet/Transaction', [
                'transactions' => ['data' => [], 'total' => 0],
                'filters' => [],
                'stats' => [
                    'total_transactions' => 0,
                    'completed_transactions' => 0,
                    'total_deposits' => 0,
                    'total_withdrawals' => 0,
                    'pending_transactions' => 0,
                    'failed_transactions' => 0,
                ],
                'statuses' => ['completed', 'pending', 'failed', 'cancelled'],
                'transaction_types' => ['deposit', 'withdrawal', 'transfer', 'payment', 'refund'],
            ])->with('error', 'Unable to load transaction history. Please try again.');
        }
    }
}
