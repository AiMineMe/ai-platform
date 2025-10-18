<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterMail;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function index(Request $request): Response | RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,unsubscribed',
            'sort_field' => 'nullable|string|in:email,status,created_at,subscribed_at',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $search = $request->get('search');
        $status = $request->get('status');
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = 20;

        $query = Subscriber::query();
        if ($search) {
            $query->where('email', 'like', '%' . $search . '%');
        }

        if ($status) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'unsubscribed') {
                $query->unsubscribed();
            }
        }

        $query->orderBy($sortField, $sortDirection);
        $subscribers = $query->paginate($perPage)->withQueryString();
        $stats = [
            'total' => Subscriber::count(),
            'active' => Subscriber::active()->count(),
            'unsubscribed' => Subscriber::unsubscribed()->count(),
            'today' => Subscriber::whereDate('created_at', today())->count(),
        ];

        $filters = [
            'search' => $search,
            'status' => $status,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
        ];

        return Inertia::render('Admin/Newsletter/Index', [
            'subscribers' => $subscribers,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'recipient_type' => 'required|in:all,active',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill in all required fields correctly.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = Subscriber::query();

            if ($request->recipient_type === 'active') {
                $query->active();
            }

            $subscribers = $query->get();
            if ($subscribers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscribers found to send email.',
                ]);
            }

            $totalCount = $subscribers->count();
            foreach ($subscribers as $subscriber) {
                try {
                    $newsletterMail = new NewsletterMail(
                        $request->input('subject'),
                        $request->input('content'),
                        $subscriber->getUnsubscribeUrl()
                    );

                    Mail::to($subscriber->email)->queue($newsletterMail);
                    Log::info('Newsletter queued for: ' . $subscriber->email, [
                        'subscriber_id' => $subscriber->id,
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to queue newsletter for: ' . $subscriber->email, [
                        'error' => $e->getMessage(),
                        'subscriber_id' => $subscriber->id,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Newsletter has been queued for {$totalCount} subscribers and will be sent shortly.",
            ]);

        } catch (\Exception $e) {
            Log::error('Newsletter queue error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to queue newsletter. Please try again.',
            ], 500);
        }
    }

    /**
     * @param Subscriber $subscriber
     * @return RedirectResponse
     */
    public function destroy(Subscriber $subscriber): \Illuminate\Http\RedirectResponse
    {
        try {
            $email = $subscriber->email;
            $subscriber->delete();

            return redirect()->back()->with('success', "Subscriber {$email} deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to delete subscriber: ' . $e->getMessage(), [
                'subscriber_id' => $subscriber->id,
            ]);

            return redirect()->back()->with('error', 'Failed to delete subscriber. Please try again.');
        }
    }
}
