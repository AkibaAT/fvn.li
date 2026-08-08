<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\DiscordServer;
use App\Models\DiscordServerGameOverride;
use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;

class DiscordRoutingService
{
    public function evaluateRoutes(
        DiscordServer $server,
        Game $game,
        string $notificationType,
        ?GameVersion $gameVersion = null,
    ): RoutingResult {
        $result = new RoutingResult;

        $override = DiscordServerGameOverride::where('discord_server_id', $server->id)
            ->where('game_id', $game->id)
            ->first();

        if ($override && $override->is_ignored) {
            $result->shouldSkip = true;

            return $result;
        }

        $config = $server->config;
        if (! $config) {
            return $result;
        }

        $rules = $config->routing_rules ?? [];
        $sortedRules = collect($rules)
            ->filter(fn ($rule) => ($rule['enabled'] ?? false) === true)
            ->sortBy(fn ($rule) => $rule['priority'] ?? 100)
            ->values();

        foreach ($sortedRules as $rule) {
            if ($this->ruleMatches($rule, $game, $notificationType, $gameVersion)) {
                $action = $rule['action'] ?? [];

                if (($action['type'] ?? '') === 'ignore') {
                    $result->shouldSkip = true;

                    return $result;
                }

                if (($action['type'] ?? '') === 'route' && ! empty($action['channel_id'])) {
                    if (! $this->canRouteGameToChannel($server, $game, (string) $action['channel_id'])) {
                        continue;
                    }

                    $result->addChannel(
                        $action['channel_id'],
                        $action['embed_override'] ?? null,
                    );
                }
            }
        }

        if ($override && $override->channel_id && $this->canRouteGameToChannel($server, $game, $override->channel_id)) {
            $embedOverride = null;
            if ($notificationType === 'new_game' && $override->new_game_embed) {
                $embedOverride = $override->new_game_embed;
            } elseif ($notificationType === 'update' && $override->update_embed) {
                $embedOverride = $override->update_embed;
            }

            $result->addChannel($override->channel_id, $embedOverride);
        }

        if (
            ! $result->hasChannels()
            && $config->notification_channel_id
            && $this->canRouteGameToChannel($server, $game, $config->notification_channel_id)
        ) {
            $result->addChannel($config->notification_channel_id);
        }

        return $result;
    }

    private function canRouteGameToChannel(DiscordServer $server, Game $game, string $channelId): bool
    {
        if (! $game->is_nsfw) {
            return true;
        }

        $channel = collect($server->available_channels ?? [])
            ->first(fn (mixed $channel): bool => is_array($channel) && (string) ($channel['id'] ?? '') === $channelId);

        if (! is_array($channel)) {
            Log::warning('NSFW channel metadata unavailable; allowing route because the channel is not confirmed non-NSFW', [
                'server_id' => $server->id,
                'game_id' => $game->id,
                'channel_id' => $channelId,
            ]);

            return true;
        }

        if (! (bool) ($channel['nsfw'] ?? false)) {
            Log::warning('NSFW game cannot route: target channel is not marked as NSFW', [
                'server_id' => $server->id,
                'game_id' => $game->id,
                'channel_id' => $channelId,
                'channel_name' => $channel['name'] ?? 'unknown',
            ]);

            return false;
        }

        return true;
    }

    private function ruleMatches(array $rule, Game $game, string $notificationType, ?GameVersion $gameVersion): bool
    {
        $conditions = $rule['conditions'] ?? [];

        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->conditionMatches($condition, $game, $notificationType, $gameVersion)) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(array $condition, Game $game, string $notificationType, ?GameVersion $gameVersion): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        $gameValue = $this->resolveFieldValue($field, $game, $notificationType, $gameVersion);

        if ($gameValue === null) {
            return in_array($operator, ['not_equals', 'not_in', 'not_contains'], true);
        }

        return match ($operator) {
            'equals' => (string) $gameValue === (string) $value,
            'not_equals' => (string) $gameValue !== (string) $value,
            'in' => is_array($value) && in_array((string) $gameValue, array_map('strval', $value), true),
            'not_in' => is_array($value) && ! in_array((string) $gameValue, array_map('strval', $value), true),
            'contains' => is_array($gameValue) && in_array(mb_strtolower((string) $value), array_map(fn ($item): string => mb_strtolower((string) $item), $gameValue), true),
            'not_contains' => is_array($gameValue) && ! in_array(mb_strtolower((string) $value), array_map(fn ($item): string => mb_strtolower((string) $item), $gameValue), true),
            'contains_any' => is_array($gameValue) && is_array($value) && ! empty(array_intersect(
                array_map(fn ($item): string => mb_strtolower((string) $item), $gameValue),
                array_map(fn ($item): string => mb_strtolower((string) $item), $value),
            )),
            default => false,
        };
    }

    private function resolveFieldValue(string $field, Game $game, string $notificationType, ?GameVersion $gameVersion): mixed
    {
        return match ($field) {
            'notification_type' => $notificationType,
            'status' => $game->status,
            'source_language' => $game->source_language_id,
            'tags' => $game->tags->pluck('name')->map(fn ($t) => mb_strtolower($t))->values()->all(),
            'content_type' => $game->content_type,
            'platform' => $game->platform,
            'is_nsfw' => $game->is_nsfw,
            'is_paid' => $game->is_paid,
            'developer' => $game->developer,
            default => null,
        };
    }
}
