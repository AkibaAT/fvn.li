<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\GameVersion;

class DiscordEmbedRendererService
{
    private const PRESERVED_DISCORD_TOKEN_PREFIX = '__discord_token_';

    public function renderEmbed(
        array $template,
        Game $game,
        string $notificationType,
        ?GameVersion $gameVersion = null,
        ?DiscordServer $server = null,
    ): array {
        $variables = $this->buildVariables($game, $notificationType, $gameVersion, $server);

        $embed = $this->substituteRecursive($template, $variables);

        $embed = $this->cleanEmbed($embed);

        return $embed;
    }

    public function renderText(
        string $template,
        Game $game,
        string $notificationType,
        ?GameVersion $gameVersion = null,
        ?DiscordServer $server = null,
    ): string {
        $variables = $this->buildVariables($game, $notificationType, $gameVersion, $server);

        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    public function getDefaultNewGameEmbed(): array
    {
        return [
            'title' => '{game.name}',
            'url' => '{game.url}',
            'description' => '{game.description}',
            'color' => 5763719,
            'fields' => [
                ['name' => 'Status', 'value' => '{game.status}', 'inline' => true],
                ['name' => 'Developer', 'value' => '{game.developer}', 'inline' => true],
                ['name' => 'Price', 'value' => '{game.price}', 'inline' => true],
            ],
            'thumbnail' => ['url' => '{game.thumbnail}'],
            'footer' => ['text' => 'New on fvn.li', 'icon_url' => 'https://fvn.li/favicon.ico'],
            'timestamp' => '{version.published_at_iso}',
        ];
    }

    public function getDefaultUpdateEmbed(): array
    {
        return [
            'title' => '{game.name}',
            'url' => '{game.url}',
            'description' => 'Version **{version.name}** has been released!',
            'color' => 5793266,
            'fields' => [
                ['name' => 'Version', 'value' => '{version.name}', 'inline' => true],
                ['name' => 'Released', 'value' => '{version.published_at_discord}', 'inline' => true],
                ['name' => 'Devlog', 'value' => '{version.devlog_markdown}', 'inline' => true],
            ],
            'thumbnail' => ['url' => '{game.thumbnail}'],
            'footer' => ['text' => 'fvn.li', 'icon_url' => 'https://fvn.li/favicon.ico'],
            'timestamp' => '{version.published_at_iso}',
        ];
    }

    public function buildVariables(
        Game $game,
        string $notificationType,
        ?GameVersion $gameVersion = null,
        ?DiscordServer $server = null,
    ): array {
        $gameUrl = route('games.show', $game->slug);
        $thumbnail = $game->optimized_thumbnail_url ?? $game->thumb_url;
        $developer = $this->resolveDeveloperName($game);
        $screenshot = null;
        if ($game->screenshots && is_array($game->screenshots) && count($game->screenshots) > 0) {
            $first = $game->screenshots[0];
            $screenshot = is_array($first) ? ($first['url'] ?? $first['small'] ?? null) : $first;
        }

        $tags = $game->tags->pluck('name')->implode(', ');

        $platforms = collect(array_filter([
            isset($game->url['itch_io']) ? 'itch.io' : null,
            isset($game->url['steam']) ? 'Steam' : null,
            isset($game->url['other']) ? 'Other' : null,
        ]))->implode(', ');

        $price = 'Free';
        if ($game->is_paid && $game->min_price) {
            $price = $game->formatted_current_price ?? ('$'.number_format($game->min_price, 2));
        }

        $wordCount = $game->english_word_count ? number_format($game->english_word_count) : '';

        $variables = [
            '{game.name}' => $game->effective_name ?? $game->name,
            '{game.slug}' => $game->slug,
            '{game.url}' => $gameUrl,
            '{game.description}' => $game->effective_description ?? $game->description ?? '',
            '{game.status}' => $game->status ?? '',
            '{game.thumbnail}' => $thumbnail ?? '',
            '{game.screenshot}' => $screenshot ?? '',
            '{game.rating}' => $game->rating_score ? (string) round($game->rating_score, 1) : '',
            '{game.rating_count}' => $game->rating_count ? (string) $game->rating_count : '',
            '{game.developer}' => $developer,
            '{game.engine}' => $game->game_engine ?? '',
            '{game.platform}' => $game->platform ?? '',
            '{game.platforms}' => $platforms,
            '{game.tags}' => $tags,
            '{game.nsfw_label}' => $game->is_nsfw ? 'NSFW' : '',
            '{game.price}' => $price,
            '{game.word_count}' => $wordCount,
            '{game.language}' => $game->sourceLanguage ? $game->sourceLanguage->ref_name : '',
            '{notification.type}' => $notificationType === 'new_game' ? 'New Game' : 'Update',
            '{server.name}' => $server?->discord_server_name ?? '',
        ];

        if ($gameVersion) {
            $publishedAt = $gameVersion->published_at;
            $engStats = $gameVersion->languageStats()->where('iso_code', 'eng')->first();
            $wordDiff = $engStats ? number_format($engStats->words) : null;

            $variables['{version.name}'] = $gameVersion->version ?? '';
            $variables['{version.published_at}'] = $publishedAt?->format('F j, Y') ?? '';
            $variables['{version.published_at_discord}'] = $publishedAt ? '<t:'.$publishedAt->timestamp.':f>' : '';
            $variables['{version.published_at_iso}'] = $publishedAt?->toIso8601String() ?? '';
            $variables['{version.devlog_url}'] = $gameVersion->devlog ?? '';
            $variables['{version.devlog_markdown}'] = $gameVersion->devlog ? '[Read devlog]('.$gameVersion->devlog.')' : '';
            $variables['{version.word_count_diff}'] = $wordDiff ? ('+'.$wordDiff) : '';
        } else {
            $variables['{version.name}'] = '';
            $variables['{version.published_at}'] = '';
            $variables['{version.published_at_discord}'] = '';
            $variables['{version.published_at_iso}'] = '';
            $variables['{version.devlog_url}'] = '';
            $variables['{version.devlog_markdown}'] = '';
            $variables['{version.word_count_diff}'] = '';
        }

        return $variables;
    }

    private function substituteRecursive(array $data, array $variables): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $result[$key] = str_replace(array_keys($variables), array_values($variables), $value);
            } elseif (is_array($value)) {
                $result[$key] = $this->substituteRecursive($value, $variables);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function cleanEmbed(array $embed): array
    {
        $embed = $this->sanitizeEmbedTextFields($embed);
        $embed = $this->removeIncompleteFields($embed);
        $embed = $this->removeEmptyStrings($embed);
        $embed = $this->enforceDiscordLimits($embed);

        return $embed;
    }

    private function removeIncompleteFields(array $embed): array
    {
        if (! isset($embed['fields']) || ! is_array($embed['fields'])) {
            return $embed;
        }

        $embed['fields'] = array_values(array_filter($embed['fields'], function (mixed $field): bool {
            if (! is_array($field)) {
                return false;
            }

            $name = $field['name'] ?? null;
            $value = $field['value'] ?? null;

            return is_string($name) && $name !== '' && is_string($value) && $value !== '';
        }));

        if ($embed['fields'] === []) {
            unset($embed['fields']);
        }

        return $embed;
    }

    private function sanitizeEmbedTextFields(array $embed): array
    {
        if (isset($embed['title']) && is_string($embed['title'])) {
            $embed['title'] = $this->sanitizeDiscordText($embed['title']);
        }

        if (isset($embed['description']) && is_string($embed['description'])) {
            $embed['description'] = $this->sanitizeDiscordText($embed['description']);
        }

        if (isset($embed['author']['name']) && is_string($embed['author']['name'])) {
            $embed['author']['name'] = $this->sanitizeDiscordText($embed['author']['name']);
        }

        if (isset($embed['footer']['text']) && is_string($embed['footer']['text'])) {
            $embed['footer']['text'] = $this->sanitizeDiscordText($embed['footer']['text']);
        }

        if (isset($embed['fields']) && is_array($embed['fields'])) {
            $embed['fields'] = array_map(function (mixed $field): mixed {
                if (! is_array($field)) {
                    return $field;
                }

                if (isset($field['name']) && is_string($field['name'])) {
                    $field['name'] = $this->sanitizeDiscordText($field['name']);
                }

                if (isset($field['value']) && is_string($field['value'])) {
                    $field['value'] = $this->sanitizeDiscordText($field['value']);
                }

                return $field;
            }, $embed['fields']);
        }

        return $embed;
    }

    private function removeEmptyStrings(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $cleaned = $this->removeEmptyStrings($value);
                if (! empty($cleaned)) {
                    $result[$key] = $cleaned;
                }
            } elseif ($value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function enforceDiscordLimits(array $embed): array
    {
        if (isset($embed['title']) && mb_strlen($embed['title']) > 256) {
            $embed['title'] = mb_substr($embed['title'], 0, 253).'...';
        }

        if (isset($embed['description']) && mb_strlen($embed['description']) > 4096) {
            $embed['description'] = mb_substr($embed['description'], 0, 4093).'...';
        }

        if (isset($embed['fields']) && is_array($embed['fields'])) {
            $embed['fields'] = array_slice($embed['fields'], 0, 25);
            $embed['fields'] = array_map(function ($field) {
                if (isset($field['name']) && mb_strlen($field['name']) > 256) {
                    $field['name'] = mb_substr($field['name'], 0, 253).'...';
                }
                if (isset($field['value']) && mb_strlen($field['value']) > 1024) {
                    $field['value'] = mb_substr($field['value'], 0, 1021).'...';
                }

                return $field;
            }, $embed['fields']);
        }

        if (isset($embed['footer']['text']) && mb_strlen($embed['footer']['text']) > 2048) {
            $embed['footer']['text'] = mb_substr($embed['footer']['text'], 0, 2045).'...';
        }

        if (isset($embed['author']['name']) && mb_strlen($embed['author']['name']) > 256) {
            $embed['author']['name'] = mb_substr($embed['author']['name'], 0, 253).'...';
        }

        $embed = $this->trimEmbedToTotalLength($embed, 6000);

        return $embed;
    }

    private function trimEmbedToTotalLength(array $embed, int $maxLength): array
    {
        $remainingOverage = $this->getEmbedTextLength($embed) - $maxLength;

        if ($remainingOverage <= 0) {
            return $embed;
        }

        if (isset($embed['description']) && is_string($embed['description'])) {
            $embed['description'] = $this->truncateByOverage($embed['description'], $remainingOverage);
            $remainingOverage = $this->getEmbedTextLength($embed) - $maxLength;
        }

        if ($remainingOverage > 0 && isset($embed['fields']) && is_array($embed['fields'])) {
            foreach ($embed['fields'] as $index => $field) {
                if ($remainingOverage <= 0 || ! is_array($field)) {
                    continue;
                }

                if (isset($field['value']) && is_string($field['value'])) {
                    $embed['fields'][$index]['value'] = $this->truncateByOverage($field['value'], $remainingOverage);
                    $remainingOverage = $this->getEmbedTextLength($embed) - $maxLength;
                }

                if ($remainingOverage > 0 && isset($field['name']) && is_string($field['name'])) {
                    $embed['fields'][$index]['name'] = $this->truncateByOverage($field['name'], $remainingOverage);
                    $remainingOverage = $this->getEmbedTextLength($embed) - $maxLength;
                }
            }
        }

        if ($remainingOverage > 0 && isset($embed['footer']['text']) && is_string($embed['footer']['text'])) {
            $embed['footer']['text'] = $this->truncateByOverage($embed['footer']['text'], $remainingOverage);
            $remainingOverage = $this->getEmbedTextLength($embed) - $maxLength;
        }

        if ($remainingOverage > 0 && isset($embed['author']['name']) && is_string($embed['author']['name'])) {
            $embed['author']['name'] = $this->truncateByOverage($embed['author']['name'], $remainingOverage);
            $remainingOverage = $this->getEmbedTextLength($embed) - $maxLength;
        }

        if ($remainingOverage > 0 && isset($embed['title']) && is_string($embed['title'])) {
            $embed['title'] = $this->truncateByOverage($embed['title'], $remainingOverage);
        }

        return $embed;
    }

    private function getEmbedTextLength(array $embed): int
    {
        $totalLength = 0;
        $totalLength += mb_strlen($embed['title'] ?? '');
        $totalLength += mb_strlen($embed['description'] ?? '');
        $totalLength += mb_strlen($embed['footer']['text'] ?? '');
        $totalLength += mb_strlen($embed['author']['name'] ?? '');

        foreach ($embed['fields'] ?? [] as $field) {
            $totalLength += mb_strlen($field['name'] ?? '');
            $totalLength += mb_strlen($field['value'] ?? '');
        }

        return $totalLength;
    }

    private function truncateByOverage(string $value, int $overage): string
    {
        if ($overage <= 0) {
            return $value;
        }

        $newLength = max(0, mb_strlen($value) - $overage);

        return mb_substr($value, 0, $newLength);
    }

    private function resolveDeveloperName(Game $game): string
    {
        $developer = $this->sanitizeDiscordText($game->developer ?? '');

        if ($developer !== '') {
            return $developer;
        }

        return $this->sanitizeDiscordText($game->authors ?? '');
    }

    private function sanitizeDiscordText(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $preservedTokens = [];
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $protected = preg_replace_callback(
            '/<(?:t:\d+:[tTdDfFR]|@!?\d+|@&\d+|#\d+|a?:[A-Za-z0-9_~]{2,32}:\d+)>/',
            function (array $matches) use (&$preservedTokens): string {
                $placeholder = self::PRESERVED_DISCORD_TOKEN_PREFIX.count($preservedTokens).'__';
                $preservedTokens[$placeholder] = $matches[0];

                return $placeholder;
            },
            $decoded,
        );

        $sanitized = strip_tags($protected);
        $sanitized = str_replace("\u{00A0}", ' ', $sanitized);
        $sanitized = preg_replace("/\r\n?/", "\n", $sanitized);
        $sanitized = preg_replace('/[ \t]+/', ' ', $sanitized);
        $sanitized = preg_replace("/ *\n */", "\n", $sanitized);
        $sanitized = trim($sanitized);

        return strtr($sanitized, $preservedTokens);
    }
}
