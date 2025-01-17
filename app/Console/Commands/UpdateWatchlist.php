<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ItchAuthService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateWatchlist extends Command
{
    protected $signature = 'games:update-watchlist';
    protected $description = 'Update games from itch.io collection';

    private ItchAuthService $authService;
    private int $processedGames = 0;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('Starting watchlist update');

        try {
            $client = $this->authService->getClient();
            $collectionId = config('services.itch.collection_id');

            if (! $collectionId) {
                throw new Exception('Itch collection ID not configured');
            }

            $page = 1;
            do {
                $this->info("Processing page {$page}");
                $hasMore = $this->processCollectionPage($client, $collectionId, $page);

                if ($hasMore) {
                    $this->info('Waiting 30 seconds before next page...');
                    sleep(30);
                }

                $page++;
            } while ($hasMore);

            $this->info("Watchlist update completed. Processed {$this->processedGames} games.");

            return 0;

        } catch (Exception $e) {
            $this->error('Error updating watchlist: ' . $e->getMessage());
            Log::error('Watchlist update failed', ['exception' => $e]);

            return 1;
        }
    }

    /**
     * Process a single page of the collection
     *
     * @throws GuzzleException
     */
    private function processCollectionPage($client, string $collectionId, int $page): bool
    {
        $response = $client->get("https://api.itch.io/collections/{$collectionId}/collection-games", [
            'query' => ['page' => $page],
        ]);

        $collection = json_decode($response->getBody()->getContents(), true);
        $games = $collection['collection_games'] ?? [];

        if (empty($games)) {
            return false;
        }

        foreach ($games as $collectionEntry) {
            $gameData = $collectionEntry['game'];
            $this->processCollectionGame($client, $gameData);
        }

        return true;
    }

    /**
     * Process a single game from the collection
     */
    private function processCollectionGame($client, array $gameData): void
    {
        $this->processedGames++;
        $gameId = $gameData['id'];

        $this->info("Processing game {$gameId}: {$gameData['title']}");

        DB::beginTransaction();

        try {
            $game = Game::firstOrNew(['game_id' => $gameId]);
            $shouldRefreshVersion = false;

            // Update if game exists but isn't visible
            if ($game->exists) {
                if (! $game->is_visible) {
                    $game->updated_at = now();
                    if (! $game->source_language_id) {
                        $game->source_language_id = 'eng';
                    }
                    $shouldRefreshVersion = true;
                }

                // Update if basic info has changed
                if ($gameData['title'] !== $game->name ||
                    ($gameData['short_text'] ?? null) !== $game->description ||
                    ($gameData['cover_url'] ?? null) !== $game->thumb_url) {

                    $game->name = $gameData['title'];
                    $game->description = $gameData['short_text'] ?? null;
                    $game->thumb_url = $gameData['cover_url'] ?? null;
                    $game->updated_at = now();
                }

                // Update initial publish date if missing
                if (! $game->initially_published_at && isset($gameData['published_at'])) {
                    $game->initially_published_at = $gameData['published_at'];
                    $game->updated_at = now();
                }
            } else {
                // Create new game
                $game->fill([
                    'initially_published_at' => $gameData['published_at'] ?? null,
                    'game_id' => $gameId,
                    'name' => $gameData['title'],
                    'description' => $gameData['short_text'] ?? null,
                    'url' => $gameData['url'],
                    'thumb_url' => $gameData['cover_url'] ?? null,
                    'source_language_id' => 'eng',
                ]);
                $shouldRefreshVersion = true;
            }

            // Load full details if needed
            if ($shouldRefreshVersion) {
                $game->is_visible = true;
                $game->loadFullDetails($client);
            }

            $game->save();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Failed to process game {$gameId}: " . $e->getMessage());
            Log::error('Failed to process game in watchlist', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
