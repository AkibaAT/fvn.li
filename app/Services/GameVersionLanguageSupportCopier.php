<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;

class GameVersionLanguageSupportCopier
{
    public function copy(Game $game, GameVersion $gameVersion): void
    {
        $previousVersion = $game->gameVersions()
            ->where('id', '!=', $gameVersion->id)
            ->whereHas('supportedLanguages')
            ->orderBy('published_at', 'desc')
            ->first();

        if ($previousVersion) {
            foreach ($previousVersion->supportedLanguages as $supported) {
                $gameVersion->addSupportedLanguage($supported->iso_code, $supported->is_available);
            }

            return;
        }

        $gameVersion->addSupportedLanguage($game->source_language_id ?: 'eng');
    }
}
