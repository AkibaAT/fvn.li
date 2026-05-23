<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\SteamReviewImportService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Queue::fake();
});

test('steam review sync preserves local reviews omitted by steam response', function () {
    $game = Game::factory()->create([
        'platform' => 'steam',
        'steam_app_id' => 12345,
    ]);

    $rater = Rater::factory()->create([
        'steam_id' => '76561198000000000',
        'external_platform' => 'steam',
    ]);

    $oldReview = Rating::create([
        'published_at' => now()->subYears(2),
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => 'Older review outside Steam API cutoff.',
        'is_visible' => true,
        'is_reviewed' => true,
        'external_id' => 'older-review',
        'source_platform' => 'steam',
        'external_metadata' => [],
    ]);

    $fetchedReview = Rating::create([
        'published_at' => now()->subDay(),
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => 'Fetched review.',
        'is_visible' => true,
        'is_reviewed' => true,
        'external_id' => 'fetched-review',
        'source_platform' => 'steam',
        'external_metadata' => [],
    ]);

    $service = new SteamReviewImportService();
    setSteamReviewImportClient($service, [
        'success' => 1,
        'reviews' => [
            [
                'recommendationid' => 'fetched-review',
                'review' => 'Fetched review.',
                'language' => 'english',
                'voted_up' => true,
                'timestamp_created' => now()->subDay()->timestamp,
                'author' => [
                    'steamid' => $rater->steam_id,
                    'playtime_forever' => 120,
                    'playtime_at_review' => 120,
                ],
            ],
        ],
    ]);

    $stats = $service->syncAllReviews($game);

    expect($stats['deleted'])->toBe(0)
        ->and(Rating::whereKey($oldReview->id)->exists())->toBeTrue()
        ->and(Rating::whereKey($fetchedReview->id)->exists())->toBeTrue();
});

function setSteamReviewImportClient(SteamReviewImportService $service, array $response): void
{
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode($response, JSON_THROW_ON_ERROR)),
    ]);

    $client = new Client([
        'handler' => HandlerStack::create($mockHandler),
    ]);

    $property = new ReflectionProperty($service, 'client');
    $property->setAccessible(true);
    $property->setValue($service, $client);
}
