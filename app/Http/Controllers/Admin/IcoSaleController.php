<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenSale;
use App\Models\IcoToken;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IcoSaleController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = TokenSale::with(['user:id,name,email', 'icoToken:id,name,symbol']);
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('sale_id', 'like', "%{$search}%")
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
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('token')) {
            $query->where('ico_token_id', $request->get('token'));
        }

        if ($request->filled('date_from')) {
            try {
                $dateFrom = \Carbon\Carbon::parse($request->get('date_from'))->startOfDay();
                $query->where('created_at', '>=', $dateFrom);
            } catch (\Exception $e) {
            }
        }

        if ($request->filled('date_to')) {
            try {
                $dateTo = \Carbon\Carbon::parse($request->get('date_to'))->endOfDay();
                $query->where('created_at', '<=', $dateTo);
            } catch (\Exception $e) {
            }
        }

        $sortField = $request->get('sort_field', 'sold_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSortFields = ['sale_id', 'tokens_sold', 'sale_price', 'total_amount', 'status', 'sold_at', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'sold_at';
        }

        if ($sortField === 'sold_at') {
            $query->orderByRaw("COALESCE(sold_at, created_at) {$sortDirection}");
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $sales = $query->paginate($perPage);
        $tokens = IcoToken::select('id', 'name', 'symbol')
            ->whereIn('id', function ($query) {
                $query->select('ico_token_id')
                    ->from('token_sales')
                    ->distinct();
            })
            ->orderBy('name')
            ->get();

        $stats = [
            'total_sales' => TokenSale::count(),
            'completed_sales' => TokenSale::where('status', 'completed')->count(),
            'total_amount' => TokenSale::where('status', 'completed')->sum('total_amount'),
            'total_tokens' => TokenSale::where('status', 'completed')->sum('tokens_sold')
        ];

        return Inertia::render('Admin/IcoTokens/SaleHistory', [
            'sales' => $sales->items(),
            'meta' => [
                'total' => $sales->total(),
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'last_page' => $sales->lastPage(),
            ],
            'tokens' => $tokens,
            'filters' => $request->only(['search', 'status', 'token', 'date_from', 'date_to', 'sort_field', 'sort_direction']),
            'stats' => $stats
        ]);
    }
}
