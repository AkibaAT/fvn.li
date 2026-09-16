<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Tag;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Config::set('fvn.min_tag_game_count', 5);
    Tag::clearPopularCache();
});

test('popularGameCounts uses a grouped pivot scan and caches results', function () {
    $popular = Tag::create(['name' => 'Popular Tag']);
    $rare = Tag::create(['name' => 'Rare Tag']);

    $games = Game::factory()->count(5)->create();
    foreach ($games as $game) {
        $game->tags()->attach($popular->id);
    }
    $games->first()->tags()->attach($rare->id);

    $counts = Tag::popularGameCounts();

    expect($counts)->toHaveKey($popular->id)
        ->and($counts[$popular->id])->toBe(5)
        ->and($counts)->not->toHaveKey($rare->id)
        ->and(Tag::popularIds())->toBe([$popular->id]);
});

test('usedAtLeast scope keeps tags that meet the minimum game count', function () {
    $popular = Tag::create(['name' => 'Popular Tag']);
    $rare = Tag::create(['name' => 'Rare Tag']);

    $games = Game::factory()->count(5)->create();
    foreach ($games as $game) {
        $game->tags()->attach([$popular->id, $rare->id]);
    }
    // Rare loses enough associations to fall below the threshold.
    $games->take(4)->each(fn (Game $game) => $game->tags()->detach($rare->id));
    Tag::clearPopularCache();

    $ids = Tag::query()->usedAtLeast()->pluck('id')->all();

    expect($ids)->toContain($popular->id)
        ->and($ids)->not->toContain($rare->id);
});

test('constrainToPopular eager load hides rare tags via whereIn popular ids', function () {
    $popular = Tag::create(['name' => 'Popular Tag']);
    $rare = Tag::create(['name' => 'Rare Tag']);

    $games = Game::factory()->count(5)->create();
    foreach ($games as $game) {
        $game->tags()->attach($popular->id);
    }

    $subject = $games->first();
    $subject->tags()->attach($rare->id);
    Tag::clearPopularCache();

    $subject->load(['tags' => Tag::constrainToPopular()]);

    expect($subject->tags->pluck('id')->all())->toBe([$popular->id]);
});
