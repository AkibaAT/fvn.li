<?php

use App\Models\Game;
use App\Models\Tag;
use App\Observers\TagObserver;
use App\Services\GamePageCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

test('tag observer refreshes visible related games and bumps recommendation cache version', function () {
    Config::set('scout.driver', null);
    Cache::put('games.recommendations.version', 0);
    Log::spy();

    $tag = Tag::create(['name' => 'Old Tag']);
    $visibleGame = Game::factory()->create(['is_visible' => true]);
    $hiddenGame = Game::factory()->create(['is_visible' => false]);
    $tag->games()->attach([$visibleGame->id, $hiddenGame->id]);

    $initialVersion = Cache::get('games.recommendations.version');

    $tag->name = 'New Tag';
    app(TagObserver::class)->updated($tag);

    expect(Cache::get('games.recommendations.version'))->toBe($initialVersion + 1);
    Log::shouldHaveReceived('info')
        ->with('Updated game search indexes for tag change', Mockery::on(
            fn (array $context) => $context['tag_id'] === $tag->id && $context['tag_name'] === 'New Tag'
        ))
        ->atLeast()
        ->once();

    app(TagObserver::class)->deleted($tag);
    expect(Cache::get('games.recommendations.version'))->toBe($initialVersion + 2);
});

test('renaming or deleting a tag refreshes the cached pages of its games', function () {
    Config::set(['scout.driver' => null, 'fvn.game_page_cache_ttl' => 300]);
    $game = Game::factory()->create(['is_visible' => true]);
    $tag = Tag::create(['name' => 'Old Tag']);
    $tag->games()->attach($game->id);

    $builds = 0;
    $page = function () use ($game, &$builds): void {
        GamePageCacheService::remember($game, 1, 1, function () use (&$builds): array {
            $builds++;

            return ['reviews' => ['last_page' => 1], 'gameVersions' => ['last_page' => 1]];
        });
    };

    $page();
    $page();
    $tag->update(['name' => 'New Tag']);
    $page();
    $tag->delete();
    $page();

    expect($builds)->toBe(3);
});
