<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MiningSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MiningSessionController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = MiningSession::with(['user:id,name,email'])
            ->select(['*']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('id', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        $sortField = $request->get('sort_field', 'total_mined');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        $miningSessions = $query->paginate(20)->withQueryString();

        $stats = [
            'totalSessions' => MiningSession::count(),
            'activeSessions' => MiningSession::where('is_active', true)->count(),
            'totalMined' => MiningSession::sum('total_mined'),
            'averageLevel' => MiningSession::avg('level') ?? 0
        ];

        return Inertia::render('Admin/MiningSessions/Index', [
            'miningSessions' => $miningSessions->items(),
            'meta' => [
                'total' => $miningSessions->total(),
                'current_page' => $miningSessions->currentPage(),
                'per_page' => $miningSessions->perPage(),
                'last_page' => $miningSessions->lastPage()
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'level', 'sort_field', 'sort_direction'])
        ]);
    }

    /**
     * @param MiningSession $miningSession
     * @return Response
     */
    public function show(MiningSession $miningSession): Response
    {
        $miningSession->load(['user:id,name,email']);
        return Inertia::render('Admin/MiningSessions/Show', [
            'miningSession' => $miningSession
        ]);
    }

    /**
     * @param MiningSession $miningSession
     * @return Response
     */
    public function edit(MiningSession $miningSession): Response
    {
        $miningSession->load(['user:id,name,email']);
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();

        return Inertia::render('Admin/MiningSessions/Form', [
            'miningSession' => $miningSession,
            'users' => $users
        ]);
    }

    /**
     * @param Request $request
     * @param MiningSession $miningSession
     * @return RedirectResponse
     */
    public function update(Request $request, MiningSession $miningSession): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'session_type' => 'required|in:standard,boost,premium',
            'mining_rate' => 'required|numeric|min:0|max:1',
            'level' => 'required|integer|min:1|max:100',
            'experience_points' => 'required|integer|min:0',
            'streak_days' => 'required|integer|min:0',
            'multiplier' => 'required|numeric|min:1|max:10',
            'is_active' => 'boolean'
        ]);

        $miningSession->update($validated);
        return redirect()->route('admin.mining-sessions.index')->with('success', 'Mining session updated successfully');
    }

    /**
     * @param MiningSession $miningSession
     * @return RedirectResponse
     */
    public function destroy(MiningSession $miningSession): RedirectResponse
    {
        $miningSession->delete();
        return redirect()->route('admin.mining-sessions.index')->with('success', 'Mining session deleted successfully');
    }

    /**
     * @param Request $request
     * @param MiningSession $miningSession
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, MiningSession $miningSession): RedirectResponse
    {
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $miningSession->update(['is_active' => $request->is_active]);
        return back()->with('success', 'Mining session status updated successfully');
    }

    /**
     * @param MiningSession $miningSession
     * @return RedirectResponse
     */
    public function resetSession(MiningSession $miningSession): RedirectResponse
    {
        $miningSession->update([
            'current_balance' => 0,
            'session_started_at' => now(),
            'session_ends_at' => now()->addHours(24),
            'last_claim_at' => null
        ]);

        return back()->with('success', 'Mining session reset successfully');
    }
}
