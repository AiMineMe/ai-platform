<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{

    public function __construct(protected readonly CurrencyService $currencyService){

    }

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Currency::query();
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $sortField = $request->get('sort_field', 'symbol');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
        $perPage = $request->get('per_page', 20);
        $currencies = $query->paginate($perPage);
        $stats = $this->calculateStats();
        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => $currencies->items(),
            'meta' => [
                'total' => $currencies->total(),
                'current_page' => $currencies->currentPage(),
                'per_page' => $currencies->perPage(),
                'last_page' => $currencies->lastPage(),
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'type', 'status', 'sort_field', 'sort_direction']),
            'types' => ['crypto', 'forex', 'stock', 'commodity']
        ]);
    }


    /**
     * @return array
     */
    private function calculateStats(): array
    {
        $totalCurrencies = Currency::count();
        $priceGainers = Currency::where('change_percent', '>', 0)->count();
        $priceDecliners = Currency::where('change_percent', '<', 0)->count();

        return [
            'totalCurrencies' => $totalCurrencies,
            'priceGainers' => $priceGainers,
            'priceDecliners' => $priceDecliners,
        ];
    }
}
