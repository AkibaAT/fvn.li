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

    public static function getIsoCodeForKey(string $key, ?int $gameId = null): ?string
    {
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
