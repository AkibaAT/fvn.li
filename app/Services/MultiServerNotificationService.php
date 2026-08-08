<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\Discord\DiscordEmbedRendererService;
use App\Services\Discord\DiscordRoutingService;
use Exception;
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
        $servers = $game->discordServers()
            ->where('discord_servers.is_active', true)
            ->wherePivot('is_active', true)
            ->get();

        foreach ($servers as $server) {
            try {
                $this->queueServerNotification(
                    $server,
                    $game,
                    'update',
                    "Game updated: {$game->name}",
                    $version,
                );
            } catch (Exception $exception) {
                Log::error('Error queuing game update notification for server', [
                    'game_id' => $game->id,
                    'server_id' => $server->id,
                    'exception' => $exception,
                ]);
            }
        }

        $this->queueTagBasedNotifications($game, 'update', $version);

        Log::info('Queued game update notifications', [
            'game_id' => $game->id,
            'servers_count' => $servers->count(),
        ]);
    }

    /**
     * Queue a notification for a specific server.
     */
    public function queueServerNotification(
        DiscordServer $server,
        Game $game,
        string $type = 'update',
        string $description = '',
        ?GameVersion $gameVersion = null,
    ): void {
        $config = $server->config;
        $result = app(DiscordRoutingService::class)->evaluateRoutes($server, $game, $type, $gameVersion);
        if ($result->shouldSkip) {
            Log::info('Notification skipped by routing rules', ['server_id' => $server->id, 'game_id' => $game->id]);

            return;
        }

        $targetChannels = $result->getTargetChannels();
        if (empty($targetChannels)) {
            Log::warning('No target channels for notification', ['server_id' => $server->id, 'game_id' => $game->id]);

            return;
        }

        $renderer = app(DiscordEmbedRendererService::class);
        foreach ($targetChannels as $target) {
            $template = $target['embed_override']
                ?? ($type === 'new_game' ? $config?->new_game_embed : $config?->update_embed)
                ?? ($type === 'new_game' ? $renderer->getDefaultNewGameEmbed() : $renderer->getDefaultUpdateEmbed());
            $payload = ['embeds' => [$renderer->renderEmbed($template, $game, $type, $gameVersion, $server)]];
            if ($config?->ping_role_id) {
                $payload['content'] = "<@&{$config->ping_role_id}>";
            }

            DiscordNotificationHistory::firstOrCreate([
                'discord_server_id' => $server->id,
                'game_id' => $game->id,
                'game_version_id' => $gameVersion?->id,
                'notification_type' => $type,
                'channel_id' => $target['channel_id'],
            ], [
                'delivery_status' => 'pending',
                'payload' => $payload,
            ]);
        }

        Log::info('Queued server notification', [
            'server_id' => $server->id,
            'game_id' => $game->id,
            'channels' => count($targetChannels),
        ]);
    }

    /**
     * Queue notifications for tag-based subscriptions.
     */
    public function queueTagBasedNotifications(Game $game, string $type = 'update', ?GameVersion $gameVersion = null): void
    {
        try {
            $gameTags = $game->tags()->pluck('name')->toArray();

            if (empty($gameTags)) {
                return;
            }

            $servers = DiscordServer::whereHas('tagSubscriptions', function ($query) use ($gameTags) {
                $query->whereIn('tag_name', $gameTags)
                    ->where('is_subscribed', true);
            })
                ->where('is_active', true)
                ->get();

            foreach ($servers as $server) {
                if (! $server->games()
                    ->where('games.id', $game->id)
                    ->wherePivot('is_active', true)
                    ->exists()) {
                    $this->queueServerNotification($server, $game, $type, gameVersion: $gameVersion);
                }
            }

            Log::info('Queued tag-based notifications', [
                'game_id' => $game->id,
                'tags' => $gameTags,
                'servers_count' => $servers->count(),
            ]);
        } catch (Exception $e) {
            Log::error('Error queuing tag-based notifications', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getPendingNotifications(DiscordServer $server, int $limit = 50)
    {
        return $server->notificationHistory()
            ->pending()
            ->with('game')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

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
    public function markAsSent(DiscordNotificationHistory $notification, ?string $messageId = null): void
    {
        $notification->markAsSent($messageId);

        Log::info('Notification marked as sent', [
            'notification_id' => $notification->id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(DiscordNotificationHistory $notification, ?string $errorMessage = null): void
    {
        $notification->markAsFailed($errorMessage);

        Log::warning('Notification marked as failed', [
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

        if (! $config) {
            return "{$game->name} has been updated.";
        }

        return $config->formatNotification($game, $type);
    }

    /**
     * Record manual update from Discord bot.
     */
    public function recordManualUpdate(DiscordServer $server, Game $game, ?string $messageId = null): void
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

            Log::info('Recorded manual update', [
                'notification_id' => $notification->id,
                'server_id' => $server->id,
                'game_id' => $game->id,
            ]);
        } catch (Exception $e) {
            Log::error('Error recording manual update', [
                'server_id' => $server->id,
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
