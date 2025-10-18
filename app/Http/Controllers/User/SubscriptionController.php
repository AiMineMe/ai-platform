<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\AdminRevenue;
use App\Models\Transaction;
use App\Enums\Wallet\Type;
use App\Services\EmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(): \Inertia\Response | RedirectResponse
    {
        try {
            $user = auth()->user();
            $plans = SubscriptionPlan::where('is_active', true)->orderBy('price')->get();

            return Inertia::render('User/Subscriptions', [
                'currentPlan' => $user->subscriptionPlan,
                'expiresAt' => $user->subscription_expires_at,
                'plans' => $plans,
                'hasActiveSubscription' => $user->hasActiveSubscription()
            ]);
        } catch (\Exception $exception) {
            return redirect()->back()->withErrors(['error' => 'Plan is not available']);
        }
    }

    public function subscribe(Request $request, SubscriptionPlan $plan): \Illuminate\Http\RedirectResponse
    {
        if (!$plan->is_active) {
            return redirect()->back()->withErrors(['error' => 'Plan is not available']);
        }

        $user = auth()->user();

        try {
            DB::beginTransaction();

            $wallet = $user->wallets()->where('type', Type::MAIN->value)->first();
            if (!$wallet || $wallet->balance < $plan->price) {
                throw new \Exception('Insufficient balance');
            }

            $wallet->decrement('balance', $plan->price);
            $expiresAt = now()->addMonth();

            $user->update([
                'subscription_plan_id' => $plan->id,
                'subscription_expires_at' => $expiresAt
            ]);

            $transactionId = 'SUB_' . Str::random(8);

            AdminRevenue::recordRevenue(
                'subscription',
                $plan->price,
                $user->id,
                "Subscription payment from {$user->name}",
                [
                    'plan_name' => $plan->name,
                    'plan_id' => $plan->id,
                    'duration' => '1 month'
                ]
            );

            Transaction::create([
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'type' => 'debit',
                'wallet_type' => 'main_wallet',
                'amount' => $plan->price,
                'post_balance' => $wallet->fresh()->balance,
                'status' => 'completed',
                'details' => "Subscription: {$plan->name}"
            ]);

            EmailTemplateService::sendTemplateEmail('subscription_purchased', $user, [
                'user_name' => e($user->name),
                'plan_name' => e($plan->name),
                'amount' => round($plan->price, 2),
                'transaction_id' => e($transactionId),
                'purchase_date' => now()->format('M d, Y'),
                'expires_date' => $expiresAt->format('M d, Y')
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Subscribed to {$plan->name} plan!");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancel(): \Illuminate\Http\RedirectResponse
    {
        auth()->user()->update([
            'subscription_plan_id' => null,
            'subscription_expires_at' => null
        ]);

        return redirect()->back()->with('success', 'Subscription cancelled');
    }
}
