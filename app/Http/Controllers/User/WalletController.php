<?php

namespace App\Http\Controllers\User;

use App\Enums\Wallet\Status;
use App\Enums\Wallet\Type;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    /**
     * @return RedirectResponse|Response
     */
    public function index(): Response|RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'wallet-index:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 50)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->with('error', "Too many requests. Please try again in {$seconds} seconds.");
            }

            $mainWallet = Wallet::firstOrCreate([
                'user_id' => $user->id,
                'type' => Type::MAIN->value,
            ], [
                'name' => 'Main USDT Wallet',
                'currency' => 'USD',
                'address' => 'main_' . bin2hex(random_bytes(20)),
                'balance' => (float) config('wallet.default_main_balance', 0.00),
                'status' => Status::ACTIVE->value,
                'last_activity' => now(),
            ]);

            $tradeWallet = Wallet::firstOrCreate([
                'user_id' => $user->id,
                'type' => Type::TRADE->value,
            ], [
                'name' => 'Trade Wallet',
                'currency' => 'USD',
                'address' => 'trade_' . bin2hex(random_bytes(20)),
                'balance' => 0,
                'status' => Status::ACTIVE->value,
                'last_activity' => now(),
            ]);

            $mainWallet->name = e($mainWallet->name);
            $mainWallet->currency = e($mainWallet->currency);
            $mainWallet->address = e($mainWallet->address);

            $tradeWallet->name = e($tradeWallet->name);
            $tradeWallet->currency = e($tradeWallet->currency);
            $tradeWallet->address = e($tradeWallet->address);

            $statistics = [
                'total_balance' => (float) ($mainWallet->balance + $tradeWallet->balance),
                'main_balance' => (float) $mainWallet->balance,
                'trade_balance' => (float) $tradeWallet->balance,
                'wallet_count' => 2,
            ];

            RateLimiter::hit($key, 60);
            return Inertia::render('User/Wallet/Index', [
                'mainWallet' => $mainWallet,
                'tradeWallet' => $tradeWallet,
                'statistics' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Wallet index failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Failed to load wallet data. Please try again.');
        }
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function transfer(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'wallet-transfer:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many transfer attempts. Please try again in {$seconds} seconds."]);
            }

            $validator = Validator::make($request->all(), [
                'from_type' => ['required', Rule::in(['main', 'trade'])],
                'to_type' => ['required', Rule::in(['main', 'trade']), 'different:from_type'],
                'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            ], [
                'from_type.required' => 'Source wallet type is required.',
                'from_type.in' => 'Invalid source wallet type.',
                'to_type.required' => 'Destination wallet type is required.',
                'to_type.in' => 'Invalid destination wallet type.',
                'to_type.different' => 'Source and destination wallets must be different.',
                'amount.required' => 'Transfer amount is required.',
                'amount.numeric' => 'Transfer amount must be a valid number.',
                'amount.min' => 'Minimum transfer amount is $0.01.',
                'amount.max' => 'Maximum transfer amount exceeded.',
            ]);

            if ($validator->fails()) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $amount = round((float) $request->amount, 2);
            if ($amount <= 0) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['amount' => 'Invalid transfer amount.']);
            }

            if ($amount <= 0) {
                RateLimiter::hit($key, 60);
                return redirect()->back()->withErrors(['amount' => 'Invalid transfer amount.']);
            }

            DB::beginTransaction();
            try {
                $fromWallet = Wallet::where('user_id', $user->id)
                    ->where('type', $request->from_type)
                    ->where('status', Status::ACTIVE->value)
                    ->first();

                $toWallet = Wallet::where('user_id', $user->id)
                    ->where('type', $request->to_type)
                    ->where('status', Status::ACTIVE->value)
                    ->first();

                if (!$fromWallet) {
                    throw new \Exception('Source wallet not found or inactive.');
                }

                if (!$toWallet) {
                    throw new \Exception('Destination wallet not found or inactive.');
                }

                if ($fromWallet->balance < $amount) {
                    throw new \Exception('Insufficient balance in source wallet.');
                }

                $fromNewBalance = round($fromWallet->balance - $amount, 2);
                $toNewBalance = round($toWallet->balance + $amount, 2);

                $fromWallet->update([
                    'balance' => $fromNewBalance,
                    'last_activity' => now(),
                ]);

                $toWallet->update([
                    'balance' => $toNewBalance,
                    'last_activity' => now(),
                ]);

                Transaction::create([
                    'transaction_id' => Str::random(16),
                    'user_id' => $user->id,
                    'type' => 'debit',
                    'wallet_type' => $request->from_type . '_wallet',
                    'amount' => $amount,
                    'post_balance' => $fromNewBalance,
                    'status' => 'completed',
                    'details' => "Transfer from " . e($fromWallet->name) . " to " . e($toWallet->name),
                ]);

                Transaction::create([
                    'transaction_id' => Str::random(16),
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'wallet_type' => $request->to_type . '_wallet',
                    'amount' => $amount,
                    'post_balance' => $toNewBalance,
                    'status' => 'completed',
                    'details' => "Transfer from " . e($fromWallet->name) . " to " . e($toWallet->name),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();
                RateLimiter::clear($key);

                Log::info('Wallet transfer completed', [
                    'user_id' => $user->id,
                    'amount' => $amount
                ]);

                $currencySymbol = Setting::get('default_currency', '$');
                return redirect()->back()->with('success',
                    "Successfully transferred {$currencySymbol}" . number_format($amount, 2) . " from " . e($fromWallet->name) . " to " . e($toWallet->name) . ".");

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Wallet transfer failed', [
                'user_id' => Auth::id(),
                'from_type' => $request->from_type ?? 'unknown',
                'to_type' => $request->to_type ?? 'unknown',
                'amount' => $request->amount ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            RateLimiter::hit($key ?? 'wallet-transfer:' . Auth::id(), 60);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function transferToUser(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'user-transfer:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many transfer attempts. Please try again in {$seconds} seconds."]);
            }

            $validator = Validator::make($request->all(), [
                'from_wallet_type' => ['required', Rule::in(['main', 'trade'])],
                'to_user_id' => ['required', 'integer', 'min:1', 'exists:users,id', 'different:' . Auth::id()],
                'to_wallet_type' => ['required', Rule::in(['main', 'trade'])],
                'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
                'note' => ['nullable', 'string', 'max:500'],
            ], [
                'from_wallet_type.required' => 'Source wallet type is required.',
                'from_wallet_type.in' => 'Invalid source wallet type.',
                'to_user_id.required' => 'Recipient user is required.',
                'to_user_id.exists' => 'Recipient user not found.',
                'to_user_id.different' => 'Cannot transfer to yourself.',
                'to_wallet_type.required' => 'Recipient wallet type is required.',
                'to_wallet_type.in' => 'Invalid recipient wallet type.',
                'amount.required' => 'Transfer amount is required.',
                'amount.numeric' => 'Transfer amount must be a valid number.',
                'amount.min' => 'Minimum transfer amount is $0.01.',
                'amount.max' => 'Maximum transfer amount exceeded.',
                'note.max' => 'Note cannot exceed 500 characters.',
            ]);

            if ($validator->fails()) {
                RateLimiter::hit($key, 300);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            if ($user->kyc_status !== 'approved') {
                RateLimiter::hit($key, 300);
                return redirect()->back()->withErrors([
                    'kyc_status' => 'Your KYC verification must be approved before you can proceed with this transaction.'
                ]);
            }

            $amount = round((float) $request->amount, 2);
            $note = $request->note ? strip_tags(trim($request->note)) : null;

            if ($amount <= 0) {
                RateLimiter::hit($key, 300);
                return redirect()->back()->withErrors(['amount' => 'Invalid transfer amount.']);
            }

            DB::beginTransaction();
            try {
                $recipient = User::where('id', $request->to_user_id)
                    ->where('status', \App\Enums\User\Status::ACTIVE->value)
                    ->first();

                if (!$recipient) {
                    throw new \Exception('Recipient user not found or inactive.');
                }

                $fromWallet = Wallet::where('user_id', $user->id)
                    ->where('type', $request->from_wallet_type)
                    ->where('status', Status::ACTIVE->value)
                    ->lockForUpdate()
                    ->first();

                if (!$fromWallet) {
                    throw new \Exception('Your wallet not found or inactive.');
                }

                if ($fromWallet->balance < $amount) {
                    throw new \Exception('Insufficient balance in your wallet.');
                }

                $toWallet = Wallet::firstOrCreate([
                    'user_id' => $request->to_user_id,
                    'type' => $request->to_wallet_type,
                ], [
                    'name' => ucfirst($request->to_wallet_type) . ' USDT Wallet',
                    'currency' => 'USD',
                    'address' => $request->to_wallet_type . '_' . bin2hex(random_bytes(20)),
                    'balance' => 0.00,
                    'status' => Status::ACTIVE->value,
                    'last_activity' => now(),
                ]);

                $toWallet = Wallet::where('id', $toWallet->id)
                    ->lockForUpdate()
                    ->first();

                $fromNewBalance = round($fromWallet->balance - $amount, 2);
                $toNewBalance = round($toWallet->balance + $amount, 2);
                $fromWallet->update([
                    'balance' => $fromNewBalance,
                    'last_activity' => now(),
                ]);

                $toWallet->update([
                    'balance' => $toNewBalance,
                    'last_activity' => now(),
                ]);

                Transaction::create([
                    'transaction_id' => Str::random(16),
                    'user_id' => $user->id,
                    'type' => 'withdrawal',
                    'wallet_type' => $request->from_wallet_type . '_wallet',
                    'amount' => $amount,
                    'post_balance' => $fromNewBalance,
                    'status' => 'completed',
                    'details' => "Sent to " . e($recipient->name) . " (" . e($recipient->email) . ")" .
                        ($note ? " - Note: " . e($note) : ""),
                ]);

                $transaction = Transaction::create([
                    'transaction_id' => Str::random(16),
                    'user_id' => $request->to_user_id,
                    'type' => 'deposit',
                    'wallet_type' => $request->to_wallet_type . '_wallet',
                    'amount' => $amount,
                    'post_balance' => $toNewBalance,
                    'status' => 'completed',
                    'details' => "Received from " . e($user->name) . " (" . e($user->email) . ")" .
                        ($note ? " - Note: " . e($note) : ""),
                ]);

                try {
                    EmailTemplateService::sendTemplateEmail('balance_transfer', $recipient, [
                        'user_name' => e($recipient->name),
                        'amount' => number_format($amount, 2),
                        'transaction_id' => e($transaction->transaction_id),
                    ]);
                } catch (\Exception $emailError) {
                    Log::warning('Email notification failed', [
                        'transaction_id' => $transaction->transaction_id,
                        'recipient_id' => $recipient->id,
                        'error' => $emailError->getMessage()
                    ]);
                }

                DB::commit();
                RateLimiter::clear($key);
                Log::info('User transfer completed', [
                    'sender_id' => $user->id,
                    'recipient_id' => $recipient->id,
                    'amount' => $amount
                ]);

                return redirect()->back()->with('success',
                    "Successfully transferred $" . number_format($amount, 2) . " to " . e($recipient->name) . ".");

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('User transfer failed', [
                'sender_id' => Auth::id(),
                'recipient_id' => $request->to_user_id ?? 'unknown',
                'from_wallet_type' => $request->from_wallet_type ?? 'unknown',
                'to_wallet_type' => $request->to_wallet_type ?? 'unknown',
                'amount' => $request->amount ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            RateLimiter::hit($key ?? 'user-transfer:' . Auth::id(), 300);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $key = 'user-search:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 30)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json(['error' => "Too many search attempts. Please try again in {$seconds} seconds."], 429);
            }

            $validator = Validator::make($request->all(), [
                'search' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-zA-Z0-9@._\s-]+$/'],
            ], [
                'search.required' => 'Search term is required.',
                'search.string' => 'Search term must be a string.',
                'search.min' => 'Search term must be at least 2 characters.',
                'search.max' => 'Search term cannot exceed 100 characters.',
                'search.regex' => 'Search term contains invalid characters.',
            ]);

            if ($validator->fails()) {
                RateLimiter::hit($key, 60);
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $searchTerm = strip_tags(trim($request->search));
            if (strlen($searchTerm) < 2) {
                RateLimiter::hit($key, 60);
                return response()->json(['error' => 'Search term too short.'], 422);
            }

            $users = User::where('id', '!=', Auth::id())
                ->where('status', \App\Enums\User\Status::ACTIVE->value)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('email', 'like', '%' . $searchTerm . '%');
                })
                ->select('id', 'name', 'email', 'created_at')
                ->limit(10)
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => e($user->name),
                        'email' => e($user->email),
                        'member_since' => $user->created_at->format('M Y'),
                    ];
                });

            RateLimiter::hit($key, 60);
            Log::info('User search results', ['search' => $users]);
            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('User search failed', [
                'search_term' => $request->search ?? 'N/A',
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Search failed. Please try again.'], 500);
        }
    }
}
