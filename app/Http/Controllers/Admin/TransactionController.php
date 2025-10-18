<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|in:deposit,withdrawal,transfer,payment,refund,credit,debit',
            'wallet_type' => 'nullable|in:main_wallet,trade_wallet',
            'status' => 'nullable|in:completed,failed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_field' => 'nullable|in:transaction_id,type,wallet_type,amount,post_balance,status,created_at',
            'sort_direction' => 'nullable|in:asc,desc'
        ]);

        try {
            $query = Transaction::with(['user:id,name,email'])
                ->select([
                    'id',
                    'transaction_id',
                    'user_id',
                    'type',
                    'wallet_type',
                    'amount',
                    'post_balance',
                    'status',
                    'details',
                    'created_at',
                    'updated_at'
                ]);

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_id', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('wallet_type')) {
                $query->where('wallet_type', $request->input('wallet_type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->input('end_date'));
            }

            $sortField = $request->input('sort_field', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $allowedSortFields = ['transaction_id', 'type', 'wallet_type', 'amount', 'post_balance', 'status', 'created_at'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $perPage = $request->input('per_page', 20);
            $transactions = $query->paginate($perPage)->appends($request->all());
            $currency = Setting::where('key', 'default_currency')->value('value') ?? 'USD';
            $transactions->getCollection()->transform(function ($transaction) use ($currency) {
                return [
                    'id' => $transaction->id,
                    'transaction_id' => $transaction->transaction_id,
                    'user' => $transaction->user,
                    'type' => $transaction->type,
                    'type_label' => ucfirst(str_replace('_', ' ', $transaction->type)),
                    'wallet_type' => $transaction->wallet_type,
                    'wallet_type_label' => ucfirst(str_replace('_', ' ', $transaction->wallet_type)),
                    'amount' => $transaction->amount,
                    'post_balance' => $transaction->post_balance,
                    'status' => $transaction->status,
                    'status_label' => ucfirst($transaction->status),
                    'details' => $transaction->details,
                    'currency' => $currency,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ];
            });

            $stats = $this->getTransactionStats();
            return Inertia::render('Admin/Transactions/Index', [
                'transactions' => $transactions,
                'transactionTypes' => ['deposit', 'withdrawal', 'transfer', 'payment', 'refund', 'credit', 'debit'],
                'walletTypes' => ['main_wallet', 'trade_wallet'],
                'statuses' => ['completed', 'failed', 'cancelled'],
                'filters' => $request->only(['search', 'type', 'wallet_type', 'status', 'start_date', 'end_date', 'sort_field', 'sort_direction']),
                'meta' => [
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'last_page' => $transactions->lastPage(),
                ],
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return back()->with('error', 'Unable to load transactions. Please try again.');
        }
    }

    /**
     * @return array
     */
    private function getTransactionStats(): array
    {
        try {
            $totalTransactions = Transaction::count();
            $totalAmount = Transaction::sum('amount');
            $transactionsByStatus = Transaction::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $transactionsByType = Transaction::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            $transactionsByWalletType = Transaction::select('wallet_type', DB::raw('count(*) as count'))
                ->groupBy('wallet_type')
                ->pluck('count', 'wallet_type')
                ->toArray();

            $recentTransactions = Transaction::where('created_at', '>=', now()->subDays(7))->count();
            $todayTransactions = Transaction::whereDate('created_at', today())->count();
            $thisMonthTransactions = Transaction::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $completedTransactions = Transaction::where('status', 'completed')->count();
            return [
                'total_transactions' => $totalTransactions,
                'total_amount' => $totalAmount,
                'transactions_by_status' => $transactionsByStatus,
                'transactions_by_type' => $transactionsByType,
                'transactions_by_wallet_type' => $transactionsByWalletType,
                'recent_transactions' => $recentTransactions,
                'today_transactions' => $todayTransactions,
                'this_month_transactions' => $thisMonthTransactions,
                'completed_transactions' => $completedTransactions,
            ];

        } catch (\Exception $e) {
            return [
                'total_transactions' => 0,
                'total_amount' => 0,
                'transactions_by_status' => [],
                'transactions_by_type' => [],
                'transactions_by_wallet_type' => [],
                'recent_transactions' => 0,
                'today_transactions' => 0,
                'this_month_transactions' => 0,
                'completed_transactions' => 0,
            ];
        }
    }
}
