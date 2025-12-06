<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'character_id',
        'display_names',
        'first_seen_in_version_id',
        'last_seen_in_version_id',
        'gender',
        'species',
        'age',
    ];

    protected $casts = [
        'display_names' => 'array',
        'display_name_corrections' => 'array',
    ];

    public static function countUniqueCharactersInLanguage(
        int $gameId,
        ?string $languageCode = null,
        ?int $versionId = null
    ): int {
        // Get characters for the specific version, excluding narrator, menu_choice, and alt
        $characters = self::query()
            ->join('version_character_stats', 'characters.id', '=', 'version_character_stats.character_id')
            ->where('characters.game_id', $gameId)
            ->where('characters.character_id', '!=', 'narrator')
            ->where('characters.character_id', '!=', 'menu_choice')
            ->where('characters.character_id', '!=', 'alt')
            ->when($versionId, function ($query) use ($versionId) {
                $query->where('version_character_stats.game_version_id', $versionId);
            })
            ->when($languageCode, function ($query) use ($languageCode) {
                $query->where('version_character_stats.iso_code', $languageCode);
            })
            ->distinct('characters.character_id')
            ->select('characters.id', 'characters.character_id', 'characters.display_names',
                'characters.display_name_corrections')
            ->get();

        // Use the provided language code for display names, fallback to English if none provided
        $displayLanguageCode = $languageCode ?? 'eng';

        // Extract display names in the specified language and count unique ones
        $displayNames = $characters->map(function ($character) use ($displayLanguageCode) {
            return $character->getDisplayName($displayLanguageCode) ?? $character->character_id;
        })->unique()->values();

        return $displayNames->count();
    }

    public function getDisplayName(string $isoCode): ?string
    {
        // First check for a manual correction
        if (isset($this->display_name_corrections[$isoCode])) {
            return $this->display_name_corrections[$isoCode];
        }

        // Fall back to the original display name
        return $this->display_names[$isoCode] ?? null;
    }

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

    public function dialogueLines(): HasMany
    {
        return $this->hasMany(DialogueLine::class);
    }
}
