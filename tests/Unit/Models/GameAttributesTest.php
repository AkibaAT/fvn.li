<?php

declare(strict_types=1);

use App\Models\Game;

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

test('thumbnail helpers expose optimized thumbnails only for display urls', function () {
    $game = new Game([
        'thumb_url' => 'https://cdn.example.com/thumb.jpg',
        'optimized_thumbnails' => [
            'default' => [
                'path' => 'thumbnails/thumb.webp',
            ],
        ],
        'screenshots' => [
            ['url' => 'https://cdn.example.com/s1.jpg'],
            ['url' => 'https://cdn.example.com/s2.jpg'],
        ],
    ]);

    expect($game->hasThumbnail())->toBeTrue()
        ->and($game->getEffectiveThumbnailUrl())->toBe('https://cdn.example.com/thumb.jpg')
        ->and($game->getThumbnailUrl('default'))->toContain('/storage/thumbnails/thumb.webp');

    $game2 = new Game([
        'screenshots' => [
            ['url' => 'https://cdn.example.com/first.jpg'],
        ],
    ]);

    expect($game2->getEffectiveThumbnailUrl())->toBe('https://cdn.example.com/first.jpg')
        ->and($game2->getThumbnailUrl('default'))->toBeNull();
});
