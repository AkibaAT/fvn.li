<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\ProcessedEvent;
use App\Services\ItchAuthService;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFeed extends Command
{
    protected $signature = 'feed:process';
    protected $description = 'Process the itch.io feed for game updates';

    private ItchAuthService $authService;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting feed processing');

        try {
            // Get authenticated client
            $client = $this->authService->getClient();

            // Get highest event ID we've processed
            $lastProcessed = ProcessedEvent::query()
                ->orderByDesc('event_id')
                ->first();

            $lastEventId = $lastProcessed?->event_id;
            $currentPage = null;

            while (true) {
                $this->info('Processing page ' . ($currentPage ? "from event {$currentPage}" : '(initial)'));

                $nextPage = $this->processFeedPage($client, $currentPage);

                if (! $nextPage) {
                    $this->info('No more pages to process');
                    break;
                }

                if ($lastEventId && $nextPage <= $lastEventId) {
                    $this->info("Reached already processed event {$lastEventId}");
                    break;
                }

                $currentPage = $nextPage;
                sleep(30); // Rate limiting between pages
            }

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

            // Skip if already processed
            if (ProcessedEvent::where('event_id', $eventId)->exists()) {
                continue;
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
                continue;
            }

            // Process game update
            $this->processGameUpdate($client, $eventId, $gameId);
        }

        return $nextPage;
    }

    /**
     * Process an individual game update
     */
    private function processGameUpdate($client, int $eventId, int $gameId): void
    {
        DB::beginTransaction();

        // Get or create game
        $game = Game::firstOrNew(['game_id' => $gameId]);

        try {
            // Skip if game isn't visible
            if (! $game->exists || ! $game->is_visible) {
                DB::commit();

                return;
            }

            $this->info("Processing update for game {$gameId}: {$game->name}");

            // Refresh game version info
            $game->refreshVersion($client);
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
