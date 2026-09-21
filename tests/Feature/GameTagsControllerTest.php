<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Tag;
use App\Services\GameFilterService;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Config::set('fvn.min_tag_game_count', 3);
    Tag::clearPopularCache();
});

function tagWithGames(string $name, int $visible, int $hidden = 0): Tag
{
    $tag = Tag::create(['name' => $name]);

    Game::factory()->count($visible)->create(['is_visible' => true])->each(fn (Game $game) => $game->tags()->attach($tag->id));
    Game::factory()->count($hidden)->create(['is_visible' => false])->each(fn (Game $game) => $game->tags()->attach($tag->id));

    return $tag;
}

test('tag index lists public tags with visible game counts', function () {
    $bara = tagWithGames('Bara', 4, 2);
    tagWithGames('Rare Niche', 1);

    $this->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tags/index')
            ->has('tags', 1)
            ->where('tags.0.slug', $bara->slug)
            ->where('tags.0.games_count', 4)
            ->where('metaTags.url', route('tags.index')));
});

test('tag page shows visible tagged games with indexable meta and structured data', function () {
    $bara = tagWithGames('Bara', 3, 1);
    $romance = Tag::create(['name' => 'Romance']);
    Game::whereHas('tags', fn ($query) => $query->whereKey($bara->id))->get()->each(fn (Game $game) => $game->tags()->attach($romance->id));
    Tag::clearPopularCache();

    $this->get(route('tags.show', $bara))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tags/show')
            ->where('tag.name', 'Bara')
            ->where('tag.games_count', 3)
            ->has('games.data', 3)
            ->where('relatedTags.0.slug', $romance->slug)
            ->where('metaTags.title', 'Bara Furry Visual Novels')
            ->where('metaTags.url', route('tags.show', $bara))
            ->missing('metaTags.noindex')
            ->where('metaTags.structuredData.@graph.0.@type', 'CollectionPage')
            ->where('metaTags.structuredData.@graph.0.mainEntity.numberOfItems', 3)
            ->where('metaTags.structuredData.@graph.1.@type', 'BreadcrumbList'));
});

test('tag pages 404 for tags below the public threshold and pages past the end', function () {
    $rare = tagWithGames('Rare Niche', 1);
    $bara = tagWithGames('Bara', 3);

    $this->get(route('tags.show', $rare))->assertNotFound();
    $this->get(route('tags.show', ['tag' => $bara, 'page' => 5]))->assertNotFound();
    $this->get('/tags/does-not-exist')->assertNotFound();
});

test('later tag pages carry the page number in title and canonical', function () {
    Config::set('fvn.min_tag_game_count', 1);
    $bara = tagWithGames('Bara', 25);

    $response = $this->get(route('tags.show', ['tag' => $bara, 'page' => 2]))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('metaTags.browserTitle', 'Bara Furry Visual Novels – Page 2')
        ->has('games.data', 1));

    expect($response->getContent())->toContain('<link rel="canonical" href="' . route('tags.show', $bara) . '?page=2"/>');
});

test('catalogue-wide tags are hidden from tag pages, filters and game data', function () {
    Config::set('fvn.hidden_tag_slugs', ['furry']);
    $furry = tagWithGames('Furry', 4);
    $bara = tagWithGames('Bara', 3);
    $game = Game::whereHas('tags', fn ($query) => $query->whereKey($bara->id))->first();
    $game->tags()->attach($furry->id);
    Tag::clearPopularCache();

    expect(Tag::popularIds())->not->toContain($furry->id)->toContain($bara->id)
        ->and(GameFilterService::getOptions()['tags'])->not->toHaveKey((string) $furry->id);

    $this->get(route('tags.show', $furry))->assertNotFound();
    $this->get(route('tags.index'))->assertInertia(fn (Assert $page) => $page->has('tags', 1)->where('tags.0.slug', $bara->slug));
    $this->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page->where('game.tags', fn ($tags) => collect($tags)->pluck('slug')->all() === [$bara->slug]));
});
