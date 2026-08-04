<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Game;
use App\Services\Discord\DiscordCatalogMessageSyncService;
use App\Services\GameFilterService;
use App\Services\HomePageCacheService;
use App\Services\RatingStatsCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameObserver
{
    public function created(Game $game): void
    {
        GameFilterService::clearCache();
        HomePageCacheService::clearAll(); // Clear home page cache for new game
        $this->bumpRecommendationCacheVersion();

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

        $game->processPendingGameJams();
        $game->processPendingTags();

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
        Log::debug('Game updated event triggered', ['game_id' => $game->id]);

        // Track when a game first becomes visible and log the visibility change
        if ($game->wasChanged('is_visible')) {
            $wasVisible = $game->getOriginal('is_visible');
            $isVisible = $game->is_visible;

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

            if ($isVisible && ! empty(trim($game->name))) {
                // Game is now visible - add to search index
                Log::debug('Adding game to search index', ['game_id' => $game->id]);

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
                Log::debug('Removing game from search index', ['game_id' => $game->id]);

                $gameId = $game->id;
                $gameName = $game->name;

                dispatch(function () use ($gameId, $gameName) {
                    $game = Game::find($gameId);

                    if (! $game || $game->is_visible) {
                        return;
                    }

                    $game->unsearchable();
                    Log::info('Removed game from search index', [
                        'game_id' => $gameId,
                        'game_name' => $gameName,
                    ]);
                })->afterCommit();
            }

            HomePageCacheService::clearAll();
            RatingStatsCacheService::clear();
            $this->bumpRecommendationCacheVersion();
        }

        if ($game->isDirty(['status', 'game_engine', 'is_visible', 'content_type'])) {
            Log::debug('Clearing filter cache after game update', ['game_id' => $game->id]);
            GameFilterService::clearCache();
        }

        if ($game->wasChanged(['first_visible_at', 'latest_version_published_at', 'trending_score', 'name', 'thumb_url'])) {
            Log::debug('Clearing home page teasers after game update', ['game_id' => $game->id]);
            HomePageCacheService::clearTeasers();
        }

        if ($game->wasChanged(['authors', 'custom_name', 'name', 'optimized_thumbnails', 'rating_count', 'rating_score', 'status', 'thumb_url', 'platform'])) {
            $this->bumpRecommendationCacheVersion();
        }

        if ($game->wasChanged('is_stats_extraction_disabled')) {
            if ($game->is_stats_extraction_disabled) {
                $this->clearExtractedStats($game);
            }

            if ($game->is_visible && ! empty(trim($game->name))) {
                $gameId = $game->id;

                dispatch(function () use ($gameId) {
                    $game = Game::with(['tags', 'gameJams', 'gameVersions'])->find($gameId);
                    if ($game && $game->is_visible) {
                        $game->searchable();
                        Log::info('Re-indexed game after stats extraction setting changed', [
                            'game_id' => $gameId,
                        ]);
                    }
                })->afterCommit();
            }
        }

        Log::debug('Processing pending game jams after game update', ['game_id' => $game->id]);
        $hadPendingGameJams = ! empty($game->pendingGameJamId);
        $game->processPendingGameJams();

        Log::debug('Processing pending tags after game update', ['game_id' => $game->id]);
        $hadPendingTags = ! empty($game->pendingTagIds);
        $game->processPendingTags();

        // If we processed any pending associations and the game is visible, re-index it
        if (($hadPendingGameJams || $hadPendingTags) && $game->is_visible) {
            Log::debug('Re-indexing game due to updated associations', ['game_id' => $game->id]);
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

        Log::debug('Game updated event complete', ['game_id' => $game->id]);

        if ($game->wasChanged([
            'name', 'custom_name', 'description', 'custom_description', 'status', 'thumb_url', 'optimized_thumbnails',
            'screenshots', 'custom_screenshots', 'developer', 'authors', 'game_engine', 'platform', 'is_paid', 'min_price',
            'source_language_id', 'content_type', 'is_visible',
        ])) {
            $gameId = $game->id;
            dispatch(fn () => app(DiscordCatalogMessageSyncService::class)->queueForGame($gameId))->afterCommit();
        }
    }

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

    private function clearExtractedStats(Game $game): void
    {
        $versionIds = $game->gameVersions()->pluck('id');

        if ($versionIds->isEmpty()) {
            return;
        }

        DB::table('version_word_frequencies')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_dialogue_lines')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_character_stats')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_language_stats')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_file_categories')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_paths')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_variable_changes')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_variables')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_menu_choices')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_edges')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_labels')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('game_versions')->whereIn('id', $versionIds)->update(['route_graph_data' => null]);

        Log::info('Cleared extracted stats for game with disabled stats extraction', [
            'game_id' => $game->id,
            'version_ids' => $versionIds->all(),
        ]);
    }
}
