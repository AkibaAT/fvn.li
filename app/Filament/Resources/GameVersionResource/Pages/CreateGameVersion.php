<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\Pages;

use App\Filament\Resources\GameVersionResource;
use App\Models\Game;
use Filament\Resources\Pages\CreateRecord;

class CreateGameVersion extends CreateRecord
{
    protected static string $resource = GameVersionResource::class;

    /**
     * Redirect back to the game view after creating the version
     */
    protected function getRedirectUrl(): string
    {
        // If we came from a specific game, redirect back to that game
        if (request()->has('game_id')) {
            $gameId = request()->input('game_id');
            $game = Game::find($gameId);

            if ($game) {
                return route('filament.admin.resources.games.view', ['record' => $game]);
            }
        }

        return $this->getResource()::getUrl('index');
    }
}
