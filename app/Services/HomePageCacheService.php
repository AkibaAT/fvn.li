<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomePageCacheService
{
    /**
     * Clear all home page caches.
     */
    public static function clearAll(): void
    {
        self::clearStats();
        self::clearTeasers();

        Log::debug('Cleared all home page caches');
    }

    /**
     * Clear home page stats cache (totalGames, totalRatings, totalUsers).
     */
    public static function clearStats(): void
    {
        Cache::forget('home.stats');

        Log::debug('Cleared home.stats cache');
    }

    /**
     * Clear home page game teasers cache.
     * This clears all teaser variants (different user ignored game combinations).
     */
    public static function clearTeasers(): void
    {
        // Clear all teaser caches by using cache tags (if available) or pattern matching
        // For now, we'll use a simple prefix-based approach

        // Since we can't easily clear all pattern-based keys without tags,
        // we'll use a cache key that we can increment to invalidate all teasers
        Cache::increment('home.teasers.version', 1);

        Log::debug('Invalidated all home.teasers caches');
    }

    /**
     * Get the current teaser cache version.
     */
    public static function getTeaserVersion(): int
    {
        return (int) Cache::get('home.teasers.version', 1);
    }
}
