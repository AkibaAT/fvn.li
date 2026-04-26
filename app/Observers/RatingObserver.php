<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateGameRating;
use App\Models\Rating;
use App\Services\HomePageCacheService;
use App\Services\RatingStatsCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RatingObserver
{
    /**
     * Handle the Rating "created" event.
     */
    public function created(Rating $rating): void
    {
        $this->dispatchRatingUpdate($rating, 'created');
        $this->clearRaterCache($rating);
        RatingStatsCacheService::clear();
        HomePageCacheService::clearStats(); // Clear home page stats for new rating
    }

    /**
     * Handle the Rating "updated" event.
     */
    public function updated(Rating $rating): void
    {
        // Only dispatch if fields that affect rating calculation changed
        if ($rating->wasChanged(['rating', 'is_visible'])) {
            $this->dispatchRatingUpdate($rating, 'updated');
        }

        // Clear cache if review content or visibility changed
        if ($rating->wasChanged(['review', 'is_reviewed', 'is_visible', 'rating'])) {
            $this->clearRaterCache($rating);
        }

        if ($rating->wasChanged(['published_at', 'game_id', 'rating', 'is_reviewed', 'is_visible'])) {
            RatingStatsCacheService::clear();
        }

        // Clear home page stats if visibility changed (affects total rating count)
        if ($rating->wasChanged('is_visible')) {
            HomePageCacheService::clearStats();
        }
    }

    /**
     * Handle the Rating "deleted" event.
     */
    public function deleted(Rating $rating): void
    {
        $this->dispatchRatingUpdate($rating, 'deleted');
        $this->clearRaterCache($rating);
        RatingStatsCacheService::clear();
        HomePageCacheService::clearStats(); // Clear home page stats for deleted rating
    }

    /**
     * Dispatch the UpdateGameRating job for the affected game.
     */
    private function dispatchRatingUpdate(Rating $rating, string $event): void
    {
        if (! $rating->game_id) {
            Log::warning('Rating has no game_id, skipping rating update', [
                'rating_id' => $rating->id,
                'event' => $event,
            ]);

            return;
        }

        UpdateGameRating::dispatch($rating->game_id);

        Log::debug('Dispatched rating update job', [
            'rating_id' => $rating->id,
            'game_id' => $rating->game_id,
            'event' => $event,
        ]);
    }

    /**
     * Clear cached data for the rater when their ratings change.
     */
    private function clearRaterCache(Rating $rating): void
    {
        if (! $rating->rater_id) {
            return;
        }

        // Clear the phrase analysis cache for this rater
        Cache::forget("rater_phrases_{$rating->rater_id}");

        Log::debug('Cleared rater cache', [
            'rating_id' => $rating->id,
            'rater_id' => $rating->rater_id,
        ]);
    }
}
