<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IcoPurchase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseHistoryController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = IcoPurchase::with(['user', 'icoToken']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purchase_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('icoToken', function ($tokenQuery) use ($search) {
                        $tokenQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('symbol', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }


        $query->orderBy('created_at', 'desc');
        $perPage = $request->get('per_page', 15);
        $purchases = $query->paginate($perPage);
        $statistics = [
            'total_sales' => IcoPurchase::where('status', 'completed')->sum('amount_usd'),
            'total_purchases' => IcoPurchase::count(),
            'unique_buyers' => IcoPurchase::distinct('user_id')->count(),
            'pending_purchases' => IcoPurchase::where('status', 'pending')->count(),
        ];

        return Inertia::render('Admin/PurchaseHistory/Index', [
            'purchases' => $purchases->items(),
            'meta' => [
                'total' => $purchases->total(),
                'current_page' => $purchases->currentPage(),
                'per_page' => $purchases->perPage(),
                'last_page' => $purchases->lastPage(),
            ],
            'statistics' => $statistics,
            'filters' => $request->only(['search', 'status'])
        ]);
    }
}
