<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IcoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class IcoTokenController extends Controller
{

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = IcoToken::query();
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('featured')) {
            $featured = $request->boolean('featured');
            $query->where('is_featured', $featured);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSortFields = ['name', 'symbol', 'price', 'status', 'created_at', 'updated_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $query->orderBy($sortField, $sortDirection);
        $perPage = min($request->get('per_page', 20), 50);
        $icoTokens = $query->paginate($perPage);

        $stats = $this->calculateIcoTokenStats();
        return Inertia::render('Admin/IcoTokens/Index', [
            'icoTokens' => $icoTokens->items(),
            'meta' => [
                'total' => $icoTokens->total(),
                'current_page' => $icoTokens->currentPage(),
                'per_page' => $icoTokens->perPage(),
                'last_page' => $icoTokens->lastPage(),
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'featured', 'sort_field', 'sort_direction']),
            'statuses' => ['active', 'paused', 'completed', 'cancelled']
        ]);
    }

    /**
     * Calculate ICO token statistics
     */
    private function calculateIcoTokenStats(): array
    {
        $allTokens = IcoToken::all();
        $totalTokens = $allTokens->count();
        $activeTokens = $allTokens->where('status', 'active')->count();
        $featuredTokens = $allTokens->where('is_featured', true)->count();
        $totalRaised = $allTokens->sum(function ($token) {
            $tokensSold = (float) ($token->tokens_sold ?? 0);
            $price = (float) ($token->price ?? 0);
            return $tokensSold * $price;
        });

        return [
            'totalTokens' => $totalTokens,
            'activeTokens' => $activeTokens,
            'totalRaised' => $totalRaised,
            'featuredTokens' => $featuredTokens,
        ];
    }


    /**
     * @return Response
     */
    public function create(): Response
    {
        return Inertia::render('Admin/IcoTokens/Form');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10|unique:ico_tokens,symbol',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.0001|max:999999.9999',
            'current_price' => 'nullable|numeric|min:0.0001|max:999999.9999',
            'total_supply' => 'required|integer|min:1|max:999999999999',
            'sale_start_date' => 'required|date|after_or_equal:today',
            'sale_end_date' => 'required|date|after:sale_start_date',
            'status' => ['required', Rule::in(['active', 'paused'])],
            'is_featured' => 'boolean'
        ]);

        $validated['tokens_sold'] = 0;
        $validated['is_featured'] = $validated['is_featured'] ?? false;
        if (!isset($validated['current_price']) || $validated['current_price'] === null) {
            $validated['current_price'] = $validated['price'];
        }

        try {
            IcoToken::create($validated);
            return redirect()->route('admin.ico-tokens.index')->with('success', 'ICO Token created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create ICO token: ' . $e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Failed to create ICO token. Please try again.']);
        }
    }

    /**
     * @param Request $request
     * @param IcoToken $icoToken
     * @return RedirectResponse
     */
    public function update(Request $request, IcoToken $icoToken): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => [
                'required',
                'string',
                'max:10',
                Rule::unique('ico_tokens', 'symbol')->ignore($icoToken->id)
            ],
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.0001|max:999999.9999',
            'current_price' => 'nullable|numeric|min:0.0001|max:999999.9999',
            'total_supply' => 'required|integer|min:1|max:999999999999',
            'tokens_sold' => 'required|integer|min:0|max:' . $request->input('total_supply', 999999999999),
            'sale_start_date' => 'required|date',
            'sale_end_date' => 'required|date|after:sale_start_date',
            'status' => ['required', Rule::in(['active', 'paused', 'completed', 'cancelled'])],
            'is_featured' => 'boolean'
        ]);

        $validated['is_featured'] = $validated['is_featured'] ?? false;
        if (!isset($validated['current_price']) || $validated['current_price'] === null) {
            $validated['current_price'] = $validated['price'];
        }

        if ($validated['tokens_sold'] > $validated['total_supply']) {
            return redirect()->back()->withErrors(['error' => 'Tokens sold cannot exceed total supply.']);
        }

        try {
            $icoToken->update($validated);
            return redirect()->route('admin.ico-tokens.index')
                ->with('success', 'ICO Token updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update ICO token: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update ICO token. Please try again.']);
        }
    }

    /**
     * @param IcoToken $icoToken
     * @return Response
     */
    public function edit(IcoToken $icoToken): Response
    {
        return Inertia::render('Admin/IcoTokens/Form', [
            'icoToken' => $icoToken
        ]);
    }

    /**
     * @param IcoToken $icoToken
     * @return RedirectResponse
     */
    public function destroy(IcoToken $icoToken): RedirectResponse
    {
        try {
            if ($icoToken->tokens_sold > 0) {
                return back()->withErrors(['error' => 'Cannot delete token with existing sales. Please contact administrator.']);
            }

            $tokenName = $icoToken->name;
            $icoToken->delete();

            return redirect()->back()->with('success', "ICO Token '{$tokenName}' deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete ICO token. Please try again.']);
        }
    }

    /**
     * @param IcoToken $icoToken
     * @return RedirectResponse
     */
    public function toggleFeatured(IcoToken $icoToken): RedirectResponse
    {
        try {
            $newStatus = !$icoToken->is_featured;

            $icoToken->update([
                'is_featured' => $newStatus
            ]);

            $statusText = $newStatus ? 'featured' : 'unfeatured';
            return redirect()->back()->with('success', "Token '{$icoToken->name}' {$statusText} successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update featured status. Please try again.']);
        }
    }

}
