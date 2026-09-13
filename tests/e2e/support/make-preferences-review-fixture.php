<?php

declare(strict_types=1);
use App\Models\Rating;

ob_start();
require __DIR__ . '/make-account-list-fixture.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$review = Rating::withoutEvents(fn () => Rating::create([
    'user_id' => $user->id, 'game_id' => $game->id, 'rating' => 2, 'review' => '<p>Original review</p>',
    'is_visible' => true, 'is_reviewed' => true, 'has_spoilers' => false,
    'source_platform' => 'fvn_li', 'published_at' => '2026-05-03T12:00:00Z',
]));
$server->config->update(['new_game_embed' => ['title' => 'Fixture embed'], 'update_embed' => ['title' => 'Update fixture']]);
echo json_encode([...$fixture, 'reviewId' => $review->id], JSON_THROW_ON_ERROR);
