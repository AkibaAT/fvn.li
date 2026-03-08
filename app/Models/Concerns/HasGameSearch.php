<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait HasGameSearch
{
    /**
     * Get the trending score for this game based on recent page views.
     *
     * Uses exponential decay with a 7-day half-life so recent views count more
     * than older ones. A view from 7 days ago is worth 50% of a view today,
     * 14 days ago is worth 25%, etc.
     *
     * Formula: score = Σ(e^(-λ × age_days)) where λ = ln(2)/7 ≈ 0.099
     */
    public function getTrendingScore(): int
    {
        $result = DB::table('click_stats')
            ->where('game_id', $this->id)
            ->where('type', 'page_view')
            ->where('clicked_at', '>=', DB::raw("NOW() - INTERVAL '14 days'"))
            ->selectRaw('COALESCE(SUM(EXP(-0.099 * EXTRACT(EPOCH FROM (NOW() - clicked_at)) / 86400)), 0) as score')
            ->first();

        return (int) round((float) $result->score);
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        if (! $this->relationLoaded('tags')) {
            $this->load('tags');
        }
        if (! $this->relationLoaded('gameJams')) {
            $this->load('gameJams');
        }

        // Get latest version data for platforms and supported languages
        $latestVersion = $this->gameVersions()->where('is_latest', true)->first();
        $supportedLanguages = [];
        $englishWordCount = null;
        $primaryWordCount = null;

        if ($latestVersion) {
            // Get supported languages from the latest version
            $supportedLanguages = $latestVersion->supportedLanguages()
                ->where('is_available', true)
                ->pluck('iso_code')
                ->toArray();

            // Get English word count
            $englishStats = $latestVersion->languageStats()
                ->where('iso_code', 'eng')
                ->first();
            $englishWordCount = $englishStats?->words;

            // Get primary language word count
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
            'authors' => $this->authors ? strip_tags($this->authors) : null,

            // Descriptions for search (with URLs removed)
            'description' => $this->stripUrlsFromText($this->description),
            'full_description' => $this->full_description ? $this->stripUrlsFromText(trim(strip_tags($this->full_description))) : null,
            'custom_description' => $this->custom_description ? $this->stripUrlsFromText(trim(strip_tags($this->custom_description))) : null,
            'custom_tags' => $this->custom_tags,

            // Tags for search and filtering
            'tags' => $this->tags->pluck('name')->toArray(),

            // Game jams for search and filtering
            'game_jams' => $this->gameJams->pluck('name')->toArray(),

            // Status and visibility
            'status' => $this->status,
            'is_visible' => $this->is_visible,
            'is_delisted' => $this->is_delisted,

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

            // Store platform (where game is hosted)
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
            'trending_score' => $this->getTrendingScore(),

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

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'games';
    }

    /**
     * Determine if the model should be searchable.
     */
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
    private function stripUrlsFromText(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        // Remove URLs (http, https, www, and basic domain patterns)
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
