<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

class GameSearchFilterService
{
    /**
     * Build search filters from request parameters
     */
    public function buildFiltersFromRequest(Request $request): array
    {
        $filters = [];

        // Always filter to visible games only
        $filters['is_visible'] = true;

        // Status filter
        if ($selectedStatuses = $this->getArrayParameter($request, 'selectedStatuses')) {
            $filters['status'] = $selectedStatuses;
        }

        // Engine filter
        if ($selectedEngines = $this->getArrayParameter($request, 'selectedEngines')) {
            $filters['game_engine'] = $selectedEngines;
        }

        // Platform filters
        if ($selectedPlatforms = $this->getArrayParameter($request, 'selectedPlatforms')) {
            foreach ($selectedPlatforms as $platform) {
                $filters["is_{$platform}"] = true;
            }
        }

        // Language filter
        if ($selectedLanguages = $this->getArrayParameter($request, 'selectedLanguages')) {
            $filters['supported_languages'] = $selectedLanguages;
        }

        // Tags filter
        if ($selectedTags = $this->getArrayParameter($request, 'selectedTags')) {
            $filters['tags'] = $selectedTags;
        }

        // NSFW/SFW filters
        $nsfw = $request->boolean('nsfw');
        $sfw = $request->boolean('sfw');
        if ($nsfw && ! $sfw) {
            $filters['is_nsfw'] = true;
        } elseif ($sfw && ! $nsfw) {
            $filters['is_nsfw'] = false;
        }

        // Paid/Free filters
        $showPaid = $request->boolean('showPaid');
        $showFree = $request->boolean('showFree');
        if ($showPaid && ! $showFree) {
            $filters['is_paid'] = true;
        } elseif ($showFree && ! $showPaid) {
            $filters['is_paid'] = false;
        }

        // Demo filter
        if ($request->boolean('showDemo')) {
            $filters['has_demo'] = true;
        }

        // Sale filter
        if ($request->boolean('showSale')) {
            $filters['is_on_sale'] = true;
        }

        return $filters;
    }

    /**
     * Build enhanced API filters from request
     */
    public function buildEnhancedApiFilters(Request $request): array
    {
        $filters = array_filter([
            'status' => $request->input('status'),
            'is_nsfw' => $request->boolean('is_nsfw', null),
            'is_paid' => $request->boolean('is_paid', null),
            'has_demo' => $request->boolean('has_demo', null),
            'game_engine' => $request->input('game_engine'),
            'tags' => $request->input('tags'),
            'supported_languages' => $request->input('supported_languages'),
        ], fn ($value) => $value !== null);

        $filters['is_visible'] = true;

        return $filters;
    }

    /**
     * Get search parameters from request
     */
    public function getSearchParams(Request $request): array
    {
        $search = $request->get('search', '');
        $isSearching = ! empty(trim($search ?? ''));
        $defaultSort = $isSearching ? 'relevance' : 'first_visible_at';

        return [
            'search' => $search,
            'isSearching' => $isSearching,
            'sortField' => $request->get('sort', $defaultSort),
            'sortDirection' => $request->get('direction', 'desc'),
            'perPage' => min(32, max(8, (int) $request->get('perPage', 8))),
            'page' => (int) $request->get('page', 1),
        ];
    }

    /**
     * Get sorting parameters for reviews
     */
    public function getReviewSortParams(Request $request): array
    {
        $sort = $request->input('sort', 'newest');

        return match ($sort) {
            'oldest' => ['published_at', 'asc'],
            'rating_high' => ['rating', 'desc'],
            'rating_low' => ['rating', 'asc'],
            'helpful' => ['published_at', 'desc'], // TODO: Implement helpful sorting
            'newest' => ['published_at', 'desc'],
            default => ['published_at', 'desc'],
        };
    }

    /**
     * Normalize array parameter from request (handles both string and array inputs)
     */
    private function getArrayParameter(Request $request, string $key): ?array
    {
        $value = $request->get($key);

        if (! $value) {
            return null;
        }

        if (is_array($value)) {
            return array_filter($value);
        }

        if (is_string($value)) {
            return array_filter(explode(',', $value));
        }

        return null;
    }
}
