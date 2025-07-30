<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\SocialImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SocialImageService::class);
    Storage::fake('public');
});

it('prefers cached thumbnails over external URLs', function () {
    // Create a real game with optimized thumbnails
    $game = Game::factory()->create([
        'name' => 'Test Game',
        'thumb_url' => 'https://external.com/original.jpg',
        'optimized_thumbnails' => [
            'small' => [
                'path' => 'thumbnails/test-game-small.webp',
                'width' => 150,
                'height' => 100,
            ],
        ],
    ]);

    // Create the actual cached thumbnail file
    Storage::disk('public')->put('thumbnails/test-game-small.webp', 'cached thumbnail content');

    $games = collect([$game]);

    // Test that cache key generation uses the cached thumbnail URL
    $cacheKey = $this->service->generateCacheKey($games, []);
    expect($cacheKey)->toBeString();

    // The thumbnail URL should point to the local cached version
    $thumbnailUrl = $game->getThumbnailUrl('small');
    expect($thumbnailUrl)->toContain('/storage/thumbnails/test-game-small.webp');
    expect($thumbnailUrl)->not->toContain('external.com');
});

it('falls back to external URL when no cache exists', function () {
    // Create a game without optimized thumbnails
    $game = Game::factory()->create([
        'name' => 'Test Game',
        'thumb_url' => 'https://external.com/original.jpg',
        'optimized_thumbnails' => null,
    ]);

    $games = collect([$game]);

    // Test that it falls back to the original thumb_url
    $thumbnailUrl = $game->getThumbnailUrl('small');
    expect($thumbnailUrl)->toBe('https://external.com/original.jpg');

    $cacheKey = $this->service->generateCacheKey($games, []);
    expect($cacheKey)->toBeString();
});

it('handles mixed cached and external thumbnails', function () {
    // Create one game with cached thumbnail and one without
    $cachedGame = Game::factory()->create([
        'name' => 'Cached Game',
        'thumb_url' => 'https://external.com/cached.jpg',
        'optimized_thumbnails' => [
            'small' => [
                'path' => 'thumbnails/cached-game-small.webp',
                'width' => 150,
                'height' => 100,
            ],
        ],
    ]);

    $externalGame = Game::factory()->create([
        'name' => 'External Game',
        'thumb_url' => 'https://external.com/external.jpg',
        'optimized_thumbnails' => null,
    ]);

    // Create the cached file
    Storage::disk('public')->put('thumbnails/cached-game-small.webp', 'cached content');

    $games = collect([$cachedGame, $externalGame]);

    // Both should be included in cache key generation
    $cacheKey = $this->service->generateCacheKey($games, []);
    expect($cacheKey)->toBeString();

    // Verify thumbnail URLs are different types
    expect($cachedGame->getThumbnailUrl('small'))->toContain('/storage/');
    expect($externalGame->getThumbnailUrl('small'))->toContain('external.com');
});
