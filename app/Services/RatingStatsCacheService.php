<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

class RatingStatsCacheService
{
    private const string VERSION_KEY = 'ratings.stats.version';

    private const int CUBE_FRESH_SECONDS = 300;

    private const int COLD_BUILD_WAIT_SECONDS = 30;

    private const int CUBE_TTL_SECONDS = 86_400;

    private const int VERSION_TTL_SECONDS = 2_592_000;

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
        Cache::put(self::cubeKey($visibleGamesOnly), ['cube' => $cube, 'computed_at' => now()->getTimestamp()], self::CUBE_TTL_SECONDS);

        return $cube;
    }

    public static function key(string $name): string
    {
        return "{$name}:v" . self::version();
    }

    public static function clear(): void
    {
        Cache::forget(self::VERSION_KEY);
        EdgeCache::purge([EdgeCache::RATINGS]);
        Cache::put(self::cubeDirtyKey(true), true, self::CUBE_TTL_SECONDS);
        Cache::put(self::cubeDirtyKey(false), true, self::CUBE_TTL_SECONDS);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::forget(self::VERSION_KEY));
        }
    }

    /**
     * @return array{cells: list<array<string, mixed>>, unique_games: int}
     */
    private static function cube(bool $visibleGamesOnly): array
    {
        $cached = Cache::get(self::cubeKey($visibleGamesOnly));

        if ($cached === null) {
            return self::lock($visibleGamesOnly)->block(
                self::COLD_BUILD_WAIT_SECONDS,
                fn (): array => Cache::get(self::cubeKey($visibleGamesOnly))['cube'] ?? self::refreshCube($visibleGamesOnly),
            );
        }

        if (Cache::has(self::cubeDirtyKey($visibleGamesOnly)) || $cached['computed_at'] < now()->subSeconds(self::CUBE_FRESH_SECONDS)->getTimestamp()) {
            defer(
                fn () => self::lock($visibleGamesOnly)->get(fn () => self::refreshCube($visibleGamesOnly)),
                self::cubeKey($visibleGamesOnly),
            );
        }

        return $cached['cube'];
    }

    private static function cubeKey(bool $visibleGamesOnly): string
    {
        return 'ratings.cube.' . ($visibleGamesOnly ? 'visible-games' : 'all-games');
    }

    private static function lock(bool $visibleGamesOnly): Lock
    {
        return Cache::lock('lock:' . self::cubeKey($visibleGamesOnly), 60);
    }

    private static function cubeDirtyKey(bool $visibleGamesOnly): string
    {
        return self::cubeKey($visibleGamesOnly) . '.dirty';
    }

    private static function version(): string
    {
        return Cache::remember(self::VERSION_KEY, self::VERSION_TTL_SECONDS, fn (): string => (string) Str::uuid());
    }
}
