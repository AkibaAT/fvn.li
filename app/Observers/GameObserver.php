<?php

declare(strict_types=1);

namespace App\Observers;

use App\Livewire\GameList;
use App\Models\Game;
use Illuminate\Support\Str;

class GameObserver
{
    public function created(Game $game): void
    {
        GameList::clearFilterCache();

        // Only generate slug for visible games
        if ($game->is_visible) {
            $this->generateSlug($game);
        }
    }

    public function updated(Game $game): void
    {
        if ($game->isDirty(['status', 'game_engine', 'is_visible'])) {
            GameList::clearFilterCache();
        }

        // Generate or clear slug based on visibility
        if ($game->isDirty('is_visible')) {
            if ($game->is_visible) {
                $this->generateSlug($game);
            } else {
                $game->slug = null;
                $game->saveQuietly();
            }
        }
    }

    public function deleted(Game $game): void
    {
        GameList::clearFilterCache();
    }

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
        $game->saveQuietly();
    }
}
