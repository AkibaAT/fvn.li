<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service for detecting game platforms from URLs and extracting platform-specific IDs
 *
 * Supports:
 * - itch.io: https://example.itch.io/game-name
 * - Steam: https://store.steampowered.com/app/123456/Game_Name/
 * - Other: Any other URL
 */
class PlatformDetectionService
{
    /**
     * Detect the platform from a game URL
     *
     * @param  string  $url  The game URL
     * @return string One of: 'itch_io', 'steam', 'other'
     */
    public function detectPlatform(string $url): string
    {
        if ($this->isItchioUrl($url)) {
            return 'itch_io';
        }

        if ($this->isSteamUrl($url)) {
            return 'steam';
        }

        return 'other';
    }

    /**
     * Extract platform-specific ID from URL
     *
     * @param  string  $url  The game URL
     * @param  string  $platform  The platform type
     * @return string|null The platform-specific ID, or null if not found
     */
    public function extractPlatformId(string $url, string $platform): ?string
    {
        return match ($platform) {
            'itch_io' => $this->extractItchioId($url),
            'steam' => $this->extractSteamAppId($url),
            default => null,
        };
    }

    /**
     * Extract itch.io game ID from URL
     *
     * Examples:
     * - https://gamer-den-project.itch.io/gamer-den → gamer-den-project/gamer-den
     * - https://example.itch.io/my-game → example/my-game
     *
     * @param  string  $url  The itch.io URL
     * @return string|null The itch.io game ID (creator/game format), or null if not found
     */
    public function extractItchioId(string $url): ?string
    {
        // Match pattern: https://[creator].itch.io/[game-name]
        if (preg_match('/https?:\/\/([^.]+)\.itch\.io\/([^\/\?#]+)/', $url, $matches)) {
            $creator = $matches[1];
            $game = $matches[2];

            return "{$creator}/{$game}";
        }

        return null;
    }

    /**
     * Extract Steam App ID from URL
     *
     * Examples:
     * - https://store.steampowered.com/app/1084640/Chicken_Police__Paint_it_RED/ → 1084640
     * - https://store.steampowered.com/app/123456/ → 123456
     *
     * @param  string  $url  The Steam store URL
     * @return string|null The Steam App ID, or null if not found
     */
    public function extractSteamAppId(string $url): ?string
    {
        // Match pattern: /app/[app-id]/
        if (preg_match('/\/app\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the human-readable platform name
     *
     * @param  string  $platform  The platform type
     * @return string The human-readable name
     */
    public function getPlatformName(string $platform): string
    {
        return match ($platform) {
            'itch_io' => 'itch.io',
            'steam' => 'Steam',
            'other' => 'Other',
            default => 'Unknown',
        };
    }

    /**
     * Validate if a platform is supported
     *
     * @param  string  $platform  The platform to validate
     * @return bool True if platform is supported
     */
    public function isValidPlatform(string $platform): bool
    {
        return in_array($platform, ['itch_io', 'steam', 'other'], true);
    }

    /**
     * Get all supported platforms
     *
     * @return array List of supported platform identifiers
     */
    public function getSupportedPlatforms(): array
    {
        return ['itch_io', 'steam', 'other'];
    }

    /**
     * Check if URL is an itch.io URL
     */
    private function isItchioUrl(string $url): bool
    {
        return str_contains($url, 'itch.io');
    }

    /**
     * Check if URL is a Steam URL
     */
    private function isSteamUrl(string $url): bool
    {
        return str_contains($url, 'steampowered.com');
    }
}
