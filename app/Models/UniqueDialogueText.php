<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class UniqueDialogueText extends Model
{
    use HasFactory;

    /**
     * Temporary storage for Meilisearch metadata.
     */
    public ?array $searchMetadata = null;

    protected $fillable = [
        'text_hash',
        'text_content',
    ];

    /**
     * Attributes that should be appended to the model's array form.
     * These are populated from Meilisearch metadata when available.
     */
    protected $appends = [];

    public function dialogueLines(): HasMany
    {
        return $this->hasMany(DialogueLine::class, 'text_id');
    }

    /**
     * Scope a query to search for text content.
     */
    public function scopeSearch($query, string $searchTerm, ?string $language = null)
    {
        $tsvectorColumn = $this->getTsvectorColumnForLanguage($language);

        return $query->whereRaw(
            "{$tsvectorColumn} @@ plainto_tsquery(?, ?)",
            [$this->getLanguageConfig($language), $searchTerm]
        );
    }

    public function getHighlightedText(string $searchTerm, ?string $language = null): string
    {
        $langConfig = $this->getLanguageConfig($language);

        $highlighted = DB::selectOne(
            "SELECT ts_headline(
                ?,
                ?,
                plainto_tsquery(?, ?),
                'StartSel=<mark>, StopSel=</mark>, MaxFragments=3, MaxWords=50, MinWords=20'
            ) as highlighted",
            [$langConfig, $this->text_content, $langConfig, $searchTerm]
        );

        return $highlighted->highlighted;
    }

    // Meilisearch indexing removed - we only index DialogueLine, not UniqueDialogueText

    public function getUsageCountAttribute($value)
    {
        return $this->searchMetadata['usage_count'] ?? $value ?? null;
    }

    public function getGamesCountAttribute($value)
    {
        return $this->searchMetadata['games_count'] ?? $value ?? null;
    }

    public function getGameIdsAttribute($value)
    {
        return $this->searchMetadata['game_ids'] ?? $value ?? null;
    }

    public function getGameNamesAttribute($value)
    {
        return $this->searchMetadata['game_names'] ?? $value ?? null;
    }

    public function getVersionIdsAttribute($value)
    {
        return $this->searchMetadata['version_ids'] ?? $value ?? null;
    }

    public function getCharacterIdsAttribute($value)
    {
        return $this->searchMetadata['character_ids'] ?? $value ?? null;
    }

    public function getCharacterNamesAttribute($value)
    {
        return $this->searchMetadata['character_names'] ?? $value ?? null;
    }

    public function getLanguagesAttribute($value)
    {
        return $this->searchMetadata['languages'] ?? $value ?? null;
    }

    protected function getTsvectorColumnForLanguage(?string $language = null): string
    {
        if ($language && in_array($language, ['japanese', 'spanish', 'french', 'german'])) {
            return "search_vector_{$language}";
        }

        return 'search_vector';
    }

    protected function getLanguageConfig(?string $language = null): string
    {
        return match ($language) {
            'japanese' => 'japanese',
            'spanish' => 'spanish',
            'french' => 'french',
            'german' => 'german',
            default => 'english'
        };
    }
}
