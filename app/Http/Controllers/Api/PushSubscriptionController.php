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
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => 'required|array',
            'subscription.endpoint' => 'required|string|max:500',
            'subscription.keys' => 'required|array',
            'subscription.keys.p256dh' => 'required|string',
            'subscription.keys.auth' => 'required|string',
            'reactivate' => 'sometimes|boolean',
        ]);

        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $existingSubscription = PushSubscription::where('endpoint', $request->input('subscription.endpoint'))->first();

        if ($existingSubscription) {
            $transferred = $existingSubscription->user_id !== $user->id;
            $keysChanged = $existingSubscription->p256dh !== $request->input('subscription.keys.p256dh')
                || $existingSubscription->auth !== $request->input('subscription.keys.auth');
            $updates = [
                'user_id' => $user->id,
                'p256dh' => $request->input('subscription.keys.p256dh'),
                'auth' => $request->input('subscription.keys.auth'),
                'subscription_data' => $request->input('subscription'),
            ];
            if ($transferred || $keysChanged || $request->boolean('reactivate')) {
                $updates += [
                    'delivery_status' => PushSubscription::STATUS_UNKNOWN,
                    'delivery_verified_at' => null,
                    'delivery_last_failed_at' => null,
                    'delivery_last_error' => null,
                ];
            }
            $existingSubscription->update($updates);

            return response()->json([
                'success' => true,
                'message' => $transferred ? 'Push subscription updated successfully' : 'Push subscription already exists',
                'id' => $existingSubscription->id,
            ]);
        }

        $subscription = PushSubscription::updateOrCreate(
            [
                'endpoint' => $request->input('subscription.endpoint'),
            ],
            [
                'user_id' => $user->id,
                'p256dh' => $request->input('subscription.keys.p256dh'),
                'auth' => $request->input('subscription.keys.auth'),
                'subscription_data' => $request->input('subscription'),
            ]
        );

        return response()->json([
            'success' => true,
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

        $exists = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->input('endpoint'))
            ->deliverable()
            ->exists();

        return response()->json(['success' => true, 'exists' => $exists]);
    }

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

        $deleted = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->input('subscription.endpoint'))
            ->delete();

        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Push subscription removed successfully']);
        }

        return response()->json(['message' => 'Push subscription not found'], 404);
    }
}
