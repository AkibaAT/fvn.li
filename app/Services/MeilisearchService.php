<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
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
        string $sortDirection = 'desc',
        array $ignoredGameIds = []
    ): LengthAwarePaginator {
        if (config('scout.driver') !== 'meilisearch') {
            return $this->searchGamesFromDatabase($query, $filters, $perPage, $page, $sortField, $sortDirection, $ignoredGameIds);
        }

        $search = Game::search(trim($query));

        // Exclude ignored games
        if (! empty($ignoredGameIds)) {
            $search->whereNotIn('id', $ignoredGameIds);
        }

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

        if (isset($filters['is_on_sale'])) {
            $search->where('is_on_sale', $filters['is_on_sale']);
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

        // Exclude specific tags (NOT IN filter)
        if (! empty($filters['excluded_tags'])) {
            $search->whereNotIn('tags', (array) $filters['excluded_tags']);
        }

        // Reading time filter based on english_word_count
        if (! empty($filters['reading_time'])) {
            match ($filters['reading_time']) {
                'short' => $search->where('english_word_count', '<', 10000),
                'medium' => (function () use ($search) {
                    $search->where('english_word_count', '>=', 10000);
                    $search->where('english_word_count', '<=', 50000);
                })(),
                'long' => $search->where('english_word_count', '>', 50000),
                default => null,
            };
        }

        if (! empty($filters['game_jams'])) {
            foreach ((array) $filters['game_jams'] as $gameJam) {
                $search->where('game_jams', $gameJam);
            }
        }

        if (! empty($filters['supported_languages'])) {
            $languages = (array) $filters['supported_languages'];
            if (count($languages) === 1) {
                $search->where('supported_languages', $languages[0]);
            } else {
                $search->whereIn('supported_languages', $languages);
            }
        }

        // Store platform filter (where game is hosted: itch_io, steam, other)
        if (! empty($filters['platform'])) {
            if (is_array($filters['platform'])) {
                $search->whereIn('platform', $filters['platform']);
            } else {
                $search->where('platform', $filters['platform']);
            }
        }

        // Game platform filters (where game runs: windows, linux, mac, android, web)
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

        // Delisted filter - only filter when explicitly set (to show only delisted games)
        if (isset($filters['is_delisted'])) {
            $search->where('is_delisted', $filters['is_delisted']);
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
            // Let Meilisearch use natural relevance ranking
        } elseif ($sortField === 'trending') {
            $search->orderBy('trending_score', $sortDirection);
        } elseif (in_array($sortField, $sortableFields)) {
            $search->orderBy($sortField, $sortDirection);
        } else {
            $search->orderBy('first_visible_at', 'desc');
        }

        return $search->paginate($perPage, 'page', $page);
    }

    /**
     * Search for unique dialogue texts with filters and pagination.
     */
    public function searchDialogue(
        string $query,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        return app(DialogueSearchService::class)->search($query, $filters, $perPage, $page);
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
        $search = Tag::search(trim($query));

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
        $tags = $this->searchTags($query, [], $limit, 1);

        return [
            'games' => $games->items(),
            'dialogue' => $dialogue->items(),
            'tags' => $tags->items(),
            'total_games' => $games->total(),
            'total_dialogue' => $dialogue->total(),
            'total_tags' => $tags->total(),
        ];
    }

    private function searchGamesFromDatabase(
        string $query,
        array $filters,
        int $perPage,
        int $page,
        string $sortField,
        string $sortDirection,
        array $ignoredGameIds
    ): LengthAwarePaginator {
        $games = Game::query();
        $searchTerm = trim($query);

        if ($searchTerm !== '' && $searchTerm !== '*') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm) . '%';
            $games->where(function (Builder $builder) use ($like) {
                $builder
                    ->where('name', 'ilike', $like)
                    ->orWhere('description', 'ilike', $like)
                    ->orWhere('authors', 'ilike', $like);
            });
        }

        if (! empty($ignoredGameIds)) {
            $games->whereNotIn('id', $ignoredGameIds);
        }

        $this->applyDatabaseGameFilters($games, $filters);
        $this->applyDatabaseGameSort($games, $sortField, $sortDirection);

        return $games->paginate($perPage, ['*'], 'page', $page);
    }

    private function applyDatabaseGameFilters(Builder $games, array $filters): void
    {
        foreach (['status', 'game_engine', 'platform'] as $field) {
            if (empty($filters[$field])) {
                continue;
            }

            is_array($filters[$field])
                ? $games->whereIn($field, $filters[$field])
                : $games->where($field, $filters[$field]);
        }

        foreach (['is_nsfw', 'is_paid', 'has_demo', 'is_on_sale', 'is_delisted'] as $field) {
            if (isset($filters[$field])) {
                $games->where($field, $filters[$field]);
            }
        }

        if (isset($filters['is_visible'])) {
            $games->where('is_visible', $filters['is_visible']);
        } elseif (! isset($filters['show_hidden']) || ! $filters['show_hidden']) {
            $games->where('is_visible', true);
        }

        foreach (['is_windows', 'is_linux', 'is_mac', 'is_android', 'is_web'] as $field) {
            if (isset($filters[$field])) {
                $games->whereHas('latestVersion', fn (Builder $version) => $version->where($field, $filters[$field]));
            }
        }

        if (! empty($filters['tags'])) {
            foreach ((array) $filters['tags'] as $tag) {
                $games->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->where('name', $tag));
            }
        }

        if (! empty($filters['excluded_tags'])) {
            $games->whereDoesntHave('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('name', (array) $filters['excluded_tags']));
        }

        if (! empty($filters['game_jams'])) {
            foreach ((array) $filters['game_jams'] as $gameJam) {
                $games->whereHas('gameJams', fn (Builder $gameJamQuery) => $gameJamQuery->where('name', $gameJam));
            }
        }

        if (! empty($filters['supported_languages'])) {
            $languages = (array) $filters['supported_languages'];
            $games->whereHas('latestVersion.supportedLanguages', fn (Builder $languageQuery) => $languageQuery->whereIn('iso_code', $languages));
        }
    }

    private function applyDatabaseGameSort(Builder $games, string $sortField, string $sortDirection): void
    {
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        match ($sortField) {
            'latest_version_published_at' => $games->orderBy(
                GameVersion::query()
                    ->select('published_at')
                    ->whereColumn('game_versions.game_id', 'games.id')
                    ->orderBy('published_at', 'desc')
                    ->limit(1),
                $direction
            ),
            'rating_score', 'rating_count', 'name', 'created_at', 'first_visible_at', 'initially_published_at' => $games->orderBy($sortField, $direction),
            'trending', 'trending_score' => $games->orderBy('rating_score', $direction)->orderBy('rating_count', $direction),
            default => $games->orderBy('first_visible_at', 'desc'),
        };
    }
}
