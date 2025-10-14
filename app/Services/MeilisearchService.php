<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Rating;
use App\Models\Tag;
use App\Models\UniqueDialogueText;
use Illuminate\Pagination\LengthAwarePaginator;

class MeilisearchService
{
    /**
     * Search for games with filters and pagination.
     */
    public function searchGames(
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1,
        string $sortField = 'first_visible_at',
        string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        // Try exact search first, then fuzzy search if needed
        return $this->performTieredSearch($query, $filters, $perPage, $page, $sortField, $sortDirection);
    }

    /**
     * Search for dialogue texts with filters and pagination.
     */
    public function searchDialogue(
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        $processedQuery = $this->processSearchQuery($query);
        $search = UniqueDialogueText::search($processedQuery);

        // Apply language filter
        if (! empty($filters['language'])) {
            $search->where('languages', $filters['language']);
        }

        // Apply game filter
        if (! empty($filters['game_names'])) {
            if (is_array($filters['game_names'])) {
                foreach ($filters['game_names'] as $gameName) {
                    $search->where('game_names', $gameName);
                }
            } else {
                $search->where('game_names', $filters['game_names']);
            }
        }

        // Apply character filter
        if (! empty($filters['character_names'])) {
            if (is_array($filters['character_names'])) {
                foreach ($filters['character_names'] as $characterName) {
                    $search->where('character_names', $characterName);
                }
            } else {
                $search->where('character_names', $filters['character_names']);
            }
        }

        return $search->paginate($perPage, 'page', $page);
    }

    /**
     * Search for reviews with filters and pagination.
     */
    public function searchReviews(
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        $processedQuery = $this->processSearchQuery($query);
        $search = Rating::search($processedQuery);

        // Apply filters
        if (! empty($filters['game_name'])) {
            $search->where('game_name', $filters['game_name']);
        }

        if (! empty($filters['rater_name'])) {
            $search->where('rater_name', $filters['rater_name']);
        }

        if (isset($filters['min_rating'])) {
            $search->where('rating', '>=', $filters['min_rating']);
        }

        if (isset($filters['max_rating'])) {
            $search->where('rating', '<=', $filters['max_rating']);
        }

        // Always filter to visible reviews
        $search->where('is_visible', true);
        $search->where('is_reviewed', true);

        return $search->paginate($perPage, 'page', $page);
    }

    /**
     * Search for tags with filters and pagination.
     */
    public function searchTags(
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        $processedQuery = $this->processSearchQuery($query);
        $search = Tag::search($processedQuery);

        // Apply filters
        if (isset($filters['min_game_count'])) {
            $search->where('game_count', '>=', $filters['min_game_count']);
        }

        return $search->paginate($perPage, 'page', $page);
    }

    /**
     * Perform a global search across all content types.
     */
    public function globalSearch(string $query, int $limit = 10): array
    {
        $games = $this->searchGames($query, ['show_hidden' => false], $limit, 1);
        $dialogue = $this->searchDialogue($query, [], $limit, 1);
        $reviews = $this->searchReviews($query, [], $limit, 1);
        $tags = $this->searchTags($query, [], $limit, 1);

        return [
            'games' => $games->items(),
            'dialogue' => $dialogue->items(),
            'reviews' => $reviews->items(),
            'tags' => $tags->items(),
            'total_games' => $games->total(),
            'total_dialogue' => $dialogue->total(),
            'total_reviews' => $reviews->total(),
            'total_tags' => $tags->total(),
        ];
    }

    /**
     * Get search suggestions for autocomplete.
     */
    public function getSearchSuggestions(string $query, int $limit = 5): array
    {
        // Get top game names that match
        $gameNames = Game::search($query)
            ->where('is_visible', true)
            ->take($limit)
            ->get()
            ->pluck('name')
            ->toArray();

        return [
            'games' => array_slice($gameNames, 0, $limit),
        ];
    }

