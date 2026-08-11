<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;

it('loads public rater game history including superseded ratings but without moderated ones', function () {
    $game = Game::factory()->create();
    $rater = Rater::factory()->create();

    $moderatedRating = Rating::create([
        'event_id' => 1001,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 3,
        'is_reviewed' => true,
        'is_visible' => false,
        'is_moderation_hidden' => true,
        'review' => 'Hidden moderated review.',
        'source_platform' => 'itch_io',
        'published_at' => now()->subDays(2),
    ]);

    $supersededRating = Rating::create([
        'event_id' => 1002,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 4,
        'is_reviewed' => true,
        'is_visible' => false,
        'review' => 'Earlier revision of the review.',
        'source_platform' => 'itch_io',
        'published_at' => now()->subDay(),
    ]);

    $currentRating = Rating::create([
        'event_id' => 1003,
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'is_reviewed' => true,
        'is_visible' => true,
        'review' => 'Current review.',
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $this->getJson(route('raters.games.history', [
        'rater' => $rater->id,
        'game' => $game->id,
    ]))
        ->assertOk()
        ->assertJsonPath('game.id', $game->id)
        ->assertJsonPath('ratings.0.id', $currentRating->id)
        ->assertJsonPath('ratings.1.id', $supersededRating->id)
        ->assertJsonCount(2, 'ratings')
        ->assertJsonMissing(['review' => 'Hidden moderated review.']);
});
