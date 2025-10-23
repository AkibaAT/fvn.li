<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class UniqueDialogueText extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'text_hash',
        'text_content',
    ];

    /**
     * Attributes that should be appended to the model's array form.
     * These are populated from Meilisearch metadata when available.
     */
    protected $appends = [];

    /**
     * Temporary storage for Meilisearch metadata.
     */
    public ?array $searchMetadata = null;

    /**
     * Get all dialogue lines using this text.
     */
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

    /**
     * Generate highlighted search results for a given search term.
     */
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

    /**
     * Get the tsvector column name for the given language.
     */
    protected function getTsvectorColumnForLanguage(?string $language = null): string
    {
        if ($language && in_array($language, ['japanese', 'spanish', 'french', 'german'])) {
            return "search_vector_{$language}";
        }

        return 'search_vector';
    }

    /**
     * Get the PostgreSQL language configuration name.
     */
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

    /**
     * Get the indexable data array for Meilisearch.
     * Aggregates all occurrences of this text across games/versions/characters/languages.
     * Uses efficient SQL aggregation instead of loading all relationships.
     */
    public function toSearchableArray(): array
    {
        // Use a single efficient query to aggregate all metadata
        $aggregated = DB::selectOne("
            SELECT
                COUNT(DISTINCT gv.game_id) as games_count,
                COUNT(vdl.id) as usage_count,
                ARRAY_AGG(DISTINCT gv.game_id) as game_ids,
                ARRAY_AGG(DISTINCT g.name) as game_names,
                ARRAY_AGG(DISTINCT vdl.game_version_id) as version_ids,
                ARRAY_AGG(DISTINCT vdl.character_id) as character_ids,
                ARRAY_AGG(DISTINCT vdl.iso_code) as languages
            FROM version_dialogue_lines vdl
            JOIN game_versions gv ON vdl.game_version_id = gv.id
            JOIN games g ON gv.game_id = g.id
            WHERE vdl.text_id = ?
        ", [$this->id]);

        // Get unique character names (requires separate query due to JSONB)
        $characterNames = DB::table('version_dialogue_lines as vdl')
            ->join('characters as c', 'vdl.character_id', '=', 'c.id')
            ->where('vdl.text_id', $this->id)
            ->distinct()
            ->pluck('c.display_names')
            ->flatMap(function ($displayNames) {
                if (is_string($displayNames)) {
                    $displayNames = json_decode($displayNames, true);
                }
                if (is_array($displayNames) && !empty($displayNames)) {
                    return [reset($displayNames)];
                }
                return [];
            })
            ->unique()
            ->values()
            ->toArray();

        // Parse PostgreSQL arrays
        $parseArray = function ($pgArray) {
            if (empty($pgArray) || $pgArray === '{}') {
                return [];
            }
            // Remove curly braces and split by comma
            $cleaned = trim($pgArray, '{}');
            if (empty($cleaned)) {
                return [];
            }
            return array_values(array_unique(array_map('intval', explode(',', $cleaned))));
        };

        $parseStringArray = function ($pgArray) {
            if (empty($pgArray) || $pgArray === '{}') {
                return [];
            }
            // Remove curly braces and split by comma, handling quoted strings
            $cleaned = trim($pgArray, '{}');
            if (empty($cleaned)) {
                return [];
            }
            // Simple parsing - may need improvement for complex strings
            return array_values(array_unique(array_map(function ($s) {
                return trim($s, '"');
            }, explode(',', $cleaned))));
        };

        return [
            'id' => $this->id,
            'text_content' => $this->text_content,
            'game_ids' => $parseArray($aggregated->game_ids ?? '{}'),
            'game_names' => $parseStringArray($aggregated->game_names ?? '{}'),
            'version_ids' => $parseArray($aggregated->version_ids ?? '{}'),
            'character_ids' => $parseArray($aggregated->character_ids ?? '{}'),
            'character_names' => $characterNames,
            'languages' => $parseStringArray($aggregated->languages ?? '{}'),
            'usage_count' => (int) ($aggregated->usage_count ?? 0),
            'games_count' => (int) ($aggregated->games_count ?? 0),
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'dialogue_texts';
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Only index texts that have actual content and are used in at least one dialogue line
        return !empty(trim($this->text_content ?? '')) && $this->dialogueLines()->exists();
    }

    /**
     * Get metadata from Meilisearch results or return null.
     * These accessors allow the controller to access metadata that was stored in Meilisearch.
     */
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
}
