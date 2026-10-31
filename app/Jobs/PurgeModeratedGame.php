<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Services\GameModerationPurger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurgeModeratedGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 24;

    public int $backoff = 3600;

    public function __construct(public int $gameId)
    {
        $this->onQueue('default');
    }

    public function handle(GameModerationPurger $purger): void
    {
        $game = Game::find($this->gameId);

        if ($game?->is_moderation_hidden) {
            $purger->purge($game);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('PurgeModeratedGame job failed', [
            'game_id' => $this->gameId,
            'error' => $exception->getMessage(),
        ]);
    }
}
