<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateGameRating;
use App\Models\Rating;
use Illuminate\Support\Facades\Log;

class RatingObserver
{
    /**
     * Handle the Rating "created" event.
     */
    public function created(Rating $rating): void
    {
        $this->dispatchRatingUpdate($rating, 'created');
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
    }

    /**
     * Handle the Rating "deleted" event.
     */
    public function deleted(Rating $rating): void
    {
        $this->dispatchRatingUpdate($rating, 'deleted');
    }

    /**
     * Dispatch the UpdateGameRating job for the affected game.
     */
    private function dispatchRatingUpdate(Rating $rating, string $event): void
    {
        if (!$rating->game_id) {
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
}

