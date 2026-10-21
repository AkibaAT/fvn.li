<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Tag;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GamePageCacheService
{
    private const int BUILD_LOCK_SECONDS = 30;

    private const int BUILD_WAIT_SECONDS = 15;

    private const int VERSION_TTL_SECONDS = 2_592_000;

    /**
     * @param  Closure(int, int): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function remember(Game $game, int $reviewsPage, int $versionsPage, Closure $build): array
    {
        $ttl = (int) config('fvn.game_page_cache_ttl');

        if ($ttl <= 0) {
            return $build($reviewsPage, $versionsPage);
        }

        $firstPage = self::rememberPage($game, 1, 1, $ttl, $build);
        $reviewsPage = min($reviewsPage, max(1, (int) $firstPage['reviews']['last_page']));
        $versionsPage = min($versionsPage, max(1, (int) $firstPage['gameVersions']['last_page']));

        if ($reviewsPage === 1 && $versionsPage === 1) {
            return $firstPage;
        }

        return self::rememberPage($game, $reviewsPage, $versionsPage, $ttl, $build);
    }

    public static function invalidate(int $gameId): void
    {
        self::invalidateMany([$gameId]);
    }

    /**
     * @param  iterable<int|null>  $gameIds
     */
    public static function invalidateMany(iterable $gameIds): void
    {
        $gameIds = collect($gameIds)->map(fn ($gameId): int => (int) $gameId)->filter()->unique()->values();

        if ($gameIds->isEmpty()) {
            return;
        }

        $forget = fn () => $gameIds->each(fn (int $gameId) => Cache::forget(self::versionKey($gameId)));
        $forget();

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($forget);
        }

        EdgeCache::purge([EdgeCache::GAMES, ...$gameIds->map(EdgeCache::game(...))->all()]);
    }

    /**
     * @param  Closure(int, int): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private static function rememberPage(Game $game, int $reviewsPage, int $versionsPage, int $ttl, Closure $build): array
    {
        $key = implode('.', [
            "game.{$game->id}.page",
            Cache::remember(self::versionKey($game->id), self::VERSION_TTL_SECONDS, fn (): string => (string) Str::uuid()),
            $game->updated_at?->getTimestamp() ?? 0,
            Tag::publicCacheVariant(),
            $reviewsPage,
            $versionsPage,
        ]);

        return Cache::get($key) ?? Cache::lock("lock:{$key}", self::BUILD_LOCK_SECONDS)->block(
            self::BUILD_WAIT_SECONDS,
            fn (): array => Cache::get($key) ?? tap($build($reviewsPage, $versionsPage), fn (array $props) => Cache::put($key, $props, $ttl)),
        );
    }

    private static function versionKey(int $gameId): string
    {
        return "game.{$gameId}.page.version";
    }
}
