<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tag;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TagObserver
{
    /**
     * Handle the Tag "updated" event.
     */
    public function updated(Tag $tag): void
    {
        // If the tag name changed, update all related games
        if ($tag->isDirty('name')) {
            $this->updateRelatedGames($tag);
        }
    }

    /**
     * Handle the Tag "deleted" event.
     */
    public function deleted(Tag $tag): void
    {
        $this->updateRelatedGames($tag);
    }

    /**
     * Update all games that use this tag.
     */
    private function updateRelatedGames(Tag $tag): void
    {
        try {
            // Update search index for all games that use this tag
            $tag->games()
                ->where('is_visible', true)
                ->orderBy('games.id')
                ->chunk(100, function ($games) {
                    $games->searchable();
                });

            Log::info('Updated game search indexes for tag change', [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
            ]);

            Cache::add('games.recommendations.version', 1);
            Cache::increment('games.recommendations.version');
        } catch (Exception $e) {
            Log::warning('Failed to update game search indexes for tag change', [
                'tag_id' => $tag->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
