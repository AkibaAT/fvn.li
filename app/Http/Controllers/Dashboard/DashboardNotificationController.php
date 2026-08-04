<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NotificationQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardNotificationController extends Controller
{
    public function getNotificationPreferences(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $preferences = $user->notificationPreferences()->first();
        if (! $preferences) {
            $preferences = $user->notificationPreferences()->create([
                'browser_notifications_enabled' => false,
                'discord_notifications_enabled' => false,
                'notification_digest' => 'asap',
            ]);
        }

        // Discord bot installation info
        $hasDiscordAccount = $user->socialAccounts()->where('provider_name', 'discord')->exists();
        $discordClientId = config('services.discord.client_id');
        $discordBotInstallUrl = $discordClientId
            ? "https://discord.com/oauth2/authorize?client_id={$discordClientId}&integration_type=1&scope=applications.commands"
            : null;

        $lastDiscordNotification = null;
        if ($hasDiscordAccount) {
            $lastNotification = NotificationQueue::where('user_id', $user->id)
                ->where('channel', 'discord')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastNotification) {
                $lastDiscordNotification = [
                    'status' => $lastNotification->status,
                    'error' => $lastNotification->error,
                    'processedAt' => $lastNotification->processed_at?->toISOString(),
                    'createdAt' => $lastNotification->created_at->toISOString(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'preferences' => [
                'browser_notifications_enabled' => (bool) $preferences->browser_notifications_enabled,
                'discord_notifications_enabled' => (bool) $preferences->discord_notifications_enabled,
                'notification_digest' => $preferences->notification_digest,
            ],
            'discordInfo' => [
                'hasAccount' => $hasDiscordAccount,
                'botInstallUrl' => $discordBotInstallUrl,
                'lastNotification' => $lastDiscordNotification,
            ],
            'vapidPublicKey' => config('webpush.vapid_public_key') ?? config('webpush.vapid.public_key'),
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $data = $request->validate([
            'browser_notifications_enabled' => 'boolean',
            'discord_notifications_enabled' => 'boolean',
            'notification_digest' => 'in:asap,daily,weekly,monthly',
        ]);

        $preferences = $user->notificationPreferences()->first();
        if (! $preferences) {
            $preferences = $user->notificationPreferences()->create([
                'browser_notifications_enabled' => false,
                'discord_notifications_enabled' => false,
                'notification_digest' => 'asap',
            ]);
        }

        $preferences->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'preferences' => [
                'browser_notifications_enabled' => (bool) $preferences->browser_notifications_enabled,
                'discord_notifications_enabled' => (bool) $preferences->discord_notifications_enabled,
                'notification_digest' => $preferences->notification_digest,
            ],
        ]);
    }
}
