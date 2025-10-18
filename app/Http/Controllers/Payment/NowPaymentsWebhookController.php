<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\PaymentGateway;
use App\Models\Transaction;
use App\Services\EmailTemplateService;
use App\Services\ReferralService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NowPaymentsWebhookController extends Controller
{
    /**
     * Handle NowPayments IPN callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            Log::info('NowPayments Webhook Received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            if (!$this->verifySignature($request)) {
                Log::warning('NowPayments webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $paymentStatus = $request->input('payment_status');
            $orderId = $request->input('order_id');
            $paymentId = $request->input('payment_id');

            if (!$orderId) {
                Log::warning('NowPayments webhook missing order_id');
                return response()->json(['error' => 'Missing order_id'], 400);
            }

            $deposit = Deposit::where('trx', $orderId)->first();
            if (!$deposit) {
                Log::warning('NowPayments webhook - deposit not found', [
                    'order_id' => $orderId
                ]);
                return response()->json(['error' => 'Deposit not found'], 404);
            }

            $paymentDetails = $deposit->payment_details ?? [];
            $paymentDetails['payment_status'] = $paymentStatus;
            $paymentDetails['nowpayments_id'] = $paymentId;
            $paymentDetails['webhook_received_at'] = now()->toDateTimeString();
            $paymentDetails['pay_amount'] = $request->input('pay_amount');
            $paymentDetails['pay_currency'] = $request->input('pay_currency');
            $paymentDetails['price_amount'] = $request->input('price_amount');
            $paymentDetails['price_currency'] = $request->input('price_currency');
            $paymentDetails['outcome_amount'] = $request->input('outcome_amount');
            $paymentDetails['outcome_currency'] = $request->input('outcome_currency');

            $deposit->update([
                'payment_details' => $paymentDetails,
                'transaction_id' => $paymentId
            ]);

            switch ($paymentStatus) {
                case 'finished':
                case 'confirmed':
                    $this->approveDeposit($deposit);
                    break;

                case 'failed':
                case 'expired':
                    $this->rejectDeposit($deposit, "Payment {$paymentStatus}");
                    break;

                case 'waiting':
                case 'confirming':
                case 'sending':
                    Log::info('NowPayments payment in progress', [
                        'deposit_id' => $deposit->id,
                        'status' => $paymentStatus
                    ]);
                    break;

                case 'partially_paid':
                    Log::warning('NowPayments partial payment received', [
                        'deposit_id' => $deposit->id,
                        'expected' => $request->input('price_amount'),
                        'received' => $request->input('pay_amount')
                    ]);
                    break;

                default:
                    Log::info('NowPayments unknown status', [
                        'deposit_id' => $deposit->id,
                        'status' => $paymentStatus
                    ]);
            }

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            Log::error('NowPayments webhook error: ' . $e->getMessage(), [
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }


    /**
     * Verify NowPayments webhook signature
     *
     * @param Request $request
     * @return bool
     */
    private function verifySignature(Request $request): bool
    {
        try {
            $gateway = PaymentGateway::where('slug', 'nowpayments')
                ->where('status', true)
                ->first();

            if (!$gateway) {
                Log::error('NowPayments gateway not found for signature verification');
                return false;
            }

            $credentials = $gateway->credentials;
            $ipnSecret = $credentials['ipn_secret'] ?? null;

            if (!$ipnSecret) {
                Log::warning('NowPayments IPN secret not configured');
                return true;
            }

            $receivedSignature = $request->header('x-nowpayments-sig');
            if (!$receivedSignature) {
                Log::warning('NowPayments webhook signature header missing');
                return false;
            }

            $payload = $request->getContent();
            $expectedSignature = hash_hmac('sha512', $payload, $ipnSecret);

            $isValid = hash_equals($expectedSignature, $receivedSignature);
            if (!$isValid) {
                Log::warning('NowPayments signature mismatch', [
                    'expected' => $expectedSignature,
                    'received' => $receivedSignature
                ]);
            }

            return $isValid;

        } catch (Exception $e) {
            Log::error('NowPayments signature verification error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Approve deposit and add balance
     *
     * @param Deposit $deposit
     * @return void
     * @throws Exception
     */
    private function approveDeposit(Deposit $deposit): void
    {
        try {
            if ($deposit->status === 'approved') {
                Log::info('Deposit already approved', ['deposit_id' => $deposit->id]);
                return;
            }

            DB::beginTransaction();

            $deposit->update([
                'status' => 'approved',
                'approved_at' => now()
            ]);

            $user = $deposit->user;
            $wallet = $user->wallets()->where('type', \App\Enums\Wallet\Type::MAIN->value)->first();

            if (!$wallet) {
                throw new Exception('Wallet not found');
            }

            $addAmount = (float) $deposit->deposit_amount;
            $previousBalance = (float) $wallet->balance;
            $newBalance = round($previousBalance + $addAmount, 2);

            $wallet->update([
                'balance' => $newBalance,
                'last_activity' => now()
            ]);

            Transaction::create([
                'transaction_id' => $deposit->trx,
                'user_id' => $user->id,
                'type' => 'deposit',
                'wallet_type' => 'main_wallet',
                'amount' => $addAmount,
                'post_balance' => $newBalance,
                'status' => 'completed',
                'details' => "Deposit via NowPayments - " . round($deposit->amount, 2) . " " . e($deposit->currency)
            ]);

            DB::commit();
            try {
                EmailTemplateService::sendTemplateEmail('deposit_confirmation', $user, [
                    'user_name' => e($user->name),
                    'amount' => round($deposit->deposit_amount, 2),
                    'transaction_id' => e($deposit->trx),
                ]);

                app(ReferralService::class)->processReferral($user->id, $addAmount);
            } catch (Exception $e) {
                Log::warning('Failed to send deposit confirmation', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('NowPayments deposit approved successfully', [
                'deposit_id' => $deposit->id,
                'user_id' => $user->id,
                'amount' => $addAmount
            ]);

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Failed to approve NowPayments deposit', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    /**
     * Reject deposit
     *
     * @param Deposit $deposit
     * @param string $reason
     * @return void
     */
    private function rejectDeposit(Deposit $deposit, string $reason): void
    {
        try {
            if ($deposit->status !== 'pending') {
                return;
            }

            $deposit->update([
                'status' => 'rejected',
                'admin_response' => $reason,
                'rejected_at' => now()
            ]);

            Log::info('NowPayments deposit rejected', [
                'deposit_id' => $deposit->id,
                'reason' => $reason
            ]);

        } catch (Exception $e) {
            Log::error('Failed to reject NowPayments deposit', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
