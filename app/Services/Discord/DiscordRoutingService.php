<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\DiscordServer;
use App\Models\DiscordServerGameOverride;
use App\Models\Game;
use App\Models\GameVersion;
use Closure;
use Illuminate\Support\Facades\Log;

class DiscordRoutingService
{
    public static function routingValidationRules(DiscordServer $server): array
    {
        return [
            'routing_rules' => 'nullable|array|max:100',
            'routing_rules.*' => 'required|array',
            'routing_rules.*.id' => 'required|string|max:255',
            'routing_rules.*.name' => 'required|string|max:255',
            'routing_rules.*.enabled' => 'required|boolean',
            'routing_rules.*.priority' => 'required|integer',
            'routing_rules.*.conditions' => 'required|array|min:1|max:50',
            'routing_rules.*.conditions.*' => ['required', 'array', function (string $attribute, mixed $condition, Closure $fail): void {
                if (! is_array($condition)) {
                    return;
                }
                $field = $condition['field'] ?? null;
                $operator = $condition['operator'] ?? null;
                $allowed = match ($field) {
                    'tags' => ['contains', 'not_contains', 'contains_any'],
                    'is_nsfw', 'is_paid' => ['equals', 'not_equals'],
                    'notification_type', 'status', 'source_language', 'content_type', 'platform', 'developer' => ['equals', 'not_equals', 'in', 'not_in'],
                    default => [],
                };
                $value = $condition['value'] ?? null;
                $multi = in_array($operator, ['in', 'not_in', 'contains', 'not_contains', 'contains_any'], true);
                $validValue = $multi
                    ? (is_string($value) && $value !== '' && strlen($value) <= 255) || (is_array($value) && count($value) > 0 && count($value) <= 100 && collect($value)->every(fn ($item) => is_string($item) && strlen($item) <= 255))
                    : (in_array($field, ['is_nsfw', 'is_paid'], true) ? is_bool($value) : is_string($value) && strlen($value) <= 255);
                if (! in_array($operator, $allowed, true) || ! $validValue) {
                    $fail('Select a valid field, operator, and value for this condition.');
                }
            }],
            'routing_rules.*.action' => 'required|array',
            'routing_rules.*.action.type' => 'required|in:ignore,route',
            'routing_rules.*.action.channel_id' => ['required_if:routing_rules.*.action.type,route', ...$server->channelValidationRules()],
            ...DiscordEmbedRendererService::validationRules('routing_rules.*.action.embed_override'),
        ];
    }

    public function evaluateRoutes(
        DiscordServer $server,
        Game $game,
        string $notificationType,
        ?GameVersion $gameVersion = null,
    ): RoutingResult {
        $result = new RoutingResult;

        if (! $game->is_visible) {
            $result->shouldSkip = true;

            return $result;
        }

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
        if (! in_array($channelId, $server->channelIds(), true)) {
            return false;
        }
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

        $values = is_array($value) ? $value : [$value];
        if (collect($values)->contains(fn ($item) => ! is_scalar($item))) {
            return false;
        }
        $values = array_map(fn ($item): string => mb_strtolower((string) $item), $values);
        $gameValues = array_map(fn ($item): string => mb_strtolower((string) $item), (array) $gameValue);
        $matching = array_intersect($values, $gameValues);

        return match ($operator) {
            'equals' => ! is_array($gameValue) && ! is_array($value) && (string) $gameValue === (string) $value,
            'not_equals' => ! is_array($gameValue) && ! is_array($value) && (string) $gameValue !== (string) $value,
            'in', 'contains_any' => $matching !== [],
            'not_in', 'not_contains' => $matching === [],
            'contains' => $values !== [] && array_diff($values, $gameValues) === [],
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
