<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ItchAuthService;
use App\Services\ItchFollowService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateWatchlist extends Command
{
    protected $signature = 'games:update-watchlist
        {--collection=both : Which collection to process (free, paid, or both)}';
    protected $description = 'Update games from itch.io collection and follow creators';

    private ItchAuthService $authService;
    private ItchFollowService $followService;
    private int $processedGames = 0;
    private int $followedCreators = 0;

    public function __construct(ItchAuthService $authService, ItchFollowService $followService)
    {
        parent::__construct();
        $this->authService = $authService;
        $this->followService = $followService;
    }

    public function handle(): int
    {
        $this->info('Starting watchlist update');

        try {
            $client = $this->authService->getClient();
            $collection = $this->option('collection');

            // Process free collection
            if ($collection === 'free' || $collection === 'both') {
                $collectionId = config('services.itch.free_collection_id');
                if (! $collectionId) {
                    $this->warn('Free collection ID not configured, skipping');
                } else {
                    $this->info('Processing free games collection');
                    $this->processCollection($client, $collectionId, false);
                }
            }

            // Process paid collection
            if ($collection === 'paid' || $collection === 'both') {
                $paidCollectionId = config('services.itch.paid_collection_id');
                if (! $paidCollectionId) {
                    $this->warn('Paid collection ID not configured, skipping');
                } else {
                    $this->info('Processing paid games collection');
                    $this->processCollection($client, $paidCollectionId, true);
                }
            }

            $this->info("Watchlist update completed. Processed {$this->processedGames} games, followed {$this->followedCreators} creators.");

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
    private function processCollectionPage($client, string $collectionId, int $page, bool $isPaid): bool
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
            $this->processCollectionGame($client, $gameData, $isPaid);
        }

        return true;
    }

    /**
     * Process a collection
     */
    private function processCollection($client, string $collectionId, bool $isPaid): void
    {
        $page = 1;
        do {
            $this->info("Processing page {$page}");
            $hasMore = $this->processCollectionPage($client, $collectionId, $page, $isPaid);

            if ($hasMore) {
                $this->info('Waiting 30 seconds before next page...');
                sleep(30);
            }

            $page++;
        } while ($hasMore);
    }

    /**
     * Process a single game from the collection
     */
    private function processCollectionGame($client, array $gameData, bool $isPaid): void
    {
        // Log the game being processed
        $this->processedGames++;
        $gameId = $gameData['id'];

        $this->info("Processing game {$gameId}: {$gameData['title']}");

        DB::beginTransaction();

        try {
            $game = Game::firstOrNew(['game_id' => $gameId]);
            $shouldRefreshVersion = false;
            $isNew = ! $game->exists;
            $wasInvisible = $game->exists && ! $game->is_visible;

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
                    ($gameData['cover_url'] ?? null) !== $game->thumb_url ||
                    $game->is_paid !== $isPaid) {

                    $game->name = $gameData['title'];
                    $game->description = $gameData['short_text'] ?? null;
                    $game->thumb_url = $gameData['cover_url'] ?? null;
                    $game->is_paid = $isPaid;
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
                    'is_paid' => $isPaid,
                    'is_visible' => true,
                ]);
                $shouldRefreshVersion = true;
            }

            // Check if we need to do a full refresh for paid games
            $needsFullRefresh = $shouldRefreshVersion;

            // For paid games, we need to do a full refresh if:
            // 1. The game is not visible
            // 2. The paid status doesn't match what we expect
            if ($isPaid && (! $game->is_visible || ! $game->is_paid)) {
                $needsFullRefresh = true;
                $this->info("  - Doing full refresh for paid game (visible: {$game->is_visible}, paid: {$game->is_paid})");
            }

            // Always set is_paid flag
            $game->is_paid = $isPaid;
            $game->is_visible = true;

            // Check for demos in the uploads data
            if ($isPaid && ! empty($game->uploads)) {
                $hasDemo = false;
                foreach ($game->uploads as $uploadData) {
                    if (isset($uploadData['traits']) && is_array($uploadData['traits']) && in_array('demo', $uploadData['traits'])) {
                        $hasDemo = true;
                        break;
                    }

                    // Also check filename and display name for demo indicators
                    $filename = strtolower($uploadData['filename'] ?? '');
                    $displayName = strtolower($uploadData['display_name'] ?? '');

                    if (str_contains($filename, 'demo') || str_contains($displayName, 'demo')) {
                        $hasDemo = true;
                        break;
                    }
                }

                if ($hasDemo) {
                    $this->info('  - Game has demo');
                    $game->has_demo = true;
                }
            }

            // Load full details if needed
            if ($needsFullRefresh) {
                $this->info('  - Loading full details');
                $game->loadFullDetails($client);
            }

            $game->save();
            DB::commit();

            // Follow creator if the game is new or was previously invisible
            if ($isNew || $wasInvisible) {
                if ($this->followCreator($game->url)) {
                    $this->followedCreators++;
                    $this->info("Successfully followed creator for {$game->name}");
                } else {
                    $this->warn("Failed to follow creator for {$game->name}");
                }
                // Add small delay between follow requests
                sleep(3);
            }

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

    /**
     * Follow the creator of a game
     */
    private function followCreator(string $gameUrl): bool
    {
        try {
            return $this->followService->followCreatorFromGameUrl($gameUrl);
        } catch (Exception $e) {
            $this->error('Error following creator: ' . $e->getMessage());
            Log::error('Error following creator', [
                'game_url' => $gameUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
