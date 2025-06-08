<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    /**
     * Store a new push subscription
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => 'required|array',
            'subscription.endpoint' => 'required|string|max:500',
            'subscription.keys' => 'required|array',
            'subscription.keys.p256dh' => 'required|string',
            'subscription.keys.auth' => 'required|string',
        ]);

        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if we already have a subscription for this endpoint
        $existingSubscription = PushSubscription::where('endpoint', $request->input('subscription.endpoint'))->first();

        if ($existingSubscription) {
            // If the subscription exists but belongs to a different user, update it
            if ($existingSubscription->user_id !== $user->id) {
                $existingSubscription->update([
                    'user_id' => $user->id,
                    'p256dh' => $request->input('subscription.keys.p256dh'),
                    'auth' => $request->input('subscription.keys.auth'),
                    'subscription_data' => json_encode($request->input('subscription')),
                ]);

                return response()->json([
                    'message' => 'Push subscription updated successfully',
                    'id' => $existingSubscription->id,
                ]);
            }

            // If it belongs to the same user, just return success
            return response()->json([
                'message' => 'Push subscription already exists',
                'id' => $existingSubscription->id,
            ]);
        }

        // Create new subscription if none exists
        $subscription = PushSubscription::updateOrCreate(
            [
                'endpoint' => $request->input('subscription.endpoint'),
            ],
            [
                'user_id' => $user->id,
                'p256dh' => $request->input('subscription.keys.p256dh'),
                'auth' => $request->input('subscription.keys.auth'),
                'subscription_data' => json_encode($request->input('subscription')),
            ]
        );

        return response()->json([
            'message' => 'Push subscription saved successfully',
            'id' => $subscription->id,
        ]);
    }

    /**
     * Verify if a push subscription exists on the server
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if subscription exists for this user
        $exists = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->input('endpoint'))
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Delete a push subscription
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => 'required|array',
            'subscription.endpoint' => 'required|string',
        ]);

        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Find and delete the subscription
        $deleted = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->input('subscription.endpoint'))
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Push subscription removed successfully']);
        }

        return response()->json(['message' => 'Push subscription not found'], 404);
    }
}
