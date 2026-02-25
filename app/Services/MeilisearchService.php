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
        string $sortDirection = 'desc',
        array $ignoredGameIds = []
    ): LengthAwarePaginator {
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
        $search = UniqueDialogueText::search(trim($query));

        if (! empty($filters['language'])) {
            $search->where('languages', $filters['language']);
        }

        if (! empty($filters['game_id'])) {
            $search->where('game_ids', (int) $filters['game_id']);
        }

        if (! empty($filters['game_names'])) {
            foreach ((array) $filters['game_names'] as $gameName) {
                $search->where('game_names', $gameName);
            }
        }

        if (! empty($filters['version_id'])) {
            $search->where('version_ids', (int) $filters['version_id']);
        }

        if (! empty($filters['character_names'])) {
            foreach ((array) $filters['character_names'] as $characterName) {
                $search->where('character_names', $characterName);
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
        $search = Rating::search(trim($query));

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
}
