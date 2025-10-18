<?php

namespace App\Http\Controllers\User;

use App\Concerns\UploadedFile;
use App\Enums\Wallet\Type;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    use UploadedFile;
    private const MAX_FILE_SIZE = 5120;

    private const ALLOWED_FILE_TYPES = ['jpeg', 'png', 'jpg', 'gif', 'pdf'];

    public function index(): Response
    {
        try {
            $user = Auth::user();
            $key = 'withdrawal-index:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 50)) {
                $seconds = RateLimiter::availableIn($key);
                return Inertia::render('User/Wallet/Withdrawal', [
                    'withdrawals' => ['data' => [], 'total' => 0],
                    'withdrawalGateways' => [],
                    'walletBalance' => 0
                ])->with('error', "Too many requests. Please try again in {$seconds} seconds.");
            }

            $withdrawalGateways = WithdrawalGateway::where('status', true)
                ->orderBy('name')
                ->get()
                ->map(function ($gateway) {
                    $gateway->name = e($gateway->name);
                    $gateway->description = e($gateway->description ?? '');
                    $gateway->currency = e($gateway->currency);
                    $gateway->min_amount = (float) $gateway->min_amount;
                    $gateway->max_amount = (float) $gateway->max_amount;
                    $gateway->fixed_charge = (float) $gateway->fixed_charge;
                    $gateway->percent_charge = (float) $gateway->percent_charge;
                    $gateway->rate = (float) $gateway->rate;
                    return $gateway;
                });

            $withdrawals = Withdrawal::with('withdrawalGateway')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $withdrawals->getCollection()->transform(function ($withdrawal) {
                $withdrawal->trx = e($withdrawal->trx);
                $withdrawal->currency = e($withdrawal->currency);
                $withdrawal->status = e($withdrawal->status);
                if ($withdrawal->withdrawalGateway) {
                    $withdrawal->withdrawalGateway->name = e($withdrawal->withdrawalGateway->name);
                }
                return $withdrawal;
            });

            $mainWallet = $user->wallets()->where('type', Type::MAIN->value)->first();
            RateLimiter::hit($key, 60);
            return Inertia::render('User/Wallet/Withdrawal', [
                'withdrawals' => $withdrawals,
                'withdrawalGateways' => $withdrawalGateways,
                'walletBalance' => $mainWallet->balance ?? 0,
            ]);
        } catch (Exception $e) {
            Log::error('Withdrawal Index Error: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return Inertia::render('User/Wallet/Withdrawal', [
                'withdrawals' => ['data' => [], 'total' => 0],
                'withdrawalGateways' => [],
                'walletBalance' => 0
            ])->with('error', 'Unable to load withdrawal page. Please try again.');
        }
    }

    /**
     * Store a new withdrawal request
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'withdrawal-create:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many withdrawal attempts. Please try again in {$seconds} seconds."]);
            }

            $isKYCRequired = Setting::get('kyc_status', true);
            if ($isKYCRequired && $user->kyc_status !== 'approved') {
                RateLimiter::hit($key, 300);

                $kycMessage = match($user->kyc_status) {
                    'pending' => 'Please complete your KYC verification before making withdrawals.',
                    'reviewing' => 'Your KYC verification is under review. Withdrawals will be available once approved.',
                    'rejected' => 'Your KYC verification was rejected. Please resubmit your documents.',
                    default => 'KYC verification is required for withdrawals.'
                };

                return redirect()->back()->withErrors(['error' => $kycMessage]);
            }

            $is2FARequired = Setting::get('two_factor_auth', false);
            if ($is2FARequired) {
                if (empty($user->two_factor_secret) || empty($user->two_factor_confirmed_at)) {
                    RateLimiter::hit($key, 300);
                    return redirect()->back()->withErrors([
                        'error' => 'Two-Factor Authentication is required for withdrawals. Please enable 2FA in your security settings.'
                    ]);
                }
            }

            $validated = $request->validate([
                'withdrawal_gateway_id' => [
                    'required',
                    'integer',
                    'min:1',
                    'exists:withdrawal_gateways,id'
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:999999999'
                ],
                'withdrawal_details' => 'required|array|max:20',
            ], [
                'withdrawal_gateway_id.required' => 'Please select a withdrawal gateway.',
                'withdrawal_gateway_id.exists' => 'Selected withdrawal gateway is invalid.',
                'amount.required' => 'Please enter a withdrawal amount.',
                'amount.numeric' => 'Withdrawal amount must be a valid number.',
                'amount.min' => 'Withdrawal amount must be greater than 0.',
                'amount.max' => 'Withdrawal amount is too large.',
                'withdrawal_details.required' => 'Please provide withdrawal details.',
                'withdrawal_details.array' => 'Invalid withdrawal details format.',
                'withdrawal_details.max' => 'Too many withdrawal details provided.'
            ]);

            $gateway = WithdrawalGateway::where('id', $validated['withdrawal_gateway_id'])
                ->where('status', true)
                ->first();

            if (!$gateway) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'withdrawal_gateway_id' => ['Selected withdrawal gateway is not available.']
                ]);
            }

            $requestedAmountGateway = round((float) $validated['amount'], 2);
            $minAmount = (float) $gateway->min_amount;
            $maxAmount = (float) $gateway->max_amount;

            if ($requestedAmountGateway < $minAmount) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ["Minimum withdrawal amount is " . e($gateway->currency) . " {$minAmount}"]
                ]);
            }

            if ($requestedAmountGateway > $maxAmount) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ["Maximum withdrawal amount is " . e($gateway->currency) . " {$maxAmount}"]
                ]);
            }

            $mainWallet = $user->mainWallet;
            if (!$mainWallet) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'error' => ['Wallet not found. Please contact support.']
                ]);
            }

            $chargeSite = $this->calculateCharge($requestedAmountGateway, $gateway);
            $finalAmount = round($requestedAmountGateway - $chargeSite, 2);
            $convertFinalAmount = $this->convertToGatewayCurrency($requestedAmountGateway, $gateway);
            $withdrawalAmount = $this->convertToGatewayCurrency($finalAmount, $gateway);
            if ($convertFinalAmount > $mainWallet->balance) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient balance for this withdrawal including fees.']
                ]);
            }

            if ($convertFinalAmount <= 0) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ['Amount too small after deducting fees.']
                ]);
            }

            if ($convertFinalAmount > 999999999) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ['Total withdrawal amount including fees exceeds maximum limit.']
                ]);
            }

            $processedDetails = $this->validateAndProcessGatewayFields($validated['withdrawal_details'], $gateway);
            DB::beginTransaction();

            try {
                $trx = 'WD' . strtoupper(bin2hex(random_bytes(5)));
                $attempts = 0;
                while (Withdrawal::where('trx', $trx)->exists() && $attempts < 10) {
                    $trx = 'WD' . strtoupper(bin2hex(random_bytes(5)));
                    $attempts++;
                }

                if ($attempts >= 10) {
                    throw new Exception('Unable to generate unique transaction ID');
                }

                $withdrawal = Withdrawal::create([
                    'user_id' => $user->id,
                    'withdrawal_gateway_id' => $gateway->id,
                    'trx' => $trx,
                    'amount' => $request->input('amount'),
                    'charge' => $chargeSite,
                    'final_amount' => $finalAmount,
                    'withdrawal_amount' => $withdrawalAmount,
                    'currency' => strtoupper($gateway->currency),
                    'status' => 'pending',
                    'user_data' => $processedDetails,
                    'conversion_rate' => $gateway->rate,
                ]);

                $this->deductFromWallet($user, $withdrawal);
                DB::commit();
                RateLimiter::clear($key);
                return redirect()->back()->with('success', 'Withdrawal request submitted successfully! Transaction ID: ' . e($trx));
            } catch (Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            Log::error('Withdrawal Store Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->except(['withdrawal_details'])
            ]);

            RateLimiter::hit($key ?? 'withdrawal-create:' . Auth::id(), 300);
            return redirect()->back()
                ->withErrors(['error' => 'Unable to process your withdrawal request. Please try again or contact support.'])
                ->withInput();
        }
    }


    /**
     * @param Withdrawal $withdrawal
     * @return RedirectResponse
     */
    public function cancel(Withdrawal $withdrawal): RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'withdrawal-cancel:' . $user->id . ':' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many cancellation attempts. Please try again in {$seconds} seconds."]);
            }

            if ($withdrawal->user_id !== $user->id) {
                RateLimiter::hit($key, 300);
                abort(403, 'Unauthorized access to withdrawal');
            }

            if ($withdrawal->status !== 'pending') {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'error' => ['Only pending withdrawals can be cancelled.']
                ]);
            }

            DB::beginTransaction();

            try {
                $withdrawal->update([
                    'status' => 'rejected',
                    'admin_response' => 'Cancelled by user',
                    'rejected_at' => now(),
                    'rejected_by' => $user->id,
                ]);

                $this->refundToWallet($withdrawal->user, $withdrawal);
                DB::commit();
                RateLimiter::clear($key);

                return redirect()->back()->with('success', 'Withdrawal cancelled successfully. Amount refunded to your wallet.');

            } catch (Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (Exception $e) {
            Log::error('Withdrawal Cancellation Error: ' . $e->getMessage(), [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => Auth::id()
            ]);

            RateLimiter::hit($key ?? 'withdrawal-cancel:' . Auth::id(), 300);
            return redirect()->back()
                ->withErrors(['error' => 'Unable to cancel withdrawal. Please try again or contact support.']);
        }
    }

    /**
     * @param $trx
     * @return Response
     */
    public function details($trx): Response
    {
        try {
            $trx = strip_tags($trx);
            if (strlen($trx) > 50 || !preg_match('/^[A-Z0-9]+$/', $trx)) {
                abort(404, 'Invalid transaction ID format');
            }

            $withdrawal = Withdrawal::where('trx', $trx)
                ->where('user_id', Auth::id())
                ->with('withdrawalGateway')
                ->firstOrFail();

            $withdrawal->trx = e($withdrawal->trx);
            $withdrawal->currency = e($withdrawal->currency);
            $withdrawal->status = e($withdrawal->status);
            if ($withdrawal->withdrawalGateway) {
                $withdrawal->withdrawalGateway->name = e($withdrawal->withdrawalGateway->name);
            }

            return Inertia::render('User/Wallet/WithdrawalDetails', [
                'withdrawal' => $withdrawal
            ]);
        } catch (Exception $e) {
            Log::error('Withdrawal Details Error: ' . $e->getMessage(), [
                'trx' => $trx,
                'user_id' => Auth::id()
            ]);

            abort(404, 'Withdrawal not found');
        }
    }

    /**
     * @param array $details
     * @param WithdrawalGateway $gateway
     * @return array
     * @throws ValidationException
     */
    private function validateAndProcessGatewayFields(array $details, WithdrawalGateway $gateway): array
    {
        if (!$gateway->parameters || !is_array($gateway->parameters)) {
            return [];
        }

        if (count($gateway->parameters) > 20) {
            throw new Exception('Too many gateway parameters');
        }

        $rules = [];
        $messages = [];
        $processedDetails = [];

        foreach ($gateway->parameters as $param) {
            if (!isset($param['field_name']) || !is_string($param['field_name'])) {
                continue;
            }

            $fieldName = strip_tags($param['field_name']);
            if (strlen($fieldName) > 50) {
                continue;
            }

            $ruleKey = "withdrawal_details.{$fieldName}";

            if (isset($param['field_required']) && $param['field_required']) {
                $rules[$ruleKey] = 'required';
                $messages["{$ruleKey}.required"] = "The " . e($param['field_label'] ?? $fieldName) . " field is required.";
            }

            switch ($param['field_type'] ?? 'text') {
                case 'email':
                    $rules[$ruleKey] = ($param['field_required'] ? 'required|' : 'nullable|') . 'email|max:255';
                    $messages["{$ruleKey}.email"] = "Please enter a valid email address.";
                    $messages["{$ruleKey}.max"] = "The " . e($param['field_label'] ?? $fieldName) . " must not exceed 255 characters.";
                    break;

                case 'number':
                    $rules[$ruleKey] = ($param['field_required'] ? 'required|' : 'nullable|') . 'numeric|min:0|max:999999999';
                    $messages["{$ruleKey}.numeric"] = "The " . e($param['field_label'] ?? $fieldName) . " must be a number.";
                    $messages["{$ruleKey}.min"] = "The " . e($param['field_label'] ?? $fieldName) . " must be at least 0.";
                    $messages["{$ruleKey}.max"] = "The " . e($param['field_label'] ?? $fieldName) . " is too large.";
                    break;

                case 'select':
                    if (isset($param['field_options']) && is_array($param['field_options']) && count($param['field_options']) <= 50) {
                        $options = array_map('strip_tags', array_keys($param['field_options']));
                        $rules[$ruleKey] = ($param['field_required'] ? 'required|' : 'nullable|') . 'in:' . implode(',', $options);
                        $messages["{$ruleKey}.in"] = "Please select a valid " . e($param['field_label'] ?? $fieldName) . ".";
                    }
                    break;

                case 'file':
                    if ($param['field_required']) {
                        $rules[$ruleKey] = 'required|file|mimes:' . implode(',', self::ALLOWED_FILE_TYPES) . '|max:' . self::MAX_FILE_SIZE;
                    } else {
                        $rules[$ruleKey] = 'nullable|file|mimes:' . implode(',', self::ALLOWED_FILE_TYPES) . '|max:' . self::MAX_FILE_SIZE;
                    }
                    $messages["{$ruleKey}.file"] = "Please upload a valid file.";
                    $messages["{$ruleKey}.mimes"] = "File must be an image or PDF.";
                    $messages["{$ruleKey}.max"] = "File size must not exceed 5MB.";
                    break;

                case 'date':
                    $rules[$ruleKey] = ($param['field_required'] ? 'required|' : 'nullable|') . 'date|after:1900-01-01|before:2100-01-01';
                    $messages["{$ruleKey}.date"] = "Please enter a valid date.";
                    $messages["{$ruleKey}.after"] = "Date must be after 1900.";
                    $messages["{$ruleKey}.before"] = "Date must be before 2100.";
                    break;

                case 'tel':
                    $rules[$ruleKey] = ($param['field_required'] ? 'required|' : 'nullable|') . 'string|max:20|regex:/^[\+\-\(\)\s\d]+$/';
                    $messages["{$ruleKey}.regex"] = "Please enter a valid phone number.";
                    $messages["{$ruleKey}.max"] = "Phone number must not exceed 20 characters.";
                    break;

                case 'text':
                default:
                    $baseRule = 'string|max:255';
                    if ($param['field_required']) {
                        $rules[$ruleKey] = 'required|' . $baseRule;
                    } else {
                        $rules[$ruleKey] = 'nullable|' . $baseRule;
                    }
                    $messages["{$ruleKey}.string"] = "The " . e($param['field_label'] ?? $fieldName) . " must be text.";
                    $messages["{$ruleKey}.max"] = "The " . e($param['field_label'] ?? $fieldName) . " must not exceed 255 characters.";
                    break;
            }
        }

        if (!empty($rules)) {
            request()->validate($rules, $messages);
        }

        foreach ($gateway->parameters as $param) {
            if (!isset($param['field_name'])) {
                continue;
            }

            $fieldName = strip_tags($param['field_name']);
            $value = $details[$fieldName] ?? null;

            if ($param['field_type'] === 'file' && request()->hasFile("withdrawal_details.{$fieldName}")) {
                try {
                    $file = request()->file("withdrawal_details.{$fieldName}");
                    $extension = strtolower($file->getClientOriginalExtension());

                    if (!in_array($extension, self::ALLOWED_FILE_TYPES)) {
                        throw new Exception('Invalid file type');
                    }

                    $processedDetails[$fieldName] = $this->move($file);
                } catch (Exception $e) {
                    Log::error('File upload error: ' . $e->getMessage());
                    throw ValidationException::withMessages([
                        "withdrawal_details.{$fieldName}" => 'Failed to upload file. Please try again.'
                    ]);
                }
            } elseif ($value !== null && $value !== '') {
                if (is_string($value)) {
                    $processedDetails[$fieldName] = htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
                } elseif (is_numeric($value)) {
                    $processedDetails[$fieldName] = (float) $value;
                } else {
                    $processedDetails[$fieldName] = $value;
                }
            }
        }

        return $processedDetails;
    }

    /**
     * @param float $amount
     * @param WithdrawalGateway $gateway
     * @return float
     * @throws Exception
     */
    private function calculateCharge(float $amount, WithdrawalGateway $gateway): float
    {
        try {
            $fixedCharge = (float) ($gateway->fixed_charge ?? 0);
            $percentCharge = (float) ($gateway->percent_charge ?? 0);
            if ($fixedCharge < 0 || $percentCharge < 0 || $percentCharge > 100) {
                throw new Exception('Invalid gateway charge configuration');
            }

            $charge = $fixedCharge + ($amount * $percentCharge / 100);
            return round($charge, 2);
        } catch (Exception $e) {
            Log::error('Charge calculation error: ' . $e->getMessage());
            throw new Exception('Unable to calculate withdrawal charges');
        }
    }

    /**
     * @param $amount
     * @param $gateway
     * @return float
     * @throws Exception
     */
    private function convertToGatewayCurrency($amount, $gateway): float
    {
        try {
            if (!is_numeric($amount) || $amount < 0) {
                throw new Exception('Invalid amount for conversion');
            }

            $rate = (float) $gateway->rate;
            if ($rate <= 0) {
                throw new Exception('Invalid gateway conversion rate');
            }

            $convertedAmount = $amount * $rate;
            return round($convertedAmount, 2);
        } catch (Exception $e) {
            Log::error('Currency conversion error: ' . $e->getMessage());
            throw new Exception('Unable to convert currency');
        }
    }

    /**
     * @param $user
     * @param $withdrawal
     * @return void
     * @throws Exception
     */
    private function deductFromWallet($user, $withdrawal): void
    {
        try {
            $wallet = $user->wallets()->where('type', Type::MAIN->value)->first();
            if (!$wallet) {
                throw new Exception('Wallet not found');
            }

            $deductionAmount = (float) $withdrawal->withdrawal_amount;
            if ($deductionAmount <= 0) {
                throw new Exception('Invalid deduction amount');
            }

            $previousBalance = (float) $wallet->balance;
            $newBalance = round($previousBalance - $deductionAmount, 2);

            if ($newBalance < 0) {
                throw new Exception('Insufficient wallet balance');
            }

            $wallet->update([
                'balance' => $newBalance,
                'last_activity' => now()
            ]);

            $finalRoundAmount = round($withdrawal->final_amount, 2);
            Transaction::create([
                'transaction_id' => Str::random(16),
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'wallet_type' => 'main_wallet',
                'amount' => $deductionAmount,
                'post_balance' => $newBalance,
                'status' => 'completed',
                'details' => "Withdrawal request via " . e($withdrawal->withdrawalGateway->name) . " - {$finalRoundAmount} " . e($withdrawal->currency)
            ]);

            try {
                EmailTemplateService::sendTemplateEmail('withdrawal_request', $user, [
                    'user_name' => e($user->name),
                    'amount' => round($withdrawal->withdrawal_amount, 2),
                    'transaction_id' => e($withdrawal->trx),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to send withdrawal request email', [
                    'user_id' => $user->id,
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage()
                ]);
            }
        } catch (Exception $e) {
            Log::error('Wallet deduction error: ' . $e->getMessage());
            throw $e;
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
        try {
            $wallet = $user->wallets()->where('type', Type::MAIN->value)->first();

            if (!$wallet) {
                throw new Exception('Wallet not found');
            }

            $refundAmount = (float) $withdrawal->withdrawal_amount;
            if ($refundAmount <= 0) {
                throw new Exception('Invalid refund amount');
            }

            $previousBalance = (float) $wallet->balance;
            $newBalance = round($previousBalance + $refundAmount, 2);

            $wallet->update([
                'balance' => $newBalance,
                'last_activity' => now()
            ]);

            $roundAmount = round($withdrawal->amount, 2);
            Transaction::create([
                'transaction_id' =>  Str::random(16),
                'user_id' => $user->id,
                'type' => 'refund',
                'wallet_type' => 'main_wallet',
                'amount' => $refundAmount,
                'post_balance' => $newBalance,
                'status' => 'completed',
                'details' => "Refund for cancelled withdrawal " . e($withdrawal->trx) . " - {$roundAmount} " . e($withdrawal->currency)
            ]);

            try {
                EmailTemplateService::sendTemplateEmail('withdrawal_cancelled', $user, [
                    'user_name' => e($user->name),
                    'amount' => round($withdrawal->withdrawal_amount, 2),
                    'transaction_id' => e($withdrawal->trx),
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to send withdrawal cancellation email', [
                    'user_id' => $user->id,
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage()
                ]);
            }
        } catch (Exception $e) {
            Log::error('Wallet refund error: ' . $e->getMessage());
            throw $e;
        }
    }
}
