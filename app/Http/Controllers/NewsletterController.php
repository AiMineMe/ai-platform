<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid email address.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;
        $existingSubscriber = Subscriber::where('email', $email)->first();

        if ($existingSubscriber) {
            if ($existingSubscriber->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already subscribed to our newsletter.',
                ], 409);
            } else {
                $existingSubscriber->resubscribe();
                return response()->json([
                    'success' => true,
                    'message' => 'Welcome back! You have been resubscribed to our newsletter.',
                ]);
            }
        }

        try {
            Subscriber::create([
                'email' => $email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for subscribing! You will receive updates in your inbox.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }


    public function unsubscribe($token): View
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            abort(404, 'Invalid unsubscribe link.');
        }

        $subscriber->unsubscribe();

        return view('newsletter.unsubscribed', [
            'email' => $subscriber->email
        ]);
    }
}
