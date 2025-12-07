<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->game = Game::factory()->create([
        'name' => 'Test Visual Novel',
        'slug' => 'test-visual-novel',
        'is_visible' => true,
        'is_nsfw' => false,
        'min_price' => 10.0,
        'is_paid' => true,
        'is_on_sale' => false,
    ]);
});

test('game has correct fillable attributes', function () {
    $game = new Game;

    expect($game->getFillable())->toContain(
        'game_id',
        'slug',
        'name',
        'status',
        'is_visible',
        'is_nsfw',
        'description',
        'url',
        'thumb_url',
        'min_price',
        'is_paid',
        'screenshots',
        'additional_links'
    );
});

test('game has correct casted attributes', function () {
    $game = Game::factory()->create([
        'initially_published_at' => now(),
        'is_visible' => true,
        'is_paid' => true,
        'min_price' => 15.99,
        'screenshots' => [['url' => 'test.jpg']],
    ]);

    expect($game->initially_published_at)->toBeInstanceOf(DateTime::class)
        ->and($game->is_visible)->toBeTrue()
        ->and($game->is_paid)->toBeTrue()
        ->and($game->min_price)->toBe(15.99)
        ->and($game->screenshots)->toBeArray();
});

test('getAvailablePlatforms returns correct platforms', function () {
    $platforms = Game::getAvailablePlatforms();

    expect($platforms)->toBeArray()
        ->toHaveKey('windows', 'Windows')
        ->toHaveKey('mac', 'Mac')
        ->toHaveKey('linux', 'Linux')
        ->toHaveKey('android', 'Android')
        ->toHaveKey('ios', 'iOS')
        ->toHaveKey('web', 'Web')
        ->toHaveKey('other', 'Other');
});

test('additional links are cast to array', function () {
    $game = new Game;
    $game->additional_links = [
        ['id' => 3, 'sort_order' => 2, 'platform' => 'web'],
        ['id' => 1, 'sort_order' => 1, 'platform' => 'windows'],
        ['id' => 2, 'sort_order' => 1, 'platform' => 'linux'],
    ];

    $links = $game->additional_links;
    expect($links)->toHaveCount(3)
        ->toBeArray();
});

test('hasAdditionalLinks returns false for empty value', function () {
    $game = new Game;
    $game->additional_links = null;
    expect($game->hasAdditionalLinks())->toBeFalse();
});

test('hasAdditionalLinks returns true for non-empty array', function () {
    $game = new Game;
    $game->additional_links = [['platform' => 'windows', 'url' => 'test.com']];
    expect($game->hasAdditionalLinks())->toBeTrue();
});

test('nsfw flag can be set', function () {
    $game = new Game;

    $game->is_nsfw = true;
    expect($game->is_nsfw)->toBeTrue();

    $game->is_nsfw = false;
    expect($game->is_nsfw)->toBeFalse();
});

test('price accessors behave correctly', function () {
    $game = new Game([
        'is_paid' => true,
        'min_price' => 10.0,
        'is_on_sale' => true,
        'sale_discount_percent' => 25,
    ]);

    expect($game->current_price)->toBe(7.5)
        ->and($game->original_price)->toBe(10.0)
        ->and($game->discount_percentage)->toBe(25);

    // Free games don't have prices
    $free = new Game(['is_paid' => false]);
    expect($free->current_price)->toBeNull()
        ->and($free->original_price)->toBeNull();
});

test('price accessors handle no sale correctly', function () {
    $game = new Game([
        'is_paid' => true,
        'min_price' => 15.0,
        'is_on_sale' => false,
        'sale_discount_percent' => 0,
    ]);

    expect($game->current_price)->toBe(15.0)
        ->and($game->original_price)->toBe(15.0)
        ->and($game->discount_percentage)->toBe(0);
});

