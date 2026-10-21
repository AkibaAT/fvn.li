<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\RatingStatsCacheService;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

function snapshotRating(Game $game, array $attributes = []): Rating
{
    return Rating::create(array_merge([
        'event_id' => fake()->unique()->numberBetween(1000, 999999),
        'game_id' => $game->id,
        'rater_id' => Rater::factory()->create()->id,
        'rating' => 5,
        'review' => 'Review',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ], $attributes));
}

it('answers every ratings filter combination from the snapshot', function () {
    $listed = Game::factory()->create(['is_visible' => true]);
    $unlisted = Game::factory()->create(['is_visible' => false]);
    snapshotRating($listed, ['rating' => 5]);
    snapshotRating($listed, ['rating' => 3, 'is_reviewed' => false, 'review' => '']);
    snapshotRating($listed, ['rating' => 5, 'source_platform' => 'steam']);
    snapshotRating($listed, ['rating' => 1, 'is_visible' => false]);
    snapshotRating($unlisted, ['rating' => 5]);

    expect(RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: true, platform: null))->toBe(2)
        ->and(RatingStatsCacheService::count(onlyReviews: false, stars: null, visibleGamesOnly: true, platform: null))->toBe(3)
        ->and(RatingStatsCacheService::count(onlyReviews: false, stars: 3, visibleGamesOnly: true, platform: null))->toBe(1)
        ->and(RatingStatsCacheService::count(onlyReviews: true, stars: 5, visibleGamesOnly: true, platform: 'steam'))->toBe(1)
        ->and(RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: false, platform: null))->toBe(3);
});

it('derives the global statistics for listed games from the snapshot', function () {
    $listed = Game::factory()->create(['is_visible' => true]);
    snapshotRating($listed, ['rating' => 5, 'published_at' => '2026-01-01 10:00:00']);
    snapshotRating($listed, ['rating' => 2, 'is_reviewed' => false, 'review' => '', 'published_at' => '2026-03-01 10:00:00']);
    snapshotRating(Game::factory()->create(['is_visible' => false]), ['rating' => 1]);

    $stats = RatingStatsCacheService::globalStats();

    expect($stats['first_rating'])->toBe('2026-01-01 10:00:00')
        ->and($stats['latest_rating'])->toBe('2026-03-01 10:00:00')
        ->and($stats['visible_games']['total_ratings'])->toBe(2)
        ->and($stats['visible_games']['reviewed_count'])->toBe(1)
        ->and($stats['visible_games']['average_rating'])->toEqual(3.5)
        ->and($stats['visible_games']['unique_games'])->toBe(1)
        ->and($stats['visible_games']['rating_distribution'])->toBe([1 => 0, 2 => 1, 3 => 0, 4 => 0, 5 => 1]);
});

it('serves the previous snapshot after a rating change and refreshes it once the response is sent', function () {
    $game = Game::factory()->create(['is_visible' => true]);
    snapshotRating($game);
    $count = fn () => RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: true, platform: null);

    expect($count())->toBe(1);

    snapshotRating($game);

    expect($count())->toBe(1);

    app(DeferredCallbackCollection::class)->invoke();

    expect($count())->toBe(2);
});

it('refreshes a stale snapshot after a web request is answered', function () {
    $game = Game::factory()->create(['is_visible' => true]);
    snapshotRating($game);
    $count = fn () => RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: true, platform: null);

    expect($count())->toBe(1);

    snapshotRating($game);

    $this->get(route('ratings.index'))->assertOk();

    expect(Cache::has('ratings.cube.visible-games.dirty'))->toBeFalse()
        ->and($count())->toBe(2);
});

it('waits for an in-flight build instead of computing a missing snapshot alongside it', function () {
    $game = Game::factory()->create(['is_visible' => true]);
    snapshotRating($game);
    Cache::lock('lock:ratings.cube.visible-games', 1)->get();
    $startedAt = microtime(true);

    expect(RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: true, platform: null))->toBe(1)
        ->and(microtime(true) - $startedAt)->toBeGreaterThan(0.5);
});

it('refreshes both snapshots from the scheduled command', function () {
    $game = Game::factory()->create(['is_visible' => true]);
    snapshotRating($game);
    RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: true, platform: null);
    Rating::withoutEvents(fn () => snapshotRating($game));

    $this->artisan('ratings:refresh-stats')->assertSuccessful();

    expect(RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: true, platform: null))->toBe(2)
        ->and(RatingStatsCacheService::count(onlyReviews: true, stars: null, visibleGamesOnly: false, platform: null))->toBe(2);
});
