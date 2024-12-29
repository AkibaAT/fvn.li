<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ItchCollectionService
{
    public function __construct(
        private readonly string $collectionId,
        private readonly string $itchApiKey,
        private readonly ProxyRotator $proxy
    ) {}

    public function updateWatchlist(): void
    {
        $page = 1;
        do {
            $hasMore = $this->processPage($page);
            $page++;
            sleep(30); // Rate limiting
        } while ($hasMore);
    }

    private function processPage(int $page): bool
    {
        Log::info("Processing page {$page}...");

        $response = $this->proxy->request()
            ->withToken($this->itchApiKey)
            ->get("https://api.itch.io/collections/{$this->collectionId}/collection-games", [
                'page' => $page,
            ]);

        if ($response->status() === 400 || $response->status() === 404) {
            return false;
        }

        $response->throw();
        $collection = $response->json();

        if (empty($collection['collection_games'])) {
            Log::info("No games found on page {$page}.");
            return false;
        }

        foreach ($collection['collection_games'] as $entry) {
            $gameData = $entry['game'];

            $game = Game::firstOrNew(['game_id' => $gameData['id']]);

            // If game exists but was hidden, make it visible again
            if ($game->exists && ! $game->is_visible) {
                $game->is_visible = true;
                $game->touch();
            }

            // Update basic info if changed
            $needsUpdate = false;
            if ($game->name !== $gameData['title']) {
                $game->name = $gameData['title'];
                $needsUpdate = true;
            }
            if ($game->description !== ($gameData['short_text'] ?? null)) {
                $game->description = $gameData['short_text'] ?? null;
                $needsUpdate = true;
            }
            if ($game->thumb_url !== ($gameData['cover_url'] ?? null)) {
                $game->thumb_url = $gameData['cover_url'] ?? null;
                $needsUpdate = true;
            }
            if ($game->url !== $gameData['url']) {
                $game->url = $gameData['url'];
                $needsUpdate = true;
            }

            // Set initially_published_at for new games
            if (! $game->exists || ! $game->initially_published_at) {
                $game->initially_published_at = Carbon::parse($gameData['published_at']);
                $needsUpdate = true;
            }

            if (! $game->exists) {
                $game->is_visible = true;
                $game->game_engine = 'unknown';
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $game->save();
            }
        }

        return true;
    }
}
