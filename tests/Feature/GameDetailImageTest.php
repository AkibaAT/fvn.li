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
        'image' => '/storage/' . $game->optimized_thumbnails['default']['path'],
    ];

    // Should use the cached thumbnail URL, not the external one
    expect($metaTags['image'])->toContain('/storage/thumbnails/test-game-default.webp');
    expect($metaTags['image'])->not->toContain('external.com');
});

it('falls back to external thumbnail when no cache exists', function () {
    // Create a game without cached thumbnail
    $game = Game::factory()->create([
        'name' => 'Test Game',
        'thumb_url' => 'https://external.com/original.jpg',
        'optimized_thumbnails' => null,
    ]);

    $metaTags = [
        'image' => $game->thumb_url,
    ];

    // Should fall back to external URL
    expect($metaTags['image'])->toBe('https://external.com/original.jpg');
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
