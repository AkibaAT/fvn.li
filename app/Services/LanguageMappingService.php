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
    public static function isPlaceholderLanguageCode(string $isoCode): bool
    {
        return $isoCode >= 'qaa' && $isoCode <= 'qtz';
    }

    /**
     * Resolve a language key to an ISO code, considering game-specific overrides.
     *
     * @param  string  $gameLanguageKey  The language key from the game files
     * @param  Game|null  $game  The game object for game-specific mappings
     * @return string|null The ISO language code, or null if not found
     */
    public function resolveLanguageCode(string $gameLanguageKey, ?Game $game = null): ?string
    {
        if ($game !== null) {
            $mapping = LanguageMapping::where('game_language_key', 'ilike', $gameLanguageKey)
                ->where('game_id', $game->id)
                ->first();

            if ($mapping) {
                return $mapping->iso_code;
            }
        }

        $mapping = LanguageMapping::where('game_language_key', 'ilike', $gameLanguageKey)
            ->whereNull('game_id')
            ->first();

        if ($mapping) {
            return $mapping->iso_code;
        }

        $language = Language::where('id', 'ilike', $gameLanguageKey)
            ->orWhere('part1', 'ilike', $gameLanguageKey)
            ->orWhere('part2b', 'ilike', $gameLanguageKey)
            ->orWhere('part2t', 'ilike', $gameLanguageKey)
            ->first();

        if ($language) {
            LanguageMapping::create([
                'game_id' => null,
                'game_language_key' => $gameLanguageKey,
                'iso_code' => $language->id,
            ]);

            return $language->id;
        }

        $highestPlaceholder = LanguageMapping::whereBetween('iso_code', ['qaa', 'qtz'])
            ->orderBy('iso_code', 'desc')
            ->value('iso_code');

        $newCode = $highestPlaceholder
            ? $this->generateNextPlaceholderCode($highestPlaceholder)
            : 'qaa';

        LanguageMapping::create([
            'game_id' => null,
            'game_language_key' => $gameLanguageKey,
            'iso_code' => $newCode,
        ]);

        Log::info("Created placeholder mapping for {$gameLanguageKey}: {$newCode}");

        return $newCode;
    }

    private function generateNextPlaceholderCode(string $current): string
    {
        if ($current >= 'qtz') {
            throw new RuntimeException('No more placeholder codes available');
        }

        $lastChar = substr($current, -1);
        if ($lastChar === 'z') {
            $middleChar = substr($current, -2, 1);

            return 'q' . chr(ord($middleChar) + 1) . 'a';
        }

        return substr($current, 0, -1) . chr(ord($lastChar) + 1);
    }
}
