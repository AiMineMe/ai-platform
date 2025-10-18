<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionPlanController extends Controller
{
    public function index(): \Inertia\Response
    {
        $plans = SubscriptionPlan::withCount('users')->orderBy('price')->get();

        $stats = [
            'total_plans' => SubscriptionPlan::count(),
            'active_plans' => SubscriptionPlan::where('is_active', true)->count(),
            'total_subscribers' => \App\Models\User::whereNotNull('subscription_plan_id')
                ->where('subscription_expires_at', '>', now())->count()
        ];

        return Inertia::render('Admin/SubscriptionPlans/Index', [
            'plans' => $plans,
            'stats' => $stats
        ]);
    }

    public function create(): \Inertia\Response
    {
        return Inertia::render('Admin/SubscriptionPlans/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'mining_fee_percentage' => 'required|numeric|min:0|max:100',
            'mining_rate_multiplier' => 'required|numeric|min:1|max:10',
            'features' => 'required|array',
            'is_active' => 'boolean'
        ]);

        SubscriptionPlan::create($validated);

        return redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan created successfully');
    }

    public function edit(SubscriptionPlan $subscriptionPlan): \Inertia\Response
    {
        return Inertia::render('Admin/SubscriptionPlans/Form', [
            'plan' => $subscriptionPlan
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'mining_fee_percentage' => 'required|numeric|min:0|max:100',
            'mining_rate_multiplier' => 'required|numeric|min:1|max:10',
            'features' => 'required|array',
            'is_active' => 'boolean'
        ]);

        $subscriptionPlan->update($validated);

        return redirect()->route('admin.subscription-plans.index')->with('success', 'Subscription plan updated successfully');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): \Illuminate\Http\RedirectResponse
    {
        $activeSubscribers = User::where('subscription_plan_id', $subscriptionPlan->id)
            ->where('subscription_expires_at', '>', now())
            ->count();

        if ($activeSubscribers > 0) {
            return redirect()->back()->withErrors([
                'error' => "Cannot delete plan with {$activeSubscribers} active subscribers"
            ]);
        }

        $subscriptionPlan->delete();

        return redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Subscription plan deleted successfully');
    }

    public function toggleStatus(SubscriptionPlan $subscriptionPlan): \Illuminate\Http\RedirectResponse
    {
        $subscriptionPlan->update(['is_active' => !$subscriptionPlan->is_active]);

        $status = $subscriptionPlan->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Plan {$status} successfully");
    }
}
