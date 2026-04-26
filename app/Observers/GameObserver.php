<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Game;
use App\Services\GameFilterService;
use App\Services\HomePageCacheService;
use App\Services\RatingStatsCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GameObserver
{
    /**
     * Handle the Game "created" event.
     */
    public function created(Game $game): void
    {
        GameFilterService::clearCache();
        HomePageCacheService::clearAll(); // Clear home page cache for new game
        $this->bumpRecommendationCacheVersion();

        // Set first_visible_at for new games that are created as visible
        // This handles the case where a game is imported with is_visible = true from the start
        if ($game->is_visible && ! $game->first_visible_at) {
            $game->first_visible_at = now();
            $game->saveQuietly(); // Prevent infinite recursion

            Log::info('Set first_visible_at for newly created visible game', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'first_visible_at' => $game->first_visible_at,
            ]);
        }

        // Process any pending associations
        $game->processPendingGameJams();
        $game->processPendingTags();

        // Add game to search index if it's visible and has a name
        // Defer indexing until after the transaction commits to ensure all relationships are saved
        if ($game->is_visible && ! empty(trim($game->name))) {
            $gameId = $game->id;
            $gameName = $game->name;

            dispatch(function () use ($gameId, $gameName) {
                $game = Game::with(['tags', 'gameJams', 'gameVersions'])->find($gameId);
                if ($game) {
                    $game->searchable();
                    Log::info('Added newly created visible game to search index', [
                        'game_id' => $gameId,
                        'game_name' => $gameName,
                    ]);
                }
            })->afterCommit();
        }
    }

    public function updated(Game $game): void
    {
        echo "    [Observer] Game updated event triggered\n";

        // Track when a game first becomes visible and log the visibility change
        if ($game->wasChanged('is_visible')) {
            $wasVisible = $game->getOriginal('is_visible');
            $isVisible = $game->is_visible;

            // Set first_visible_at when game becomes visible for the first time
            // Only set if: game is now visible, was not visible before, and first_visible_at is not already set
            if ($isVisible && ! $wasVisible && ! $game->first_visible_at) {
                $game->first_visible_at = now();
                $game->saveQuietly(); // Prevent infinite recursion

                Log::info('Set first_visible_at for game becoming visible', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                    'first_visible_at' => $game->first_visible_at,
                ]);
            }

            // Update search index when visibility changes
            if ($isVisible && ! empty(trim($game->name))) {
                // Game is now visible - add to search index
                echo "    [Observer] Adding game to search index\n";

                // Defer indexing until after the transaction commits to ensure all relationships are saved
                $gameId = $game->id;
                $gameName = $game->name;

                dispatch(function () use ($gameId, $gameName) {
                    $game = Game::with(['tags', 'gameJams', 'gameVersions'])->find($gameId);
                    if ($game) {
                        $game->searchable();
                        Log::info('Added game to search index', [
                            'game_id' => $gameId,
                            'game_name' => $gameName,
                        ]);
                    }
                })->afterCommit();
            } elseif (! $isVisible) {
                // Game is now hidden - remove from search index
                echo "    [Observer] Removing game from search index\n";
                $game->unsearchable();
                Log::info('Removed game from search index', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                ]);
            }

            // Clear home page cache when visibility changes
            HomePageCacheService::clearAll();
            RatingStatsCacheService::clear();
            $this->bumpRecommendationCacheVersion();
        }

        // Clear filter cache if relevant fields changed
        if ($game->isDirty(['status', 'game_engine', 'is_visible', 'content_type'])) {
            echo "    [Observer] Clearing filter cache\n";
            GameFilterService::clearCache();
        }

        // Clear home page teasers if fields that affect sorting/display changed
        if ($game->wasChanged(['first_visible_at', 'latest_version_published_at', 'trending_score', 'name', 'thumb_url'])) {
            echo "    [Observer] Clearing home page teasers\n";
            HomePageCacheService::clearTeasers();
        }

        if ($game->wasChanged(['authors', 'custom_name', 'name', 'optimized_thumbnails', 'rating_count', 'rating_score', 'status', 'thumb_url', 'platform'])) {
            $this->bumpRecommendationCacheVersion();
        }

        // Process any pending associations
        echo "    [Observer] Processing pending game jams\n";
        $hadPendingGameJams = ! empty($game->pendingGameJamId);
        $game->processPendingGameJams();

        echo "    [Observer] Processing pending tags\n";
        $hadPendingTags = ! empty($game->pendingTagIds);
        $game->processPendingTags();

        // If we processed any pending associations and the game is visible, re-index it
        if (($hadPendingGameJams || $hadPendingTags) && $game->is_visible) {
            echo "    [Observer] Re-indexing game due to updated associations\n";
            $this->bumpRecommendationCacheVersion();
            $gameId = $game->id;

            dispatch(function () use ($gameId) {
                $game = Game::with(['tags', 'gameJams', 'gameVersions'])->find($gameId);
                if ($game && $game->is_visible) {
                    $game->searchable();
                    Log::info('Re-indexed game after updating associations', [
                        'game_id' => $gameId,
                    ]);
                }
            })->afterCommit();
        }

        echo "    [Observer] Game updated event complete\n";
    }

    /**
     * Handle the Game "deleted" event.
     */
    public function deleted(Game $game): void
    {
        GameFilterService::clearCache();
        HomePageCacheService::clearAll(); // Clear home page cache when game deleted
        $this->bumpRecommendationCacheVersion();

        // Clean up optimized thumbnails if they exist
        if ($game->optimized_thumbnails) {
            $game->clearOptimizedThumbnails();
        }
    }

    private function bumpRecommendationCacheVersion(): void
    {
        Cache::add('games.recommendations.version', 1);
        Cache::increment('games.recommendations.version');
    }
}
