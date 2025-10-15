<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateGameRating;
use App\Models\Game;
use App\Models\ImportState;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\ItchAuthService;
use DateMalformedStringException;
use DateTime;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRatings extends Command
{
    private const string IMPORT_STATE_TYPE = 'ratings';

    protected $signature = 'ratings:import';

    protected $description = 'Import latest ratings from itch.io';

    private Client $client;

    private ItchAuthService $authService;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        // Use sync mode for Scout indexing in CLI to avoid queueing ratings
        Config::set('scout.queue', false);

        try {
            // Get authenticated client
            $this->client = $this->authService->getClient();

            // Get the import state
            $importState = ImportState::firstWhere('type', self::IMPORT_STATE_TYPE);

            // If we have a stored last_processed_id, start from there
            $startEventId = $importState?->last_processed_id;

            $successCount = 0;
            $errorCount = 0;
            $newRatingsCount = 0;
            $newRatingsInBatch = 0;

            do {
                try {
                    $startEventId = $this->importRatingsPage($startEventId, $newRatingsInBatch);
                    $newRatingsCount += $newRatingsInBatch;

                    if ($startEventId) {
                        $successCount++;
                        sleep(10); // Rate limiting between pages
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Error processing ratings page', [
                        'exception' => $e,
                        'start_event_id' => $startEventId,
                    ]);

                    // If we've had too many errors, stop processing
                    if ($errorCount >= 3) {
                        throw new Exception('Too many errors while processing ratings pages');
                    }

                    // Wait a bit longer before retrying after an error
                    sleep(60);
                }
            } while ($startEventId !== null);

            $this->info("Successfully processed {$successCount} pages with {$newRatingsCount} new ratings and {$errorCount} errors");
            Cache::forget('system_status.rating_stats');

            return 0;
        } catch (Exception $e) {
            $this->error('Error importing ratings: ' . $e->getMessage());
            Log::error('Ratings import failed: ' . $e->getMessage(), ['exception' => $e]);

            return 1;
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws GuzzleException
     */
    private function importRatingsPage(?int $fromEventId, int &$newRatingsCount = 0): ?int
    {
        $url = 'https://itch.io/feed?filter=ratings&format=json';
        if ($fromEventId) {
            $url .= '&from_event=' . $fromEventId;
        }

        $this->info("Fetching ratings from: {$url}");

        $response = $this->client->get($url);
        $events = json_decode($response->getBody()->getContents(), true);

        $nextEventId = $events['next_page'] ?? null;

        if (! isset($events['content'])) {
            return null;
        }

        $doc = HTMLDocument::createFromString($events['content'], LIBXML_NOERROR);
        $newRatingsCount = 0;

        // Process each review
        foreach ($doc->querySelectorAll('div.event_row') as $review) {
            try {
                DB::beginTransaction();

                // Extract user ID from script
                $script = $review->querySelector('script[type="text/javascript"]');
                preg_match('/user_id.*:(\d+)/', $script->textContent, $matches);
                $userId = (int) $matches[1];

                // Get user info
                $userLink = $review->querySelector('a.event_source_user');
                $userName = $userLink->textContent;
                $userUsername = basename(explode('.', $userLink->getAttribute('href'))[0]);

                // Get event timing
                $eventTime = $review->querySelector('a.event_time');
                $eventId = (int) basename($eventTime->getAttribute('href'));
                $updatedAt = new DateTime($eventTime->getAttribute('title'));

                // If we find an existing rating, clear the import state and stop processing
                if (Rating::where('event_id', $eventId)->exists()) {
                    DB::commit();
                    $this->output->write('.');
                    ImportState::where('type', self::IMPORT_STATE_TYPE)->delete();
                    $this->info("\nFound existing rating at event {$eventId}, clearing import state");

                    return null;
                }

                // Get game info
                $gameLink = $review->querySelector('a.object_title');
                $gameName = $gameLink->textContent;
                $gameUrl = $gameLink->getAttribute('href');

                // Get game ID (either from game cell or by fetching)
                $gameCell = $review->querySelector('div.game_cell');
                $gameId = $gameCell
                    ? (int) $gameCell->getAttribute('data-game_id')
                    : $this->authService->getGameId($gameUrl);

                // Count star rating (exact class match)
                $rating = count($review->querySelectorAll('span.icon-star'));

                // Get review text if present
                $ratingBlurb = $review->querySelector('div.rating_blurb');
                $reviewText = $ratingBlurb ? $ratingBlurb->ownerDocument->saveHTML($ratingBlurb) : '';

                $this->processRating(
                    $eventId,
                    $userId,
                    $userName,
                    $userUsername,
                    $updatedAt,
                    $gameId,
                    $gameName,
                    $gameUrl,
                    $rating,
                    $reviewText
                );

                // Update the last processed ID after each successful rating
                ImportState::updateOrCreate(
                    ['type' => self::IMPORT_STATE_TYPE],
                    ['last_processed_id' => $eventId]
                );

                $newRatingsCount++;
                $this->output->write('+');
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error processing individual rating', [
                    'exception' => $e,
                    'event_id' => $eventId ?? null,
                    'user_id' => $userId ?? null,
                    'game_id' => $gameId ?? null,
                ]);

                // Continue processing other ratings
                continue;
            }
        }

        return $nextEventId;
    }

    /**
     * @throws Exception
     */
    private function processRating(
        int $eventId,
        int $userId,
        string $userName,
        string $userUsername,
        DateTime $updatedAt,
        int $gameId,
        string $gameName,
        string $gameUrl,
        int $rating,
        string $reviewText
    ): void {
        // Get or create rater
        $rater = Rater::firstOrNew(['user_id' => $userId]);
        if (! $rater->exists || $rater->name !== $userName || $rater->username !== $userUsername) {
            $rater->name = $userName;
            $rater->username = $userUsername;
            $rater->save();
        }

        // Get or create game
        $game = Game::firstOrNew(['game_id' => $gameId]);
        if (! $game->exists) {
            $game->fill([
                'game_id' => $gameId,
                'name' => $gameName,
                'url' => $gameUrl,
            ]);
            $game->save();
        } elseif ($game->name !== $gameName || $game->url !== $gameUrl) {
            $game->name = $gameName;
            $game->url = $gameUrl;
            $game->save();
        }

        // Mark previous ratings as not visible (using Eloquent to trigger search index updates)
        Rating::where('game_id', $game->id)
            ->where('rater_id', $rater->id)
            ->get()
            ->each(function ($rating) {
                $rating->update(['is_visible' => false]);
            });

        // Create new rating
        Rating::create([
            'event_id' => $eventId,
            'published_at' => $updatedAt,
            'game_id' => $game->id,
            'rater_id' => $rater->id,
            'rating' => $rating,
            'review' => $reviewText,
            'is_visible' => true,
            'is_reviewed' => $reviewText !== '',
        ]);

        UpdateGameRating::dispatch($game->id);
    }
}