    /**
     * Get faceted search data for filters.
     */
    public function getFacets(string $query = ''): array
    {
        // This would typically use Meilisearch's faceting features
        // For now, we'll return static data that can be enhanced later
        return [
            'statuses' => ['released', 'in_development', 'prototype', 'canceled'],
            'engines' => ['renpy', 'unity', 'twine', 'rpgmaker', 'other'],
            'languages' => ['eng', 'jpn', 'spa', 'fra', 'deu'],
        ];
    }

    /**
     * Highlight search terms in text content.
     */
    public function highlightText(string $text, string $query): string
    {
        if (empty($query)) {
            return $text;
        }

        // Simple highlighting - Meilisearch provides better highlighting in responses
        $terms = explode(' ', $query);
        foreach ($terms as $term) {
            if (strlen($term) > 2) {
                $text = preg_replace(
                    '/(' . preg_quote($term, '/') . ')/i',
                    '<mark>$1</mark>',
                    $text
                );
            }
        }

        return $text;
    }

    /**
     * Get search analytics data.
     */
    public function getSearchAnalytics(): array
    {
        // This would integrate with Meilisearch analytics
        // For now, return placeholder data
        return [
            'total_searches' => 0,
            'top_queries' => [],
            'no_results_queries' => [],
        ];
    }

    /**
     * Perform a two-tier search: exact first, then fuzzy if needed.
     */
    private function performTieredSearch(
        string $query,
        array $filters,
        int $perPage,
        int $page,
        string $sortField,
        string $sortDirection
    ): LengthAwarePaginator {
        // First try: Exact matching with quotes for precise results
        $exactQuery = $this->processSearchQuery($query, true); // true = exact mode
        $exactResults = $this->executeSearch($exactQuery, $filters, $perPage, $page, $sortField, $sortDirection);

        // If we have good results from exact search, use them
        // Consider "good results" as having at least 3 results or being on page 1 with any results
        if ($exactResults->total() >= 3 || ($page === 1 && $exactResults->total() > 0)) {
            return $exactResults;
        }

        // Second try: Fuzzy matching for typo tolerance
        $fuzzyQuery = $this->processSearchQuery($query, false); // false = fuzzy mode

        return $this->executeSearch($fuzzyQuery, $filters, $perPage, $page, $sortField, $sortDirection);
    }

    /**
     * Execute the actual search with given query and filters.
     */
    private function executeSearch(
        string $processedQuery,
        array $filters,
        int $perPage,
        int $page,
        string $sortField,
        string $sortDirection
    ): LengthAwarePaginator {
        $search = Game::search($processedQuery);

        // Apply filters
        if (! empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $search->whereIn('status', $filters['status']);
            } else {
                $search->where('status', $filters['status']);
            }
        }

        if (isset($filters['is_nsfw'])) {
            $search->where('is_nsfw', $filters['is_nsfw']);
        }

        if (isset($filters['is_paid'])) {
            $search->where('is_paid', $filters['is_paid']);
        }

        if (isset($filters['has_demo'])) {
            $search->where('has_demo', $filters['has_demo']);
        }

        if (! empty($filters['game_engine'])) {
            if (is_array($filters['game_engine'])) {
                $search->whereIn('game_engine', $filters['game_engine']);
            } else {
                $search->where('game_engine', $filters['game_engine']);
            }
        }

        if (! empty($filters['tags'])) {
            foreach ((array) $filters['tags'] as $tag) {
                $search->where('tags', $tag);
            }
        }

        if (! empty($filters['game_jams'])) {
            foreach ((array) $filters['game_jams'] as $gameJam) {
                $search->where('game_jams', $gameJam);
            }
        }

        if (! empty($filters['supported_languages'])) {
            foreach ((array) $filters['supported_languages'] as $language) {
                $search->where('supported_languages', $language);
            }
        }

        // Platform filters
        if (isset($filters['is_windows'])) {
            $search->where('is_windows', $filters['is_windows']);
        }
        if (isset($filters['is_linux'])) {
            $search->where('is_linux', $filters['is_linux']);
        }
        if (isset($filters['is_mac'])) {
            $search->where('is_mac', $filters['is_mac']);
        }
        if (isset($filters['is_android'])) {
            $search->where('is_android', $filters['is_android']);
        }
        if (isset($filters['is_web'])) {
            $search->where('is_web', $filters['is_web']);
        }

