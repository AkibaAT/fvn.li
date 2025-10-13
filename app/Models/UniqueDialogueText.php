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
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        if (! $this->relationLoaded('dialogueLines')) {
            $this->load(['dialogueLines.character', 'dialogueLines.gameVersion.game']);
        }

        // Extract character data
        $characterNames = $this->dialogueLines
            ->pluck('character.display_names')
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        $characterIds = $this->dialogueLines
            ->pluck('character.character_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Extract game data
        $gameNames = $this->dialogueLines
            ->pluck('gameVersion.game.name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $gameIds = $this->dialogueLines
            ->pluck('gameVersion.game.id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $gameSlugs = $this->dialogueLines
            ->pluck('gameVersion.game.slug')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Extract version data
        $versionIds = $this->dialogueLines
            ->pluck('game_version_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Extract language data
        $languages = $this->dialogueLines
            ->pluck('iso_code')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Extract context data
        $contexts = $this->dialogueLines
            ->pluck('context')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $filePaths = $this->dialogueLines
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
