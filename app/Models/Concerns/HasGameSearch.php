<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ClickStat;

trait HasGameSearch
{
    public function getTrendingScore(): int
    {
        return (int) ClickStat::trendingScores([$this->id])->get($this->id, 0);
    }

    public function toSearchableArray(): array
    {
        if (! $this->relationLoaded('tags')) {
            $this->load('tags');
        }
        if (! $this->relationLoaded('gameJams')) {
            $this->load('gameJams');
        }

        $latestVersion = $this->gameVersions()->where('is_latest', true)->first();
        $supportedLanguages = [];
        $englishWordCount = null;
        $primaryWordCount = null;

        if ($latestVersion) {
            $supportedLanguages = $latestVersion->supportedLanguages()
                ->where('is_available', true)
                ->pluck('iso_code')
                ->toArray();

            $englishStats = $latestVersion->languageStats()
                ->where('iso_code', 'eng')
                ->first();
            $englishWordCount = $englishStats?->words;

            $sourceLanguageId = $this->source_language_id ?? 'eng';
            if ($sourceLanguageId !== 'eng') {
                $primaryStats = $latestVersion->languageStats()
                    ->where('iso_code', $sourceLanguageId)
                    ->first();
                $primaryWordCount = $primaryStats?->words;
            } else {
                $primaryWordCount = $englishWordCount;
            }
        }

        return [
            // Basic identifiers
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'authors' => $this->toPlainText($this->authors),

            // Descriptions for search (with URLs removed)
            'description' => $this->stripUrlsFromText($this->toPlainText($this->description)),
            'full_description' => $this->stripUrlsFromText($this->toPlainText($this->full_description)),
            'custom_description' => $this->stripUrlsFromText($this->toPlainText($this->custom_description)),
            'custom_tags' => $this->custom_tags,

            // Tags for search and filtering
            'tags' => $this->tags->pluck('name')->toArray(),

            // Game jams for search and filtering
            'game_jams' => $this->gameJams->pluck('name')->toArray(),

            // Status and visibility
            'status' => $this->status,
            'is_visible' => $this->is_visible,
            'is_delisted' => $this->is_delisted,
            'is_stats_extraction_disabled' => $this->is_stats_extraction_disabled,

            // Content flags
            'is_nsfw' => $this->is_nsfw,

            // Pricing and availability
            'is_paid' => $this->is_paid,
            'has_demo' => $this->has_demo,
            'min_price' => $this->min_price,
            'currency' => $this->currency,
            'is_on_sale' => $this->is_on_sale,
            'sale_discount_percent' => $this->sale_discount_percent,

            // Technical details
            'game_engine' => $this->game_engine,
            'supported_languages' => $supportedLanguages,
            'english_word_count' => $englishWordCount,
            'primary_word_count' => $primaryWordCount,
            'source_language_id' => $this->source_language_id,

            'platform' => $this->platform,

            // Platform support (from latest version - where game runs)
            'latest_version_id' => $latestVersion ? $latestVersion->id : null,
            'is_windows' => $latestVersion ? $latestVersion->is_windows : false,
            'is_linux' => $latestVersion ? $latestVersion->is_linux : false,
            'is_mac' => $latestVersion ? $latestVersion->is_mac : false,
            'is_android' => $latestVersion ? $latestVersion->is_android : false,
            'is_web' => $latestVersion ? $latestVersion->is_web : false,

            // Ratings and popularity
            'rating_score' => $this->rating_score,
            'rating_count' => $this->rating_count,
            'trending_score' => (int) ($this->trending_score ?: $this->getTrendingScore()),

            // Dates for sorting and display
            'created_at' => $this->created_at?->toISOString(),
            'initially_published_at' => $this->initially_published_at?->toISOString(),
            'latest_version_published_at' => $latestVersion?->published_at?->toISOString(),
            'first_visible_at' => $this->first_visible_at?->toISOString(),

            // Custom page features
            'has_custom_page' => $this->has_custom_page,
            'custom_page_updated_at' => $this->custom_page_updated_at?->toISOString(),
        ];
    }

    public function searchableAs(): string
    {
        return 'games';
    }

    public function shouldBeSearchable(): bool
    {
        // Only index visible games with names
        return $this->is_visible && ! empty(trim($this->name));
    }

    /**
     * Strip URLs from text for search indexing.
     *
     * Removes HTTP/HTTPS URLs to prevent them from polluting search results.
     */
    /**
     * Reduce stored markup to the words a search engine should see.
     *
     * Entities outlive tag stripping, and a decoded &nbsp; is whitespace that
     * \s does not match, so both are resolved into ordinary spaces here.
     */
    private function toPlainText(?string $html): ?string
    {
        if (empty($html)) {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    private function stripUrlsFromText(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        $patterns = [
            // Full URLs with protocol
            '/https?:\/\/[^\s\]]+/i',
            // www.domain.com patterns
            '/www\.[^\s\]]+/i',
            // Basic domain.com patterns (be careful not to remove legitimate words)
            '/\b[a-zA-Z0-9-]+\.[a-zA-Z]{2,}(?:\/[^\s\]]*)?/i',
        ];

        $cleanText = $text;
        foreach ($patterns as $pattern) {
            $cleanText = preg_replace($pattern, '', $cleanText);
        }

        // Clean up extra whitespace left by URL removal
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        return trim($cleanText);
    }
}
