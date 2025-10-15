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
