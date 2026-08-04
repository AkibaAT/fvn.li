<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordNotificationHistoryController extends Controller
{
    public function index(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        $query = $server->notificationHistory();

        if ($request->has('status')) {
            $query->where('delivery_status', $request->get('status'));
        }

        if ($request->has('type')) {
            $query->where('notification_type', $request->get('type'));
        }

        if ($request->has('from_date')) {
            $query->where('sent_at', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('sent_at', '<=', $request->get('to_date'));
        }

        $history = $query->with('game')
            ->orderBy('sent_at', 'desc')
            ->paginate(50);

        return response()->json($history);
    }

    public function show(DiscordServer $server, DiscordNotificationHistory $notification, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        if ($notification->discord_server_id !== $server->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'notification' => $notification->load('game'),
        ]);
    }

    public function stats(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('view', $server);

        $days = (int) $request->get('days', 30);

        $stats = [
            'total' => $server->notificationHistory()->recent($days)->count(),
            'sent' => $server->notificationHistory()->recent($days)->sent()->count(),
            'failed' => $server->notificationHistory()->recent($days)->failed()->count(),
            'pending' => $server->notificationHistory()->recent($days)->pending()->count(),
            'by_type' => $server->notificationHistory()
                ->recent($days)
                ->selectRaw('notification_type, COUNT(*) as count')
                ->groupBy('notification_type')
                ->get()
                ->keyBy('notification_type'),
        ];

        return response()->json($stats);
    }

    /**
     * Resend a failed notification.
     */
    public function resend(DiscordServer $server, DiscordNotificationHistory $notification, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        if ($notification->discord_server_id !== $server->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($notification->delivery_status !== 'failed') {
            return response()->json([
                'message' => 'Only failed notifications can be resent',
            ], 400);
        }

        // Reset to pending for retry
        $notification->update([
            'delivery_status' => 'pending',
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'Notification queued for resend',
            'notification' => $notification,
        ]);
    }

    /**
     * Send a test notification.
     */
    public function sendTest(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        if (! $server->isConfigured()) {
            return response()->json([
                'message' => 'Server is not configured with a notification channel',
            ], 400);
        }

        $testNotification = DiscordNotificationHistory::create([
            'discord_server_id' => $server->id,
            'game_id' => null,
            'notification_type' => 'manual',
            'channel_id' => $server->config->notification_channel_id,
            'delivery_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Test notification queued',
            'notification' => $testNotification,
        ], 201);
    }

    /**
     * Clear notification history.
     */
    public function clear(DiscordServer $server, Request $request): JsonResponse
    {
        $this->authorize('update', $server);

        $days = (int) $request->get('days', 30);

        $deleted = $server->notificationHistory()
            ->where('sent_at', '<', now()->subDays($days))
            ->delete();

        return response()->json([
            'message' => 'Notification history cleared',
            'deleted_count' => $deleted,
        ]);
    }
}
