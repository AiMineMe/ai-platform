<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginAttemptResource;
use App\Models\LoginAttempt;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class LoginAttemptController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $successful = $request->get('successful');
        $deviceType = $request->get('device_type');
        $browser = $request->get('browser');
        $platform = $request->get('platform');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $sortField = $request->get('sort_field', 'attempted_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query = LoginAttempt::with('user:id,name,email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($successful !== null && $successful !== '') {
            $query->where('successful', (bool) $successful);
        }

        if ($deviceType) {
            $query->where('device_type', $deviceType);
        }

        if ($browser) {
            $query->where('browser', $browser);
        }

        if ($platform) {
            $query->where('platform', $platform);
        }

        if ($dateFrom) {
            try {
                $query->where('attempted_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (Exception $e) {
                throw new Exception("Invalid date from {$dateFrom}");
            }
        }

        if ($dateTo) {
            try {
                $query->where('attempted_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (Exception $e) {
                throw new Exception("Invalid date to {$dateTo}");
            }
        }

        $allowedSortFields = [
            'email', 'ip_address', 'location', 'device_type',
            'browser', 'platform', 'successful', 'attempted_at', 'created_at'
        ];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $perPage = $request->get('per_page', 20);
        $loginAttempts = $query->paginate($perPage)->appends($request->all());
        $transformedAttempts = $loginAttempts->through(function ($attempt) use ($request) {
            return (new LoginAttemptResource($attempt))->toArray($request);
        });

        $filterOptions = $this->getFilterOptions();
        return Inertia::render('Admin/LoginAttempts/Index', [
            'loginAttempts' => $transformedAttempts->items(),
            'meta' => [
                'total' => $transformedAttempts->total(),
                'current_page' => $transformedAttempts->currentPage(),
                'per_page' => $transformedAttempts->perPage(),
                'last_page' => $transformedAttempts->lastPage(),
            ],
            'filters' => [
                'search' => $search,
                'successful' => $successful,
                'device_type' => $deviceType,
                'browser' => $browser,
                'platform' => $platform,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ],
            'filterOptions' => $filterOptions,
            'statistics' => $this->getStatistics(),
            'currentUser' => auth()->user() ? [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'role' => auth()->user()->role ?? 'user',
            ] : null,
        ]);
    }

    /**
     * @return array
     */
    private function getFilterOptions(): array
    {
        return [
            'device_types' => LoginAttempt::select('device_type')
                ->distinct()
                ->whereNotNull('device_type')
                ->where('device_type', '!=', '')
                ->orderBy('device_type')
                ->pluck('device_type')
                ->toArray(),

            'browsers' => LoginAttempt::select('browser')
                ->distinct()
                ->whereNotNull('browser')
                ->where('browser', '!=', '')
                ->orderBy('browser')
                ->pluck('browser')
                ->toArray(),

            'platforms' => LoginAttempt::select('platform')
                ->distinct()
                ->whereNotNull('platform')
                ->where('platform', '!=', '')
                ->orderBy('platform')
                ->pluck('platform')
                ->toArray(),
        ];
    }


    /**
     * @return array
     */
    private function getStatistics(): array
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'total_attempts' => LoginAttempt::count(),
            'successful_attempts' => LoginAttempt::where('successful', true)->count(),
            'failed_attempts' => LoginAttempt::where('successful', false)->count(),
            'today_attempts' => LoginAttempt::whereDate('attempted_at', $today)->count(),
            'week_attempts' => LoginAttempt::where('attempted_at', '>=', $thisWeek)->count(),
            'month_attempts' => LoginAttempt::where('attempted_at', '>=', $thisMonth)->count(),
            'unique_ips_today' => LoginAttempt::whereDate('attempted_at', $today)
                ->distinct('ip_address')
                ->count('ip_address'),
            'success_rate' => $this->calculateSuccessRate(),
        ];
    }

    /**
     * @return float
     */
    private function calculateSuccessRate(): float
    {
        $totalAttempts = LoginAttempt::count();

        if ($totalAttempts === 0) {
            return 0;
        }

        $successfulAttempts = LoginAttempt::where('successful', true)->count();
        return round(($successfulAttempts / $totalAttempts) * 100, 2);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function clearOld(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365'
        ]);

        $cutoffDate = Carbon::now()->subDays($validated['days']);
        $deletedCount = LoginAttempt::where('attempted_at', '<', $cutoffDate)->delete();
        return redirect()->back()->with('success', "Deleted {$deletedCount} old login attempts.");
    }
}
