<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\ImageProcessingService;
use App\Services\SteamDataSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('steam image sync processes unchanged screenshots that are missing optimized variants', function () {
    $screenshots = [
        [
            'url' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/4222040/example/ss_example.1920x1080.jpg',
            'thumbnail_url' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/4222040/example/ss_example.600x338.jpg',
        ],
    ];

    $game = Game::factory()->make([
        'thumb_url' => null,
        'screenshots' => $screenshots,
    ]);

    $imageService = Mockery::mock(ImageProcessingService::class);
    $imageService->shouldReceive('processGameScreenshots')
        ->once()
        ->with($game);
    $imageService->shouldNotReceive('processGameThumbnail');
    app()->instance(ImageProcessingService::class, $imageService);

    $service = new SteamDataSyncService;
    $method = new ReflectionMethod($service, 'processImages');
    $method->setAccessible(true);
    $method->invoke($service, $game, null, $screenshots);
});

test('steam image sync fails when screenshot optimization fails completely', function () {
    $screenshots = [
        [
            'url' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/4222040/example/ss_example.1920x1080.jpg',
            'thumbnail_url' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/4222040/example/ss_example.600x338.jpg',
        ],
    ];

    $game = Game::factory()->make([
        'thumb_url' => null,
        'screenshots' => $screenshots,
    ]);

    $imageService = Mockery::mock(ImageProcessingService::class);
    $imageService->shouldReceive('processGameScreenshots')
        ->once()
        ->with($game)
        ->andThrow(new Exception('Failed to optimize any screenshots'));
    app()->instance(ImageProcessingService::class, $imageService);

    $service = new SteamDataSyncService;
    $method = new ReflectionMethod($service, 'processImages');
    $method->setAccessible(true);
    $method->invoke($service, $game, null, $screenshots);
})->throws(Exception::class, 'Failed to optimize any screenshots');
