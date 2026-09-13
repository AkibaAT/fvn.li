<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class DiscordCatalogMessageSyncService
{
    public function trackMessage(int $serverId, int $gameId, string $channelId, ?string $messageId, ?string $hash = null): void
    {
        $identity = ['discord_server_id' => $serverId, 'game_id' => $gameId];
        $values = ['discord_message_id' => $messageId, 'discord_payload_hash' => $hash, 'updated_at' => now()];
        DB::table('discord_catalog_messages')->upsert(
            [$identity + ['discord_channel_id' => $channelId, 'created_at' => now()] + $values],
            ['discord_server_id', 'game_id', 'discord_channel_id'], array_keys($values),
        );

        // Keep the legacy API's primary destination without replacing it with each additional channel.
        DB::table('discord_server_games')->insertOrIgnore($identity + ['created_at' => now(), 'updated_at' => now()]);
        DB::table('discord_server_games')->where($identity)
            ->where(fn ($query) => $query->whereNull('discord_channel_id')->orWhere('discord_channel_id', $channelId))
            ->update($values + ['discord_channel_id' => $channelId, 'discord_updated_at' => now()]);
    }

    public function queueForGame(Game|int $game): int
    {
        $game = $game instanceof Game ? $game : Game::find($game);
        if (! $game) {
            return 0;
        }

        if (! $game->is_visible) {
            DiscordNotificationHistory::where('game_id', $game->id)
                ->whereIn('delivery_status', ['pending', 'processing'])
                ->update(['delivery_status' => 'failed', 'batch_key' => null, 'error_message' => 'game_hidden']);

            return 0;
        }

        $game->loadMissing(['tags', 'sourceLanguage', 'latestVersion']);
        $metadataRows = DB::table('discord_catalog_messages')
            ->where('game_id', $game->id)
            ->whereNotNull('discord_channel_id')
            ->get();

        $queued = 0;
        foreach ($metadataRows as $metadata) {
            $server = DiscordServer::with(['config', 'gameOverrides'])
                ->whereKey($metadata->discord_server_id)
                ->where('is_active', true)
                ->where('bot_present', true)
                ->first();
            if (! $server) {
                continue;
            }

            $override = $server->gameOverrides->firstWhere('game_id', $game->id);
            $routing = app(DiscordRoutingService::class)->evaluateRoutes($server, $game, 'new_game', $game->latestVersion);
            if ($routing->shouldSkip || ! isset($routing->targetChannels[$metadata->discord_channel_id])) {
                DiscordNotificationHistory::where('discord_server_id', $server->id)->where('game_id', $game->id)
                    ->where('channel_id', $metadata->discord_channel_id)
                    ->where('notification_type', 'new_game')->whereIn('delivery_status', ['pending', 'processing'])
                    ->update(['delivery_status' => 'failed', 'batch_key' => null, 'error_message' => 'catalog_route_changed']);

                continue;
            }

            $renderer = app(DiscordEmbedRendererService::class);
            $template = $routing->targetChannels[$metadata->discord_channel_id]['embed_override'] ?? $override?->new_game_embed;
            $payload = $renderer->renderPayload($server, $game, 'new_game', $game->latestVersion, $template);
            $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            if ($metadata->discord_message_id && $metadata->discord_payload_hash === $hash) {
                continue;
            }

            $deliveryMode = $metadata->discord_message_id ? 'edit' : 'send';

            $alreadyQueued = DiscordNotificationHistory::query()
                ->where('discord_server_id', $server->id)
                ->where('game_id', $game->id)
                ->where('channel_id', $metadata->discord_channel_id)
                ->where('delivery_mode', $deliveryMode)
                ->where('payload_hash', $hash)
                ->whereIn('delivery_status', ['pending', 'processing'])
                ->exists();
            if ($alreadyQueued) {
                continue;
            }

            DiscordNotificationHistory::create([
                'discord_server_id' => $server->id,
                'game_id' => $game->id,
                'game_version_id' => $game->latestVersion?->id,
                'notification_type' => 'new_game',
                'delivery_mode' => $deliveryMode,
                'message_id' => $metadata->discord_message_id,
                'channel_id' => $metadata->discord_channel_id,
                'delivery_status' => 'pending',
                'payload' => $payload,
                'payload_hash' => $hash,
            ]);
            $queued++;
        }

        return $queued;
    }

    public function queueAll(): int
    {
        $queued = 0;
        DB::table('discord_catalog_messages')
            ->whereNotNull('discord_channel_id')
            ->distinct()
            ->orderBy('game_id')
            ->pluck('game_id')
            ->each(function (int $gameId) use (&$queued): void {
                $queued += $this->queueForGame($gameId);
            });

        return $queued;
    }
}
