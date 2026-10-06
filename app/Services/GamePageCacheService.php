<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Tag;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GamePageCacheService
{
    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function remember(Game $game, int $reviewsPage, int $versionsPage, Closure $build): array
    {
        $ttl = (int) config('fvn.game_page_cache_ttl');

        if ($ttl <= 0) {
            return $build();
        }

        $key = implode('.', [
            "game.{$game->id}.page",
            (int) Cache::get(self::versionKey($game->id), 0),
            $game->updated_at?->getTimestamp() ?? 0,
            Tag::publicCacheVariant(),
            $reviewsPage,
            $versionsPage,
        ]);

        return Cache::remember($key, $ttl, $build);
    }

    public static function invalidate(int $gameId): void
    {
        Cache::add(self::versionKey($gameId), 0);
        Cache::increment(self::versionKey($gameId));

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::increment(self::versionKey($gameId)));
        }
    }

    private static function versionKey(int $gameId): string
    {
        return "game.{$gameId}.page.version";
    }
}
