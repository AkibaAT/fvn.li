<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unified notification service that handles both automatic (fvn.li) and manual (Discord bot) updates
 */
class UnifiedNotificationService
{
    /**
     * Queue a game update notification for all channels
     * This handles both automatic fvn.li notifications and Discord bot notifications
     */
    public function queueGameUpdate(Game $game, GameVersion $gameVersion, array $options = []): void
    {
        if ($game->is_paid) {
            Log::info('Skipping notifications for paid game', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'version' => $gameVersion->version,
            ]);

            return;
        }

        $options = array_merge([
            'notify_fvn_li' => true,      // Send to fvn.li push subscribers
            'notify_discord' => true,      // Send to Discord bot
            'manual_update' => false,      // Is this a manual update from Discord bot?
            'update_url' => null,          // URL of the update post (for manual updates)
        ], $options);

        Log::info('Queuing unified game update notification', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'version' => $gameVersion->version,
            'options' => $options,
        ]);

        // Queue fvn.li push notifications
        if ($options['notify_fvn_li']) {
            $this->queueFvnLiNotification($game, $gameVersion);
        }

        // Queue Discord bot notifications
        if ($options['notify_discord'] && config('services.discord.bot_enabled')) {
            $this->queueDiscordNotification($game, $gameVersion, $options);
        }
    }

    /**
     * Record a manual update from Discord bot
     * This creates a game version record and queues notifications
     */
    public function recordManualDiscordUpdate(
        Game $game,
        string $updateUrl,
        string $updateTitle = '',
        ?string $devlog = null
    ): GameVersion {
        // Create or update game version
        $gameVersion = GameVersion::create([
            'game_id' => $game->id,
            'version' => $updateTitle ?: 'Update',
            'devlog' => $devlog,
            'published_at' => now(),
        ]);
        $gameVersion->forceFill(['is_latest' => true])->save();

        // Queue notifications
        $this->queueGameUpdate($game, $gameVersion, [
            'notify_fvn_li' => true,
            'notify_discord' => true,
            'manual_update' => true,
            'update_url' => $updateUrl,
        ]);

        Log::info('Recorded manual Discord update', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'update_url' => $updateUrl,
            'version_id' => $gameVersion->id,
        ]);

        return $gameVersion;
    }

    /**
     * Get pending notifications for a specific channel
     */
    public function getPendingNotifications(string $channel, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationQueue::where('channel', $channel)
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark notification as sent
     */
    public function markNotificationSent(NotificationQueue $notification, bool $success = true): void
    {
        $notification->update([
            'status' => $success ? 'sent' : 'failed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Queue a notification for fvn.li push subscribers
     */
    private function queueFvnLiNotification(Game $game, GameVersion $gameVersion): void
    {
        $recipients = $this->notificationRecipients($game->id)
            ->where('browser_notifications_enabled', true);

        foreach ($recipients as $recipient) {
            NotificationQueue::create([
                'user_id' => $recipient->user_id,
                'game_id' => $game->id,
                'game_version_id' => $gameVersion->id,
                'channel' => 'browser',
                'payload' => [
                    'title' => $game->name . ' - New Update Available',
                    'body' => 'Version ' . $gameVersion->version . ' is now available.',
                    'data' => [
                        'url' => route('games.show', $game->slug),
                        'game_id' => $game->id,
                        'game_version_id' => $gameVersion->id,
                        'version' => $gameVersion->version,
                    ],
                    'icon' => $game->getThumbnailUrl('small'),
                ],
                'scheduled_at' => now(),
                'status' => 'pending',
            ]);
        }

        Log::info('Queued fvn.li push notifications', [
            'game_id' => $game->id,
            'recipient_count' => $recipients->count(),
        ]);
    }

    /**
     * Queue a notification for Discord bot
     */
    private function queueDiscordNotification(Game $game, GameVersion $gameVersion, array $options): void
    {
        $recipients = $this->notificationRecipients($game->id)
            ->where('discord_notifications_enabled', true)
            ->where('has_discord_account', true);

        foreach ($recipients as $recipient) {
            NotificationQueue::create([
                'user_id' => $recipient->user_id,
                'game_id' => $game->id,
                'game_version_id' => $gameVersion->id,
                'channel' => 'discord',
                'payload' => [
                    'game_name' => $game->name,
                    'version' => $gameVersion->version,
                    'published_at' => $gameVersion->published_at->timestamp,
                    'url' => $game->url,
                    'devlog' => $gameVersion->devlog,
                    'manual_update' => $options['manual_update'],
                    'update_url' => $options['update_url'],
                ],
                'scheduled_at' => now(),
                'status' => 'pending',
            ]);
        }

        Log::info('Queued Discord notification', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'manual_update' => $options['manual_update'],
            'recipient_count' => $recipients->count(),
        ]);
    }

    private function notificationRecipients(int $gameId): Collection
    {
        return DB::table('user_game_progress')
            ->select([
                'users.id as user_id',
                'user_notification_preferences.browser_notifications_enabled',
                'user_notification_preferences.discord_notifications_enabled',
                DB::raw("EXISTS(SELECT 1 FROM social_accounts WHERE social_accounts.user_id = users.id AND social_accounts.provider_name = 'discord') as has_discord_account"),
            ])
            ->join('users', 'user_game_progress.user_id', '=', 'users.id')
            ->join('user_notification_preferences', 'users.id', '=', 'user_notification_preferences.user_id')
            ->where('user_game_progress.game_id', $gameId)
            ->where('user_game_progress.receive_updates', true)
            ->get()
            ->map(function ($recipient) {
                $recipient->browser_notifications_enabled = (bool) $recipient->browser_notifications_enabled;
                $recipient->discord_notifications_enabled = (bool) $recipient->discord_notifications_enabled;
                $recipient->has_discord_account = (bool) $recipient->has_discord_account;

                return $recipient;
            });
    }
}
