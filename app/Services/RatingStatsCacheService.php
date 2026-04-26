<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RatingStatsCacheService
{
    private const string VERSION_KEY = 'ratings.stats.version';

    public static function key(string $name): string
    {
        return "{$name}:v" . self::version();
    }

    public static function clear(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);

        // Drop the previous unversioned key eagerly so old deployments do not
        // keep serving stale data during a rolling deploy.
        Cache::forget('global_rating_stats');
    }

    private static function version(): int
    {
        Cache::add(self::VERSION_KEY, 1);

        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
