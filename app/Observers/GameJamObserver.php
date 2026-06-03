<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GameJam;
use App\Services\GameFilterService;

class GameJamObserver
{
    public function saved(GameJam $gameJam): void
    {
        GameFilterService::clearCache();

        if ($gameJam->wasChanged('name')) {
            $gameJam->games()
                ->where('is_visible', true)
                ->chunk(100, function ($games) {
                    $games->searchable();
                });
        }
    }

    public function deleted(GameJam $gameJam): void
    {
        GameFilterService::clearCache();
    }
}
