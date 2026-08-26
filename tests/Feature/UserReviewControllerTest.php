<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;

function userReviewGame(array $attributes = []): Game
{
    return Game::factory()->create(array_merge([
        'name' => 'Reviewable Game',
        'is_visible' => true,
        'platform' => 'itch_io',
        'url' => ['itch_io' => 'https://example.itch.io/reviewable-game'],
    ], $attributes));
}

it('requires authentication for user review endpoints', function () {
    $game = userReviewGame();

    $this->getJson(route('browser-api.user-reviews.show', $game->id))->assertUnauthorized();
    $this->postJson(route('browser-api.user-reviews.store', $game->id), ['rating' => 4])->assertUnauthorized();
    $this->deleteJson(route('browser-api.user-reviews.destroy', $game->id))->assertUnauthorized();
});

it('creates sanitizes and updates the authenticated user review', function () {
    $user = User::factory()->create();
    $game = userReviewGame();

    $this->actingAs($user)
        ->getJson(route('browser-api.user-reviews.show', $game->id))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'review' => null,
        ]);

    $this->actingAs($user)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 5,
            'review' => '<p>Excellent route.</p><img src="/x.png"><script>alert("x")</script><iframe src="/embed"></iframe>',
            'has_spoilers' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Review submitted.')
        ->assertJsonPath('review.rating', 5)
        ->assertJsonPath('review.has_spoilers', true);

    $rating = Rating::where('user_id', $user->id)->where('game_id', $game->id)->firstOrFail();

    expect($rating->rating)->toBe(5.0)
        ->and($rating->review)->toBe('<p>Excellent route.</p>')
        ->and($rating->has_spoilers)->toBeTrue()
        ->and($rating->is_reviewed)->toBeTrue()
        ->and($rating->source_platform)->toBe('fvn_li');

    $this->actingAs($user)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 3,
            'review' => "Updated thoughts.\n\n\n\nStill recommended.",
            'has_spoilers' => false,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Review updated.')
        ->assertJsonPath('review.id', $rating->id)
        ->assertJsonPath('review.rating', 3)
        ->assertJsonPath('review.review', "Updated thoughts.\n\nStill recommended.")
        ->assertJsonPath('review.has_spoilers', false);

    expect($rating->fresh()->review)->toBe("Updated thoughts.\n\nStill recommended.")
        ->and(Rating::where('user_id', $user->id)->where('game_id', $game->id)->count())->toBe(1);
});

it('stores rating-only submissions as not reviewed', function () {
    $user = User::factory()->create();
    $game = userReviewGame();

    $this->actingAs($user)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 4,
            'review' => '',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $rating = Rating::where('user_id', $user->id)->where('game_id', $game->id)->firstOrFail();

    expect($rating->review)->toBe('')
        ->and($rating->is_reviewed)->toBeFalse();
});

it('sanitizes dangerous review html before storing and returning it', function () {
    $user = User::factory()->create();
    $game = userReviewGame();

    $response = $this->actingAs($user)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 5,
            'review' => '<div onmouseover="alert(1)">X</div><a href="javascript:alert(2)">link</a><script>alert(3)</script><p style="background-image:url(javascript:alert(4));color:red;text-align:center;position:absolute">Styled</p>',
            'has_spoilers' => false,
        ])
        ->assertOk();

    $expected = '<div>X</div><a rel="noopener">link</a><p style="color:red;text-align:center">Styled</p>';

    $rating = Rating::where('user_id', $user->id)->where('game_id', $game->id)->firstOrFail();

    expect($rating->review)->toBe($expected)
        ->and($response->json('review.review'))->toBe($expected);
});

it('validates user review submissions and blocks banned reviewers', function () {
    $user = User::factory()->create();
    $bannedUser = User::factory()->create(['is_review_banned' => true]);
    $game = userReviewGame();

    $this->actingAs($user)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 6,
            'review' => str_repeat('x', 10001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating', 'review']);

    $this->actingAs($bannedUser)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 4,
            'review' => 'This should not be accepted.',
        ])
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'You are not allowed to submit reviews.',
        ]);
});

it('shows and deletes only the authenticated user review', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $game = userReviewGame();

    Rating::create([
        'game_id' => $game->id,
        'user_id' => $otherUser->id,
        'rating' => 1,
        'review' => 'Other user review.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'fvn_li',
        'published_at' => now(),
    ]);

    $ownRating = Rating::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'rating' => 5,
        'review' => '<p>My review.</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'has_spoilers' => false,
        'source_platform' => 'fvn_li',
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('browser-api.user-reviews.show', $game->id))
        ->assertOk()
        ->assertJsonPath('review.id', $ownRating->id)
        ->assertJsonPath('review.review', '<p>My review.</p>');

    $this->actingAs($user)
        ->deleteJson(route('browser-api.user-reviews.destroy', $game->id))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Review deleted.',
        ]);

    expect(Rating::find($ownRating->id))->toBeNull()
        ->and(Rating::where('user_id', $otherUser->id)->where('game_id', $game->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->deleteJson(route('browser-api.user-reviews.destroy', $game->id))
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Review not found.',
        ]);
});

it('does not edit or delete linked imported ratings', function () {
    $user = User::factory()->create();
    $game = userReviewGame();
    $rater = Rater::factory()->create([
        'user_id' => $user->id,
        'external_platform' => 'itch_io',
    ]);
    $imported = Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 2,
        'review' => 'Imported itch review.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->getJson(route('browser-api.user-reviews.show', $game->id))
        ->assertOk()
        ->assertJsonPath('review', null);

    $this->actingAs($user)
        ->deleteJson(route('browser-api.user-reviews.destroy', $game->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('browser-api.user-reviews.store', $game->id), [
            'rating' => 5,
            'review' => 'My site review.',
        ])
        ->assertOk();

    expect($imported->fresh()->rating)->toBe(2.0)
        ->and($imported->fresh()->review)->toBe('Imported itch review.')
        ->and($imported->fresh()->source_platform)->toBe('itch_io')
        ->and(Rating::where('user_id', $user->id)->where('game_id', $game->id)->where('source_platform', 'fvn_li')->exists())->toBeTrue();
});
