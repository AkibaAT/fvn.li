<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class NotificationHealthService
{
    public function forUser(User $user): array
    {
        $preferences = $user->notificationPreferences()->firstOrCreate([], [
            'browser_notifications_enabled' => false,
            'discord_notifications_enabled' => false,
            'notification_digest' => 'asap',
        ]);
        $hasDiscord = $user->socialAccounts()->where('provider_name', 'discord')->exists();
        $clientId = config('services.discord.client_id');
        $botStatus = Cache::get('discord-bot:status');
        $botOnline = is_array($botStatus)
            && isset($botStatus['received_at'])
            && Carbon::parse($botStatus['received_at'])->gte(now()->subMinutes(5));

        return [
            'browser' => $this->channel($user, 'browser') + [
                'enabled' => (bool) $preferences->browser_notifications_enabled,
                'configured' => $this->pushConfigured(),
                'subscriptionCount' => PushSubscription::where('user_id', $user->id)->deliverable()->count(),
            ],
            'discord' => $this->channel($user, 'discord') + [
                'enabled' => (bool) $preferences->discord_notifications_enabled,
                'linked' => $hasDiscord,
                'dmStatus' => $preferences->discord_dm_status,
                'dmStatusReason' => $preferences->discord_dm_status_reason,
                'userInstalledAt' => $preferences->discord_user_installed_at?->toISOString(),
                // Routed through the app so the grant is state-checked and matched
                // against the linked Discord account.
                'userInstallUrl' => $clientId && $hasDiscord ? route('dashboard.discord.user-install') : null,
                'botOnline' => $botOnline,
            ],
            'digest' => [
                'frequency' => $preferences->notification_digest,
                'lastSentAt' => NotificationHistory::where('user_id', $user->id)
                    ->where('success', true)
                    ->whereRaw("meta_data::jsonb @> '{\"digest\": true}'::jsonb")
                    ->max('created_at'),
                'nextScheduledAt' => NotificationQueue::where('user_id', $user->id)->where('status', 'pending')->min('scheduled_at'),
            ],
        ];
    }

    private function channel(User $user, string $channel): array
    {
        $lastSuccess = NotificationHistory::where('user_id', $user->id)
            ->where('type', $channel)
            ->where('success', true)
            ->latest('created_at')
            ->first();
        $lastFailure = NotificationQueue::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('status', 'failed')
            ->latest('processed_at')
            ->first();
        $lastTest = NotificationQueue::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('payload->type', 'test')
            ->latest()
            ->first();

        return [
            'pendingCount' => NotificationQueue::where('user_id', $user->id)->where('channel', $channel)->where('status', 'pending')->count(),
            'lastSuccessAt' => $lastSuccess?->created_at?->toISOString(),
            'lastFailure' => $lastFailure ? [
                'at' => $lastFailure->processed_at?->toISOString(),
                'error' => $lastFailure->error,
                'code' => $this->errorCode($lastFailure->error, $channel),
            ] : null,
            'lastTest' => $lastTest ? [
                'id' => $lastTest->id,
                'status' => $lastTest->status,
                'error' => $lastTest->error,
                'processedAt' => $lastTest->processed_at?->toISOString(),
            ] : null,
        ];
    }

    private function errorCode(?string $error, string $channel): string
    {
        return match (true) {
            $error === 'no_push_subscriptions' => 'no_subscriptions',
            $error === 'discord_not_linked' => 'discord_not_linked',
            $error === 'discord_undeliverable' => 'discord_dm_blocked',
            $channel === 'browser' && str_contains(mb_strtolower((string) $error), 'endpoint') => 'endpoint_rejected',
            $channel === 'browser' && str_contains(mb_strtolower((string) $error), 'vapid') => 'push_not_configured',
            default => 'unknown',
        };
    }

    private function pushConfigured(): bool
    {
        return filled(config('webpush.vapid.subject'))
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }
}
