<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalGatewayController extends Controller
{
    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'sort_field' => 'nullable|in:name,currency,status,created_at',
            'sort_direction' => 'nullable|in:asc,desc'
        ]);

        try {
            $query = WithdrawalGateway::query();
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('currency', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->filled('status')) {
                $status = $request->status === 'active';
                $query->where('status', $status);
            }

            $sortField = $request->get('sort_field', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            $gateways = $query->paginate(20)->appends($request->all());
            $stats = [
                'total_gateways' => WithdrawalGateway::count(),
                'active_gateways' => WithdrawalGateway::where('status', true)->count(),
                'inactive_Gateways' => WithdrawalGateway::where('status', false)->count(),
                'average_rate' => WithdrawalGateway::active()->average('rate'),
            ];

            return Inertia::render('Admin/WithdrawalGateways/Index', [
                'gateways' => $gateways->items(),
                'meta' => [
                    'total' => $gateways->total(),
                    'current_page' => $gateways->currentPage(),
                    'per_page' => $gateways->perPage(),
                    'last_page' => $gateways->lastPage(),
                ],
                'stats' => $stats,
                'filters' => $request->only(['search', 'status', 'sort_field', 'sort_direction']),
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load withdrawal gateways. Please try again.']);
        }
    }

    public function create(): Response | RedirectResponse
    {
        try {
            return Inertia::render('Admin/WithdrawalGateways/Form', [
                'currencies' => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDT'],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form. Please try again.']);
        }
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:withdrawal_gateways,name',
            'currency' => 'required|string|max:10',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'fixed_charge' => 'nullable|numeric|min:0',
            'percent_charge' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'parameters' => 'nullable|string',
            'status' => 'required|in:true,false,1,0',
        ]);

        try {
            $data = $request->except(['image', 'parameters']);
            $data['status'] = filter_var($request->status, FILTER_VALIDATE_BOOLEAN);
            if ($request->filled('parameters')) {
                $parameters = json_decode($request->parameters, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['parameters' => 'Invalid JSON format for parameters'])->withInput();
                }
                $data['parameters'] = $parameters;
            }
            $data['fixed_charge'] = $data['fixed_charge'] ?? 0;
            $data['percent_charge'] = $data['percent_charge'] ?? 0;

            WithdrawalGateway::create($data);
            return redirect()->route('admin.withdrawal-gateways.index')->with('success', 'Withdrawal gateway created successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create withdrawal gateway. Please try again.']);
        }
    }

    public function edit(WithdrawalGateway $withdrawalGateway): Response | RedirectResponse
    {
        try {
            return Inertia::render('Admin/WithdrawalGateways/Form', [
                'gateway' => $withdrawalGateway,
                'currencies' => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDT'],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load edit form. Please try again.']);
        }
    }

    public function update(Request $request, WithdrawalGateway $withdrawalGateway): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:withdrawal_gateways,name,' . $withdrawalGateway->id,
            'currency' => 'required|string|max:10',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'fixed_charge' => 'nullable|numeric|min:0',
            'percent_charge' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'parameters' => 'nullable|string',
            'status' => 'required|in:true,false,1,0',
        ]);

        try {
            $data = $request->except(['image', 'parameters']);
            $data['status'] = filter_var($request->status, FILTER_VALIDATE_BOOLEAN);
            if ($request->filled('parameters')) {
                $parameters = json_decode($request->parameters, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['parameters' => 'Invalid JSON format for parameters'])->withInput();
                }
                $data['parameters'] = $parameters;
            } else {
                $data['parameters'] = null;
            }

            $data['fixed_charge'] = $data['fixed_charge'] ?? 0;
            $data['percent_charge'] = $data['percent_charge'] ?? 0;
            $withdrawalGateway->update($data);
            return redirect()->route('admin.withdrawal-gateways.index')->with('success', 'Withdrawal gateway updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update withdrawal gateway. Please try again.']);
        }
    }

    /**
     * @param WithdrawalGateway $withdrawalGateway
     * @return RedirectResponse
     */
    public function destroy(WithdrawalGateway $withdrawalGateway): \Illuminate\Http\RedirectResponse
    {
        try {
            if ($withdrawalGateway->withdrawals()->exists()) {
                return back()->withErrors('error', 'Cannot delete withdrawal gateway that has existing withdrawals.');
            }

            $withdrawalGateway->delete();
            return redirect()->back()->with('success', 'Withdrawal gateway deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors('error', 'Failed to delete withdrawal gateway. Please try again.');
        }
    }
}
