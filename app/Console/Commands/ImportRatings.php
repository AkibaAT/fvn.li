<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
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
use Illuminate\Support\Facades\Log;

class ImportRatings extends Command
{
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
        try {
            // Get authenticated client
            $this->client = $this->authService->getClient();

            $endEventId = Rating::orderByDesc('event_id')->value('event_id');
            $startEventId = null;

            do {
                $startEventId = $this->importRatingsPage($startEventId);
                if ($startEventId && $endEventId && $startEventId <= $endEventId) {
                    $this->info("Reached already imported ratings at event {$startEventId}");
                    break;
                }
                if ($startEventId) {
                    sleep(30); // Rate limiting between pages
                }
            } while ($startEventId !== null);

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
    private function importRatingsPage(?int $fromEventId): ?int
    {
        $url = 'https://itch.io/feed?filter=ratings&format=json';
        if ($fromEventId) {
            $url .= '&from_event=' . $fromEventId;
        }

        $this->info("Fetching ratings from: {$url}");

        $response = $this->client->get($url);
        $events = json_decode($response->getBody()->getContents(), true);

        $startEventId = $events['next_page'] ?? null;

        if (! isset($events['content'])) {
            return null;
        }

        $doc = HTMLDocument::createFromString($events['content'], LIBXML_NOERROR);

        // Process each review
        foreach ($doc->querySelectorAll('div.event_row') as $review) {
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
        }

        return $startEventId;
    }

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
        // Skip if we already have this event
        if (Rating::where('event_id', $eventId)->exists()) {
            return;
        }

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

        // Mark previous ratings as not visible
        Rating::where('game_id', $game->id)
            ->where('rater_id', $rater->id)
            ->update(['is_visible' => false]);

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
    }
}
