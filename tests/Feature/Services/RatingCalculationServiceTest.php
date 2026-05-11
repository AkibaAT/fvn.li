<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\RatingCalculationService;

function ratingCalculationServiceCapturingReindexes(array &$reindexedGames): RatingCalculationService
{
    return new class($reindexedGames) extends RatingCalculationService
    {
        public function __construct(private array &$reindexedGames) {}

        protected function refreshGameSearchIndex(Game $game): void
        {
            $this->reindexedGames[] = [
                'id' => $game->id,
                'rating_score' => $game->rating_score,
                'rating_count' => $game->rating_count,
            ];
        }
    };
}

it('refreshes the game search document after quiet rating updates', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'rating_score' => null,
        'rating_count' => 0,
    ]);
    $rater = Rater::factory()->create();

    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 4,
        'review' => 'Good rating.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $reindexedGames = [];
    ratingCalculationServiceCapturingReindexes($reindexedGames)->updateGameRating($game);

    expect($game->fresh()->rating_score)->toBe(4.0)
        ->and($game->fresh()->rating_count)->toBe(1)
        ->and($reindexedGames)->toBe([[
            'id' => $game->id,
            'rating_score' => 4.0,
            'rating_count' => 1,
        ]]);
});

it('refreshes game search documents during bulk rating recalculation', function () {
    $ratedGame = Game::factory()->create([
        'is_visible' => true,
        'rating_score' => null,
        'rating_count' => 0,
    ]);
    $unratedGame = Game::factory()->create([
        'is_visible' => true,
        'rating_score' => 5.0,
        'rating_count' => 8,
    ]);
    $hiddenGame = Game::factory()->create([
        'is_visible' => false,
        'rating_score' => 3.0,
        'rating_count' => 2,
    ]);
    $rater = Rater::factory()->create();

    Rating::create([
        'game_id' => $ratedGame->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => 'Great rating.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $reindexedGames = [];
    $updatedCount = ratingCalculationServiceCapturingReindexes($reindexedGames)->recalculateAllGameRatings();

    expect($updatedCount)->toBe(2)
        ->and($ratedGame->fresh()->rating_score)->toBe(5.0)
        ->and($ratedGame->fresh()->rating_count)->toBe(1)
        ->and($unratedGame->fresh()->rating_score)->toBeNull()
        ->and($unratedGame->fresh()->rating_count)->toBe(0)
        ->and($hiddenGame->fresh()->rating_score)->toBe(3.0)
        ->and($reindexedGames)->toBe([
            [
                'id' => $ratedGame->id,
                'rating_score' => 5.0,
                'rating_count' => 1,
            ],
            [
                'id' => $unratedGame->id,
                'rating_score' => null,
                'rating_count' => 0,
            ],
        ]);
});
