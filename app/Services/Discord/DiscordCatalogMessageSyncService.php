<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class DiscordCatalogMessageSyncService
{
    public function queueForGame(Game|int $game): int
    {
        if (! config('services.discord.server_bot_enabled')) {
            return 0;
        }

        $game = $game instanceof Game ? $game : Game::find($game);
        if (! $game) {
            return 0;
        }

        $game->loadMissing(['tags', 'sourceLanguage', 'latestVersion']);
        $metadataRows = DB::table('discord_server_games')
            ->where('game_id', $game->id)
            ->whereNotNull('discord_channel_id')
            ->whereNotNull('discord_message_id')
            ->get();

        $queued = 0;
        foreach ($metadataRows as $metadata) {
            $server = DiscordServer::with(['config', 'gameOverrides'])
                ->whereKey($metadata->discord_server_id)
                ->where('is_active', true)
                ->first();
            if (! $server) {
                continue;
            }

            $override = $server->gameOverrides->firstWhere('game_id', $game->id);
            if ($override?->is_ignored) {
                continue;
            }

            $renderer = app(DiscordEmbedRendererService::class);
            $template = $override?->new_game_embed
                ?? $server->config?->new_game_embed
                ?? $renderer->getDefaultNewGameEmbed();
            $payload = ['embeds' => [$renderer->renderEmbed($template, $game, 'new_game', $game->latestVersion, $server)]];
            $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            if ($metadata->discord_payload_hash === $hash) {
                continue;
            }

            $alreadyQueued = DiscordNotificationHistory::query()
                ->where('discord_server_id', $server->id)
                ->where('game_id', $game->id)
                ->where('delivery_mode', 'edit')
                ->where('payload_hash', $hash)
                ->whereIn('delivery_status', ['pending', 'processing'])
                ->exists();
            if ($alreadyQueued) {
                continue;
            }

            DiscordNotificationHistory::create([
                'discord_server_id' => $server->id,
                'game_id' => $game->id,
                'notification_type' => 'new_game',
                'delivery_mode' => 'edit',
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
        if (! config('services.discord.server_bot_enabled')) {
            return 0;
        }

        $queued = 0;
        DB::table('discord_server_games')
            ->whereNotNull('discord_channel_id')
            ->whereNotNull('discord_message_id')
            ->distinct()
            ->orderBy('game_id')
            ->pluck('game_id')
            ->each(function (int $gameId) use (&$queued): void {
                $queued += $this->queueForGame($gameId);
            });

        return $queued;
    }
}
