<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\ManagesFlareSolverrSession;
use App\Models\Game;
use App\Models\ProcessedEvent;
use App\Services\ItchAuthService;
use Carbon\Carbon;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillFeed extends Command
{
    use ManagesFlareSolverrSession;

    protected $signature = 'feed:backfill
        {--months=6 : Number of months to backfill (default: 6)}';

    protected $description = 'Backfill missed feed events from the past N months';

    private ItchAuthService $authService;

    private int $processedCount = 0;

    private int $skippedCount = 0;

    private array $latestEventPerGame = [];

    private Carbon $cutoffDate;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    /**
     * Execute the console command.
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    public function handle(): int
    {
        return $this->executeWithFlareSolverrSession(function () {
            return $this->executeBackfill();
        });
    }

    /**
     * Execute the backfill logic
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    private function executeBackfill(): int
    {
        // Use sync mode for Scout indexing in CLI to avoid queueing
        Config::set('scout.queue', false);

        $months = (int) $this->option('months');
        $this->cutoffDate = Carbon::now()->subMonths($months);

        $this->info("Starting feed backfill for events since {$this->cutoffDate->format('Y-m-d')}");
        $this->info('+ = processed event, . = skipped event (already processed newer), x = skipped (before cutoff date)');

        // Reset counters
        $this->processedCount = 0;
        $this->skippedCount = 0;

        // Build a map of the latest event ID we've already processed for each game
        $this->info('Building map of latest processed events per game...');
        $this->buildLatestEventMap();
        $this->info('Found ' . count($this->latestEventPerGame) . ' games with processed events');

        try {
            $currentPage = null;
            $pageCount = 0;

            while (true) {
                $pageCount++;
                $this->info("\nProcessing page {$pageCount}" . ($currentPage ? " (from event {$currentPage})" : ' (initial)'));

                // Get authenticated client for feed page
                $client = $this->authService->getClient();
                $result = $this->processFeedPage($client, $currentPage);

                if (! $result) {
                    $this->info("\nNo more pages to process or reached cutoff date");
                    break;
                }

                [$nextPage, $reachedCutoff] = $result;

                if ($reachedCutoff) {
                    $this->info("\nReached cutoff date of {$this->cutoffDate->format('Y-m-d')}");
                    break;
                }

                if (! $nextPage) {
                    $this->info("\nNo more pages available");
                    break;
                }

                $currentPage = $nextPage;

                $this->info("\nWaiting 30 seconds before next page...");
                sleep(30); // Rate limiting between pages
            }

            $this->info("\nFeed backfill completed. Processed {$this->processedCount} events, skipped {$this->skippedCount} events.");

            return 0;
        } catch (Exception $e) {
            $this->error('Error during backfill: ' . $e->getMessage());
            Log::error('Feed backfill failed', ['exception' => $e]);

            return 1;
        }
    }

    /**
     * Build a map of the latest event ID we've processed for each game
     */
    private function buildLatestEventMap(): void
    {
        $events = ProcessedEvent::query()
            ->select('game_id', DB::raw('MAX(event_id) as latest_event_id'))
            ->groupBy('game_id')
            ->get();

        foreach ($events as $event) {
            $this->latestEventPerGame[$event->game_id] = $event->latest_event_id;
        }
    }

    /**
     * Process a single feed page
     *
     * @return array|null Returns [nextPage, reachedCutoff] or null if no more pages
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    private function processFeedPage($client, ?int $fromEvent = null): ?array
    {
        $url = 'https://itch.io/my-feed?filter=posts&format=json';
        if ($fromEvent) {
            $url .= "&from_event={$fromEvent}";
        }

        $this->info("Fetching feed from: {$url}");

        $response = $client->get($url);
        $feedData = json_decode($response->getBody()->getContents(), true);

        // Get next page ID if available
        $nextPage = $feedData['next_page'] ?? null;

        if (! isset($feedData['content'])) {
            return null;
        }

        $doc = HTMLDocument::createFromString($feedData['content'], LIBXML_NOERROR);

        $reachedCutoff = false;

        // Process each event row
        foreach ($doc->querySelectorAll('div.event_row') as $eventRow) {
            // Get event ID from like button
            $likeBtn = $eventRow->querySelector('span.like_btn');
            if (! $likeBtn || ! $likeBtn->hasAttribute('data-like_url')) {
                continue;
            }

            $eventId = (int) basename(dirname($likeBtn->getAttribute('data-like_url')));

            // Get event timestamp to check against cutoff date
            $eventTime = $eventRow->querySelector('a.event_time');
            if ($eventTime && $eventTime->hasAttribute('title')) {
                $eventDate = Carbon::parse($eventTime->getAttribute('title'));
                if ($eventDate->lt($this->cutoffDate)) {
                    $this->output->write('x');
                    $reachedCutoff = true;

                    continue;
                }
            }

            // Extract game information
            $gameId = null;
            $gameTitle = null;
            $gameUrl = null;

            // First try game cell
            $gameCell = $eventRow->querySelector('div.game_cell');
            if ($gameCell && $gameCell->hasAttribute('data-game_id')) {
                $gameId = (int) $gameCell->getAttribute('data-game_id');

                // Get game link info
                $gameLink = $gameCell->querySelector('a.game_link');
                if ($gameLink) {
                    $gameUrl = $gameLink->getAttribute('href');
                }
            }

            // If no game cell, try summary
            if (! $gameId || ! $gameUrl) {
                $summary = $eventRow->querySelector('div.object_short_summary');
                if ($summary) {
                    $gameLink = $summary->querySelector('a');
                    if ($gameLink) {
                        $gameUrl = $gameLink->getAttribute('href');
                        $gameTitle = $gameLink->textContent;
                        try {
                            $gameId = $this->authService->getGameId($gameUrl);
                        } catch (Exception) {
                            continue;
                        }
                    }
                }
            }

            // Skip if we couldn't get essential game info
            if (! $gameId || ! $gameUrl) {
                $this->output->write('.');
                $this->skippedCount++;

                continue;
            }

            // Get game title from summary if we didn't get it from game cell
            if (! $gameTitle) {
                $summary = $eventRow->querySelector('div.object_short_summary');
                if ($summary) {
                    $gameLink = $summary->querySelector('a');
                    if ($gameLink) {
                        $gameTitle = $gameLink->textContent;
                    }
                }
            }

            if (! $gameTitle) {
                $this->output->write('.');
                $this->skippedCount++;

                continue;
            }

            // Check if we've already processed a newer event for this game
            if (isset($this->latestEventPerGame[$gameId]) && $eventId <= $this->latestEventPerGame[$gameId]) {
                $this->output->write('.');
                $this->skippedCount++;

                continue;
            }

            // Process game update
            $this->output->write('+');
            $this->processedCount++;
            $this->processGameUpdate($eventId, $gameId);

            // Update our map with this event
            if (! isset($this->latestEventPerGame[$gameId]) || $eventId > $this->latestEventPerGame[$gameId]) {
                $this->latestEventPerGame[$gameId] = $eventId;
            }
        }

        return [$nextPage, $reachedCutoff];
    }

    /**
     * Process an individual game update
     *
     * @throws Throwable
     */
    private function processGameUpdate(int $eventId, int $gameId): void
    {
        $gameStartTime = microtime(true);

        DB::beginTransaction();

        // Get or create game
        $game = Game::firstOrNew(['itch_id' => $gameId]);

        try {
            // Skip if game isn't visible
            if (! $game->exists || ! $game->is_visible) {
                DB::commit();
                $this->output->write('.');
                $this->skippedCount++;

                return;
            }

            $this->info("\n[BACKFILL] Processing update for game {$gameId}: {$game->name}");

            // Refresh game version info (only create NEW versions, don't update existing)
            $versionStartTime = microtime(true);
            $game->refreshVersion();
            $versionElapsed = round(microtime(true) - $versionStartTime, 2);
            $this->info("[BACKFILL] refreshVersion took {$versionElapsed}s");

            $game->error = null;
            $game->save();

            // Record that we processed this event
            ProcessedEvent::create([
                'event_id' => $eventId,
                'game_id' => $game->id,
            ]);

            $gameElapsed = round(microtime(true) - $gameStartTime, 2);
            $this->info("[BACKFILL] Total game processing took {$gameElapsed}s");

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing game update during backfill', [
                'game_id' => $gameId,
                'event_id' => $eventId,
                'exception' => $e,
            ]);
        }
    }
}
