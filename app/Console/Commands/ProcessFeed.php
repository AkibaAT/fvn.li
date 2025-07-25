<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\ImportState;
use App\Models\ProcessedEvent;
use App\Services\ItchAuthService;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFeed extends Command
{
    private const string IMPORT_STATE_TYPE = 'feed';

    protected $signature = 'feed:process';
    protected $description = 'Process the itch.io feed for game updates';

    private ItchAuthService $authService;
    private int $processedCount = 0;
    private int $skippedCount = 0;

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
        $this->info('Starting feed processing');
        $this->info('+ = processed event, . = skipped event');

        // Reset counters
        $this->processedCount = 0;
        $this->skippedCount = 0;

        try {
            // Use the ItchHttpClientService which now uses an authenticated client

            // Get the import state if it exists
            $importState = ImportState::firstWhere('type', self::IMPORT_STATE_TYPE);
            $currentPage = $importState?->last_processed_id;

            if ($currentPage) {
                $this->info("Resuming from previous import state at event {$currentPage}");
            }

            // Get highest event ID we've processed (for final check)
            $lastProcessed = ProcessedEvent::query()
                ->orderByDesc('event_id')
                ->first();
            $lastEventId = $lastProcessed?->event_id;

            while (true) {
                $this->info('Processing page ' . ($currentPage ? "from event {$currentPage}" : '(initial)'));

                // Get authenticated client for feed page
                $client = $this->authService->getClient();
                $nextPage = $this->processFeedPage($client, $currentPage);

                if (! $nextPage) {
                    $this->info("\nNo more pages to process");
                    // Clear import state when we reach the end
                    ImportState::where('type', self::IMPORT_STATE_TYPE)->delete();
                    break;
                }

                if ($lastEventId && $nextPage <= $lastEventId) {
                    $this->info("\nReached already processed event {$lastEventId}");
                    // Clear import state when we reach already processed events
                    ImportState::where('type', self::IMPORT_STATE_TYPE)->delete();
                    break;
                }

                $currentPage = $nextPage;

                // Update import state after each page
                ImportState::updateOrCreate(
                    ['type' => self::IMPORT_STATE_TYPE],
                    ['last_processed_id' => $currentPage]
                );

                $this->info("\nWaiting 30 seconds before next page...");
                sleep(30); // Rate limiting between pages
            }

            $this->info("\nFeed processing completed. Processed {$this->processedCount} events, skipped {$this->skippedCount} events.");

            return 0;
        } catch (Exception $e) {
            $this->error('Error processing feed: ' . $e->getMessage());
            Log::error('Feed processing failed', ['exception' => $e]);

            return 1;
        }
    }

    /**
     * Process a single feed page
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    private function processFeedPage($client, ?int $fromEvent = null): ?int
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

        // Process each event row
        foreach ($doc->querySelectorAll('div.event_row') as $eventRow) {
            // Get event ID from like button
            $likeBtn = $eventRow->querySelector('span.like_btn');
            if (! $likeBtn || ! $likeBtn->hasAttribute('data-like_url')) {
                continue;
            }

            $eventId = (int) basename(dirname($likeBtn->getAttribute('data-like_url')));

            // If we find an already processed event, clear the import state and stop processing
            if (ProcessedEvent::where('event_id', $eventId)->exists()) {
                $this->output->write('.');
                $this->skippedCount++;
                $this->info("\nFound already processed event {$eventId}, clearing import state");
                ImportState::where('type', self::IMPORT_STATE_TYPE)->delete();

                return null;
            }

            // Update the import state for each event we process
            ImportState::updateOrCreate(
                ['type' => self::IMPORT_STATE_TYPE],
                ['last_processed_id' => $eventId]
            );

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

            // Process game update
            $this->output->write('+');
            $this->processedCount++;
            $this->processGameUpdate($eventId, $gameId);
        }

        return $nextPage;
    }

    /**
     * Process an individual game update
     *
     * @throws Throwable
     */
    private function processGameUpdate(int $eventId, int $gameId): void
    {
        DB::beginTransaction();

        // Get or create game
        $game = Game::firstOrNew(['game_id' => $gameId]);

        try {
            // Skip if game isn't visible or is suspended
            if (! $game->exists || ! $game->is_visible || $game->is_suspended) {
                DB::commit();
                $this->output->write('.');
                $this->skippedCount++;

                return;
            }

            $this->info("Processing update for game {$gameId}: {$game->name}");

            // Refresh game version info
            $game->refreshVersion(true); // Force refresh
            $game->error = null;
            $game->save();

            // Record that we processed this event
            ProcessedEvent::create([
                'event_id' => $eventId,
                'game_id' => $gameId,
            ]);

            DB::commit();

            sleep(10); // Rate limiting between games
        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Error updating game {$gameId}: " . $e->getMessage());

            // Save error state outside transaction
            try {
                $game->error = $e->getMessage();
                $game->save();
            } catch (Exception $saveError) {
                Log::error('Failed to save game error state', [
                    'game_id' => $gameId,
                    'original_error' => $e->getMessage(),
                    'save_error' => $saveError->getMessage(),
                ]);
            }
        }
    }
}
