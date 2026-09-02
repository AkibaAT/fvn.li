<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\WebPushConfigurationException;
use App\Http\Controllers\Controller;
use App\Models\NotificationQueue;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationHealthService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class NotificationHealthController extends Controller
{
    public function __construct(
        private readonly NotificationHealthService $health,
        private readonly NotificationService $notifications,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'health' => $this->health->forUser($request->user())]);
    }

    public function test(Request $request): JsonResponse
    {
        $channel = $request->validate(['channel' => 'required|in:browser,discord'])['channel'];
        $user = User::findOrFail($request->user()->id);

        if ($channel === 'browser') {
            $subscriptions = PushSubscription::where('user_id', $user->id)->deliverable()->get();
            if ($subscriptions->isEmpty()) {
                return response()->json(['success' => false, 'code' => 'no_subscriptions'], 422);
            }

            try {
                $result = $this->notifications->sendPushNotifications($subscriptions, [
                    'title' => 'FVN.li notification test',
                    'body' => 'Browser notifications are working on this device.',
                    'data' => ['url' => route('dashboard'), 'type' => 'test'],
                ]);
            } catch (WebPushConfigurationException) {
                return response()->json(['success' => false, 'code' => 'push_not_configured'], 422);
            }

            return response()->json([
                'success' => $result['sent'] > 0,
                'code' => $result['sent'] > 0 ? null : 'endpoint_rejected',
                'result' => Arr::except($result, 'errors'),
            ], $result['sent'] > 0 ? 200 : 422);
        }

        if (! $user->socialAccounts()->where('provider_name', 'discord')->exists()) {
            return response()->json(['success' => false, 'code' => 'discord_not_linked'], 422);
        }

        $preferences = $user->notificationPreferences()->firstOrCreate([], [
            'browser_notifications_enabled' => false,
            'discord_notifications_enabled' => false,
            'notification_digest' => 'asap',
        ]);
        $preferences->markDiscordUnverified();
        $notification = NotificationQueue::create([
            'user_id' => $user->id,
            'channel' => 'discord',
            'status' => 'pending',
            'scheduled_at' => now(),
            'payload' => ['type' => 'test'],
        ]);

        return response()->json(['success' => true, 'notificationId' => $notification->id], 202);
    }
}
