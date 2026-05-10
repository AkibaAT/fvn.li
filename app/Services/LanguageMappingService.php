<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Language;
use App\Models\LanguageMapping;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LanguageMappingService
{
    /**
     * Resolve a language key to an ISO code, considering game-specific overrides.
     *
     * @param  string  $gameLanguageKey  The language key from the game files
     * @param  Game|null  $game  The game object for game-specific mappings
     * @return string|null The ISO language code, or null if not found
     */
    public function resolveLanguageCode(string $gameLanguageKey, ?Game $game = null): ?string
    {
        // First try game-specific mapping if a game is provided
        if ($game !== null) {
            $mapping = LanguageMapping::where('game_language_key', 'ilike', $gameLanguageKey)
                ->where('game_id', $game->id)
                ->first();

            if ($mapping) {
                return $mapping->iso_code;
            }
        }

        // Then try global mapping
        $mapping = LanguageMapping::where('game_language_key', 'ilike', $gameLanguageKey)
            ->whereNull('game_id')
            ->first();

        if ($mapping) {
            return $mapping->iso_code;
        }

        // Try to find a matching language
        $language = Language::where('id', 'ilike', $gameLanguageKey)
            ->orWhere('part1', 'ilike', $gameLanguageKey)
            ->orWhere('part2b', 'ilike', $gameLanguageKey)
            ->orWhere('part2t', 'ilike', $gameLanguageKey)
            ->first();

        if ($language) {
            // Create global mapping for future use
            LanguageMapping::create([
                'game_id' => null,
                'game_language_key' => $gameLanguageKey,
                'iso_code' => $language->id,
            ]);

            return $language->id;
        }

        // Generate a new placeholder code in the qaa-qtz range
        $highestPlaceholder = LanguageMapping::where('iso_code', 'like', 'q%')
            ->orderBy('iso_code', 'desc')
            ->value('iso_code');

        $newCode = $highestPlaceholder
            ? $this->generateNextPlaceholderCode($highestPlaceholder)
            : 'qaa';

        // Create mapping with placeholder code (as a global mapping)
        LanguageMapping::create([
            'game_id' => null,
            'game_language_key' => $gameLanguageKey,
            'iso_code' => $newCode,
        ]);

        Log::info("Created placeholder mapping for {$gameLanguageKey}: {$newCode}");

        return $newCode;
    }

    /**
     * Generate the next placeholder language code
     */
    private function generateNextPlaceholderCode(string $current): string
    {
        $lastChar = substr($current, -1);
        if ($lastChar === 'z') {
            $middleChar = substr($current, -2, 1);
            if ($middleChar === 'z') {
                throw new RuntimeException('No more placeholder codes available');
            }

            return 'q'.chr(ord($middleChar) + 1).'a';
        }

        return substr($current, 0, -1).chr(ord($lastChar) + 1);
    }
}
