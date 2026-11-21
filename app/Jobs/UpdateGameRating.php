<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Services\RatingCalculationService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateGameRating implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public int $timeout = 60;

    public int $uniqueFor = 30;

    public function __construct(
        public int $gameId
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->gameId;
    }

    public function handle(RatingCalculationService $ratingService): void
    {
        $game = Game::find($this->gameId);

        if (! $game) {
            Log::warning('Game not found for rating update', ['game_id' => $this->gameId]);

            return;
        }

        try {
            $ratingService->updateGameRating($game);

            Log::info('Successfully updated game rating via queue', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'rating_score' => $game->rating_score,
                'rating_count' => $game->rating_count,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update game rating via queue', [
                'game_id' => $this->gameId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('UpdateGameRating job failed', [
            'game_id' => $this->gameId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
