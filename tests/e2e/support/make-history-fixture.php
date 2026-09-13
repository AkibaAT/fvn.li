<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rater;
use App\Models\Rating;
use App\Services\RouteGraphService;

ob_start();
require __DIR__ . '/make-game-view-fixture.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

$graph = ['graph_revision' => RouteGraphService::GRAPH_REVISION, 'has_graph_data' => true, 'nodes' => [], 'edges' => []];
$version->forceFill(['route_graph_data' => $graph])->saveQuietly();
$older = GameVersion::withoutEvents(fn () => GameVersion::factory()->create([
    'game_id' => $game->id,
    'version' => 'older',
    'is_latest' => false,
    'published_at' => '2025-01-01',
    'route_graph_data' => $graph,
]));
$rater = Rater::factory()->create();
for ($i = 1; $i <= 12; $i++) {
    $ratedGame = Game::withoutEvents(fn () => Game::factory()->create([
        'name' => "History fixture {$suffix} {$i}",
        'is_visible' => true,
        'is_nsfw' => false,
        'is_paid' => false,
        'thumb_url' => null,
    ]));
    Rating::withoutEvents(fn () => Rating::create([
        'game_id' => $ratedGame->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => "History review {$i}",
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now()->subMinutes($i),
    ]));
}

echo json_encode([...$fixture, 'olderVersionId' => $older->id, 'raterId' => $rater->id, 'gameSearch' => "History fixture {$suffix}"], JSON_THROW_ON_ERROR);
