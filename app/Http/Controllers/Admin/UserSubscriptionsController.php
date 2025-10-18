<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserSubscriptionsController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $plan = $request->get('plan');

        $query = User::with(['subscriptionPlan'])
            ->whereNotNull('subscription_plan_id')
            ->select(['id', 'name', 'email', 'subscription_plan_id', 'subscription_expires_at', 'created_at']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            switch ($status) {
                case 'active':
                    $query->whereNotNull('subscription_plan_id')
                        ->where('subscription_expires_at', '>', now());
                    break;
                case 'expired':
                    $query->whereNotNull('subscription_plan_id')
                        ->where('subscription_expires_at', '<=', now());
                    break;
                case 'none':
                    $query->whereNull('subscription_plan_id');
                    break;
            }
        }

        if ($plan) {
            $query->where('subscription_plan_id', $plan);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_subscriptions' => User::count(),
            'active_subscriptions' => User::whereNotNull('subscription_plan_id')
                ->where('subscription_expires_at', '>', now())
                ->count(),
            'expired_subscriptions' => User::whereNotNull('subscription_plan_id')
                ->where('subscription_expires_at', '<=', now())
                ->count(),
            'total_revenue' => DB::table('admin_revenues')
                ->where('revenue_type', 'subscription')
                ->sum('amount')
        ];

        $plans = SubscriptionPlan::select(['id', 'name'])->get();

        return Inertia::render('Admin/UserSubscriptions', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'plan' => $plan
            ]
        ]);
    }

    public function cancel(User $user): \Illuminate\Http\RedirectResponse
    {
        try {
            $user->update([
                'subscription_plan_id' => null,
                'subscription_expires_at' => null
            ]);

            return redirect()->back()->with('success', "Subscription cancelled for {$user->name}");

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to cancel subscription']);
        }
    }
}
