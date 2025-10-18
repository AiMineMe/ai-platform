<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status', 'start_date', 'end_date', 'per_page']);
        $perPage = $filters['per_page'] ?? 20;
        $query = Referral::with(['referrer:id,name,email', 'referred:id,name,email']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('referrer', function ($subQ) use ($filters) {
                    $subQ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                })->orWhereHas('referred', function ($subQ) use ($filters) {
                    $subQ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $referrals = $query->latest()->paginate($perPage);
        $referrals->getCollection()->transform(function ($referral) {
            return [
                'id' => $referral->id,
                'referrer_name' => $referral->referrer->name,
                'referrer_email' => $referral->referrer->email,
                'referred_name' => $referral->referred->name,
                'referred_email' => $referral->referred->email,
                'commission' => $referral->commission,
                'status' => $referral->status,
                'created_at' => $referral->created_at->toISOString(),
            ];
        });

        $stats = [
            'total_referrals' => Referral::count(),
            'pending_referrals' => Referral::where('status', 'pending')->count(),
            'paid_referrals' => Referral::where('status', 'paid')->count(),
            'total_commission_paid' => Referral::where('status', 'paid')->sum('commission'),
            'total_commission_pending' => Referral::where('status', 'pending')->sum('commission'),
            'total_users_with_referrals' => User::has('referrals')->count(),
        ];

        return Inertia::render('Admin/Referrals/Index', [
            'referrals' => $referrals,
            'filters' => $filters,
            'stats' => $stats,
            'statuses' => ['pending', 'paid'],
        ]);
    }

    /**
     * @param Request $request
     * @param Referral $referral
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, Referral $referral): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:pending,paid'
        ]);

        $referral->update(['status' => $request->status]);
        return redirect()->back()->with('success', 'Referral status updated successfully!');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'referral_ids' => 'required|array',
            'referral_ids.*' => 'exists:referrals,id',
            'status' => 'required|in:pending,paid'
        ]);

        Referral::whereIn('id', $request->referral_ids)->update(['status' => $request->status]);
        $count = count($request->referral_ids);

        return redirect()->back()->with('success', "{$count} referrals updated successfully!");
    }
}
