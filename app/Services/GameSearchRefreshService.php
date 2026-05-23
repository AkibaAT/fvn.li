<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;

class GameSearchRefreshService
{
    /**
     * Refresh public game search and homepage teaser caches after latest-version data changes.
     */
    public static function refreshForLatestVersion(GameVersion $version, string $reason): void
    {
        if (! $version->is_latest) {
            return;
        }

        HomePageCacheService::clearTeasers();

        $gameId = $version->game_id;

        dispatch(function () use ($gameId, $reason) {
            $game = Game::with(['tags', 'gameJams', 'gameVersions'])->find($gameId);
            if (! $game) {
                return;
            }

            if ($game->shouldBeSearchable()) {
                $game->searchable();
            } else {
                $game->unsearchable();
            }

            Log::info('Refreshed game search document after latest version change', [
                'game_id' => $gameId,
                'reason' => $reason,
            ]);
        })->afterCommit();
    }
}
