<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameJam;
use App\Models\Tag;
use App\Services\GameFilterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('scout.driver', 'null');
});

test('filter options exclude tags used by fewer than the minimum game count', function () {
    Config::set('fvn.min_tag_game_count', 5);
    GameFilterService::clearCache();

    $popular = Tag::create(['name' => 'Popular Romance']);
    $rare = Tag::create(['name' => 'Rare Niche']);

    $games = Game::factory()->count(5)->create(['is_visible' => true]);
    foreach ($games as $game) {
        $game->tags()->attach($popular->id);
    }

    Game::factory()->create(['is_visible' => true])->tags()->attach($rare->id);

    GameFilterService::clearCache();
    $options = GameFilterService::getOptions();

    expect($options['tags'])->toHaveKey((string) $popular->id)
        ->and($options['tags'][(string) $popular->id])->toContain('Popular Romance')
        ->and($options['tags'])->not->toHaveKey((string) $rare->id);
});

test('game jam changes clear cached filter options', function () {
    $cacheKey = 'react-game-filter-options:min-' . Tag::minPublicGameCount();
    Cache::put($cacheKey, ['gameJams' => []], 3600);

    GameJam::create([
        'name' => 'New Production Jam',
        'url' => 'https://itch.io/jam/new-production-jam',
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('game jam associations clear cached filter options', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
    ]);
    $jam = GameJam::create([
        'name' => 'Linked Production Jam',
        'url' => 'https://itch.io/jam/linked-production-jam',
    ]);

    $cacheKey = 'react-game-filter-options:min-' . Tag::minPublicGameCount();
    Cache::put($cacheKey, ['gameJams' => []], 3600);

    $game->pendingGameJamId = [$jam->id];
    $game->processPendingGameJams();

    expect(Cache::has($cacheKey))->toBeFalse();
});
