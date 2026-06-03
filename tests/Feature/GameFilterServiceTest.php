<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameJam;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('scout.driver', 'null');
});

test('game jam changes clear cached filter options', function () {
    Cache::put('react-game-filter-options', ['gameJams' => []], 3600);

    GameJam::create([
        'name' => 'New Production Jam',
        'url' => 'https://itch.io/jam/new-production-jam',
    ]);

    expect(Cache::has('react-game-filter-options'))->toBeFalse();
});

test('game jam associations clear cached filter options', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
    ]);
    $jam = GameJam::create([
        'name' => 'Linked Production Jam',
        'url' => 'https://itch.io/jam/linked-production-jam',
    ]);

    Cache::put('react-game-filter-options', ['gameJams' => []], 3600);

    $game->pendingGameJamId = [$jam->id];
    $game->processPendingGameJams();

    expect(Cache::has('react-game-filter-options'))->toBeFalse();
});
