<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Wallet\Status;
use App\Enums\Wallet\Type;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletsController extends Controller
{

    public function __construct(protected readonly WalletService $walletService){

    }

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {

        $request->validate([
            'search' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'status' => 'nullable|in:0,1,2',
            'type' => 'nullable|in:main,trade',
            'sort_field' => 'nullable|in:name,type,balance,status,created_at',
            'sort_direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|in:10,25,50,100'
        ]);

        try {
            $search = $request->get('search');
            $currency = $request->get('currency');
            $status = $request->get('status');
            $type = $request->get('type');
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $walletsQuery = Wallet::with(['user'])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('currency', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($currency, function ($query, $currency) {
                    $query->where('currency', $currency);
                })
                ->when($status !== null, function ($query) use ($status) {
                    if (is_numeric($status)) {
                        $query->where('status', (int)$status);
                    }
                })
                ->when($type, function ($query, $type) {
                    $query->where('type', $type);
                });

            $allowedSortFields = ['name', 'type', 'balance', 'status', 'created_at'];
            if (in_array($sortField, $allowedSortFields)) {
                $walletsQuery->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
            } else {
                $walletsQuery->orderBy('created_at', 'desc');
            }

            $perPage = (int) $request->get('per_page', 20);
            $wallets = $walletsQuery->paginate($perPage)->appends($request->all());
            $walletsData = $wallets->through(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'type' => $wallet->type,
                    'currency' => $wallet->currency,
                    'address' => $wallet->address,
                    'balance' => (float) $wallet->balance,
                    'status' => $wallet->status,
                    'last_activity' => $wallet->last_activity?->toISOString(),
                    'created_at' => $wallet->created_at->toISOString(),
                    'user' => $wallet->user ? [
                        'id' => $wallet->user->id,
                        'name' => $wallet->user->name,
                        'email' => $wallet->user->email,
                    ] : null,
                ];
            });

            $stats = [
                'total_wallets' => Wallet::count(),
                'active_wallets' => Wallet::where('status', Status::ACTIVE->value)->count(),
                'main_wallets' => Wallet::where('type', Type::MAIN->value)->count(),
                'trade_wallets' => Wallet::where('type', Type::TRADE->value)->count(),
            ];

            return Inertia::render('Admin/Wallets/Index', [
                'wallets' => $walletsData,
                'meta' => [
                    'total' => $walletsData->total(),
                    'current_page' => $walletsData->currentPage(),
                    'per_page' => $walletsData->perPage(),
                    'last_page' => $walletsData->lastPage(),
                ],
                'filters' => [
                    'search' => $search,
                    'currency' => $currency,
                    'status' => $status,
                    'type' => $type,
                    'sort_field' => $sortField,
                    'sort_direction' => $sortDirection,
                ],
                'wallet_types' => [
                    ['value' => Type::MAIN->value, 'label' => Type::MAIN->label()],
                    ['value' => Type::TRADE->value, 'label' => Type::TRADE->label()],
                ],
                'stats' => $stats,
                'currentUser' => auth()->user() ? [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                    'role' => auth()->user()->role ?? 'user',
                ] : null,
            ]);

        } catch (\Exception $e) {
            dd($e->getMessage());
            Log::error('Error loading wallets', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' =>  'Unable to load wallets. Please try again.']);
        }
    }

    /**
     * Update wallet status
     * @param Request $request
     * @param Wallet $wallet
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, Wallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', Rule::in([Status::INACTIVE->value, Status::ACTIVE->value, Status::LOCKED->value])]
        ]);

        try {
            $wallet->update(['status' => $validated['status']]);
            return redirect()->back()->with('success', 'Wallet status updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating wallet status', [
                'wallet_id' => $wallet->id,
                'status' => $validated['status'],
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->withErrors(['error' => 'Failed to update wallet status. Please try again.']);
        }
    }

    /**
     * Adjust wallet balance
     *
     * @param Request $request
     * @param Wallet $wallet
     * @return RedirectResponse
     */
    public function adjustBalance(Request $request, Wallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:255']
        ]);

        try {
            DB::transaction(function () use ($wallet, $validated) {
                $currentBalance = $wallet->balance;
                $amount = $validated['amount'];
                $action = $validated['action'];

                if ($action === 'add') {
                    $newBalance = $currentBalance + $amount;
                } else {
                    $newBalance = $currentBalance - $amount;
                    if ($newBalance < 0) {
                        throw new \Exception('Insufficient balance for this operation!');
                    }
                }

                $wallet->update([
                    'balance' => $newBalance,
                    'last_activity' => now()
                ]);

                Transaction::create([
                    'transaction_id' => Str::random(),
                    'user_id' => $wallet->user_id,
                    'type' => $action === 'add' ? 'deposit' : 'withdrawal',
                    'wallet_type' => $wallet->type === Type::MAIN->value ? 'main_wallet' : 'trade_wallet',
                    'amount' => $amount,
                    'post_balance' => $newBalance,
                    'status' => 'completed',
                    'details' => $validated['reason'] ?? "Admin {$action} balance adjustment"
                ]);
            });

            return redirect()->back()->with('success', ucfirst($validated['action']) . ' balance operation completed successfully!');

        } catch (\Exception $e) {
            Log::error('Error adjusting wallet balance', [
                'wallet_id' => $wallet->id,
                'action' => $validated['action'],
                'amount' => $validated['amount'],
                'error' => $e->getMessage()
            ]);

            if ($e->getMessage() === 'Insufficient balance for this operation!') {
                return redirect()->back()->withErrors(['error' =>  'Insufficient balance for this operation!']);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to adjust wallet balance. Please try again.']);
        }
    }
}