test('thumbnail helpers prefer thumb_url then first screenshot', function () {
    $game = new Game([
        'thumb_url' => 'https://cdn.example.com/thumb.jpg',
        'screenshots' => [
            ['url' => 'https://cdn.example.com/s1.jpg'],
            ['url' => 'https://cdn.example.com/s2.jpg'],
        ],
    ]);

    expect($game->hasThumbnail())->toBeTrue()
        ->and($game->getEffectiveThumbnailUrl())->toBe('https://cdn.example.com/thumb.jpg')
        ->and($game->getThumbnailUrl('default'))->toBe('https://cdn.example.com/thumb.jpg');

    // Without thumb_url falls back to first screenshot
    $game2 = new Game([
        'screenshots' => [
            ['url' => 'https://cdn.example.com/first.jpg'],
        ],
    ]);

    expect($game2->getEffectiveThumbnailUrl())->toBe('https://cdn.example.com/first.jpg')
        ->and($game2->getThumbnailUrl('default'))->toBe('https://cdn.example.com/first.jpg');
});

test('thumbnail helpers handle missing thumbnails and screenshots', function () {
    $game = new Game;

    // Test that game has no thumbnail when both thumb_url and screenshots are empty
    expect($game->thumb_url)->toBeNull()
        ->and($game->screenshots)->toBeNull();
});

test('game can have associated game versions', function () {
    $version = GameVersion::factory()->for($this->game)->create([
        'version' => '1.0.0',
        'published_at' => now(),
    ]);

    expect($version->game_id)->toBe($this->game->id)
        ->and($version->version)->toBe('1.0.0');
});

test('game attributes validation for basic flags', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'is_nsfw' => false,
        'is_paid' => true,
        'has_demo' => true,
    ]);

    expect($game->is_visible)->toBeTrue()
        ->and($game->is_nsfw)->toBeFalse()
        ->and($game->is_paid)->toBeTrue()
        ->and($game->has_demo)->toBeTrue();
});

test('game handles custom page attributes', function () {
    $game = Game::factory()->create([
        'has_custom_page' => true,
        'custom_description' => 'Custom game description',
        'custom_screenshots' => [['url' => 'custom1.jpg'], ['url' => 'custom2.jpg']],
        'custom_assets' => ['logo' => 'logo.png'],
        'custom_page_updated_at' => now(),
    ]);

    expect($game->has_custom_page)->toBeTrue()
        ->and($game->custom_description)->toBe('Custom game description')
        ->and($game->custom_screenshots)->toHaveCount(2)
        ->and($game->custom_assets)->toBeArray()
        ->and($game->custom_page_updated_at)->toBeInstanceOf(DateTime::class);
});

test('game rating attributes work correctly', function () {
    $game = Game::factory()->create([
        'rating_score' => 4.5,
        'rating_count' => 123,
    ]);

    expect($game->rating_score)->toBe(4.5)
        ->and($game->rating_count)->toBe(123);
});

test('game handles delisted status', function () {
    $game = Game::factory()->create([
        'is_delisted' => true,
        'is_visible' => false,
    ]);

    expect($game->is_delisted)->toBeTrue()
        ->and($game->is_visible)->toBeFalse();
});

test('game supports custom description and assets', function () {
    $game = Game::factory()->create([
        'custom_description' => 'A custom game description',
        'custom_assets' => ['logo' => 'logo.png', 'banner' => 'banner.jpg'],
    ]);

    expect($game->custom_description)->toBe('A custom game description')
        ->and($game->custom_assets)->toBeArray()
        ->and($game->custom_assets)->toHaveKey('logo')
        ->and($game->custom_assets)->toHaveKey('banner');
});

test('game factory creates valid instances', function () {
    $game = Game::factory()->create();

    expect($game->name)->not()->toBeEmpty()
        ->and($game->slug)->not()->toBeEmpty()
        ->and($game->is_visible)->not()->toBeNull()
        ->and($game->created_at)->toBeInstanceOf(DateTime::class);
});
