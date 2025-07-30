<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Services\RatingCalculationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateGameRating implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $gameId
    ) {
        // Set queue name for rating updates
        $this->onQueue('default');
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
