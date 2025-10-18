<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Wallet\Type;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Models\WithdrawalGateway;
use App\Services\EmailTemplateService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,approved,rejected',
            'gateway' => 'nullable|integer|exists:withdrawal_gateways,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_field' => 'nullable|in:created_at,amount,status,trx',
            'sort_direction' => 'nullable|in:asc,desc'
        ]);

        try {
            $query = Withdrawal::with(['user', 'withdrawalGateway']);
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('trx', 'like', '%' . $request->search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($request) {
                            $userQuery->where('name', 'like', '%' . $request->search . '%')
                                ->orWhere('email', 'like', '%' . $request->search . '%');
                        })
                        ->orWhereHas('withdrawalGateway', function ($gatewayQuery) use ($request) {
                            $gatewayQuery->where('name', 'like', '%' . $request->search . '%');
                        });
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('gateway')) {
                $query->where('withdrawal_gateway_id', $request->gateway);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            $withdrawals = $query->paginate(20)->appends($request->all());
            $stats = [
                'total_withdrawals' => Withdrawal::count(),
                'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
                'total_amount' => Withdrawal::where('status', 'approved')->sum('withdrawal_amount'),
                'pending_amount' => Withdrawal::where('status', 'pending')->sum('withdrawal_amount'),
            ];

            $gateways = WithdrawalGateway::where('status', true)->get(['id', 'name']);

            return Inertia::render('Admin/Withdrawals/Index', [
                'withdrawals' => $withdrawals->items(),
                'meta' => [
                    'total' => $withdrawals->total(),
                    'current_page' => $withdrawals->currentPage(),
                    'per_page' => $withdrawals->perPage(),
                    'last_page' => $withdrawals->lastPage(),
                ],
                'stats' => $stats,
                'gateways' => $gateways,
                'filters' => $request->only(['search', 'status', 'gateway', 'date_from', 'date_to', 'sort_field', 'sort_direction']),
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load withdrawals. Please try again.']);
        }
    }


    /**
     * @param Request $request
     * @param Withdrawal $withdrawal
     * @return RedirectResponse
     */
    public function approve(Request $request, Withdrawal $withdrawal): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'response' => 'nullable|string|max:1000',
        ]);

        try {
            if ($withdrawal->status !== 'pending') {
                return back()->withErrors(['error' => 'Only pending withdrawals can be approved.']);
            }

            DB::transaction(function () use ($withdrawal, $request) {
                $withdrawal->update([
                    'status' => 'approved',
                    'admin_response' => $request->response,
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                ]);

                if ($withdrawal->user) {
                    try {
                        EmailTemplateService::sendTemplateEmail('withdrawal_approved', $withdrawal->user, [
                            'user_name' => $withdrawal->user->name,
                            'amount' => round($withdrawal->withdrawal_amount, 2),
                            'transaction_id' => $withdrawal->trx,
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Failed to send withdrawal approval email', [
                            'user_id' => $withdrawal->user->id,
                            'withdrawal_id' => $withdrawal->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

            return back()->with('success', 'Withdrawal approved successfully.');

        } catch (Exception $e) {
            Log::error('Withdrawal Approval Error: ' . $e->getMessage(), [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => Auth::id()
            ]);
            return back()->withErrors(['error' => 'Failed to approve withdrawal. Please try again.']);
        }
    }


    /**
     * @param Request $request
     * @param Withdrawal $withdrawal
     * @return RedirectResponse
     */
    public function reject(Request $request, Withdrawal $withdrawal): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'response' => 'required|string|max:1000',
        ]);

        try {
            if ($withdrawal->status !== 'pending') {
                return back()->withErrors(['error' => 'Only pending withdrawals can be rejected.']);
            }

            DB::transaction(function () use ($withdrawal, $request) {
                $withdrawal->update([
                    'status' => 'rejected',
                    'admin_response' => $request->response,
                    'rejected_at' => now(),
                    'rejected_by' => Auth::id(),
                ]);

                $this->refundToWallet($withdrawal->user, $withdrawal);
                if ($withdrawal->user) {
                    try {
                        EmailTemplateService::sendTemplateEmail('withdrawal_rejected', $withdrawal->user, [
                            'user_name' => $withdrawal->user->name,
                            'amount' => round($withdrawal->withdrawal_amount,2),
                            'transaction_id' => $withdrawal->trx,
                        ]);
                    } catch (Exception $e) {
                        Log::warning('Failed to send withdrawal rejection email', [
                            'user_id' => $withdrawal->user->id,
                            'withdrawal_id' => $withdrawal->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

            return back()->with('success', 'Withdrawal rejected successfully and amount refunded to user wallet.');
        } catch (Exception $e) {
            Log::error('Withdrawal Rejection Error: ' . $e->getMessage(), [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => Auth::id()
            ]);
            return back()->withErrors(['error' => 'Failed to reject withdrawal. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function bulkAction(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'withdrawal_ids' => 'required|array|min:1',
            'withdrawal_ids.*' => 'exists:withdrawals,id',
            'response' => 'nullable|string|max:1000',
        ]);

        try {
            $withdrawals = Withdrawal::whereIn('id', $request->withdrawal_ids)
                ->where('status', 'pending')
                ->get();

            if ($withdrawals->isEmpty()) {
                return back()->withErrors(['error' => 'No pending withdrawals found for the selected items.']);
            }

            if ($request->action === 'reject' && !$request->response) {
                return back()->withErrors(['error' => 'Response is required when rejecting withdrawals.']);
            }

            DB::transaction(function () use ($withdrawals, $request) {
                foreach ($withdrawals as $withdrawal) {
                    if ($request->action === 'approve') {
                        $withdrawal->update([
                            'status' => 'approved',
                            'admin_response' => $request->response,
                            'approved_at' => now(),
                            'approved_by' => Auth::id(),
                        ]);
                    } else {
                        $withdrawal->update([
                            'status' => 'rejected',
                            'admin_response' => $request->response,
                            'rejected_at' => now(),
                            'rejected_by' => Auth::id(),
                        ]);

                        $this->refundToWallet($withdrawal->user, $withdrawal);
                    }

                    if ($withdrawal->user) {
                        try {
                            $template = $request->action === 'approve' ? 'withdrawal_approved' : 'withdrawal_rejected';
                            EmailTemplateService::sendTemplateEmail($template, $withdrawal->user, [
                                'user_name' => $withdrawal->user->name,
                                'amount' => round($withdrawal->withdrawal_amount, 2),
                                'transaction_id' => $withdrawal->trx,
                            ]);
                        } catch (Exception $e) {
                            Log::warning('Failed to send bulk withdrawal email', [
                                'user_id' => $withdrawal->user->id,
                                'withdrawal_id' => $withdrawal->id,
                                'action' => $request->action,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            });

            $action = $request->action === 'approve' ? 'approved' : 'rejected';
            $count = $withdrawals->count();
            return back()->with('success', "{$count} withdrawal(s) {$action} successfully.");
        } catch (Exception $e) {
            Log::error('Bulk withdrawal action error', [
                'action' => $request->action,
                'withdrawal_ids' => $request->withdrawal_ids,
                'error' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to process bulk action. Please try again.']);
        }
    }

    /**
     * @param $user
     * @param $withdrawal
     * @return void
     * @throws Exception
     */
    private function refundToWallet($user, $withdrawal): void
    {
        $wallet = $user->wallets()->where('type', Type::MAIN->value)->first();
        if (!$wallet) {
            throw new Exception('User wallet not found');
        }

        $refundAmount = $withdrawal->withdrawal_amount;
        $previousBalance = $wallet->balance;
        $newBalance = round($previousBalance + $refundAmount, 2);

        $wallet->update([
            'balance' => $newBalance,
            'last_activity' => now()
        ]);

        $roundAmount = round($withdrawal->final_amount, 2);
        Transaction::create([
            'transaction_id' =>  Str::random(16),
            'user_id' => $user->id,
            'type' => 'deposit',
            'wallet_type' => 'main_wallet',
            'amount' => $refundAmount,
            'post_balance' => $newBalance,
            'status' => 'completed',
            'details' => "Refund for rejected withdrawal {$withdrawal->trx} - {$roundAmount} {$withdrawal->currency}"
        ]);
    }
}
