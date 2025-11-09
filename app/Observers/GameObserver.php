<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Game;
use App\Services\GameFilterService;
use App\Services\HomePageCacheService;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GameObserver
{
    /**
     * Handle the Game "saving" event.
     */
    public function saving(Game $game): void
    {
        // Generate slug if it doesn't exist or if relevant fields changed
        if (! $game->slug || $game->isDirty(['url', 'name'])) {
            $this->generateSlug($game);
        }
    }

    /**
     * Handle the Game "created" event.
     */
    public function created(Game $game): void
    {
        GameFilterService::clearCache();
        HomePageCacheService::clearAll(); // Clear home page cache for new game

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
    }

    /**
     * Handle the Game "updated" event.
     */
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

            // Clear home page cache when visibility changes
            HomePageCacheService::clearAll();
        }

        // Clear filter cache if relevant fields changed
        if ($game->isDirty(['status', 'game_engine', 'is_visible'])) {
            echo "    [Observer] Clearing filter cache\n";
            GameFilterService::clearCache();
        }

        // Clear home page teasers if fields that affect sorting/display changed
        if ($game->wasChanged(['first_visible_at', 'latest_version_published_at', 'trending_score', 'name', 'thumb_url'])) {
            echo "    [Observer] Clearing home page teasers\n";
            HomePageCacheService::clearTeasers();
        }

        // Process any pending associations
        echo "    [Observer] Processing pending game jams\n";
        $game->processPendingGameJams();
        echo "    [Observer] Processing pending tags\n";
        $game->processPendingTags();
        echo "    [Observer] Game updated event complete\n";
    }

    /**
     * Handle the Game "deleted" event.
     */
    public function deleted(Game $game): void
    {
        GameFilterService::clearCache();
        HomePageCacheService::clearAll(); // Clear home page cache when game deleted

        // Clean up optimized thumbnails if they exist
        if ($game->optimized_thumbnails) {
            $game->clearOptimizedThumbnails();
        }
    }

    /**
     * Generate a unique slug for the game.
     */
    protected function generateSlug(Game $game): void
    {
        // Get base slug from game URL
        $baseSlug = basename($game->url);

        // If URL doesn't provide a usable slug, generate from name
        if (empty($baseSlug) || $baseSlug === '/') {
            $baseSlug = Str::slug($game->name);
        }

        // Find a unique slug
        $slug = $baseSlug;
        $counter = 1;

        while (Game::where('slug', $slug)->where('id', '!=', $game->id)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $game->slug = $slug;
    }
}
