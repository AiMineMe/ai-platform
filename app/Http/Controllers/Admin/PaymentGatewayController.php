<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\UploadedFile;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayController extends Controller
{
    use UploadedFile;

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:5|max:100',
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|in:automatic,manual',
            'status' => 'nullable|in:0,1',
            'sort_field' => 'nullable|in:name,type,currency,rate,status,sort_order,created_at',
            'sort_direction' => 'nullable|in:asc,desc'
        ]);

        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            $type = $request->get('type');
            $status = $request->get('status');
            $sortField = $request->get('sort_field', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            $query = PaymentGateway::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($status !== null && $status !== '') {
                $query->where('status', (bool)$status);
            }

            $gateways = $query->orderBy($sortField, $sortDirection)
                ->paginate($perPage);

            $stats = [
                'total_gateways' => PaymentGateway::count(),
                'active_gateways' => PaymentGateway::where('status', true)->count(),
                'automatic_gateways' => PaymentGateway::where('type', 'automatic')->count(),
                'manual_gateways' => PaymentGateway::where('type', 'manual')->count(),
            ];

            return Inertia::render('Admin/PaymentGateways/Index', [
                'gateways' => $gateways->items(),
                'meta' => [
                    'total' => $gateways->total(),
                    'current_page' => $gateways->currentPage(),
                    'per_page' => $gateways->perPage(),
                    'last_page' => $gateways->lastPage(),
                ],
                'stats' => $stats,
                'filters' => $request->only(['search', 'type', 'status', 'sort_field', 'sort_direction']),
                'gateway_types' => [
                    ['value' => 'automatic', 'label' => 'Automatic'],
                    ['value' => 'manual', 'label' => 'Manual'],
                ],
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load payment gateways. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:payment_gateways,name',
            'type' => 'required|in:automatic,manual',
            'currency' => 'required|string|max:10',
            'rate' => 'required|numeric|min:0.01|max:999999.99',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'fixed_charge' => 'nullable|numeric|min:0',
            'percent_charge' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:1000',
            'credentials' => 'nullable|string',
            'parameters' => 'nullable|string',
            'status' => 'nullable',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $data = $validator->validated();
            $data['status'] = $request->status === 'true' || $request->status === true;
            $data['slug'] = Str::slug($data['name']);

            if ($request->hasFile('file')) {
                $uploadedFile = $this->move($request->file('file'));
                if ($uploadedFile) {
                    $data['file'] = $uploadedFile;
                }
            }

            if ($request->credentials) {
                $credentials = json_decode($request->credentials, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['credentials' => 'Invalid JSON format for credentials'])->withInput();
                }
                $data['credentials'] = $credentials;
            } else {
                unset($data['credentials']);
            }

            if ($request->type === 'manual' && $request->parameters) {
                $parameters = json_decode($request->parameters, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['parameters' => 'Invalid JSON format for parameters'])->withInput();
                }
                $data['parameters'] = $parameters;
            } else {
                unset($data['parameters']);
            }

            $data['fixed_charge'] = $data['fixed_charge'] ?? 0;
            $data['percent_charge'] = $data['percent_charge'] ?? 0;
            PaymentGateway::create($data);
            return redirect()->back()->with('success', 'Payment gateway created successfully');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create payment gateway. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param PaymentGateway $paymentGateway
     * @return RedirectResponse
     */
    public function update(Request $request, PaymentGateway $paymentGateway): \Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:payment_gateways,name,' . $paymentGateway->id,
            'type' => 'required|in:automatic,manual',
            'currency' => 'required|string|max:10',
            'rate' => 'required|numeric|min:0.01|max:999999.99',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'fixed_charge' => 'nullable|numeric|min:0',
            'percent_charge' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:1000',
            'credentials' => 'nullable|string',
            'parameters' => 'nullable|string',
            'status' => 'nullable',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $data = $validator->validated();
            $data['status'] = $request->status === 'true' || $request->status === true;
            $data['slug'] = Str::slug($data['name']);

            // Handle file upload
            if ($request->hasFile('file')) {
                $oldFile = $paymentGateway->file;
                $uploadedFile = $this->move($request->file('file'), null, $oldFile);
                if ($uploadedFile) {
                    $data['file'] = $uploadedFile;
                }
            }

            if ($request->credentials) {
                $credentials = json_decode($request->credentials, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['credentials' => 'Invalid JSON format for credentials'])->withInput();
                }
                $data['credentials'] = $credentials;
            } else {
                $data['credentials'] = null;
            }

            if ($request->type === 'manual' && $request->parameters) {
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
            $paymentGateway->update($data);

            return redirect('/admin/payment-gateways')->with('success', 'Payment gateway updated successfully');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update payment gateway. Please try again.']);
        }
    }

    /**
     * @return Response|RedirectResponse
     */
    public function create(): Response | RedirectResponse
    {
        try {
            return Inertia::render('Admin/PaymentGateways/Form', [
                'currencies' => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDT'],
                'gateway_types' => [
                    ['value' => 'automatic', 'label' => 'Automatic'],
                    ['value' => 'manual', 'label' => 'Manual'],
                ],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load create form. Please try again.']);
        }
    }

    /**
     * @param PaymentGateway $paymentGateway
     * @return Response|RedirectResponse
     */
    public function edit(PaymentGateway $paymentGateway): Response | RedirectResponse
    {
        try {
            return Inertia::render('Admin/PaymentGateways/Form', [
                'gateway' => $paymentGateway,
                'currencies' => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDT'],
                'gateway_types' => [
                    ['value' => 'automatic', 'label' => 'Automatic'],
                    ['value' => 'manual', 'label' => 'Manual'],
                ],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to load edit form. Please try again.']);
        }
    }

    /**
     * @param PaymentGateway $paymentGateway
     * @return RedirectResponse
     */
    public function destroy(PaymentGateway $paymentGateway): \Illuminate\Http\RedirectResponse
    {
        try {
            if ($paymentGateway->deposits()->exists()) {
                return back()->withErrors(['error' => 'Cannot delete withdrawal gateway that has existing withdrawals.']);
            }

            $paymentGateway->delete();
            return back()->with('success', 'Payment gateway deleted successfully');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete payment gateway. Please try again.']);
        }
    }
}
