<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Game;
use App\Services\GameFilterService;
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

        // Process any pending associations
        $game->processPendingGameJams();
        $game->processPendingTags();
    }

    /**
     * Handle the Game "updated" event.
     */
    public function updated(Game $game): void
    {
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
        }

        // Clear filter cache if relevant fields changed
        if ($game->isDirty(['status', 'game_engine', 'is_visible'])) {
            GameFilterService::clearCache();
        }

        // Handle thumbnail updates
        if ($game->wasChanged('thumb_url')) {
            // Clear old thumbnails if they exist
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }

            // Process new thumbnail if it exists, or if we have screenshots as fallback
            if ($game->thumb_url || ! empty($game->screenshots)) {
                // Process in background to avoid blocking the main operation
                dispatch(function () use ($game) {
                    try {
                        Artisan::call('games:process-thumbnails', [
                            '--game-id' => $game->id,
                            '--force' => true,
                        ]);
                    } catch (Exception $e) {
                        Log::error('Failed to process game thumbnail after update', [
                            'game_id' => $game->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                })->afterResponse();
            }
        }

        // Handle screenshot updates - if no thumbnail exists, process screenshots as thumbnail fallback
        if ($game->wasChanged('screenshots') && ! $game->thumb_url && ! empty($game->screenshots)) {
            // Clear old thumbnails if they exist
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }

            // Process first screenshot as thumbnail in background
            dispatch(function () use ($game) {
                try {
                    Artisan::call('games:process-thumbnails', [
                        '--game-id' => $game->id,
                        '--force' => true,
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to process screenshot as thumbnail fallback', [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }

        // Process any pending associations
        $game->processPendingGameJams();
        $game->processPendingTags();
    }

    /**
     * Handle the Game "deleted" event.
     */
    public function deleted(Game $game): void
    {
        GameFilterService::clearCache();

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
