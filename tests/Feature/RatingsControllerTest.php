<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function createRatingRecord(array $attributes = []): Rating
{
    $game = $attributes['game'] ?? Game::factory()->create([
        'name' => 'Rated Game',
        'is_visible' => true,
        'url' => ['itch_io' => 'https://example.itch.io/rated-game'],
        'platform' => 'itch_io',
    ]);
    unset($attributes['game']);

    $rater = $attributes['rater'] ?? Rater::factory()->create([
        'name' => 'Known Rater',
        'external_platform' => 'itch_io',
    ]);
    unset($attributes['rater']);

    return Rating::create(array_merge([
        'event_id' => fake()->unique()->numberBetween(1000, 999999),
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 4,
        'review' => '<p>Strong pacing and memorable scenes.</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'has_spoilers' => false,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ], $attributes));
}

it('renders the ratings index with filters, sanitized reviews, and global stats', function () {
    $itchRater = Rater::factory()->create([
        'name' => 'Itch Reviewer',
        'external_platform' => 'itch_io',
    ]);
    $steamRater = Rater::factory()->create([
        'name' => 'Steam Reviewer',
        'external_platform' => 'steam',
    ]);
    $visibleGame = Game::factory()->create([
        'name' => 'Visible Rated Game',
        'is_visible' => true,
        'url' => ['itch_io' => 'https://visible.example/game'],
    ]);
    $hiddenGame = Game::factory()->create([
        'name' => 'Hidden Rated Game',
        'is_visible' => false,
    ]);

    $matchingRating = createRatingRecord([
        'game' => $visibleGame,
        'rater' => $itchRater,
        'rating' => 5,
        'review' => '<script>alert("x")</script><p>Excellent route work.</p>',
        'published_at' => now()->subHour(),
    ]);
    createRatingRecord([
        'game' => $visibleGame,
        'rater' => $steamRater,
        'rating' => 5,
        'review' => 'Steam review.',
    ]);
    createRatingRecord([
        'game' => $hiddenGame,
        'rater' => $itchRater,
        'rating' => 5,
        'review' => 'Hidden game review.',
    ]);
    createRatingRecord([
        'game' => $visibleGame,
        'rater' => $itchRater,
        'rating' => 3,
        'is_reviewed' => false,
        'review' => '',
    ]);

    $response = $this->get(route('ratings.index', [
        'platform' => 'itch_io',
        'stars' => 5,
        'showOnlyReviews' => 'true',
        'showOnlyVisibleGames' => 'true',
        'sortField' => 'rating',
        'sortDirection' => 'asc',
        'perPage' => 2,
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('ratings/index')
        ->and($props['filters']['platform'])->toBe('itch_io')
        ->and($props['filters']['stars'])->toBe(5)
        ->and($props['filters']['sortField'])->toBe('rating')
        ->and($props['ratings']['total'])->toBe(1)
        ->and($props['ratings']['data'][0]['id'])->toBe($matchingRating->id)
        ->and($props['ratings']['data'][0]['review'])->toContain('Excellent route work')
        ->and($props['ratings']['data'][0]['review'])->not->toContain('<script')
        ->and($props['stats']['visible_games']['total_ratings'])->toBeGreaterThanOrEqual(2);
});

it('normalizes invalid ratings index filters to safe defaults', function () {
    createRatingRecord(['rating' => 4]);

    $response = $this->get(route('ratings.index', [
        'stars' => 99,
        'showOnlyReviews' => 'false',
        'showOnlyVisibleGames' => 'false',
        'sortField' => 'unknown',
        'sortDirection' => 'sideways',
        'perPage' => 500,
        'page' => -2,
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['filters']['stars'])->toBeNull()
        ->and($props['filters']['sortField'])->toBe('published_at')
        ->and($props['filters']['sortDirection'])->toBe('desc')
        ->and($props['ratings']['current_page'])->toBe(1)
        ->and($props['ratings']['per_page'])->toBe(100);
});

it('renders a rater page with filtered ratings, previous hidden counts, stats, and phrases', function () {
    $rater = Rater::factory()->create(['name' => 'Phrase Rater']);
    $visibleGame = Game::factory()->create([
        'name' => 'Visible Game',
        'is_visible' => true,
    ]);
    $hiddenGame = Game::factory()->create([
        'name' => 'Hidden Game',
        'is_visible' => false,
    ]);

    $current = createRatingRecord([
        'game' => $visibleGame,
        'rater' => $rater,
        'rating' => 4,
        'review' => 'Memorable route ending. Memorable route ending.',
        'is_visible' => true,
        'is_reviewed' => true,
    ]);
    createRatingRecord([
        'game' => $visibleGame,
        'rater' => $rater,
        'rating' => 2,
        'review' => 'Old visible false review.',
        'is_visible' => false,
        'is_reviewed' => true,
        'published_at' => now()->subDay(),
    ]);
    createRatingRecord([
        'game' => $hiddenGame,
        'rater' => $rater,
        'rating' => 5,
        'review' => 'Hidden game text.',
        'is_visible' => true,
        'is_reviewed' => true,
    ]);

    $response = $this->get(route('raters.show', [
        'rater' => $rater->id,
        'showOnlyVisibleGames' => 'true',
        'showOnlyReviews' => 'true',
        'sortField' => 'rating',
        'sortDirection' => 'desc',
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('raters/show')
        ->and($props['rater']['id'])->toBe($rater->id)
        ->and($props['ratings']['total'])->toBe(1)
        ->and($props['ratings']['data'][0]['id'])->toBe($current->id)
        ->and($props['previousRatingCounts'][$visibleGame->id])->toBe(1)
        ->and($props['stats']['all_games']['total_ratings'])->toBe(2)
        ->and($props['stats']['visible_games']['total_ratings'])->toBe(1)
        ->and($props['filters']['sortField'])->toBe('rating');
});

it('bounds rater phrase mining to a recent cached review sample', function () {
    $rater = Rater::factory()->create(['name' => 'Bounded Phrase Rater']);

    for ($i = 0; $i < 80; $i++) {
        createRatingRecord([
            'rater' => $rater,
            'review' => str_repeat('Modern route scene. ', 20),
            'published_at' => now()->subMinutes($i),
        ]);
    }

    createRatingRecord([
        'rater' => $rater,
        'review' => str_repeat('Ancient willow lantern. ', 20),
        'published_at' => now()->subDays(30),
    ]);
    createRatingRecord([
        'rater' => $rater,
        'review' => str_repeat('Ancient willow lantern. ', 20),
        'published_at' => now()->subDays(31),
    ]);

    $response = $this->get(route('raters.show', ['rater' => $rater->id]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect(array_key_exists('ancient willow lantern', $props['phrases']))->toBeFalse()
        ->and(Cache::has("rater_phrases_v2_{$rater->id}"))->toBeTrue();
});

it('returns a 404 for a missing rater on a full rater page load', function () {
    $this->get(route('raters.show', ['rater' => 999999]))->assertNotFound();
});

it('renders a single review detail with relationships and sanitized content', function () {
    $user = User::factory()->create(['name' => 'Local Reviewer']);
    $game = Game::factory()->create([
        'name' => 'Review Detail Game',
        'is_visible' => true,
    ]);
    $rating = createRatingRecord([
        'game' => $game,
        'user_id' => $user->id,
        'review' => '<p>Helpful detail.</p><script>bad()</script>',
        'rating' => 3,
        'has_spoilers' => true,
    ]);

    $response = $this->get(route('reviews.show', $rating));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('reviews/show')
        ->and($props['review']['id'])->toBe($rating->id)
        ->and($props['review']['game']['id'])->toBe($game->id)
        ->and($props['review']['user']['id'])->toBe($user->id)
        ->and($props['review']['review'])->toContain('Helpful detail')
        ->and($props['review']['review'])->not->toContain('<script')
        ->and($props['review']['has_spoilers'])->toBeTrue();
});

it('does not render invisible reviews on the review detail route', function () {
    $rating = createRatingRecord(['is_visible' => false]);

    $this->get(route('reviews.show', $rating))->assertNotFound();
});

it('renders user review listings with pagination and aggregate stats', function () {
    $user = User::factory()->create(['name' => 'Review User']);
    $gameA = Game::factory()->create(['name' => 'Game A']);
    $gameB = Game::factory()->create(['name' => 'Game B']);
    createRatingRecord([
        'game' => $gameA,
        'user_id' => $user->id,
        'rating' => 5,
        'is_reviewed' => true,
        'published_at' => now()->subDay(),
    ]);
    createRatingRecord([
        'game' => $gameB,
        'user_id' => $user->id,
        'rating' => 3,
        'is_reviewed' => false,
        'review' => '',
        'published_at' => now(),
    ]);
    createRatingRecord([
        'user_id' => $user->id,
        'rating' => 1,
        'is_visible' => false,
    ]);

    $response = $this->get(route('users.reviews', [
        'user' => $user->id,
        'sortField' => 'rating',
        'sortDirection' => 'asc',
        'perPage' => 1,
    ]));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($response->viewData('page')['component'])->toBe('reviews/user')
        ->and($props['reviewUser']['id'])->toBe($user->id)
        ->and($props['reviews']['total'])->toBe(2)
        ->and($props['reviews']['per_page'])->toBe(1)
        ->and($props['reviews']['last_page'])->toBe(2)
        ->and($props['reviews']['data'][0]['rating'])->toBe(3)
        ->and($props['stats']['total_ratings'])->toBe(2)
        ->and($props['stats']['reviewed_count'])->toBe(1)
        ->and($props['stats']['average_rating'])->toBe(4.0)
        ->and($props['filters']['sortField'])->toBe('rating');
});

it('returns chronological visible and hidden history for a rater game pair', function () {
    $game = Game::factory()->create(['name' => 'History Game']);
    $rater = Rater::factory()->create();
    $older = createRatingRecord([
        'game' => $game,
        'rater' => $rater,
        'rating' => 2,
        'is_visible' => false,
        'published_at' => now()->subDays(2),
    ]);
    $newer = createRatingRecord([
        'game' => $game,
        'rater' => $rater,
        'rating' => 5,
        'is_visible' => true,
        'published_at' => now()->subDay(),
    ]);

    $this->getJson(route('raters.games.history', [
        'rater' => $rater->id,
        'game' => $game->id,
    ]))->assertOk()
        ->assertJsonPath('game.name', 'History Game')
        ->assertJsonPath('ratings.0.id', $newer->id)
        ->assertJsonPath('ratings.1.id', $older->id);
});
