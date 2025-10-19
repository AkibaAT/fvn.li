<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;

/**
 * Handles notifications for multiple Discord servers with per-server preferences.
 */
class MultiServerNotificationService
{
    /**
     * Queue game update notifications for all subscribed servers.
     */
    public function queueGameUpdate(Game $game, GameVersion $version): void
    {
        try {
            // Find all servers subscribed to this game
            $servers = $game->discordServers()
                ->where('is_active', true)
                ->get();

            foreach ($servers as $server) {
                $this->queueServerNotification(
                    $server,
                    $game,
                    'update',
                    "Game updated: {$game->name}"
                );
            }

            // Also check for tag-based subscriptions
            $this->queueTagBasedNotifications($game, 'update');

            Log::info("Queued game update notifications", [
                'game_id' => $game->id,
                'servers_count' => $servers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error queuing game update notifications", [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue a notification for a specific server.
     */
    public function queueServerNotification(
        DiscordServer $server,
        Game $game,
        string $type = 'update',
        string $description = ''
    ): void {
        try {
            if (!$server->isConfigured()) {
                Log::warning("Server not configured for notifications", [
                    'server_id' => $server->id,
                ]);
                return;
            }

            $config = $server->config;

            // Create notification history record
            $notification = DiscordNotificationHistory::create([
                'discord_server_id' => $server->id,
                'game_id' => $game->id,
                'notification_type' => $type,
                'channel_id' => $config->notification_channel_id,
                'delivery_status' => 'pending',
            ]);

            Log::info("Queued server notification", [
                'notification_id' => $notification->id,
                'server_id' => $server->id,
                'game_id' => $game->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Error queuing server notification", [
                'server_id' => $server->id,
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue notifications for tag-based subscriptions.
     */
    public function queueTagBasedNotifications(Game $game, string $type = 'update'): void
    {
        try {
            // Get game tags
            $gameTags = $game->tags()->pluck('name')->toArray();

            if (empty($gameTags)) {
                return;
            }

            // Find servers subscribed to these tags
            $servers = DiscordServer::whereHas('tagSubscriptions', function ($query) use ($gameTags) {
                $query->whereIn('tag_name', $gameTags)
                    ->where('is_subscribed', true);
            })
                ->where('is_active', true)
                ->get();

            foreach ($servers as $server) {
                // Check if not already subscribed directly
                if (!$server->games()->where('game_id', $game->id)->exists()) {
                    $this->queueServerNotification($server, $game, $type);
                }
            }

            Log::info("Queued tag-based notifications", [
                'game_id' => $game->id,
                'tags' => $gameTags,
                'servers_count' => $servers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error queuing tag-based notifications", [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get pending notifications for a server.
     */
    public function getPendingNotifications(DiscordServer $server, int $limit = 50)
    {
        return $server->notificationHistory()
            ->pending()
            ->with('game')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending notifications for all servers.
     */
    public function getAllPendingNotifications(int $limit = 100)
    {
        return DiscordNotificationHistory::pending()
            ->with(['discordServer', 'game'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(DiscordNotificationHistory $notification, string $messageId = null): void
    {
        $notification->markAsSent($messageId);

        Log::info("Notification marked as sent", [
            'notification_id' => $notification->id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(DiscordNotificationHistory $notification, string $errorMessage = null): void
    {
        $notification->markAsFailed($errorMessage);

        Log::warning("Notification marked as failed", [
            'notification_id' => $notification->id,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Format notification message for a server.
     */
    public function formatNotification(DiscordServer $server, Game $game, string $type = 'update'): string
    {
        $config = $server->config;

        if (!$config) {
            return "{$game->name} has been updated!";
        }

        return $config->formatNotification($game, $type);
    }

    /**
     * Record manual update from Discord bot.
     */
    public function recordManualUpdate(DiscordServer $server, Game $game, string $messageId = null): void
    {
        try {
            $notification = DiscordNotificationHistory::create([
                'discord_server_id' => $server->id,
                'game_id' => $game->id,
                'notification_type' => 'manual',
                'message_id' => $messageId,
                'channel_id' => $server->config->notification_channel_id ?? 'unknown',
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info("Recorded manual update", [
                'notification_id' => $notification->id,
                'server_id' => $server->id,
                'game_id' => $game->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Error recording manual update", [
                'server_id' => $server->id,
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

