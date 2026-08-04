<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EssentialCharacterService
{
    public function getOrCreateNarratorCharacter(int $gameId): Character
    {
        $narratorCharacter = Character::where('game_id', $gameId)
            ->where('character_id', 'narrator')
            ->first();

        if ($narratorCharacter) {
            return $narratorCharacter;
        }

        $languages = $this->getGameLanguages($gameId);
        $defaultLanguage = $this->getGameDefaultLanguage($gameId);

        if (! empty($languages)) {
            $result = $this->createEssentialCharactersWithLanguages($gameId, $languages, $defaultLanguage);

            return $result['narrator'];
        } else {
            // Fallback: create basic narrator character
            Log::warning("No language data found for game {$gameId}, creating basic narrator character");

            return $this->createBasicNarratorCharacter($gameId);
        }
    }

    public function createEssentialCharactersWithLanguages(
        int $gameId,
        array $languages,
        string $defaultLanguage = 'eng'
    ): array {
        $narratorDisplayNames = [];
        $menuChoiceDisplayNames = [];

        foreach ($languages as $isoCode) {
            $narratorDisplayNames[$isoCode] = 'Narrator';
            $menuChoiceDisplayNames[$isoCode] = 'Menu Choice';
        }

        if (! in_array('eng', $languages)) {
            $narratorDisplayNames['eng'] = 'Narrator';
            $menuChoiceDisplayNames['eng'] = 'Menu Choice';
        }

        if (! in_array($defaultLanguage, $languages) && $defaultLanguage !== 'eng') {
            $narratorDisplayNames[$defaultLanguage] = 'Narrator';
            $menuChoiceDisplayNames[$defaultLanguage] = 'Menu Choice';
        }

        $narratorCharacter = $this->createOrUpdateCharacter(
            $gameId,
            'narrator',
            $narratorDisplayNames
        );

        $menuChoiceCharacter = $this->createOrUpdateCharacter(
            $gameId,
            'menu_choice',
            $menuChoiceDisplayNames
        );

        Log::info("Created/updated essential characters for game {$gameId} with languages: " . implode(', ',
            array_keys($narratorDisplayNames)));

        return [
            'narrator' => $narratorCharacter,
            'menu_choice' => $menuChoiceCharacter,
        ];
    }

    public function createBasicNarratorCharacter(int $gameId): Character
    {
        $defaultLanguage = $this->getGameDefaultLanguage($gameId);

        $displayNames = ['eng' => 'Narrator'];
        if ($defaultLanguage !== 'eng') {
            $displayNames[$defaultLanguage] = 'Narrator';
        }

        return Character::create([
            'game_id' => $gameId,
            'character_id' => 'narrator',
            'display_names' => $displayNames,
        ]);
    }

    public function getOrCreateMenuChoiceCharacter(int $gameId): Character
    {
        $menuChoiceCharacter = Character::where('game_id', $gameId)
            ->where('character_id', 'menu_choice')
            ->first();

        if ($menuChoiceCharacter) {
            return $menuChoiceCharacter;
        }

        $languages = $this->getGameLanguages($gameId);
        $defaultLanguage = $this->getGameDefaultLanguage($gameId);

        if (! empty($languages)) {
            $result = $this->createEssentialCharactersWithLanguages($gameId, $languages, $defaultLanguage);

            return $result['menu_choice'];
        } else {
            // Fallback: create basic menu_choice character
            Log::warning("No language data found for game {$gameId}, creating basic menu_choice character");

            return $this->createBasicMenuChoiceCharacter($gameId);
        }
    }

    public function createBasicMenuChoiceCharacter(int $gameId): Character
    {
        $defaultLanguage = $this->getGameDefaultLanguage($gameId);

        $displayNames = ['eng' => 'Menu Choice'];
        if ($defaultLanguage !== 'eng') {
            $displayNames[$defaultLanguage] = 'Menu Choice';
        }

        return Character::create([
            'game_id' => $gameId,
            'character_id' => 'menu_choice',
            'display_names' => $displayNames,
        ]);
    }

    private function getGameLanguages(int $gameId): array
    {
        $languages = DB::table('version_supported_languages as vsl')
            ->join('game_versions as gv', 'vsl.game_version_id', '=', 'gv.id')
            ->where('gv.game_id', $gameId)
            ->orderBy('gv.published_at', 'desc')
            ->limit(20) // Get from recent versions
            ->pluck('vsl.iso_code')
            ->unique()
            ->values()
            ->toArray();

        return $languages;
    }

    private function getGameDefaultLanguage(int $gameId): string
    {
        $game = Game::find($gameId);

        return $game?->source_language_id ?? 'eng';
    }

    private function createOrUpdateCharacter(int $gameId, string $characterId, array $displayNames): Character
    {
        $character = Character::firstOrNew([
            'game_id' => $gameId,
            'character_id' => $characterId,
        ]);

        if ($character->exists) {
            // Merge with existing display_names, but don't overwrite existing values
            $existingNames = $character->display_names ?? [];
            // Only add new languages that don't already exist
            foreach ($displayNames as $lang => $name) {
                if (! isset($existingNames[$lang])) {
                    $existingNames[$lang] = $name;
                }
            }
            $character->display_names = $existingNames;
        } else {
            $character->display_names = $displayNames;
        }

        $character->save();

        return $character;
    }
}
