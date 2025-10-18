<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradeSetting;
use App\Models\Trade;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TradeSettingsController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $payoutRange = $request->get('payout_range');
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = TradeSetting::with('currency');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('currency', function ($currencyQuery) use ($search) {
                    $currencyQuery->where('symbol', 'like', "%{$search}%");
                });
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', (bool) $status);
        }

        if ($payoutRange) {
            switch ($payoutRange) {
                case '0-50':
                    $query->whereBetween('payout_rate', [0, 50]);
                    break;
                case '51-75':
                    $query->whereBetween('payout_rate', [51, 75]);
                    break;
                case '76-90':
                    $query->whereBetween('payout_rate', [76, 90]);
                    break;
                case '91-100':
                    $query->where('payout_rate', '>=', 91);
                    break;
            }
        }

        $allowedSortFields = ['symbol', 'is_active', 'payout_rate', 'min_amount', 'max_amount', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $settings = $query->paginate($perPage)->appends($request->all());
        $transformedSettings = $settings->through(function ($setting) {
            $formattedDurations = [];
            if ($setting->durations) {
                $durations = is_string($setting->durations) ? json_decode($setting->durations, true) : $setting->durations;
                if (is_array($durations)) {
                    foreach ($durations as $duration) {
                        if ($duration >= 60) {
                            $minutes = intval($duration / 60);
                            $formattedDurations[] = $minutes . 'm';
                        } else {
                            $formattedDurations[] = $duration . 's';
                        }
                    }
                }
            }

            return array_merge($setting->toArray(), [
                'formatted_durations' => $formattedDurations,
                'trade_count' => $setting->trades_count ?? 0,
                'active_trades' => $setting->active_trades_count ?? 0,
                'total_volume' => $setting->total_volume ?? 0,
            ]);
        });

        $statsQuery = TradeSetting::query();
        $stats = [
            'totalSymbols' => $statsQuery->count(),
            'activeSymbols' => (clone $statsQuery)->where('is_active', true)->count(),
            'totalActiveTrades' => $this->getTotalActiveTrades($statsQuery),
            'totalVolume' => $this->getTotalVolume($statsQuery),
        ];

        return Inertia::render('Admin/TradeSettings/Index', [
            'tradeSettings' => $transformedSettings->items(),
            'meta' => [
                'total' => $transformedSettings->total(),
                'current_page' => $transformedSettings->currentPage(),
                'per_page' => $transformedSettings->perPage(),
                'last_page' => $transformedSettings->lastPage(),
            ],
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payout_range' => $payoutRange,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * @param $query
     * @return mixed
     */
    private function getTotalActiveTrades($query): mixed
    {
        return $query->withCount(['trades' => function ($q) {
            $q->where('status', 'active');
        }])->get()->sum('trades_count');
    }


    /**
     * @param $query
     * @return int
     */
    private function getTotalVolume($query): int
    {
        return $query->withSum(['trades' => function ($q) {
        }], 'amount')->get()->sum('trades_sum_amount') ?? 0;
    }

    /**
     * @return Response
     */
    public function create(): Response
    {
        $currencies = Currency::orderBy('type')->orderBy('symbol')->get();
        return Inertia::render('Admin/TradeSettings/Form', [
            'currencies' => $currencies
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'symbol' => 'required|string|max:20|unique:trade_settings',
                'is_active' => 'boolean',
                'min_amount' => 'required|numeric|min:0.01',
                'max_amount' => 'required|numeric|gt:min_amount',
                'payout_rate' => 'required|numeric|min:1|max:1000',
                'durations' => 'required|array|min:1',
                'durations.*' => 'integer|min:1',
                'trading_hours' => 'required|array',
                'trading_hours.*.enabled' => 'boolean',
                'trading_hours.*.start' => 'required|string|date_format:H:i',
                'trading_hours.*.end' => 'required|string|date_format:H:i',
            ]);

            $currency = Currency::where('symbol', $request->symbol)->first();
            if (!$currency) {
                return redirect()->back()->withErrors(['error' => 'Currency not found for this symbol']);
            }

            $data = $request->all();
            $data['is_active'] = $request->boolean('is_active');
            $data['currency_id'] = $currency->id;

            TradeSetting::create($data);
            return redirect()->back()->with('success', 'Trade setting created successfully');
        }catch (\Exception $exception){
            return redirect()->back()->withErrors(['error' => 'Failed to create trade setting']);
        }
    }

    /**
     * @param TradeSetting $tradeSetting
     * @return Response
     */
    public function show(TradeSetting $tradeSetting): Response
    {
        return Inertia::render('Admin/TradeSettings/Show', [
            'setting' => $tradeSetting
        ]);
    }

    /**
     * @param TradeSetting $tradeSetting
     * @return Response
     */
    public function stats(TradeSetting $tradeSetting): Response
    {
        $trades = Trade::where('symbol', $tradeSetting->symbol)->with('user')->get();
        $stats = [
            'total_trades' => $trades->count(),
            'completed_trades' => $trades->whereIn('status', ['active', 'won', 'lost', 'draw'])->count(),
            'active_trades' => $trades->where('status', 'active')->count(),
            'won_trades' => $trades->where('status', 'won')->count(),
            'lost_trades' => $trades->where('status', 'lost')->count(),
            'total_volume' => $trades->sum('amount'),
            'total_profit' => $trades->where('status', 'lost')->sum('amount'),
            'total_loss' => $trades->where('status', 'won')->sum('profit_loss'),
            'win_rate' => $trades->count() > 0 ? ($trades->where('status', 'lost')->count() / $trades->count()) * 100 : 0,
            'net_profit_loss' => $trades->where('status', 'lost')->sum('amount') - $trades->where('status', 'won')->sum('profit_loss'),
        ];

        return Inertia::render('Admin/TradeSettings/Stats', [
            'setting' => $tradeSetting,
            'stats' => $stats,
            'trades' => $trades->take(50)
        ]);
    }

    /**
     * @param TradeSetting $tradeSetting
     * @return Response
     */
    public function edit(TradeSetting $tradeSetting): Response
    {
        $trades = Trade::where('symbol', $tradeSetting->symbol)->with('user')->get();
        $stats = [
            'total_trades' => $trades->count(),
            'active_trades' => $trades->where('status', 'active')->count(),
            'total_volume' => $trades->sum('amount'),
        ];

        $currencies = Currency::orderBy('type')->orderBy('symbol')->get();
        return Inertia::render('Admin/TradeSettings/Form', [
            'setting' => $tradeSetting,
            'currencies' => $currencies,
            'stats' => $stats,
        ]);
    }

    /**
     * @param Request $request
     * @param TradeSetting $tradeSetting
     * @return RedirectResponse
     */
    public function update(Request $request, TradeSetting $tradeSetting): RedirectResponse
    {
        $request->validate([
            'symbol' => 'required|string|max:20|unique:trade_settings,symbol,' . $tradeSetting->id,
            'is_active' => 'boolean',
            'min_amount' => 'required|numeric|min:0.01',
            'max_amount' => 'required|numeric|gt:min_amount',
            'payout_rate' => 'required|numeric|min:1|max:1000',
            'durations' => 'required|array|min:1',
            'durations.*' => 'integer|min:1',
            'trading_hours' => 'required|array',
            'trading_hours.*.enabled' => 'boolean',
            'trading_hours.*.start' => 'required|string|date_format:H:i',
            'trading_hours.*.end' => 'required|string|date_format:H:i',
        ]);

        if ($request->symbol !== $tradeSetting->symbol) {
            $currency = Currency::where('symbol', $request->symbol)->first();
            if (!$currency) {
                return redirect()->back()->withErrors(['error' => 'Currency not found for this symbol']);
            }
            $currencyId = $currency->id;
        } else {
            $currencyId = $tradeSetting->currency_id;
        }

        $data = $request->all();
        $data['is_active'] = $request->boolean('is_active');
        $data['currency_id'] = $currencyId;

        $tradeSetting->update($data);
        return redirect()->back()->with('success', 'Trade setting updated successfully');
    }

    /**
     * @param TradeSetting $tradeSetting
     * @return RedirectResponse
     */
    public function destroy(TradeSetting $tradeSetting): RedirectResponse
    {
        $activeTrades = Trade::where('symbol', $tradeSetting->symbol)
            ->where('status', 'active')
            ->exists();

        if ($activeTrades) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete setting with active trades']);
        }

        $tradeSetting->delete();
        return redirect()->back()->with('success', 'Trade setting deleted successfully');
    }
}
