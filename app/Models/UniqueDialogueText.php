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
     * Get all dialogue lines using this text.
     */
    public function dialogueLines(): HasMany
    {
        return $this->hasMany(DialogueLine::class, 'text_id');
    }

    /**
     * Get the indexable data array for the model.
     * Optimized to use raw queries instead of loading full Eloquent relationships.
     */
    public function toSearchableArray(): array
    {
        // Use a single optimized query to get all related data
        // This is much more memory efficient than loading nested Eloquent models
        $relatedData = DB::table('version_dialogue_lines as dl')
            ->select(
                'c.display_names as character_names',
                'c.character_id',
                'g.name as game_name',
                'g.id as game_id',
                'g.slug as game_slug',
                'dl.game_version_id',
                'dl.iso_code',
                'dl.context',
                'dl.file_path'
            )
            ->leftJoin('characters as c', 'dl.character_id', '=', 'c.id')
            ->leftJoin('game_versions as gv', 'dl.game_version_id', '=', 'gv.id')
            ->leftJoin('games as g', 'gv.game_id', '=', 'g.id')
            ->where('dl.text_id', $this->id)
            ->get();

        // Extract and deduplicate data
        $characterNames = $relatedData
            ->pluck('character_names')
            ->filter()
            ->map(fn($names) => is_array($names) ? $names : json_decode($names, true))
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        $characterIds = $relatedData
            ->pluck('character_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $gameNames = $relatedData
            ->pluck('game_name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $gameIds = $relatedData
            ->pluck('game_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $gameSlugs = $relatedData
            ->pluck('game_slug')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $versionIds = $relatedData
            ->pluck('game_version_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $languages = $relatedData
            ->pluck('iso_code')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $contexts = $relatedData
            ->pluck('context')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $filePaths = $relatedData
            ->pluck('file_path')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return [
            'id' => $this->id,
            'text_content' => $this->text_content,
            'text_hash' => $this->text_hash,

            // Character data
            'character_names' => $characterNames,
            'character_ids' => $characterIds,

            // Game data
            'game_names' => $gameNames,
            'game_ids' => $gameIds,
            'game_slugs' => $gameSlugs,

            // Version data
            'version_ids' => $versionIds,

            // Language and context
            'languages' => $languages,
            'contexts' => $contexts,
            'file_paths' => $filePaths,

            'created_at' => $this->created_at?->timestamp,
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
        // Only index texts that have actual content
        return ! empty(trim($this->text_content));
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
}
