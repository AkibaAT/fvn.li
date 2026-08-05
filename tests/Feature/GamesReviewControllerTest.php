<?php

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\User;

test('game reviews endpoint filters visible reviews, sanitizes text, and exposes available ratings', function () {
    $game = Game::factory()->create();
    $rater = Rater::factory()->create(['name' => 'External Reviewer']);
    $user = User::factory()->create(['name' => 'Site Reviewer']);

    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => '<p>Great VN.</p><script>alert("x")</script>',
        'is_visible' => true,
        'is_reviewed' => true,
        'published_at' => now(),
        'event_id' => 1,
        'source_platform' => 'itch_io',
    ]);
    Rating::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'rating' => 3,
        'review' => '',
        'is_visible' => true,
        'is_reviewed' => false,
        'published_at' => now()->subDay(),
        'event_id' => 2,
        'source_platform' => 'fvn_li',
    ]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 1,
        'review' => 'Hidden text',
        'is_visible' => false,
        'is_reviewed' => true,
        'published_at' => now()->subDays(2),
        'event_id' => 3,
        'source_platform' => 'itch_io',
    ]);
    Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 1,
        'review' => 'Moderated text',
        'is_visible' => false,
        'is_moderation_hidden' => true,
        'is_reviewed' => true,
        'published_at' => now()->subDays(3),
        'event_id' => 4,
        'source_platform' => 'itch_io',
    ]);

    $this
        ->getJson(route('browser-api.games.reviews', [
            'game' => $game->id,
            'perPage' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'reviews.data')
        ->assertJsonPath('reviews.data.0.rating', 5)
        ->assertJsonPath('reviews.data.0.previous_ratings_count', 1)
        ->assertJsonPath('availableRatings', [5])
        ->assertJsonMissing(['review' => '<script>alert("x")</script>']);

    $this
        ->getJson(route('browser-api.games.reviews', [
            'game' => $game->id,
            'showAllRatings' => 'true',
            'selectedRating' => 3,
            'perPage' => 10,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'reviews.data')
        ->assertJsonPath('reviews.data.0.rating', 3)
        ->assertJsonPath('reviews.data.0.previous_ratings_count', 0)
        ->assertJsonPath('availableRatings', [3, 5]);
});

test('game reviews endpoint validates filter parameters', function () {
    $game = Game::factory()->create();

    $this
        ->getJson(route('browser-api.games.reviews', [
            'game' => $game->id,
            'selectedRating' => 6,
            'perPage' => 500,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['selectedRating', 'perPage']);
});
