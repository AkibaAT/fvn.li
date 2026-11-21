<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncGameSearchIndex implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public int $uniqueFor = 15;

    public function __construct(
        public int $gameId,
        public bool $remove = false,
        public string $context = 'updated',
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return "{$this->gameId}:" . ($this->remove ? 'remove' : 'index');
    }

    public function handle(): void
    {
        if ($this->remove) {
            $game = Game::find($this->gameId);

            if (! $game || $game->is_visible) {
                return;
            }

            $game->unsearchable();
            Log::info('Removed game from search index', [
                'game_id' => $this->gameId,
                'game_name' => $game->name,
            ]);

            return;
        }

        $game = Game::with(['tags', 'gameJams', 'gameVersions'])->find($this->gameId);

        if (! $game || ! $game->is_visible) {
            return;
        }

        $game->searchable();
        Log::info($this->context === 'created'
            ? 'Added newly created visible game to search index'
            : 'Added game to search index', [
                'game_id' => $this->gameId,
                'game_name' => $game->name,
            ]);
    }
}
