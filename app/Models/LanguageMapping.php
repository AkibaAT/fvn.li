<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanguageMapping extends Model
{
    protected $fillable = [
        'game_id',
        'game_language_key',
        'iso_code',
    ];

    /**
     * Get ISO code for a game language key.
     *
     * This method will first try to find a game-specific mapping,
     * and if none exists, it will fall back to the global mapping.
     */
    public static function getIsoCodeForKey(string $key, ?int $gameId = null): ?string
    {
        // Try to find a game-specific mapping if game ID is provided
        if ($gameId !== null) {
            $mapping = self::where('game_language_key', $key)
                ->where('game_id', $gameId)
                ->first();

            if ($mapping) {
                return $mapping->iso_code;
            }
        }

        // Fall back to global mapping
        $mapping = self::where('game_language_key', $key)
            ->whereNull('game_id')
            ->first();

        return $mapping?->iso_code;
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
