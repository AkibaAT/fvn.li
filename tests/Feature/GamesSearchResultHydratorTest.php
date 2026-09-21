<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Tag;
use App\Services\GamesSearchResultHydrator;
use Illuminate\Support\Facades\Config;

test('hydrated games list their tags most-used first, then alphabetically', function () {
    Config::set('fvn.min_tag_game_count', 1);
    Tag::clearPopularCache();

    $game = Game::factory()->create(['is_visible' => true]);
    $rare = Tag::create(['name' => 'Aardvark']);
    $common = Tag::create(['name' => 'Zebra']);
    $tiedB = Tag::create(['name' => 'Beta']);
    $tiedA = Tag::create(['name' => 'alpha']);
    $game->tags()->attach([$rare->id, $common->id, $tiedB->id, $tiedA->id]);

    Game::factory()->count(3)->create(['is_visible' => true])->each(fn (Game $other) => $other->tags()->attach($common->id));
    Game::factory()->create(['is_visible' => true])->tags()->attach([$tiedA->id, $tiedB->id]);
    Tag::clearPopularCache();

    $models = (new Game)->newCollection([$game->fresh()]);
    app(GamesSearchResultHydrator::class)->hydrateModels($models);

    expect($models->first()->tags->pluck('name')->all())->toBe(['Zebra', 'alpha', 'Beta', 'Aardvark']);
});
