<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Rating;
use Illuminate\Support\Facades\Log;

class RatingCalculationService
{
    public function updateGameRating(Game $game): void
    {
        $ratingData = $this->calculateGameRating($game->id);

        $game->updateQuietly([
            'rating_score' => $ratingData['average_rating'],
            'rating_count' => $ratingData['total_count'],
        ]);
        $this->refreshGameSearchIndex($game);

        Log::info('Updated game rating', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'rating_score' => $ratingData['average_rating'],
            'rating_count' => $ratingData['total_count'],
        ]);
    }

    public function calculateGameRating(int $gameId): array
    {
        $ratingStats = Rating::where('game_id', $gameId)->where('is_visible', true)
            ->selectRaw('
                COUNT(*) as total_count,
                AVG(rating) as average_rating
            ')
            ->first();

        return [
            'total_count' => (int) ($ratingStats->total_count ?? 0),
            'average_rating' => $ratingStats->average_rating ? round((float) $ratingStats->average_rating, 2) : null,
        ];
    }

    /**
     * Recalculate ratings for all visible games
     */
    public function recalculateAllGameRatings(): int
    {
        $updatedCount = 0;
        $resetCount = 0;

        // Only iterate over visible games since we only care about those
        Game::where('is_visible', true)
            ->orderBy('id')
            ->chunk(100, function ($games) use (&$updatedCount, &$resetCount) {
                foreach ($games as $game) {
                    $ratingData = $this->calculateGameRating($game->id);

                    if ($ratingData['total_count'] > 0) {
                        // Game has ratings - update with calculated values
                        $game->updateQuietly([
                            'rating_score' => $ratingData['average_rating'],
                            'rating_count' => $ratingData['total_count'],
                        ]);
                        $this->refreshGameSearchIndex($game);
                        $updatedCount++;
                    } else {
                        // Game has no ratings - reset to null/0
                        $game->updateQuietly([
                            'rating_score' => null,
                            'rating_count' => 0,
                        ]);
                        $this->refreshGameSearchIndex($game);
                        $resetCount++;
                    }
                }
            });

        Log::info('Recalculated ratings for visible games', [
            'games_updated' => $updatedCount,
            'games_reset' => $resetCount,
            'total_processed' => $updatedCount + $resetCount,
        ]);

        return $updatedCount + $resetCount;
    }

    protected function refreshGameSearchIndex(Game $game): void
    {
        if ($game->shouldBeSearchable()) {
            $game->searchable();

            return;
        }

        $game->unsearchable();
    }
}
