<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Collection;
use Meilisearch\Client;
use Meilisearch\Contracts\SimilarDocumentsQuery;

class SimilarGamesService
{
    public function __construct(
        private Client $client
    ) {}

    /**
     * Find similar games using Meilisearch vector similarity on game descriptions.
     *
     * @return Collection<int, Game>
     */
    public function findSimilarGames(Game $game, int $limit = 6): Collection
    {
        if (! $game->shouldBeSearchable()) {
            return collect();
        }

        $query = new SimilarDocumentsQuery($game->id, 'default');
        $query->setLimit($limit)
            ->setFilter(['is_visible = true']);

        $results = $this->client->index('games')->searchSimilarDocuments($query);

        $ids = collect($results->getHits())->pluck('id')->toArray();

        if (empty($ids)) {
            return collect();
        }

        // Fetch full Eloquent models preserving Meilisearch ranking order
        $games = Game::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $games->get($id))
            ->filter()
            ->values();
    }

    /**
     * Find other visible games by the same developer/author.
     *
     * @return Collection<int, Game>
     */
    public function findDeveloperGames(Game $game, int $limit = 12): Collection
    {
        if (empty($game->authors)) {
            return collect();
        }

        // Strip HTML tags from authors to get the plain text for matching
        $plainAuthors = strip_tags($game->authors);
        if (empty(trim($plainAuthors))) {
            return collect();
        }

        // Match games that share the same authors field (stripped of HTML)
        // We compare the raw authors field since developers may use HTML links
        return Game::where('is_visible', true)
            ->where('id', '!=', $game->id)
            ->where(function ($query) use ($game, $plainAuthors) {
                // Exact match on authors field
                $query->where('authors', $game->authors)
                    // Also try matching by plain-text extraction for cases
                    // where the HTML wrapper differs but the name is the same
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(authors, '<a', ''), '</a>', ''), '>', '') LIKE ?", ["%{$plainAuthors}%"]);
            })
            ->orderBy('rating_score', 'desc')
            ->limit($limit)
            ->get();
    }
}
