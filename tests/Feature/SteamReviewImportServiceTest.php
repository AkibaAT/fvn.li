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

function steamImportGame(array $attributes = []): Game
{
    return Game::factory()->create(array_merge([
        'name' => 'Steam Import Game',
        'platform' => 'steam',
        'steam_app_id' => 123456,
        'url' => ['steam' => 'https://store.steampowered.com/app/123456/Steam_Import_Game/'],
    ], $attributes));
}

function steamReviewServiceWithResponses(array $responses): SteamReviewImportService
{
    $service = new SteamReviewImportService;
    $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

    $property = new ReflectionProperty($service, 'client');
    $property->setAccessible(true);
    $property->setValue($service, $client);

    return $service;
}

function steamReviewPayload(array $overrides = []): array
{
    return array_merge([
        'recommendationid' => 'rec-1',
        'review' => "[b]Great[/b]\n[url=https://example.com]Link[/url]\n[spoiler]secret[/spoiler]",
        'language' => 'english',
        'voted_up' => true,
        'votes_up' => 4,
        'votes_funny' => 1,
        'weighted_vote_score' => '0.75',
        'comment_count' => 2,
        'steam_purchase' => true,
        'received_for_free' => false,
        'written_during_early_access' => false,
        'timestamp_created' => 1_700_000_000,
        'timestamp_updated' => 1_700_000_500,
        'author' => [
            'steamid' => '76561198000000000',
            'playtime_forever' => 120,
            'playtime_at_review' => 60,
        ],
    ], $overrides);
}

it('imports Steam reviews, creates raters, converts BBCode, and preserves local Steam reviews missing from the response', function () {
    $game = steamImportGame();
    $staleRater = Rater::factory()->create([
        'name' => 'Stale Steam User',
        'external_platform' => 'steam',
        'steam_id' => 'stale',
    ]);
    $staleRating = Rating::create([
        'game_id' => $game->id,
        'rater_id' => $staleRater->id,
        'external_id' => 'stale-rec',
        'source_platform' => 'steam',
        'rating' => 1,
        'review' => 'Stale review',
        'is_visible' => true,
        'is_reviewed' => true,
        'published_at' => now(),
    ]);

    $service = steamReviewServiceWithResponses([
        new Response(200, [], json_encode([
            'success' => 1,
            'reviews' => [steamReviewPayload()],
        ])),
        new Response(200, [], '<profile><steamID><![CDATA[Steam Reviewer]]></steamID></profile>'),
    ]);

    $stats = $service->syncAllReviews($game);

    expect($stats)->toBe([
        'fetched' => 1,
        'imported' => 1,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0,
        'errors' => 0,
    ])->and(Rating::find($staleRating->id))->not->toBeNull();

    $rating = Rating::where('external_id', 'rec-1')->firstOrFail();

    expect($rating->game_id)->toBe($game->id)
        ->and($rating->rating)->toBe(5.0)
        ->and($rating->review)->toContain('<strong>Great</strong>')
        ->and($rating->review)->toContain('<a href="https://example.com" target="_blank" rel="noopener">Link</a>')
        ->and($rating->review)->toContain('class="spoiler"')
        ->and($rating->is_visible)->toBeTrue()
        ->and($rating->is_reviewed)->toBeTrue()
        ->and($rating->external_metadata['playtime_forever'])->toBe(120)
        ->and($rating->rater->name)->toBe('Steam Reviewer')
        ->and($rating->rater->steam_id)->toBe('76561198000000000');
});

it('updates existing Steam reviews and skips unchanged or invalid records', function () {
    $game = steamImportGame();
    $rater = Rater::factory()->create([
        'name' => 'Existing Steam User',
        'external_platform' => 'steam',
        'steam_id' => 'existing',
    ]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'external_id' => 'rec-update',
        'source_platform' => 'steam',
        'rating' => 1,
        'review' => 'Old review',
        'is_visible' => true,
        'is_reviewed' => true,
        'published_at' => now(),
    ]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'external_id' => 'rec-same',
        'source_platform' => 'steam',
        'rating' => 5,
        'review' => 'Same review',
        'is_visible' => true,
        'is_reviewed' => true,
        'external_metadata' => [
            'voted_up' => true,
            'votes_up' => 0,
            'votes_funny' => 0,
            'weighted_vote_score' => 0,
            'comment_count' => 0,
            'steam_purchase' => false,
            'received_for_free' => false,
            'written_during_early_access' => false,
            'playtime_forever' => 0,
            'playtime_at_review' => 0,
            'timestamp_updated' => null,
        ],
        'published_at' => now(),
    ]);

    $service = steamReviewServiceWithResponses([
        new Response(200, [], json_encode([
            'success' => 1,
            'reviews' => [
                steamReviewPayload([
                    'recommendationid' => 'rec-update',
                    'review' => 'Updated review',
                    'voted_up' => true,
                    'author' => ['steamid' => 'existing'],
                ]),
                steamReviewPayload([
                    'recommendationid' => 'rec-same',
                    'review' => 'Same review',
                    'voted_up' => true,
                    'votes_up' => 0,
                    'votes_funny' => 0,
                    'weighted_vote_score' => 0,
                    'comment_count' => 0,
                    'steam_purchase' => false,
                    'author' => ['steamid' => 'existing'],
                    'timestamp_updated' => null,
                ]),
                steamReviewPayload(['recommendationid' => null]),
                steamReviewPayload(['recommendationid' => 'no-text', 'review' => '   ']),
                steamReviewPayload(['recommendationid' => 'non-english', 'language' => 'german']),
            ],
        ])),
    ]);

    $stats = $service->syncAllReviews($game);

    expect($stats['fetched'])->toBe(5)
        ->and($stats['imported'])->toBe(0)
        ->and($stats['updated'])->toBe(1)
        ->and($stats['skipped'])->toBe(4)
        ->and($stats['deleted'])->toBe(0)
        ->and(Rating::where('external_id', 'rec-update')->first()?->review)->toBe('Updated review');
});

it('handles unsuccessful and failing Steam review API responses', function () {
    $game = steamImportGame();

    $unsuccessful = steamReviewServiceWithResponses([
        new Response(200, [], json_encode(['success' => 0, 'reviews' => []])),
    ]);

    expect($unsuccessful->syncAllReviews($game))->toBe([
        'fetched' => 0,
        'imported' => 0,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0,
        'errors' => 0,
    ]);

    $failing = steamReviewServiceWithResponses([
        new Response(500, [], 'server error'),
    ]);

    expect($failing->syncAllReviews($game)['errors'])->toBe(1);
});

it('rejects non-Steam games and Steam games without app IDs', function () {
    $service = new SteamReviewImportService;

    expect(fn () => $service->syncAllReviews(Game::factory()->create(['platform' => 'itch_io'])))
        ->toThrow(Exception::class, 'Game is not a Steam game');

    expect(fn () => $service->syncAllReviews(steamImportGame(['steam_app_id' => null])))
        ->toThrow(Exception::class, 'Game does not have a Steam App ID');
});

it('updates game rating stats from visible ratings', function () {
    $game = steamImportGame();
    $rater = Rater::factory()->create(['external_platform' => 'steam']);

    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => 'Visible',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'steam',
        'published_at' => now(),
    ]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 1,
        'review' => 'Hidden',
        'is_visible' => false,
        'is_reviewed' => true,
        'source_platform' => 'steam',
        'published_at' => now(),
    ]);

    (new SteamReviewImportService)->updateGameRatingStats($game);

    expect($game->fresh()->rating_score)->toBe(5.0)
        ->and($game->fresh()->rating_count)->toBe(1);
});