        // Visibility filter
        if (isset($filters['is_visible'])) {
            $search->where('is_visible', $filters['is_visible']);
        } elseif (! isset($filters['show_hidden']) || ! $filters['show_hidden']) {
            $search->where('is_visible', true);
        }

        // Apply sorting
        $sortableFields = [
            'first_visible_at',
            'latest_version_published_at',
            'created_at',
            'rating_score',
            'rating_count',
            'name',
            'english_word_count',
            'initially_published_at',
            'trending_score',
        ];

        if ($sortField === 'relevance') {
            // For relevance sorting, don't apply any orderBy - let Meilisearch use natural relevance
            // This gives the best search results based on matching quality
        } elseif ($sortField === 'trending') {
            // Map 'trending' to 'trending_score' for compatibility
            $search->orderBy('trending_score', $sortDirection);
        } elseif (in_array($sortField, $sortableFields)) {
            $search->orderBy($sortField, $sortDirection);
        } else {
            // Default sort for non-search browsing
            $search->orderBy('first_visible_at', 'desc');
        }

        // Use Laravel Scout's standard pagination
        return $search->paginate($perPage, 'page', $page);
    }

    /**
     * Process search query to use AND logic with optional exact matching.
     *
     * @param  string  $query  The search query
     * @param  bool  $exact  Whether to use exact matching (true) or allow typos (false)
     */
    private function processSearchQuery(string $query, bool $exact = false): string
    {
        // If it's a wildcard search, return as-is
        if (trim($query) === '*' || empty(trim($query))) {
            return $query;
        }

        // Split the query into individual terms
        $terms = preg_split('/\s+/', trim($query));

        // If only one term, return as-is (with or without quotes based on exact mode)
        if (count($terms) <= 1) {
            if ($exact && ! str_starts_with($query, '"')) {
                return '"' . addslashes(trim($query)) . '"';
            }

            return $query;
        }

        // For multiple terms, use AND operator
        return implode(' AND ', array_map(function ($term) use ($exact) {
            $term = trim($term);
            if (empty($term)) {
                return '';
            }

            if ($exact) {
                // Exact mode: wrap in quotes for precise matching
                return '"' . addslashes($term) . '"';
            } else {
                // Fuzzy mode: no quotes to allow typo tolerance
                return $term;
            }
        }, array_filter($terms)));
    }

    /**
     * Convert raw Meilisearch results to Laravel paginated format.
     */
    private function convertToPaginatedResults(array $results, int $perPage, int $page): LengthAwarePaginator
    {
        $hits = $results['hits'] ?? [];
        $total = $results['estimatedTotalHits'] ?? count($hits);

        // Convert hits to objects for easier access
        $items = collect($hits)->map(function ($hit) {
            return (object) $hit;
        });

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Enhance models with data from search results.
     *
     * This ensures that the indexed data is available on the model instances
     * for display in the frontend.
     */
    private function enhanceModelsWithSearchData($models): void
    {
        foreach ($models as $model) {
            // Get the searchable array data (this includes the indexed data)
            $searchableData = $model->toSearchableArray();

            // Set the supported languages from the search index
            if (isset($searchableData['supported_languages'])) {
                $model->supported_languages = $searchableData['supported_languages'];
            }

            // Set platform flags from search data
            $model->is_windows = $searchableData['is_windows'] ?? false;
            $model->is_linux = $searchableData['is_linux'] ?? false;
            $model->is_mac = $searchableData['is_mac'] ?? false;
            $model->is_android = $searchableData['is_android'] ?? false;
            $model->is_web = $searchableData['is_web'] ?? false;

            // Set version data
            $model->latest_version_id = $searchableData['latest_version_id'] ?? null;
            $model->latest_version_published_at = $searchableData['latest_version_published_at'] ?? null;

            // Set word count if available
            $model->english_word_count = $searchableData['english_word_count'] ?? null;
        }
    }
}
