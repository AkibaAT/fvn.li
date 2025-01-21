<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = [
        'game_id',
        'character_id',
        'display_names',
        'first_seen_in_version_id',
        'last_seen_in_version_id',
    ];

    protected $casts = [
        'display_names' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function firstSeenVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'first_seen_in_version_id');
    }

    public function lastSeenVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'last_seen_in_version_id');
    }

    public function versionStats(): HasMany
    {
        return $this->hasMany(VersionCharacterStats::class);
    }

    public function getDisplayName(string $isoCode): ?string
    {
        return $this->display_names[$isoCode] ?? null;
    }

    public static function countUniqueCharactersInLanguage(int $gameId, ?string $languageCode = null): int
    {
        $characters = self::where('game_id', $gameId)
            ->get()
            ->filter(function ($character) use ($languageCode) {
                if (!$languageCode) return true;
                return $character->getDisplayName($languageCode) !== null;
            })
            ->map(function ($character) use ($languageCode) {
                return $languageCode ? $character->getDisplayName($languageCode) : $character->character_id;
            })
            ->unique()
            ->values();

        return $characters->count();
    }
}
