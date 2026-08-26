<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;
use App\Services\ItchAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGameReviewApiFixture(array $gameAttributes = []): array
{
    $game = Game::factory()->create(array_merge([
        'name' => 'Reviewed API Game',
        'platform' => 'itch_io',
        'itch_id' => 987654,
        'url' => ['itch_io' => 'https://developer.itch.io/reviewed-api-game'],
    ], $gameAttributes));
    $rater = Rater::factory()->create([
        'name' => 'API Reviewer',
        'external_platform' => 'itch_io',
    ]);

    $newest = Rating::create([
        'event_id' => 1001,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => 'Excellent route pacing.',
        'is_visible' => true,
        'is_reviewed' => true,
        'has_spoilers' => false,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);
    $olderRatingOnly = Rating::create([
        'event_id' => 1002,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 3,
        'review' => '',
        'is_visible' => true,
        'is_reviewed' => false,
        'has_spoilers' => false,
        'source_platform' => 'itch_io',
        'published_at' => now()->subDay(),
    ]);

    return [$game, $rater, $newest, $olderRatingOnly];
}

it('validates review lookup identifiers', function () {
    $this->getJson('/api/game-reviews')
        ->assertUnprocessable()
        ->assertJsonPath('error', 'At least one of url, game_id, or itch_game_id must be provided');

    $this->getJson('/api/game-reviews?url=not-a-url')
        ->assertUnprocessable()
        ->assertJsonPath('error.url.0', 'The url field must be a valid URL.');
});

it('returns aggregate review data by game id', function () {
    [$game, $rater, $newest] = createGameReviewApiFixture();

    $this->getJson('/api/game-reviews?game_id=' . $game->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('has_reviews', true)
        ->assertJsonPath('game.id', $game->id)
        ->assertJsonPath('game.name', 'Reviewed API Game')
        ->assertJsonPath('review_data.total_reviews', 2)
        ->assertJsonPath('review_data.average_rating', 4)
        ->assertJsonPath('review_data.rating_distribution.5.count', 1)
        ->assertJsonPath('review_data.rating_distribution.5.percentage', 50)
        ->assertJsonPath('review_data.rating_distribution.3.count', 1)
        ->assertJsonPath('review_data.recent_reviews.0.id', $newest->id)
        ->assertJsonPath('review_data.recent_reviews.0.rater.id', $rater->id)
        ->assertJsonPath('review_data.recent_reviews.0.rater.platform', 'itch_io');
});

it('resolves hidden games but excludes non-visible ratings from broad review API responses', function () {
    [$game, , $visibleReview] = createGameReviewApiFixture([
        'name' => 'Hidden Reviewed API Game',
        'is_visible' => false,
    ]);
    $rater = Rater::factory()->create([
        'name' => 'Hidden API Reviewer',
        'external_platform' => 'itch_io',
    ]);
    $hiddenReview = Rating::create([
        'event_id' => 2001,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 1,
        'review' => 'Moderation-hidden API review text.',
        'is_visible' => false,
        'is_reviewed' => true,
        'has_spoilers' => false,
        'source_platform' => 'itch_io',
        'published_at' => now()->addMinute(),
    ]);

    $this->getJson('/api/game-reviews?game_id=' . $game->id)
        ->assertOk()
        ->assertJsonPath('game.id', $game->id)
        ->assertJsonPath('game.name', 'Hidden Reviewed API Game')
        ->assertJsonPath('review_data.total_reviews', 2)
        ->assertJsonPath('review_data.recent_reviews.0.id', $visibleReview->id)
        ->assertJsonMissing(['id' => $hiddenReview->id])
        ->assertJsonMissing(['review' => 'Moderation-hidden API review text.']);

    $this->getJson('/api/game-reviews/paginated?' . http_build_query([
        'game_id' => $game->id,
        'show_all_ratings' => 'true',
        'per_page' => 10,
    ]))
        ->assertOk()
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonMissing(['id' => $hiddenReview->id])
        ->assertJsonMissing(['review' => 'Moderation-hidden API review text.']);
});

it('finds games by itch id, direct URL, normalized URL, and extracted itch game id', function () {
    [$game] = createGameReviewApiFixture();
    $fallbackGame = Game::factory()->create([
        'name' => 'Fallback ID Game',
        'platform' => 'itch_io',
        'itch_id' => 111222,
        'url' => ['itch_io' => 'https://fallback.itch.io/id-game'],
    ]);

    $itchAuth = Mockery::mock(ItchAuthService::class);
    $itchAuth->shouldReceive('getGameId')
        ->once()
        ->with('https://unknown.itch.io/id-game')
        ->andReturn(111222);
    $this->app->instance(ItchAuthService::class, $itchAuth);

    $this->getJson('/api/game-reviews?itch_game_id=' . $game->itch_id)
        ->assertOk()
        ->assertJsonPath('game.id', $game->id);

    $this->getJson('/api/game-reviews?url=' . urlencode('https://developer.itch.io/reviewed-api-game'))
        ->assertOk()
        ->assertJsonPath('game.id', $game->id);

    $this->getJson('/api/game-reviews?url=' . urlencode('http://developer.itch.io/reviewed-api-game/?source=launcher'))
        ->assertOk()
        ->assertJsonPath('game.id', $game->id);

    $this->getJson('/api/game-reviews?url=' . urlencode('https://unknown.itch.io/id-game'))
        ->assertOk()
        ->assertJsonPath('game.id', $fallbackGame->id);
});

it('returns not found when no matching itch game exists', function () {
    $itchAuth = Mockery::mock(ItchAuthService::class);
    $itchAuth->shouldReceive('getGameId')
        ->once()
        ->andThrow(new RuntimeException('No game id'));
    $this->app->instance(ItchAuthService::class, $itchAuth);

    $this->getJson('/api/game-reviews?url=' . urlencode('https://missing.itch.io/game'))
        ->assertNotFound()
        ->assertJsonPath('error', 'Game not found')
        ->assertJsonPath('has_reviews', false)
        ->assertJsonPath('review_data', null);
});

it('does not fetch non itch urls when resolving review lookups', function () {
    $itchAuth = Mockery::mock(ItchAuthService::class);
    $itchAuth->shouldNotReceive('getGameId');
    $this->app->instance(ItchAuthService::class, $itchAuth);

    $this->getJson('/api/game-reviews?url=' . urlencode('http://127.0.0.1:8765/internal-only?token=secret'))
        ->assertNotFound()
        ->assertJsonPath('error', 'Game not found')
        ->assertJsonPath('has_reviews', false);

    $this->getJson('/api/game-reviews/paginated?url=' . urlencode('http://169.254.169.254/latest/meta-data/'))
        ->assertNotFound()
        ->assertJsonPath('error', 'Game not found');
});

it('does not fetch non https itch urls when resolving review lookups', function () {
    $itchAuth = Mockery::mock(ItchAuthService::class);
    $itchAuth->shouldNotReceive('getGameId');
    $this->app->instance(ItchAuthService::class, $itchAuth);

    $this->getJson('/api/game-reviews?url=' . urlencode('http://missing.itch.io/game'))
        ->assertNotFound()
        ->assertJsonPath('error', 'Game not found')
        ->assertJsonPath('has_reviews', false);
});

it('includes site ratings in aggregate and paginated review API responses', function () {
    [$game] = createGameReviewApiFixture();
    $user = User::factory()->create(['name' => 'Site Reviewer']);
    $siteRating = Rating::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'rating' => 4,
        'review' => "Posted on the site.\n\nWould replay.",
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'fvn_li',
        'published_at' => now()->addMinute(),
    ]);

    $this->getJson('/api/game-reviews?game_id=' . $game->id)
        ->assertOk()
        ->assertJsonPath('review_data.total_reviews', 3)
        ->assertJsonPath('review_data.recent_reviews.0.id', $siteRating->id)
        ->assertJsonPath('review_data.recent_reviews.0.user.id', $user->id)
        ->assertJsonPath('review_data.recent_reviews.0.user.name', 'Site Reviewer')
        ->assertJsonPath('review_data.recent_reviews.0.rater', null);

    $this->getJson('/api/game-reviews/paginated?' . http_build_query([
        'game_id' => $game->id,
        'per_page' => 10,
    ]))
        ->assertOk()
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('reviews.0.id', $siteRating->id)
        ->assertJsonPath('reviews.0.user.id', $user->id)
        ->assertJsonPath('reviews.0.rater', null);
});

it('returns paginated reviews with rating and review-only filters', function () {
    [$game, , $newest, $olderRatingOnly] = createGameReviewApiFixture();

    $this->getJson('/api/game-reviews/paginated?' . http_build_query([
        'game_id' => $game->id,
        'rating_filter' => 5,
        'per_page' => 1,
        'page' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('reviews.0.id', $newest->id)
        ->assertJsonPath('reviews.0.rating', 5)
        ->assertJsonPath('reviews.0.is_reviewed', true)
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('pagination.has_more', false)
        ->assertJsonPath('filters.rating_filter', 5)
        ->assertJsonPath('filters.show_all_ratings', false);

    $this->getJson('/api/game-reviews/paginated?' . http_build_query([
        'game_id' => $game->id,
        'show_all_ratings' => 'true',
        'per_page' => 5,
    ]))
        ->assertOk()
        ->assertJsonPath('pagination.total', 2)
        ->assertJsonPath('reviews.1.id', $olderRatingOnly->id)
        ->assertJsonPath('filters.show_all_ratings', true);
});
