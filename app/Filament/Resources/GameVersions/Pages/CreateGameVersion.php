<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersions\Pages;

use App\Filament\Resources\GameVersions\GameVersionResource;
use App\Filament\Resources\GameVersions\Traits\HandlesGameVersionLanguages;
use App\Models\Game;
use App\Models\GameVersion;
use Filament\Resources\Pages\CreateRecord;

class CreateGameVersion extends CreateRecord
{
    use HandlesGameVersionLanguages;

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

    protected function afterCreate(): void
    {
        /** @var GameVersion $gameVersion */
        $gameVersion = $this->record;

        if (isset($this->data['supported_languages']) && is_array($this->data['supported_languages'])) {
            $this->saveSupportedLanguages($gameVersion, $this->data['supported_languages']);
        }
    }
}
