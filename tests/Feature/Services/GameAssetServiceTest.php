<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\GameAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('uploads images, creates variants, and returns public asset metadata', function () {
    Storage::fake('public');
    $service = app(GameAssetService::class);
    $game = Game::factory()->create(['name' => 'Asset Game']);
    $file = UploadedFile::fake()->image('screenshot.png', 1280, 720)->size(1024);

    $asset = $service->uploadImage($game, $file);

    expect($asset['original'])->toStartWith("game-assets/{$game->slug}/")
        ->and($asset['variants'])->toHaveKeys(['small', 'medium', 'large'])
        ->and($asset['url'])->toContain("/storage/game-assets/{$game->slug}/")
        ->and($asset['alt'])->toBe('')
        ->and($asset['caption'])->toBe('');

    Storage::disk('public')->assertExists($asset['original']);
    foreach ($asset['variants'] as $variant) {
        Storage::disk('public')->assertExists($variant);
    }

    expect($service->getImageUrl($asset, 'small'))->toContain("/storage/game-assets/{$game->slug}/")
        ->and($service->getImageUrl($asset, 'missing'))->toContain($asset['original']);
});

it('uploads only valid files from a mixed batch', function () {
    Storage::fake('public');
    $service = app(GameAssetService::class);
    $game = Game::factory()->create(['slug' => 'batch-game']);

    $assets = $service->uploadMultipleImages($game, [
        UploadedFile::fake()->image('one.jpg', 640, 480),
        'not-a-file',
        UploadedFile::fake()->image('two.webp', 640, 480),
    ]);

    expect($assets)->toHaveCount(2);
});

it('rejects invalid image uploads', function () {
    Storage::fake('public');
    $service = app(GameAssetService::class);
    $game = Game::factory()->create();

    expect(fn () => $service->uploadImage($game, UploadedFile::fake()->create('notes.txt', 10, 'text/plain')))
        ->toThrow(Exception::class, 'Invalid image type');

    expect(fn () => $service->uploadImage($game, UploadedFile::fake()->image('huge.png')->size(11 * 1024)))
        ->toThrow(Exception::class, 'Image file too large');
});

it('updates image metadata on the matching custom asset only', function () {
    $service = app(GameAssetService::class);
    $game = Game::factory()->create([
        'custom_assets' => [
            ['original' => 'game-assets/game/one.png', 'alt' => 'Old alt', 'caption' => 'Old caption'],
            ['original' => 'game-assets/game/two.png', 'alt' => 'Other alt', 'caption' => 'Other caption'],
        ],
    ]);

    $service->updateImageMetadata($game, 'game-assets/game/one.png', [
        'alt' => 'New alt',
        'caption' => 'New caption',
    ]);

    $game->refresh();
    expect($game->custom_assets[0]['alt'])->toBe('New alt')
        ->and($game->custom_assets[0]['caption'])->toBe('New caption')
        ->and($game->custom_assets[1]['alt'])->toBe('Other alt');
});

it('deletes original images and variants', function () {
    Storage::fake('public');
    $service = app(GameAssetService::class);
    $paths = [
        'game-assets/game/screen.png',
        'game-assets/game/screen_small.png',
        'game-assets/game/screen_medium.png',
        'game-assets/game/screen_large.png',
    ];

    foreach ($paths as $path) {
        Storage::disk('public')->put($path, 'image');
    }

    expect($service->deleteImage('game-assets/game/screen.png'))->toBeTrue();

    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('cleans up unused assets while preserving configured and referenced files', function () {
    Storage::fake('public');
    $service = app(GameAssetService::class);
    $game = Game::factory()->create([
        'name' => 'Cleanup Game',
        'custom_assets' => [[
            'original' => 'game-assets/cleanup-game/kept.png',
            'variants' => ['small' => 'game-assets/cleanup-game/kept_small.png'],
        ]],
        'custom_description' => '<img src="/storage/game-assets/cleanup-game/inline.png">',
    ]);
    $game->forceFill([
        'custom_assets' => [[
            'original' => "game-assets/{$game->slug}/kept.png",
            'variants' => ['small' => "game-assets/{$game->slug}/kept_small.png"],
        ]],
        'custom_description' => "<img src=\"/storage/game-assets/{$game->slug}/inline.png\">",
    ])->save();

    foreach (['kept.png', 'kept_small.png', 'inline.png', 'unused.png'] as $file) {
        Storage::disk('public')->put("game-assets/{$game->slug}/{$file}", 'image');
    }

    $service->cleanupUnusedAssets($game);

    Storage::disk('public')->assertExists("game-assets/{$game->slug}/kept.png");
    Storage::disk('public')->assertExists("game-assets/{$game->slug}/kept_small.png");
    Storage::disk('public')->assertExists("game-assets/{$game->slug}/inline.png");
    Storage::disk('public')->assertMissing("game-assets/{$game->slug}/unused.png");
});
