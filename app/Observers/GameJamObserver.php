<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GameJam;
use App\Services\GameFilterService;
use App\Services\ObserverSearchIndexService;

class GameJamObserver
{
    public function saved(GameJam $gameJam): void
    {
        GameFilterService::clearCache();

        if ($gameJam->wasChanged('name')) {
            app(ObserverSearchIndexService::class)->reindexGames(
                $gameJam->games(),
                'game jam change',
                ['game_jam_id' => $gameJam->id]
            );
        }
    }

    public function deleted(GameJam $gameJam): void
    {
        GameFilterService::clearCache();
    }
}
