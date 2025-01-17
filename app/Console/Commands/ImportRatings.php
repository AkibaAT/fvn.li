<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\ItchAuthService;
use DateMalformedStringException;
use DateTime;
use DOMDocument;
use DOMXPath;
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

        $doc = new DOMDocument;
        @$doc->loadHTML($events['content']);

        $xpath = new DOMXPath($doc);
        $reviews = $xpath->query("//div[contains(@class, 'event_row')]");

        foreach ($reviews as $review) {
            $userId = null;
            $scripts = $xpath->query(".//script[@type='text/javascript']", $review);
            foreach ($scripts as $script) {
                if (preg_match('/user_id.*:(\d+)/', $script->textContent, $matches)) {
                    $userId = (int) $matches[1];
                    break;
                }
            }

            $userName = $xpath->query(".//a[@data-label='event_user' and @class='event_source_user']", $review)[0]->textContent;
            $userUsername = basename(
                explode(
                    '.',
                    $xpath->query(".//a[@data-label='event_user' and @class='event_source_user']", $review)[0]->getAttribute('href')
                )[0]
            );

            $eventTime = $xpath->query(".//a[contains(@class, 'event_time')]", $review)[0];
            $eventId = (int) basename($eventTime->getAttribute('href'));
            $updatedAt = new DateTime($eventTime->getAttribute('title'));

            $gameInfo = $xpath->query(".//a[contains(@class, 'object_title')]", $review)[0];
            $gameName = $gameInfo->textContent;
            $gameUrl = $gameInfo->getAttribute('href');

            $gameCell = $xpath->query(".//div[contains(@class, 'game_cell')]", $review)[0] ?? null;
            $gameId = $gameCell ? (int) $gameCell->getAttribute('data-game_id') : $this->authService->getGameId($gameUrl);

            $rating = count($xpath->query(".//span[contains(@class, 'icon-star')]", $review));

            $ratingBlurb = $xpath->query(".//div[contains(@class, 'rating_blurb')]", $review)[0] ?? null;
            $reviewText = $ratingBlurb ? trim($ratingBlurb->textContent) : '';

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
