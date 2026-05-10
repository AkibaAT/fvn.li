<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EssentialCharacterService
{
    /**
     * Get or create narrator character with fallback language support
     * This method is used by fix commands and other services that don't have full language context
     */
    public function getOrCreateNarratorCharacter(int $gameId): Character
    {
        $narratorCharacter = Character::where('game_id', $gameId)
            ->where('character_id', 'narrator')
            ->first();

        if ($narratorCharacter) {
            return $narratorCharacter;
        }

        // Create narrator with best available language information
        $languages = $this->getGameLanguages($gameId);
        $defaultLanguage = $this->getGameDefaultLanguage($gameId);

        if (! empty($languages)) {
            // Use comprehensive language support if we have language data
            $result = $this->createEssentialCharactersWithLanguages($gameId, $languages, $defaultLanguage);

            return $result['narrator'];
        } else {
            // Fallback: create basic narrator character
            Log::warning("No language data found for game {$gameId}, creating basic narrator character");

            return $this->createBasicNarratorCharacter($gameId);
        }
    }

    /**
     * Create or update essential characters (narrator, menu_choice) with comprehensive language support
     * This is the primary method that should be used during stats import when all languages are known
     */
    public function createEssentialCharactersWithLanguages(
        int $gameId,
        array $languages,
        string $defaultLanguage = 'eng'
    ): array {
        // Build display_names for all languages
        $narratorDisplayNames = [];
        $menuChoiceDisplayNames = [];

        foreach ($languages as $isoCode) {
            $narratorDisplayNames[$isoCode] = 'Narrator';
            $menuChoiceDisplayNames[$isoCode] = 'Menu Choice';
        }

        // Ensure English is always included
        if (! in_array('eng', $languages)) {
            $narratorDisplayNames['eng'] = 'Narrator';
            $menuChoiceDisplayNames['eng'] = 'Menu Choice';
        }

        // Ensure default language is always included
        if (! in_array($defaultLanguage, $languages) && $defaultLanguage !== 'eng') {
            $narratorDisplayNames[$defaultLanguage] = 'Narrator';
            $menuChoiceDisplayNames[$defaultLanguage] = 'Menu Choice';
        }

        // Create or update narrator character
        $narratorCharacter = $this->createOrUpdateCharacter(
            $gameId,
            'narrator',
            $narratorDisplayNames
        );

        // Create or update menu_choice character
        $menuChoiceCharacter = $this->createOrUpdateCharacter(
            $gameId,
            'menu_choice',
            $menuChoiceDisplayNames
        );

        Log::info("Created/updated essential characters for game {$gameId} with languages: ".implode(', ',
            array_keys($narratorDisplayNames)));

        return [
            'narrator' => $narratorCharacter,
            'menu_choice' => $menuChoiceCharacter,
        ];
    }

    /**
     * Create basic narrator character with minimal language support (fallback)
     */
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

    /**
     * Get or create menu_choice character with fallback language support
     */
    public function getOrCreateMenuChoiceCharacter(int $gameId): Character
    {
        $menuChoiceCharacter = Character::where('game_id', $gameId)
            ->where('character_id', 'menu_choice')
            ->first();

        if ($menuChoiceCharacter) {
            return $menuChoiceCharacter;
        }

        // Create menu_choice with best available language information
        $languages = $this->getGameLanguages($gameId);
        $defaultLanguage = $this->getGameDefaultLanguage($gameId);

        if (! empty($languages)) {
            // Use comprehensive language support if we have language data
            $result = $this->createEssentialCharactersWithLanguages($gameId, $languages, $defaultLanguage);

            return $result['menu_choice'];
        } else {
            // Fallback: create basic menu_choice character
            Log::warning("No language data found for game {$gameId}, creating basic menu_choice character");

            return $this->createBasicMenuChoiceCharacter($gameId);
        }
    }

    /**
     * Create basic menu_choice character with minimal language support (fallback)
     */
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

    /**
     * Get all languages for a game from existing version data
     */
    private function getGameLanguages(int $gameId): array
    {
        // Get languages from the most recent version's supported languages
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

    /**
     * Get the default language for a game
     */
    private function getGameDefaultLanguage(int $gameId): string
    {
        $game = Game::find($gameId);

        return $game?->source_language_id ?? 'eng';
    }

    /**
     * Create or update a character with the given display names
     */
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
