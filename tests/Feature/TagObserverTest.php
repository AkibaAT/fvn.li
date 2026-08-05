<?php

use App\Models\Game;
use App\Models\Tag;
use App\Observers\TagObserver;
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
