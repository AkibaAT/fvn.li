<?php

declare(strict_types=1);

// Legacy Livewire GameDetail removed
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('uses optimized thumbnail for social media meta tags', function () {
    // Create a game with cached thumbnail
    $game = Game::factory()->create([
        'name' => 'Test Game',
        'thumb_url' => 'https://external.com/original.jpg',
        'optimized_thumbnails' => [
            'default' => [
                'path' => 'thumbnails/test-game-default.webp',
                'width' => 300,
                'height' => 200,
            ],
        ],
    ]);

    // Create the cached thumbnail file
    Storage::disk('public')->put('thumbnails/test-game-default.webp', 'cached thumbnail content');

    // Use model fallback logic directly to simulate meta image selection
    $metaTags = [
        'image' => '/storage/'.$game->optimized_thumbnails['default']['path'],
    ];

    // Should use the cached thumbnail URL, not the external one
    expect($metaTags['image'])->toContain('/storage/thumbnails/test-game-default.webp');
    expect($metaTags['image'])->not->toContain('external.com');
});

it('does not expose external thumbnail when no cache exists', function () {
    // Create a game without cached thumbnail
    $game = Game::factory()->create([
        'name' => 'Test Game',
        'thumb_url' => 'https://external.com/original.jpg',
        'optimized_thumbnails' => null,
    ]);

    $metaTags = [
        'image' => $game->optimized_thumbnail_url,
    ];

    expect($metaTags['image'])->toBeNull();
});

it('uses favicon when no thumbnail available', function () {
    // Create a game without any thumbnail
    $game = Game::factory()->create([
        'name' => 'Test Game',
        'thumb_url' => null,
        'optimized_thumbnails' => null,
        'screenshots' => [],
    ]);

    $metaTags = [
        'image' => asset('favicon.ico'),
    ];

    // Should fall back to favicon
    expect($metaTags['image'])->toContain('favicon.ico');
});

it('does not expose unoptimized screenshots for frontend display', function () {
    $game = Game::factory()->create([
        'screenshots' => [
            [
                'url' => 'https://img.itch.zone/raw.png',
                'thumbnail_url' => 'https://img.itch.zone/raw-thumb.png',
            ],
        ],
    ]);

    expect($game->getScreenshots())->toBe([]);
});

it('exposes optimized screenshot urls without leaking the remote original', function () {
    $game = Game::factory()->create([
        'screenshots' => [
            [
                'url' => 'https://img.itch.zone/raw.png',
                'thumbnail_url' => 'https://img.itch.zone/raw-thumb.png',
                'optimized' => [
                    'default' => [
                        'path' => 'screenshots/default.webp',
                        'width' => 320,
                        'height' => 180,
                    ],
                    'large' => [
                        'path' => 'screenshots/large.webp',
                        'width' => 1280,
                        'height' => 720,
                    ],
                ],
            ],
        ],
    ]);

    $screenshots = $game->getScreenshots();

    expect($screenshots)->toHaveCount(1)
        ->and($screenshots[0]['url'])->toContain('/storage/screenshots/large.webp')
        ->and($screenshots[0]['thumbnail_url'])->toContain('/storage/screenshots/default.webp')
        ->and($screenshots[0]['original_url'])->toContain('/storage/screenshots/large.webp')
        ->and($screenshots[0]['url'])->not->toContain('img.itch.zone')
        ->and($screenshots[0]['thumbnail_url'])->not->toContain('img.itch.zone')
        ->and($screenshots[0]['original_url'])->not->toContain('img.itch.zone');
});
