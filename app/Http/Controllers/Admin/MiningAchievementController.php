<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MiningAchievement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MiningAchievementController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = MiningAchievement::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $achievements = $query->paginate(20)->withQueryString();
        $stats = [
            'totalAchievements' => MiningAchievement::count(),
            'activeAchievements' => MiningAchievement::where('is_active', true)->count(),
            'totalRewards' => MiningAchievement::where('is_active', true)->sum('reward_amount'),
            'types' => MiningAchievement::distinct('type')->pluck('type')->count()
        ];

        return Inertia::render('Admin/MiningAchievements/Index', [
            'achievements' => $achievements->items(),
            'meta' => [
                'total' => $achievements->total(),
                'current_page' => $achievements->currentPage(),
                'per_page' => $achievements->perPage(),
                'last_page' => $achievements->lastPage()
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'type', 'status', 'sort_field', 'sort_direction'])
        ]);
    }

    /**
     * @return Response
     */
    public function create(): Response
    {
        return Inertia::render('Admin/MiningAchievements/Form');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'icon' => 'nullable|string|max:255',
            'type' => 'required|in:mining,streak,level,total,competition',
            'condition' => 'required|string|max:255',
            'reward_amount' => 'required|numeric|min:0',
            'reward_type' => 'required|in:tokens,multiplier,boost',
            'required_value' => 'required|integer|min:1',
            'is_active' => 'boolean'
        ]);

        MiningAchievement::create($validated);
        return redirect()->route('admin.mining-achievements.index')->with('success', 'Achievement created successfully');
    }

    /**
     * @param MiningAchievement $miningAchievement
     * @return Response
     */
    public function edit(MiningAchievement $miningAchievement): Response
    {
        return Inertia::render('Admin/MiningAchievements/Form', [
            'achievement' => $miningAchievement
        ]);
    }

    /**
     * @param Request $request
     * @param MiningAchievement $miningAchievement
     * @return RedirectResponse
     */
    public function update(Request $request, MiningAchievement $miningAchievement): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'icon' => 'nullable|string|max:255',
            'type' => 'required|in:mining,streak,level,total,competition',
            'condition' => 'required|string|max:255',
            'reward_amount' => 'required|numeric|min:0',
            'reward_type' => 'required|in:tokens,multiplier,boost',
            'required_value' => 'required|integer|min:1',
            'is_active' => 'boolean'
        ]);

        $miningAchievement->update($validated);
        return redirect()->route('admin.mining-achievements.index')->with('success', 'Achievement updated successfully');
    }

    /**
     * @param MiningAchievement $miningAchievement
     * @return RedirectResponse
     */
    public function destroy(MiningAchievement $miningAchievement): RedirectResponse
    {
        $miningAchievement->delete();
        return redirect()->route('admin.mining-achievements.index')->with('success', 'Achievement deleted successfully');
    }

    /**
     * @param MiningAchievement $miningAchievement
     * @return RedirectResponse
     */
    public function toggleStatus(MiningAchievement $miningAchievement): RedirectResponse
    {
        $miningAchievement->update(['is_active' => !$miningAchievement->is_active]);
        return back()->with('success', 'Achievement status updated successfully');
    }
}
