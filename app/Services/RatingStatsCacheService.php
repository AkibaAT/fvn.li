<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RatingStatsCacheService
{
    public const string GLOBAL_STATS_KEY = 'global_rating_stats';

    private const string VERSION_KEY = 'ratings.stats.version';

    private const array LEGACY_FOREVER_NAMES = [
        'ratings.global_stats',
    ];

    public static function key(string $name): string
    {
        return "{$name}:v".self::version();
    }

    public static function clear(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        $version = self::version();

        foreach (self::LEGACY_FOREVER_NAMES as $name) {
            Cache::forget(self::versionedKey($name, $version));

            if ($version > 1) {
                Cache::forget(self::versionedKey($name, $version - 1));
            }
        }

        Cache::increment(self::VERSION_KEY);

        Cache::forget(self::GLOBAL_STATS_KEY);
    }

    private static function version(): int
    {
        Cache::add(self::VERSION_KEY, 1);

        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    private static function versionedKey(string $name, int $version): string
    {
        return "{$name}:v{$version}";
    }
}
