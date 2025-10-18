<?php

namespace App\Http\Controllers\Admin;

use App\Enums\User\RoleStatus;
use App\Enums\User\Status;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\GlobalMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $role = $request->get('role');
        $status = $request->get('status');
        $kycStatus = $request->get('kyc_status');
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query = User::where('role', '!=', RoleStatus::ADMIN->value);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($kycStatus !== null && $kycStatus !== '') {
            $query->where('kyc_status', $kycStatus);
        }

        $allowedSortFields = ['name', 'email', 'role', 'status', 'kyc_status', 'created_at', 'last_login'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $perPage = (int) $request->get('per_page', 20);
        $users = $query->paginate($perPage)->appends($request->all());
        $transformedUsers = $users->through(function ($user) use ($request) {
            return (new UserResource($user))->toArray($request);
        });

        $statsQuery = User::where('role', '!=', RoleStatus::ADMIN->value);
        if ($search) {
            $statsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }
        if ($role) {
            $statsQuery->where('role', $role);
        }
        if ($status !== null && $status !== '') {
            $statsQuery->where('status', $status);
        }
        if ($kycStatus !== null && $kycStatus !== '') {
            $statsQuery->where('kyc_status', $kycStatus);
        }

        $stats = [
            'totalUsers' => $statsQuery->count(),
            'activeUsers' => (clone $statsQuery)->where('status', 1)->count(),
            'emailVerifiedUsers' => (clone $statsQuery)->whereNotNull('email_verified_at')->count(),
            'pendingUsers' => (clone $statsQuery)->where('status', 2)->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $transformedUsers->items(),
            'meta' => [
                'total' => $transformedUsers->total(),
                'current_page' => $transformedUsers->currentPage(),
                'per_page' => $transformedUsers->perPage(),
                'last_page' => $transformedUsers->lastPage(),
            ],
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'kyc_status' => $kycStatus,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ],
            'currentUser' => auth()->user() ? [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'role' => auth()->user()->role ?? 'user',
            ] : null,
        ]);
    }


    /**
     * Update user status
     * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'status' => [
                    'required',
                    'integer',
                    Rule::in([
                        Status::PENDING->value,
                        Status::INACTIVE->value,
                        Status::ACTIVE->value,
                        Status::SUSPENDED->value,
                    ])
                ]
            ]);

            $newStatus = $validated['status'];
            $oldStatus = $user->status;

            if ($oldStatus == Status::PENDING->value && $newStatus == Status::ACTIVE->value && is_null($user->email_verified_at)) {
                $user->update([
                    'email_verified_at' => now(),
                    'status' => $newStatus
                ]);
            } else {
                $user->update(['status' => $newStatus]);
            }

            Log::info('User status updated', [
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => auth()->id()
            ]);

            return redirect()->back()->with('success', 'User status updated successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to update user status', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }


    /**
     * Send mail to specific user
     * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    public function sendMail(Request $request, User $user): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'subject' => 'required|string|max:255',
                'content' => 'required|string'
            ]);

            if (!$user->email) {
                return redirect()->back()->with('error', 'User does not have an email address.');
            }

            Mail::send(new GlobalMail($user, $validated['subject'], $validated['content']));
            Log::info('Mail sent to user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'subject' => $validated['subject'],
                'sent_by' => auth()->id()
            ]);

            return redirect()->back()->with('success', "Mail sent successfully to {$user->email}!");

        } catch (\Exception $e) {
            Log::error('Failed to send mail to user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to send mail. Please try again.');
        }
    }


    /**
     * Login as specific user (Admin impersonation)
     * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    public function loginAs(Request $request, User $user): RedirectResponse
    {
        try {
            if ($user->status === Status::INACTIVE->value) {
                return redirect()->back()->with('error', 'Cannot login as inactive user.');
            }

            if ($user->role === RoleStatus::ADMIN->value) {
                return redirect()->back()->with('error', 'Cannot login as another admin user.');
            }

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            auth()->login($user);
            return redirect()->route('user.dashboard')->with('success', "Successfully logged in as {$user->name}");

        } catch (\Exception $e) {
            Log::error('Failed to login as user', [
                'admin_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to login as user. Please try again.');
        }
    }
}
