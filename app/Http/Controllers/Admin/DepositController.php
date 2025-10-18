<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\UploadedFile;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\PaymentGateway;
use App\Models\Transaction;
use App\Services\EmailTemplateService;
use App\Services\ReferralService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    use UploadedFile;
    public function __construct(protected readonly ReferralService $referralService){

    }

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,approved,rejected',
            'gateway' => 'nullable|integer|exists:payment_gateways,id',
            'type' => 'nullable|in:manual,automatic',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_field' => 'nullable|in:created_at,amount,status,trx',
            'sort_direction' => 'nullable|in:asc,desc'
        ]);

        try {
            $query = Deposit::with(['user', 'paymentGateway', 'approvedBy', 'rejectedBy']);
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('trx', 'like', '%' . $request->search . '%')
                        ->orWhere('transaction_id', 'like', '%' . $request->search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($request) {
                            $userQuery->where('name', 'like', '%' . $request->search . '%')
                                ->orWhere('email', 'like', '%' . $request->search . '%');
                        })
                        ->orWhereHas('paymentGateway', function ($gatewayQuery) use ($request) {
                            $gatewayQuery->where('name', 'like', '%' . $request->search . '%');
                        });
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('gateway')) {
                $query->where('payment_gateway_id', $request->gateway);
            }

            if ($request->filled('type')) {
                if ($request->type === 'manual') {
                    $query->manual();
                } elseif ($request->type === 'automatic') {
                    $query->automatic();
                }
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
            $deposits = $query->paginate(20)->appends($request->all());

            $stats = [
                'total_deposits' => Deposit::count(),
                'pending_deposits' => Deposit::pending()->count(),
                'total_amount' => Deposit::approved()->sum('deposit_amount'),
                'pending_amount' => Deposit::pending()->sum('deposit_amount'),
            ];

            $gateways = PaymentGateway::get(['id', 'name', 'type']);
            return Inertia::render('Admin/Deposits/Index', [
                'deposits' => $deposits->items(),
                'meta' => [
                    'total' => $deposits->total(),
                    'current_page' => $deposits->currentPage(),
                    'per_page' => $deposits->perPage(),
                    'last_page' => $deposits->lastPage(),
                ],
                'stats' => $stats,
                'gateways' => $gateways,
                'filters' => $request->only(['search', 'status', 'gateway', 'type', 'date_from', 'date_to', 'sort_field', 'sort_direction']),
            ]);

        } catch (\Exception $e) {
            return back()->with('error', 'Unable to load deposits. Please try again.');
        }
    }

    /**
     * @param Request $request
     * @param Deposit $deposit
     * @return RedirectResponse
     */
    public function approve(Request $request, Deposit $deposit): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'response' => 'nullable|string|max:1000',
        ]);

        try {
            if (!$deposit->isPending()) {
                return back()->withErrors(['error' => 'Only pending deposits can be approved.']);
            }

            DB::transaction(function () use ($deposit, $request) {
                $user = $deposit->user;
                $wallet = $user->mainWallet;
                $wallet->increment('balance', $deposit->deposit_amount);
                $wallet->update(['last_activity' => now()]);
                $wallet->fresh();

                $roundAmount = round($deposit->amount, 2);
                $deposit->update([
                    'status' => 'approved',
                    'admin_response' => $request->response,
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                ]);

                Transaction::create([
                    'transaction_id' => $deposit->trx,
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'wallet_type' => 'main_wallet',
                    'amount' => $deposit->deposit_amount,
                    'post_balance' => $wallet->balance,
                    'status' => 'completed',
                    'details' => "Deposit via " . e($deposit->paymentGateway->name) . " - {$roundAmount} " . e($deposit->currency)
                ]);

                $this->referralService->processReferral($user->referred_by, $deposit->deposit_amount);
            });

            try {
                EmailTemplateService::sendTemplateEmail('deposit_confirmation', $deposit->user, [
                    'user_name' => e($deposit->user->name),
                    'amount' => round($deposit->deposit_amount, 2),
                    'transaction_id' => e($deposit->trx),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to send deposit confirmation email', [
                    'user_id' => $deposit->user->id,
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage()
                ]);
            }

            return back()->with('success', 'Deposit approved successfully.');
        } catch (\Exception $e) {
            Log::error('Error approving deposit', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Failed to approve deposit. Please try again.');
        }
    }

    /**
     * @param Request $request
     * @param Deposit $deposit
     * @return RedirectResponse
     */
    public function reject(Request $request, Deposit $deposit): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'response' => 'required|string|max:1000',
        ]);

        try {
            if (!$deposit->isPending()) {
                return back()->with('error', 'Only pending deposits can be rejected.');
            }

            $deposit->update([
                'status' => 'rejected',
                'admin_response' => $request->response,
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
            ]);

            return back()->with('success', 'Deposit rejected successfully.');

        } catch (\Exception $e) {
            Log::error('Error rejecting deposit', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Failed to reject deposit. Please try again.');
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
            'deposit_ids' => 'required|array|min:1',
            'deposit_ids.*' => 'exists:deposits,id',
            'response' => 'nullable|string|max:1000',
        ]);

        try {
            $deposits = Deposit::whereIn('id', $request->deposit_ids)
                ->pending()
                ->get();

            if ($deposits->isEmpty()) {
                return back()->with('error', 'No pending deposits found for the selected items.');
            }

            if ($request->action === 'approve') {
                DB::transaction(function () use ($deposits, $request) {
                    foreach ($deposits as $deposit) {
                        $user = $deposit->user;
                        $wallet = $user->mainWallet;
                        $wallet->increment('balance', $deposit->deposit_amount);
                        $wallet->update(['last_activity' => now()]);
                        $wallet->fresh();

                        $roundAmount = round($deposit->amount, 2);
                        $deposit->update([
                            'status' => 'approved',
                            'admin_response' => $request->response,
                            'approved_at' => now(),
                            'approved_by' => Auth::id(),
                        ]);

                        Transaction::create([
                            'transaction_id' => $deposit->trx,
                            'user_id' => $user->id,
                            'type' => 'deposit',
                            'wallet_type' => 'main_wallet',
                            'amount' => $deposit->deposit_amount,
                            'post_balance' => $wallet->balance,
                            'status' => 'completed',
                            'details' => "Deposit via " . e($deposit->paymentGateway->name) . " - {$roundAmount} " . e($deposit->currency)
                        ]);

                        try {
                            EmailTemplateService::sendTemplateEmail('deposit_confirmation', $user, [
                                'user_name' => e($user->name),
                                'amount' => round($deposit->deposit_amount, 2),
                                'transaction_id' => e($deposit->trx),
                            ]);

                            $this->referralService->processReferral($user->referred_by, $deposit->deposit_amount);

                        } catch (Exception $e) {
                            Log::warning('Failed to send deposit confirmation email', [
                                'user_id' => $user->id,
                                'deposit_id' => $deposit->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                });
            } else {
                if (!$request->response) {
                    return back()->with('error', 'Response is required when rejecting deposits.');
                }

                $updateData = [
                    'status' => 'rejected',
                    'admin_response' => $request->response,
                    'rejected_at' => now(),
                    'rejected_by' => Auth::id(),
                ];

                foreach ($deposits as $deposit) {
                    $deposit->update($updateData);
                }
            }

            $action = $request->action === 'approve' ? 'approved' : 'rejected';
            $count = $deposits->count();

            return back()->with('success', "{$count} deposit(s) {$action} successfully.");

        } catch (\Exception $e) {
            Log::error('Error in bulk deposit action', [
                'action' => $request->action,
                'deposit_ids' => $request->deposit_ids,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Failed to process bulk action. Please try again.');
        }
    }

    public function viewFile(Request $request, Deposit $deposit, string $fieldName)
    {
        try {
            $fieldName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fieldName);
            if (!$deposit->payment_details || !is_array($deposit->payment_details)) {
                abort(404, 'No payment details found');
            }

            if (!isset($deposit->payment_details[$fieldName])) {
                abort(404, 'File not found');
            }

            $filePath = $deposit->payment_details[$fieldName];
            return $this->download($filePath);

        } catch (Exception $e) {
            Log::error('Deposit file view error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id ?? null,
                'field_name' => $fieldName ?? null,
                'user_id' => Auth::id()
            ]);

            abort(404, 'File not found');
        }
    }
}
