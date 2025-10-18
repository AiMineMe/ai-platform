<?php

namespace App\Http\Controllers\User;

use App\Concerns\UploadedFile;
use App\Enums\Wallet\Type;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Http;

class DepositController extends Controller
{
    use UploadedFile;
    private const MAX_FILE_SIZE = 5120;

    private const ALLOWED_FILE_TYPES = ['jpeg', 'png', 'jpg', 'gif', 'pdf'];

    public function __construct(protected readonly ReferralService  $referralService){

    }


    /**
     * @return Response
     */
    public function index(): Response
    {
        try {
            $user = Auth::user();

            $paymentGateways = PaymentGateway::where('status', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($gateway) {
                    $gateway->name = e($gateway->name);
                    $gateway->description = e($gateway->description ?? '');
                    $gateway->min_amount = (float) $gateway->min_amount;
                    $gateway->max_amount = (float) $gateway->max_amount;
                    $gateway->fixed_charge = (float) $gateway->fixed_charge;
                    $gateway->percent_charge = (float) $gateway->percent_charge;
                    $gateway->rate = (float) $gateway->rate;
                    return $gateway;
                });

            $deposits = Deposit::with('paymentGateway')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $deposits->getCollection()->transform(function ($deposit) {
                $deposit->trx = e($deposit->trx);
                $deposit->currency = e($deposit->currency);
                if ($deposit->paymentGateway) {
                    $deposit->paymentGateway->name = e($deposit->paymentGateway->name);
                }
                return $deposit;
            });

            $mainWallet = $user->wallets()->where('type', Type::MAIN->value)->first();
            return Inertia::render('User/Wallet/Deposit', [
                'deposits' => $deposits,
                'paymentGateways' => $paymentGateways,
                'walletBalance' => $mainWallet->balance ?? 0,
            ]);
        } catch (Exception $e) {
            Log::error('Deposit Index Error: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return Inertia::render('User/Wallet/Deposit', [
                'deposits' => ['data' => [], 'total' => 0],
                'paymentGateways' => [],
                'walletBalance' => 0
            ])->with('error', 'Unable to load deposit page. Please try again.');
        }
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $key = 'create-deposit:' . $user->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return redirect()->back()->withErrors(['error' => "Too many deposit attempts. Please try again in {$seconds} seconds."]);
            }

            $validated = $request->validate([
                'payment_gateway_id' => [
                    'required',
                    'integer',
                    'min:1',
                    'exists:payment_gateways,id'
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:999999999'
                ],
                'payment_details' => 'sometimes|array|max:20',
                'payment_method_id' => 'sometimes|string|max:255',
                'selected_currency' => 'sometimes|string|max:10',
            ], [
                'payment_gateway_id.required' => 'Please select a payment gateway.',
                'payment_gateway_id.exists' => 'Selected payment gateway is invalid.',
                'amount.required' => 'Please enter a deposit amount.',
                'amount.numeric' => 'Deposit amount must be a valid number.',
                'amount.min' => 'Deposit amount must be greater than 0.',
                'amount.max' => 'Deposit amount is too large.',
                'payment_details.array' => 'Invalid payment details format.',
                'payment_details.max' => 'Too many payment details provided.'
            ]);

            $gateway = PaymentGateway::where('id', $validated['payment_gateway_id'])
                ->where('status', true)
                ->first();

            if (!$gateway) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'payment_gateway_id' => ['Selected payment gateway is not available.']
                ]);
            }

            $requestedAmountGateway = round((float) $validated['amount'], 2);
            $minAmount = (float) $gateway->min_amount;
            $maxAmount = (float) $gateway->max_amount;

            if ($requestedAmountGateway < $minAmount) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ["Minimum deposit amount is " . e($gateway->currency) . " {$minAmount}"]
                ]);
            }

            if ($requestedAmountGateway > $maxAmount) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'amount' => ["Maximum deposit amount is " . e($gateway->currency) . " {$maxAmount}"]
                ]);
            }

            $mainWallet = $user->wallets()->where('type', Type::MAIN->value)->first();
            if (!$mainWallet) {
                RateLimiter::hit($key, 300);
                throw ValidationException::withMessages([
                    'error' => ['Wallet not found. Please contact support.']
                ]);
            }

            $chargeGateway = $this->calculateCharge($requestedAmountGateway, $gateway);
            $finalAmountGateway = round($requestedAmountGateway + $chargeGateway, 2);
            $depositAmountSite = $this->convertToSiteCurrency($requestedAmountGateway, $gateway);
            $processedDetails = [];
            if ($gateway->type === 'manual') {
                $processedDetails = $this->validateAndProcessGatewayFields(
                    $validated['payment_details'] ?? [],
                    $gateway
                );
            }

            DB::beginTransaction();

            try {
                $trx = 'DP' . strtoupper(bin2hex(random_bytes(5)));
                $attempts = 0;
                while (Deposit::where('trx', $trx)->exists() && $attempts < 10) {
                    $trx = 'DP' . strtoupper(bin2hex(random_bytes(5)));
                    $attempts++;
                }

                if ($attempts >= 10) {
                    throw new Exception('Unable to generate unique transaction ID');
                }

                $deposit = Deposit::create([
                    'user_id' => $user->id,
                    'payment_gateway_id' => $gateway->id,
                    'trx' => $trx,
                    'amount' => $requestedAmountGateway,
                    'charge' => $chargeGateway,
                    'final_amount' => $finalAmountGateway,
                    'deposit_amount' => $depositAmountSite,
                    'currency' => strtoupper($gateway->currency),
                    'status' => 'pending',
                    'payment_details' => $processedDetails,
                    'conversion_rate' => $gateway->rate,
                ]);

                if ($gateway->type === 'automatic') {
                    $result = $this->processAutomaticPayment($deposit, $gateway, $request);

                    if ($result['success']) {
                        if ($gateway->slug === 'nowpayments') {
                            DB::commit();
                            RateLimiter::clear($key);

                            return redirect()->back()->with('success', 'Payment request created! Please send ' .
                                    ($deposit->payment_details['pay_amount'] ?? '') . ' ' .
                                    strtoupper($deposit->payment_details['pay_currency'] ?? '') .
                                    ' to complete your deposit. Transaction ID: ' . e($trx));
                        }

                        $deposit->update([
                            'status' => 'approved',
                            'transaction_id' => substr($result['transaction_id'], 0, 255),
                            'approved_at' => now()
                        ]);

                        $this->addBalanceToWallet($user, $deposit);

                        DB::commit();
                        RateLimiter::clear($key);

                        return redirect()->back()->with('success', 'Deposit successful! Balance added to your account. Transaction ID: ' . e($trx));

                    } else {
                        $deposit->update([
                            'status' => 'rejected',
                            'admin_response' => substr($result['message'], 0, 500),
                            'rejected_at' => now()
                        ]);

                        DB::commit();
                        RateLimiter::hit($key, 300);
                        return redirect()->back()->withErrors(['payment' => e($result['message'])]);
                    }
                } else {
                    DB::commit();
                    RateLimiter::clear($key);
                    return redirect()->back()->with('success', 'Deposit request submitted successfully! Please wait for admin approval. Transaction ID: ' . e($trx));
                }

            } catch (Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (Exception $e) {
            Log::error('Deposit Store Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->except(['payment_details', 'payment_method_id'])
            ]);

            RateLimiter::hit($key ?? 'create-deposit:' . Auth::id(), 300);
            return redirect()->back()
                ->withErrors(['error' => 'Unable to process your deposit request. Please try again or contact support.'])
                ->withInput();
        }
    }

    /**
     * @param Deposit $deposit
     * @return RedirectResponse
     */
    public function cancel(Deposit $deposit): RedirectResponse
    {
        try {
            if ($deposit->user_id !== Auth::id()) {
                abort(403, 'Unauthorized access to deposit');
            }

            if ($deposit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'error' => ['Only pending deposits can be cancelled.']
                ]);
            }

            $deposit->update([
                'status' => 'rejected',
                'admin_response' => 'Cancelled by user',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Deposit cancelled successfully.');

        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (Exception $e) {
            Log::error('Deposit Cancellation Error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id,
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Unable to cancel deposit. Please try again or contact support.']);
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

            $deposit = Deposit::where('trx', $trx)
                ->where('user_id', Auth::id())
                ->with('paymentGateway')
                ->firstOrFail();

            $deposit->trx = e($deposit->trx);
            $deposit->currency = e($deposit->currency);
            if ($deposit->paymentGateway) {
                $deposit->paymentGateway->name = e($deposit->paymentGateway->name);
            }

            return Inertia::render('User/Wallet/DepositDetails', [
                'deposit' => $deposit
            ]);
        } catch (Exception $e) {
            Log::error('Deposit Details Error: ' . $e->getMessage(), [
                'trx' => $trx,
                'user_id' => Auth::id()
            ]);

            abort(404, 'Deposit not found');
        }
    }


    /**
     * @param array $details
     * @param PaymentGateway $gateway
     * @return array
     * @throws ValidationException
     */
    private function validateAndProcessGatewayFields(array $details, PaymentGateway $gateway): array
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

            $ruleKey = "payment_details.{$fieldName}";

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
                case 'datetime-local':
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

            if ($param['field_type'] === 'file' && request()->hasFile("payment_details.{$fieldName}")) {
                try {
                    $file = request()->file("payment_details.{$fieldName}");
                    $extension = strtolower($file->getClientOriginalExtension());

                    if (!in_array($extension, self::ALLOWED_FILE_TYPES)) {
                        throw new Exception('Invalid file type');
                    }

                    $processedDetails[$fieldName] = $this->move($file);
                } catch (Exception $e) {
                    Log::error('File upload error: ' . $e->getMessage());
                    throw ValidationException::withMessages([
                        "payment_details.{$fieldName}" => 'Failed to upload file. Please try again.'
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
     * @param PaymentGateway $gateway
     * @return float
     * @throws Exception
     */
    private function calculateCharge(float $amount, PaymentGateway $gateway): float
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
            throw new Exception('Unable to calculate deposit charges');
        }
    }


    /**
     * @param $amount
     * @param $gateway
     * @return float
     * @throws Exception
     */
    private function convertToSiteCurrency($amount, $gateway): float
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
     * @param $deposit
     * @param $gateway
     * @param $request
     * @return array
     */
    private function processAutomaticPayment($deposit, $gateway, $request): array
    {
        try {
            return match ($gateway->slug) {
                'stripe' => $this->processStripePayment($deposit, $gateway, $request),
                'nowpayments' => $this->processNowPaymentsPayment($deposit, $gateway, $request),
                default => ['success' => false, 'message' => 'Payment gateway not supported'],
            };
        } catch (Exception $e) {
            Log::error('Automatic Payment Error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id,
                'gateway_slug' => $gateway->slug
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    /**
     * @param $deposit
     * @param $gateway
     * @param $request
     * @return array
     */
    private function processStripePayment($deposit, $gateway, $request): array
    {
        $credentials = $gateway->credentials;

        if (!isset($credentials['secret_key']) || !is_string($credentials['secret_key'])) {
            return ['success' => false, 'message' => 'Stripe configuration error'];
        }

        Stripe::setApiKey($credentials['secret_key']);

        try {
            $amountInCents = intval(round($deposit->final_amount * 100));
            if ($amountInCents < 50) {
                return ['success' => false, 'message' => 'Amount too small for processing'];
            }

            $paymentMethodId = $request->payment_method_id;
            if (!$paymentMethodId || !is_string($paymentMethodId) || strlen($paymentMethodId) > 100) {
                return ['success' => false, 'message' => 'Invalid payment method'];
            }

            Log::info('Stripe Payment Details', [
                'final_amount' => $deposit->final_amount,
                'amount_in_cents' => $amountInCents,
                'currency' => $gateway->currency,
                'deposit_id' => $deposit->id
            ]);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($gateway->currency),
                'payment_method' => $paymentMethodId,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => url('/user/deposits'),
                'metadata' => [
                    'deposit_id' => (string) $deposit->id,
                    'user_id' => (string) $deposit->user_id,
                    'deposit_trx' => $deposit->trx
                ]
            ]);

            if ($paymentIntent->status === 'succeeded') {
                return [
                    'success' => true,
                    'transaction_id' => $paymentIntent->id
                ];
            } elseif ($paymentIntent->status === 'requires_action') {
                return [
                    'success' => false,
                    'message' => 'Payment requires additional authentication',
                    'requires_action' => true,
                    'payment_intent' => $paymentIntent
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment failed: ' . $paymentIntent->status
                ];
            }
        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'message' => $e->getError()->message
            ];
        } catch (Exception $e) {
            Log::error('Stripe Error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id
            ]);
            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Process NowPayments payment
     *
     * @param $deposit
     * @param $gateway
     * @param $request
     * @return array
     */
    private function processNowPaymentsPayment($deposit, $gateway, $request): array
    {
        $credentials = $gateway->credentials;

        if (!isset($credentials['api_key']) || !is_string($credentials['api_key'])) {
            return ['success' => false, 'message' => 'NowPayments configuration error'];
        }

        try {
            $selectedCurrency = $request->input('selected_currency', 'btc');
            $amount = round($deposit->final_amount, 2);

            if ($amount < 1) {
                return ['success' => false, 'message' => 'Amount too small for processing'];
            }

            Log::info('NowPayments Payment Details', [
                'final_amount' => $amount,
                'currency' => $selectedCurrency,
                'deposit_id' => $deposit->id
            ]);

            $response = Http::withHeaders([
                'x-api-key' => $credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://api.nowpayments.io/v1/payment', [
                'price_amount' => $amount,
                'price_currency' => strtolower($gateway->currency),
                'pay_currency' => strtolower($selectedCurrency),
                'ipn_callback_url' => route('nowpayments.callback'),
                'order_id' => $deposit->trx,
                'order_description' => "Deposit #{$deposit->trx}",
                'success_url' => route('user.deposits.success', ['trx' => $deposit->trx]),
                'cancel_url' => route('user.wallet.index'),
            ]);

            if (!$response->successful()) {
                Log::error('NowPayments API Error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'deposit_id' => $deposit->id
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment gateway error: ' . ($response->json('message') ?? 'Unknown error')
                ];
            }

            $paymentData = $response->json();
            Log::info('NowPayments Payment Created', [
                'deposit_id' => $deposit->id,
                'deposit_trx' => $deposit->trx,
                'payment_id' => $paymentData['payment_id'] ?? null,
                'pay_address' => $paymentData['pay_address'] ?? null,
                'pay_amount' => $paymentData['pay_amount'] ?? null,
                'pay_currency' => $paymentData['pay_currency'] ?? null,
                'payment_status' => $paymentData['payment_status'] ?? null,
                'invoice_url' => $paymentData['invoice_url'] ?? null,
                'full_response' => $paymentData
            ]);
            $deposit->update([
                'transaction_id' => $paymentData['payment_id'] ?? null,
                'payment_details' => [
                    'nowpayments_id' => $paymentData['payment_id'] ?? null,
                    'pay_address' => $paymentData['pay_address'] ?? null,
                    'pay_amount' => $paymentData['pay_amount'] ?? null,
                    'pay_currency' => $paymentData['pay_currency'] ?? null,
                    'payment_status' => $paymentData['payment_status'] ?? 'waiting',
                ]
            ]);

            return [
                'success' => true,
                'transaction_id' => $paymentData['payment_id'] ?? 'NP-' . $deposit->trx,
                'payment_url' => $paymentData['invoice_url'] ?? null,
                'requires_redirect' => true
            ];

        } catch (Exception $e) {
            Log::error('NowPayments Error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id
            ]);
            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage()
            ];
        }
    }


    /**
     * @param $user
     * @param $deposit
     * @return void
     * @throws Exception
     */
    private function addBalanceToWallet($user, $deposit): void
    {
        try {
            $wallet = $user->wallets()->where('type', Type::MAIN->value)->first();
            if (!$wallet) {
                throw new Exception('Wallet not found');
            }

            $addAmount = (float) $deposit->deposit_amount;
            if ($addAmount <= 0) {
                throw new Exception('Invalid deposit amount');
            }

            $previousBalance = (float) $wallet->balance;
            $newBalance = round($previousBalance + $addAmount, 2);

            $wallet->update([
                'balance' => $newBalance,
                'last_activity' => now()
            ]);

            $roundAmount = round($deposit->amount, 2);
            Transaction::create([
                'transaction_id' => $deposit->trx,
                'user_id' => $user->id,
                'type' => 'deposit',
                'wallet_type' => 'main_wallet',
                'amount' => $addAmount,
                'post_balance' => $newBalance,
                'status' => 'completed',
                'details' => "Deposit via " . e($deposit->paymentGateway->name) . " - {$roundAmount} " . e($deposit->currency)
            ]);

            try {
                EmailTemplateService::sendTemplateEmail('deposit_confirmation', $user, [
                    'user_name' => e($user->name),
                    'amount' => round($deposit->deposit_amount, 2),
                    'transaction_id' => e($deposit->trx),
                ]);

                $this->referralService->processReferral($user->id, $addAmount);
            } catch (Exception $e) {
                Log::warning('Failed to send deposit confirmation email', [
                    'user_id' => $user->id,
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage()
                ]);
            }
        } catch (Exception $e) {
            Log::error('Wallet addition error: ' . $e->getMessage());
            throw $e;
        }
    }


    /**
     * @param Request $request
     * @param Deposit $deposit
     * @param string $fieldName
     * @return BinaryFileResponse|void
     */
    public function downloadFile(Request $request, Deposit $deposit, string $fieldName)
    {
        try {
            if ($deposit->user_id !== Auth::id()) {
                abort(403, 'Unauthorized access to deposit file');
            }

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
            Log::error('Deposit file download error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id ?? null,
                'field_name' => $fieldName ?? null,
                'user_id' => Auth::id()
            ]);

            abort(404, 'File not found');
        }
    }


    public function viewFile(Request $request, Deposit $deposit, string $fieldName)
    {
        try {
            if ($deposit->user_id !== Auth::id()) {
                abort(403, 'Unauthorized access to deposit file');
            }

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


    /**
     * Success page after NowPayments redirect
     *
     * @param string $trx
     * @return RedirectResponse
     */
    public function success(string $trx): RedirectResponse
    {
        try {
            $deposit = Deposit::where('trx', $trx)
                ->where('user_id', Auth::id())
                ->first();

            if (!$deposit) {
                return redirect()->route('user.wallet.index')
                    ->withErrors(['error' => 'Deposit not found']);
            }

            // Check payment status with NowPayments
            if ($deposit->paymentGateway && $deposit->paymentGateway->slug === 'nowpayments') {
                $this->checkNowPaymentsStatus($deposit);
            }

            return redirect()->route('user.deposits.details', ['trx' => $trx])
                ->with('success', 'Payment initiated! Please wait for confirmation.');

        } catch (Exception $e) {
            Log::error('Deposit success page error: ' . $e->getMessage());
            return redirect()->route('user.wallet.index')
                ->with('info', 'Payment initiated! Please check your deposit status.');
        }
    }


    /**
     * Check NowPayments payment status
     *
     * @param Deposit $deposit
     * @return void
     */
    private function checkNowPaymentsStatus(Deposit $deposit): void
    {
        try {
            $gateway = $deposit->paymentGateway;
            $credentials = $gateway->credentials;

            if (!isset($credentials['api_key'])) {
                return;
            }

            $paymentId = $deposit->payment_details['nowpayments_id'] ?? $deposit->transaction_id;

            if (!$paymentId) {
                return;
            }

            $response = Http::withHeaders([
                'x-api-key' => $credentials['api_key'],
            ])->get("https://api.nowpayments.io/v1/payment/{$paymentId}");

            if ($response->successful()) {
                $paymentData = $response->json();
                $status = $paymentData['payment_status'] ?? 'waiting';

                // Update payment details
                $paymentDetails = $deposit->payment_details;
                $paymentDetails['payment_status'] = $status;
                $paymentDetails['updated_at'] = now()->toDateTimeString();

                $deposit->update([
                    'payment_details' => $paymentDetails
                ]);

                // If payment is finished/confirmed, approve the deposit
                if (in_array($status, ['finished', 'confirmed'])) {
                    $this->approveNowPaymentsDeposit($deposit);
                }
            }

        } catch (Exception $e) {
            Log::error('NowPayments status check error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id
            ]);
        }
    }


    /**
     * Approve NowPayments deposit and add balance
     *
     * @param Deposit $deposit
     * @return void
     */
    private function approveNowPaymentsDeposit(Deposit $deposit): void
    {
        try {
            if ($deposit->status === 'approved') {
                return;
            }

            DB::beginTransaction();

            $deposit->update([
                'status' => 'approved',
                'approved_at' => now()
            ]);

            $user = $deposit->user;
            $this->addBalanceToWallet($user, $deposit);
            DB::commit();

            Log::info('NowPayments deposit approved', [
                'deposit_id' => $deposit->id,
                'user_id' => $user->id,
                'amount' => $deposit->deposit_amount
            ]);

        } catch (Exception $e) {
            DB::rollback();
            Log::error('NowPayments deposit approval error: ' . $e->getMessage(), [
                'deposit_id' => $deposit->id
            ]);
        }
    }
}
