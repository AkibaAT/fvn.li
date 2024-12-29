<?php

declare(strict_types=1);

namespace App\Observers;

use App\Livewire\GameList;
use App\Models\Game;

class GameObserver
{
    public function created(Game $game): void
    {
        GameList::clearFilterCache();
    }

    public function updated(Game $game): void
    {
        if ($game->isDirty(['status', 'game_engine', 'is_visible'])) {
            GameList::clearFilterCache();
        }
    }

    public function deleted(Game $game): void
    {
        GameList::clearFilterCache();
    }
}
