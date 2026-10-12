<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Illuminate\Support\defer;

class RatingStatsCacheService
{
    private const string VERSION_KEY = 'ratings.stats.version';

    private const int CUBE_FRESH_SECONDS = 300;

    private const array LEGACY_FOREVER_NAMES = [
        'ratings.global_stats',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function globalStats(): array
    {
        return app(RatingAnalyticsService::class)->globalStatsFromCube(self::cube(visibleGamesOnly: true));
    }

    public static function count(bool $onlyReviews, ?int $stars, bool $visibleGamesOnly, ?string $platform): int
    {
        return (int) collect(self::cube($visibleGamesOnly)['cells'])
            ->when($onlyReviews, fn ($cells) => $cells->where('is_reviewed', true))
            ->when($stars !== null, fn ($cells) => $cells->where('rating', $stars))
            ->when($platform !== null, fn ($cells) => $cells->where('platform', $platform))
            ->sum('count');
    }

    /**
     * @return array{cells: list<array<string, mixed>>, unique_games: int}
     */
    public static function refreshCube(bool $visibleGamesOnly): array
    {
        Cache::forget(self::cubeDirtyKey($visibleGamesOnly));
        $cube = app(RatingAnalyticsService::class)->ratingCube($visibleGamesOnly);
        Cache::forever(self::cubeKey($visibleGamesOnly), ['cube' => $cube, 'computed_at' => now()->getTimestamp()]);

        return $cube;
    }

    public static function key(string $name): string
    {
        return "{$name}:v" . self::version();
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
        Cache::forever(self::cubeDirtyKey(true), true);
        Cache::forever(self::cubeDirtyKey(false), true);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function (): void {
                Cache::increment(self::VERSION_KEY);
            });
        }
    }

    /**
     * @return array{cells: list<array<string, mixed>>, unique_games: int}
     */
    private static function cube(bool $visibleGamesOnly): array
    {
        $cached = Cache::get(self::cubeKey($visibleGamesOnly));

        if ($cached === null) {
            return self::refreshCube($visibleGamesOnly);
        }

        if (Cache::has(self::cubeDirtyKey($visibleGamesOnly)) || $cached['computed_at'] < now()->subSeconds(self::CUBE_FRESH_SECONDS)->getTimestamp()) {
            defer(
                fn () => Cache::lock('lock:' . self::cubeKey($visibleGamesOnly), 60)->get(fn () => self::refreshCube($visibleGamesOnly)),
                self::cubeKey($visibleGamesOnly),
            );
        }

        return $cached['cube'];
    }

    private static function cubeKey(bool $visibleGamesOnly): string
    {
        return 'ratings.cube.' . ($visibleGamesOnly ? 'visible-games' : 'all-games');
    }

    private static function cubeDirtyKey(bool $visibleGamesOnly): string
    {
        return self::cubeKey($visibleGamesOnly) . '.dirty';
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
