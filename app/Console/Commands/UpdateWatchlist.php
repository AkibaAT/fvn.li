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
use Throwable;

class UpdateWatchlist extends Command
{
    protected $signature = 'games:update-watchlist
        {--collection=both : Which collection to process (free, paid, or both)}';

    protected $description = 'Update games from itch.io collection';

    private ItchAuthService $authService;

    private int $processedGames = 0;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
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

            $this->info("Watchlist update completed. Processed {$this->processedGames} games.");

            return 0;

        } catch (Exception $e) {
            $this->error('Error updating watchlist: ' . $e->getMessage());
            Log::error('Watchlist update failed', ['exception' => $e]);

            return 1;
        }
    }

    /**
     * Process a collection
     *
     * @throws GuzzleException
     * @throws Throwable
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
     * Process a single page of the collection
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    private function processCollectionPage($client, string $collectionId, int $page, bool $isPaid): bool
    {
        // First get the API data
        $response = $client->get("https://api.itch.io/collections/{$collectionId}/collection-games", [
            'query' => ['page' => $page],
        ]);

        $collection = json_decode($response->getBody()->getContents(), true);
        $games = $collection['collection_games'] ?? [];

        if (empty($games)) {
            return false;
        }

        // We'll extract price information for each game individually in processCollectionGame

        foreach ($games as $collectionEntry) {
            $gameData = $collectionEntry['game'];
            $this->processCollectionGame($gameData, $isPaid);
        }

        return true;
    }

    /**
     * Process a single game from the collection
     *
     * @throws Throwable
     */
    private function processCollectionGame(array $gameData, bool $isPaid): void
    {
        // Log the game being processed
        $this->processedGames++;
        $gameId = $gameData['id'];

        $this->info("Processing game {$gameId}: {$gameData['title']}");

        // Extract price information from collection data if available
        if ($isPaid && isset($gameData['min_price'])) {
            // Convert from cents to dollars
            $priceInDollars = $gameData['min_price'] / 100;

            // Check for sale status and extract discount percentage
            $isOnSale = isset($gameData['sale']) && ! empty($gameData['sale']) &&
                isset($gameData['sale']['rate']) && $gameData['sale']['rate'] > 0;
            $discountPercent = $isOnSale ? (int) $gameData['sale']['rate'] : null;
            $saleInfo = '';
            if ($isOnSale) {
                $saleInfo = ' (on sale: ' . $discountPercent . '% off)';
            }

            $this->info('  - Price from collection: $' . number_format($priceInDollars, 2) . $saleInfo);
        }

        DB::beginTransaction();

        try {
            $game = Game::firstOrNew(['game_id' => $gameId]);
            $shouldRefreshVersion = false;
            $isNew = ! $game->exists;
            $wasInvisible = $game->exists && ! $game->is_visible;

            // Skip if game is suspended
            if ($game->exists && $game->is_suspended) {
                DB::rollBack();
                $this->info("Skipping suspended game: {$game->name}");

                return;
            }

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

                // Update price information from collection data if available
                if ($isPaid && isset($gameData['min_price'])) {
                    $priceInDollars = $gameData['min_price'] / 100;
                    $game->min_price = $priceInDollars;

                    // Check for sale status and store discount percentage
                    $game->is_on_sale = isset($gameData['sale']) && ! empty($gameData['sale']) &&
                        isset($gameData['sale']['rate']) && $gameData['sale']['rate'] > 0;
                    $game->sale_discount_percent = $game->is_on_sale ? (int) $gameData['sale']['rate'] : null;

                    // Mark that price was set from API data (temporary flag for this request)
                    $game->priceSetFromApi = true;

                    $saleInfo = '';
                    if ($game->is_on_sale) {
                        $saleInfo = ' (on sale: ' . $game->sale_discount_percent . '% off)';
                    }

                    $this->info('  - Updated price from collection: $' . number_format($priceInDollars, 2) . $saleInfo);
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

                // Set price information from collection data if available
                if ($isPaid && isset($gameData['min_price'])) {
                    $priceInDollars = $gameData['min_price'] / 100;
                    $game->min_price = $priceInDollars;

                    // Check for sale status and store discount percentage
                    $game->is_on_sale = isset($gameData['sale']) && ! empty($gameData['sale']) &&
                        isset($gameData['sale']['rate']) && $gameData['sale']['rate'] > 0;
                    $game->sale_discount_percent = $game->is_on_sale ? (int) $gameData['sale']['rate'] : null;

                    // Mark that price was set from API data (temporary flag for this request)
                    $game->priceSetFromApi = true;

                    $saleInfo = '';
                    if ($game->is_on_sale) {
                        $saleInfo = ' (on sale: ' . $game->sale_discount_percent . '% off)';
                    }

                    $this->info('  - Set price from collection: $' . number_format($priceInDollars, 2) . $saleInfo);
                }

                $shouldRefreshVersion = true;
            }

            // Check if we need to do a full refresh
            $needsFullRefresh = $shouldRefreshVersion;

            // For paid games, check if we need a full refresh for missing data
            if ($isPaid) {
                $needsFullRefreshForPaid =
                    ! $game->exists ||
                    ! $game->is_visible ||
                    ! $game->is_paid ||
                    empty($game->screenshots) ||
                    empty($game->full_description);

                if ($needsFullRefreshForPaid) {
                    $needsFullRefresh = true;
                    $visibleStr = $game->is_visible ? 'true' : 'false';
                    $paidStr = $game->is_paid ? 'true' : 'false';
                    $this->info("  - Doing full refresh for paid game (visible: {$visibleStr}, paid: {$paidStr})");

                    // Log the reason for the refresh
                    if (! $game->exists) {
                        $this->info('    - Reason: New game');
                    } elseif (! $game->is_visible) {
                        $this->info('    - Reason: Previously invisible');
                    } elseif (! $game->is_paid) {
                        $this->info('    - Reason: Previously marked as free');
                    } elseif (empty($game->screenshots)) {
                        $this->info('    - Reason: Missing screenshots');
                    } elseif (empty($game->full_description)) {
                        $this->info('    - Reason: Missing full description');
                    }
                } else {
                    // Price information is already updated from collection data above
                    $this->info('  - Skipping metadata refresh (price updated from collection data)');
                }
            }

            // Always set is_paid flag
            $game->is_paid = $isPaid;

            // Only set is_visible = true if it's not already true to avoid unnecessary observer triggers
            if (! $game->is_visible) {
                $game->is_visible = true;
            }

            // Save the game first to ensure it has an ID before loading full details
            // This is necessary because loadFullDetails() -> refreshVersion() needs the game ID
            // to create GameVersion records
            if ($needsFullRefresh && !$game->exists) {
                $this->info('  - Saving new game before loading full details');
                $game->save();
                $game->refresh();
            }

            // Load full details if needed
            if ($needsFullRefresh) {
                $this->info('  - Loading full details');
                $game->loadFullDetails();

                // Make sure the is_paid flag is still set correctly after loading details
                if ($isPaid && ! $game->is_paid) {
                    $this->info('  - Restoring paid status after metadata refresh');
                    $game->is_paid = true;
                }

                // Now check for demos in the uploads data after we've loaded the full details
                if ($isPaid && ! empty($game->uploads)) {
                    $hasDemo = false;
                    foreach ($game->uploads as $uploadData) {
                        if (isset($uploadData['traits']) && is_array($uploadData['traits']) && in_array('demo',
                            $uploadData['traits'])) {
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
            }

            // Remove temporary flag before saving (not a database column)
            unset($game->priceSetFromApi);

            $game->save();

            // Process any pending associations now that the game is saved
            $game->processPendingGameJams();
            $game->processPendingTags();

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
