<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\ManagesFlareSolverrSession;
use App\Models\Game;
use App\Models\ImportState;
use App\Models\ProcessedEvent;
use App\Services\ItchAuthService;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFeed extends Command
{
    use ManagesFlareSolverrSession;

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
        return $this->executeWithFlareSolverrSession(function () {
            return $this->executeFeedProcessing();
        });
    }

    /**
     * Execute the feed processing logic
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    private function executeFeedProcessing(): int
    {
        Config::set('scout.queue', false);

        $this->info('Starting feed processing');
        $this->info('+ = processed event, . = skipped event');

        $this->processedCount = 0;
        $this->skippedCount = 0;

        try {

            $importState = ImportState::firstWhere('type', self::IMPORT_STATE_TYPE);
            $currentPage = $importState?->last_processed_id;

            if ($currentPage) {
                $this->info("Resuming from previous import state at event {$currentPage}");
            }

            $lastProcessed = ProcessedEvent::query()
                ->orderBy('event_id', 'desc')
                ->first();
            $lastEventId = $lastProcessed?->event_id;

            while (true) {
                $this->info('Processing page ' . ($currentPage ? "from event {$currentPage}" : '(initial)'));

                $client = $this->authService->getClient();
                $nextPage = $this->processFeedPage($client, $currentPage);

                if (! $nextPage) {
                    $this->info("\nNo more pages to process");
                    ImportState::where('type', self::IMPORT_STATE_TYPE)->delete();
                    break;
                }

                if ($lastEventId && $nextPage <= $lastEventId) {
                    $this->info("\nReached already processed event {$lastEventId}");
                    ImportState::where('type', self::IMPORT_STATE_TYPE)->delete();
                    break;
                }

                $currentPage = $nextPage;

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

        $nextPage = $feedData['next_page'] ?? null;

        if (! isset($feedData['content'])) {
            return null;
        }

        $doc = HTMLDocument::createFromString($feedData['content'], LIBXML_NOERROR);

        foreach ($doc->querySelectorAll('div.event_row') as $eventRow) {
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

            ImportState::updateOrCreate(
                ['type' => self::IMPORT_STATE_TYPE],
                ['last_processed_id' => $eventId]
            );

            $gameId = null;
            $gameTitle = null;
            $gameUrl = null;

            $gameCell = $eventRow->querySelector('div.game_cell');
            if ($gameCell && $gameCell->hasAttribute('data-game_id')) {
                $gameId = (int) $gameCell->getAttribute('data-game_id');

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

            if (! $gameId || ! $gameUrl) {
                $this->output->write('.');
                $this->skippedCount++;

                continue;
            }

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
        $gameStartTime = microtime(true);

        DB::beginTransaction();

        $game = Game::firstOrNew(['itch_id' => $gameId]);

        try {
            if (! $game->exists) {
                $game->platform = 'itch_io';
            }

            if (! $game->exists || ! $game->is_visible) {
                DB::commit();
                $this->output->write('.');
                $this->skippedCount++;

                return;
            }

            $this->info("\n[TIMING] Processing update for game {$gameId}: {$game->name}");

            // Refresh game version info (only create NEW versions, don't update existing)
            $versionStartTime = microtime(true);
            $game->refreshVersion();
            $versionElapsed = round(microtime(true) - $versionStartTime, 2);
            $this->info("[TIMING] refreshVersion took {$versionElapsed}s");

            $game->error = null;
            $game->save();

            // Record that we processed this event
            ProcessedEvent::create([
                'event_id' => $eventId,
                'game_id' => $gameId,
            ]);

            DB::commit();

            $gameElapsed = round(microtime(true) - $gameStartTime, 2);
            $this->info("[TIMING] Total game update took {$gameElapsed}s");

            sleep(10); // Rate limiting between games
        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Error updating game {$gameId}: " . $e->getMessage());

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
