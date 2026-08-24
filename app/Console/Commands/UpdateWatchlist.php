<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Language;
use App\Services\HomePageCacheService;
use App\Services\ImageProcessingService;
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

            if ($collection === 'free' || $collection === 'both') {
                $collectionId = config('services.itch.free_collection_id');
                if (! $collectionId) {
                    $this->warn('Free collection ID not configured, skipping');
                } else {
                    $this->info('Processing free games collection');
                    $this->processCollection($client, $collectionId, false);
                }
            }

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
            $blurb = $collectionEntry['blurb'] ?? null;
            $this->processCollectionGame($gameData, $isPaid, $blurb);
        }

        return true;
    }

    /**
     * Process a single game from the collection
     *
     * @throws Throwable
     */
    private function processCollectionGame(array $gameData, bool $isPaid, ?string $blurb = null): void
    {
        $this->processedGames++;
        $gameId = $gameData['id'];

        $this->info("Processing game {$gameId}: {$gameData['title']}");

        $sourceLanguageId = 'eng';
        $blurbTags = [];

        if ($blurb) {
            if (preg_match('/\blang:([a-z]{3})\b/', $blurb, $matches)) {
                $langCode = $matches[1];
                if (! Language::where('id', $langCode)->exists()) {
                    $this->error("  Skipping game {$gameId} ({$gameData['title']}): invalid language code '{$langCode}' in blurb");
                    Log::error('UpdateWatchlist: invalid language code in blurb', [
                        'game_id' => $gameId,
                        'title' => $gameData['title'],
                        'blurb' => $blurb,
                        'lang_code' => $langCode,
                    ]);

                    return;
                }
                $sourceLanguageId = $langCode;
                $this->info("  - Source language from blurb: {$langCode}");
            }

            if (preg_match_all('/\btag:(?:"([^"]+)"|\'([^\']+)\'|([\w-]+))/i', $blurb, $tagMatches, PREG_SET_ORDER)) {
                $blurbTags = array_map(
                    fn (array $match) => $match[1] ?: ($match[2] ?: $match[3]),
                    $tagMatches
                );
                $this->info('  - Tags from blurb: ' . implode(', ', $blurbTags));
            }
        }

        if ($isPaid && isset($gameData['min_price'])) {
            $priceInDollars = $gameData['min_price'] / 100;

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
            $game = Game::firstOrNew(['itch_id' => $gameId]);
            $shouldRefreshVersion = false;
            $isNew = ! $game->exists;
            $wasInvisible = $game->exists && ! $game->is_visible;
            $originalThumbUrl = $game->thumb_url;

            if ($game->exists) {
                if (! $game->is_visible) {
                    $game->updated_at = now();
                    if (! $game->source_language_id) {
                        $game->source_language_id = $sourceLanguageId;
                    }
                    $shouldRefreshVersion = true;
                }

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

                if (! $game->initially_published_at && isset($gameData['published_at'])) {
                    $game->initially_published_at = $gameData['published_at'];
                    $game->updated_at = now();
                }

                if ($isPaid && isset($gameData['min_price'])) {
                    $priceInDollars = $gameData['min_price'] / 100;
                    $game->min_price = $priceInDollars;

                    $game->is_on_sale = isset($gameData['sale']) && ! empty($gameData['sale']) &&
                        isset($gameData['sale']['rate']) && $gameData['sale']['rate'] > 0;
                    $game->sale_discount_percent = $game->is_on_sale ? (int) $gameData['sale']['rate'] : null;

                    $game->priceSetFromApi = true;

                    $saleInfo = '';
                    if ($game->is_on_sale) {
                        $saleInfo = ' (on sale: ' . $game->sale_discount_percent . '% off)';
                    }

                    $this->info('  - Updated price from collection: $' . number_format($priceInDollars, 2) . $saleInfo);
                }
            } else {
                $game->fill([
                    'initially_published_at' => $gameData['published_at'] ?? null,
                    'itch_id' => $gameId,
                    'name' => $gameData['title'],
                    'description' => $gameData['short_text'] ?? null,
                    'platform' => 'itch_io',
                    'thumb_url' => $gameData['cover_url'] ?? null,
                    'source_language_id' => $sourceLanguageId,
                    'is_paid' => $isPaid,
                    'is_visible' => false,
                ]);

                $game->setUrlForPlatform('itch_io', $gameData['url']);

                if ($isPaid && isset($gameData['min_price'])) {
                    $priceInDollars = $gameData['min_price'] / 100;
                    $game->min_price = $priceInDollars;

                    $game->is_on_sale = isset($gameData['sale']) && ! empty($gameData['sale']) &&
                        isset($gameData['sale']['rate']) && $gameData['sale']['rate'] > 0;
                    $game->sale_discount_percent = $game->is_on_sale ? (int) $gameData['sale']['rate'] : null;

                    $game->priceSetFromApi = true;

                    $saleInfo = '';
                    if ($game->is_on_sale) {
                        $saleInfo = ' (on sale: ' . $game->sale_discount_percent . '% off)';
                    }

                    $this->info('  - Set price from collection: $' . number_format($priceInDollars, 2) . $saleInfo);
                }

                $shouldRefreshVersion = true;
            }

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

            $game->is_paid = $isPaid;

            // This is necessary because loadFullDetails() -> refreshVersion() needs the game ID
            // to create GameVersion records
            // For new games, we save them as invisible first WITHOUT triggering observers
            // (to avoid premature Meilisearch indexing)
            if ($needsFullRefresh && ! $game->exists) {
                $this->info('  - Saving new game (invisible, quietly) before loading full details');

                if (empty($game->slug) && ! empty($game->name)) {
                    $game->slug = $game->generateUniqueSlug($game->name);
                }

                $game->saveQuietly();
                $game->refresh();
            }

            if ($needsFullRefresh) {
                $this->info('  - Loading full details');
                $game->loadFullDetails();
                // DO NOT refresh here - we want to keep the in-memory changes from loadFullDetails
                // (like processed images) until we save at the end
                $this->info('  - Full details loaded successfully');

                // Make sure the is_paid flag is still set correctly after loading details
                if ($isPaid && ! $game->is_paid) {
                    $this->info('  - Restoring paid status after metadata refresh');
                    $game->is_paid = true;
                }

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

                // Visibility flips only after all data is loaded, so observers never announce a half-imported game.
                if (! $game->is_visible) {
                    $this->info('  - Making game visible now that all data is loaded');
                    $game->is_visible = true;
                }
            } else {
                // For existing games that don't need full refresh, just ensure visibility
                if (! $game->is_visible) {
                    $game->is_visible = true;
                }
            }

            if (! empty($blurbTags)) {
                $game->custom_tags = $this->mergeCustomTags($game->custom_tags, $blurbTags);
            }

            $this->processThumbnailIfSourceChanged($game, $originalThumbUrl);

            $game->save();

            $game->processPendingGameJams();
            $game->processPendingTags();

            DB::commit();

            if ($isNew || $wasInvisible) {
                HomePageCacheService::clearAll();
                $this->info('  - Cleared home page cache');
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

    private function processThumbnailIfSourceChanged(Game $game, ?string $originalThumbUrl): void
    {
        if (! $game->thumb_url || $game->thumb_url === $originalThumbUrl) {
            return;
        }

        try {
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }

            app(ImageProcessingService::class)
                ->setProgressReporter(fn (string $message) => $this->line($message))
                ->processGameThumbnail($game);
            $this->info('  - Thumbnail changed; optimized image reprocessed');
        } catch (Exception $e) {
            Log::error('Failed to process thumbnail after watchlist cover update', [
                'game_id' => $game->id,
                'old_thumb_url' => $originalThumbUrl,
                'new_thumb_url' => $game->thumb_url,
                'error' => $e->getMessage(),
            ]);
            $this->warn('  - Thumbnail changed but optimized image processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Merge tags declared in an itch.io collection blurb into the game's
     * custom tag field. Collection tags are additive so a watchlist update
     * cannot wipe manually-curated custom tags when the blurb has no markers.
     *
     * @param  array<int, string>  $blurbTags
     */
    private function mergeCustomTags(?string $existingTags, array $blurbTags): string
    {
        $tagNames = array_filter(array_map('trim', explode(',', $existingTags ?? '')));

        foreach ($blurbTags as $slug) {
            $tagName = $this->formatCollectionTag($slug);
            if ($tagName !== '') {
                $tagNames[] = $tagName;
            }
        }

        $uniqueTags = [];
        foreach ($tagNames as $tagName) {
            $key = str($tagName)->slug()->toString();
            if ($key === '' || isset($uniqueTags[$key])) {
                continue;
            }
            $uniqueTags[$key] = $tagName;
        }

        return implode(', ', array_values($uniqueTags));
    }

    private function formatCollectionTag(string $slug): string
    {
        $tag = trim(preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $slug)) ?? '');

        return implode(' ', array_map(
            fn (string $word) => strtolower($word) === 'ai' ? 'AI' : ucfirst(strtolower($word)),
            array_filter(explode(' ', $tag), fn (string $word) => $word !== '')
        ));
    }
}
