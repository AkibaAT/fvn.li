<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Casts\LanguageCodeCast;

class DialogueLine extends Model
{
    protected $table = 'version_dialogue_lines';

    protected $fillable = [
        'game_version_id',
        'character_id',
        'text_id',
        'iso_code',
        'file_path',
        'line_number',
        'context',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'iso_code' => LanguageCodeCast::class,
    ];

    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'iso_code', 'id');
    }

    public function text(): BelongsTo
    {
        return $this->belongsTo(UniqueDialogueText::class, 'text_id');
    }

    /**
     * Get the text content from the referenced text record.
     */
    public function getTextContentAttribute(): ?string
    {
        return $this->text?->text_content;
    }

    /**
     * Set text content by creating or finding the appropriate unique text.
     */
    public function setTextContentAttribute(string $value): void
    {
        $textHash = md5($value);

        // Find or create the unique text entry
        $text = UniqueDialogueText::firstOrCreate(
            ['text_hash' => $textHash],
            ['text_content' => $value]
        );

        $this->attributes['text_id'] = $text->id;
    }

    /**
     * Scope query to include text content in a single query.
     */
    public function scopeWithTextContent(Builder $query): Builder
    {
        return $query->join('unique_dialogue_texts', 'version_dialogue_lines.text_id', '=', 'unique_dialogue_texts.id')
            ->addSelect([
                'version_dialogue_lines.*',
                'unique_dialogue_texts.text_content',
            ]);
    }

    /**
     * Scope query to search dialogue by text content.
     */
    public function scopeSearch(Builder $query, string $searchTerm, ?string $language = null): Builder
    {
        $langConfig = $this->getLanguageConfig($language);
        $tsvectorColumn = $this->getTsvectorColumnForLanguage($language);

        return $query->join('unique_dialogue_texts', 'version_dialogue_lines.text_id', '=', 'unique_dialogue_texts.id')
            ->whereRaw("unique_dialogue_texts.{$tsvectorColumn} @@ plainto_tsquery(?, ?)",
                [$langConfig, $searchTerm])
            ->addSelect([
                'version_dialogue_lines.*',
                'unique_dialogue_texts.text_content',
                DB::raw("ts_headline(
                    ?,
                    unique_dialogue_texts.text_content,
                    plainto_tsquery(?, ?),
                    'StartSel=<mark>, StopSel=</mark>, MaxFragments=3, MaxWords=50, MinWords=20'
                ) as highlighted_text"),
            ])
            ->setBindings(array_merge($query->getBindings(), [$langConfig, $searchTerm, $langConfig]));
    }

    /**
     * Get the PostgreSQL language configuration name.
     */
    protected function getLanguageConfig(?string $language = null): string
    {
        return match ($language) {
            'jpn' => 'japanese',
            'spa' => 'spanish',
            'fra' => 'french',
            'deu' => 'german',
            default => 'english'
        };
    }

    /**
     * Get the tsvector column name for the given language.
     */
    protected function getTsvectorColumnForLanguage(?string $language = null): string
    {
        // Map ISO language code to database column
        $languageMap = [
            'jpn' => 'japanese',
            'spa' => 'spanish',
            'fra' => 'french',
            'deu' => 'german',
        ];

        // If we have a specific language column for this ISO code, use it
        if ($language && isset($languageMap[$language])) {
            $columnName = "search_vector_{$languageMap[$language]}";

            // Check if this column actually exists
            $hasColumn = DB::selectOne(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_name = 'unique_dialogue_texts'
                 AND column_name = ?",
                [$columnName]
            );

            if ($hasColumn) {
                return $columnName;
            }
        }

        // Default to English
        return 'search_vector';
    }
}
