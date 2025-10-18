<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionParticipant;
use App\Models\MiningCompetition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MiningCompetitionController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = MiningCompetition::withCount('participants');
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

        $sortField = $request->get('sort_field', 'starts_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $competitions = $query->paginate(20)->withQueryString();
        $stats = [
            'totalCompetitions' => MiningCompetition::count(),
            'activeCompetitions' => MiningCompetition::where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->count(),
            'totalPrizePool' => MiningCompetition::where('is_active', true)->sum('prize_pool'),
            'totalParticipants' => DB::table('competition_participants')->count()
        ];

        return Inertia::render('Admin/MiningCompetitions/Index', [
            'competitions' => $competitions->items(),
            'meta' => [
                'total' => $competitions->total(),
                'current_page' => $competitions->currentPage(),
                'per_page' => $competitions->perPage(),
                'last_page' => $competitions->lastPage()
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
        return Inertia::render('Admin/MiningCompetitions/Form');
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
            'type' => 'required|in:daily,weekly,monthly',
            'prize_pool' => 'required|numeric|min:0',
            'entry_fee' => 'required|numeric|min:0',
            'admin_fee_percentage' => 'required|numeric|min:0|max:100',
            'prizes' => 'required|array',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'max_participants' => 'nullable|integer|min:1',
            'is_active' => 'boolean'
        ]);

        MiningCompetition::create($validated);
        return redirect()->route('admin.mining-competitions.index')->with('success', 'Competition created successfully');
    }

    /**
     * @param MiningCompetition $miningCompetition
     * @return Response
     */
    public function edit(MiningCompetition $miningCompetition): Response
    {
        return Inertia::render('Admin/MiningCompetitions/Form', [
            'competition' => $miningCompetition
        ]);
    }

    /**
     * @param Request $request
     * @param MiningCompetition $miningCompetition
     * @return RedirectResponse
     */
    public function update(Request $request, MiningCompetition $miningCompetition): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:daily,weekly,monthly',
            'prize_pool' => 'required|numeric|min:0',
            'entry_fee' => 'required|numeric|min:0',
            'admin_fee_percentage' => 'required|numeric|min:0|max:100',
            'prizes' => 'required|array',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'max_participants' => 'nullable|integer|min:1',
            'is_active' => 'boolean'
        ]);

        $miningCompetition->update($validated);
        return redirect()->route('admin.mining-competitions.index')->with('success', 'Competition updated successfully');
    }

    /**
     * @param MiningCompetition $miningCompetition
     * @return RedirectResponse
     */
    public function destroy(MiningCompetition $miningCompetition): RedirectResponse
    {
        $miningCompetition->delete();
        return redirect()->route('admin.mining-competitions.index')->with('success', 'Competition deleted successfully');
    }

    /**
     * @param Request $request
     * @param MiningCompetition $miningCompetition
     * @return Response
     */
    public function show(Request $request, MiningCompetition $miningCompetition): Response
    {
        $query = CompetitionParticipant::with(['user:id,name,email'])
            ->where('mining_competition_id', $miningCompetition->id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'prize_won') {
                $query->where('prize_won', '>', 0);
            } elseif ($request->status === 'prize_claimed') {
                $query->where('prize_claimed', true);
            } elseif ($request->status === 'no_prize') {
                $query->where('prize_won', 0);
            }
        }

        $sortField = $request->get('sort_field', 'mined_amount');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $participants = $query->paginate(20)->withQueryString();
        $rankedParticipants = $participants->getCollection()->map(function ($participant, $index) use ($request) {
            $participant->calculated_rank = ($request->get('page', 1) - 1) * 20 + $index + 1;
            return $participant;
        });

        $participants->setCollection($rankedParticipants);
        $stats = [
            'totalParticipants' => CompetitionParticipant::where('mining_competition_id', $miningCompetition->id)->count(),
            'totalMined' => CompetitionParticipant::where('mining_competition_id', $miningCompetition->id)->sum('mined_amount'),
            'totalPrizeWon' => CompetitionParticipant::where('mining_competition_id', $miningCompetition->id)->sum('prize_won'),
            'totalEntryFees' => CompetitionParticipant::where('mining_competition_id', $miningCompetition->id)->sum('entry_fee_paid'),
            'averageMined' => CompetitionParticipant::where('mining_competition_id', $miningCompetition->id)->avg('mined_amount'),
        ];

        return Inertia::render('Admin/MiningCompetitions/Show', [
            'competition' => $miningCompetition,
            'participants' => $participants->items(),
            'meta' => [
                'total' => $participants->total(),
                'current_page' => $participants->currentPage(),
                'per_page' => $participants->perPage(),
                'last_page' => $participants->lastPage()
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'sort_field', 'sort_direction'])
        ]);
    }
}
