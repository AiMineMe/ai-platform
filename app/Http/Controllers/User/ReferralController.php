<?php

namespace App\Http\Controllers\User;

use App\Enums\User\Status;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    /**
     * @return Response
     */
    /**
     * @return Response
     */
    public function dashboard(): Response
    {
        $user = auth()->user();
        $referrals = User::where('referred_by', $user->id)->get();
        $totalCommission = Referral::where('referrer_id', $user->id)->sum('commission');
        $stats = [
            'total_referrals' => $referrals->count(),
            'total_commission' => $totalCommission,
            'this_month_referrals' => User::where('referred_by', $user->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'commission_rate' => Setting::get('referral_deposit_commission_rate', 1),
        ];

        $recentReferrals = User::where('referred_by', $user->id)
            ->select('id', 'name', 'email', 'status', 'created_at')
            ->latest()
            ->paginate(10)
            ->through(function ($referredUser) {
                return [
                    'id' => $referredUser->id,
                    'referred_user' => $referredUser->name,
                    'referred_email' => $referredUser->email,
                    'status' => Status::getName($referredUser->status),
                    'date' => $referredUser->created_at->format('M d, Y'),
                    'time' => $referredUser->created_at->format('H:i:s'),
                ];
            });

        return Inertia::render('User/Referral/Dashboard', [
            'stats' => $stats,
            'recentReferrals' => $recentReferrals,
            'referralCode' => $user->referral_code,
            'referralLink' => url('/register?ref=' . $user->referral_code),
        ]);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function commissions(Request $request): Response
    {
        $user = auth()->user();
        $filters = $request->only(['search', 'status', 'start_date', 'end_date', 'per_page']);
        $perPage = $filters['per_page'] ?? 10;

        $query = $user->referrals()->with('referred:id,name,email');
        if (!empty($filters['search'])) {
            $query->whereHas('referred', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
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

        $commissions = $query->latest()->paginate($perPage);
        $commissions->getCollection()->transform(function ($referral) {
            return [
                'id' => $referral->id,
                'referred_user' => $referral->referred->name,
                'referred_email' => $referral->referred->email,
                'commission' => $referral->commission,
                'status' => $referral->status,
                'created_at' => $referral->created_at->toISOString(),
                'date' => $referral->created_at->format('M d, Y'),
                'time' => $referral->created_at->format('H:i:s'),
            ];
        });

        return Inertia::render('User/Referral/Commissions', [
            'commissions' => $commissions,
            'filters' => $filters,
            'statuses' => ['pending', 'paid'],
        ]);
    }
}
