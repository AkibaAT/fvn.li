<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\ImageDownloadUrlValidator;
use App\Services\ImageProcessingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('repair images dry run reports missing files without changing metadata', function () {
    $game = Game::factory()->create([
        'thumb_url' => null,
        'screenshots' => [[
            'url' => 'https://img.itch.zone/missing.png',
            'optimized' => repairImageVariants('screenshots/missing', ['small', 'default', 'large']),
        ]],
        'optimized_thumbnails' => repairImageVariants('thumbnails/missing', ['small', 'default']),
    ]);
    $originalScreenshots = $game->screenshots;
    $originalThumbnails = $game->optimized_thumbnails;

    $this->artisan('games:repair-images', [
        '--game-id' => $game->id,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain("{$game->name} (ID: {$game->id}) has invalid processed images")
        ->expectsOutputToContain('screenshot 1: small: missing file screenshots/missing_small.webp')
        ->expectsOutputToContain('thumbnail: default: missing file thumbnails/missing_default.webp')
        ->expectsOutputToContain('Inspection complete: 1 of 1 game(s) need repair.')
        ->assertSuccessful();

    $game->refresh();
    expect($game->screenshots)->toEqual($originalScreenshots)
        ->and($game->optimized_thumbnails)->toEqual($originalThumbnails);
});

test('repair images regenerates only invalid screenshots and verifies the result', function () {
    $sourceUrl = 'https://img.itch.zone/missing.png';
    $healthyUrl = 'https://img.itch.zone/healthy.png';
    $healthyVariants = repairImageVariants('screenshots/healthy', ['small', 'default', 'large']);
    $thumbnailVariants = repairImageVariants('thumbnails/healthy', ['small', 'default']);
    repairStoreVariants($healthyVariants);
    repairStoreVariants($thumbnailVariants);

    $game = Game::factory()->create([
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'screenshots' => [
            [
                'url' => $sourceUrl,
                'optimized' => repairImageVariants('screenshots/missing', ['small', 'default', 'large']),
            ],
            [
                'url' => $healthyUrl,
                'optimized' => $healthyVariants,
            ],
        ],
        'optimized_thumbnails' => $thumbnailVariants,
    ]);

    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'image/png'], repairImagePayload()),
    ]);
    $service = new ImageProcessingService(
        new Client(['handler' => HandlerStack::create($mock)]),
        new ImageDownloadUrlValidator,
    );
    $this->app->instance(ImageProcessingService::class, $service);

    $this->artisan('games:repair-images', ['--game-id' => $game->id])
        ->expectsOutputToContain("Repaired {$game->name}.")
        ->expectsOutputToContain('Repair complete: 1 repaired, 0 still invalid, 1 initially invalid.')
        ->assertSuccessful();

    $game->refresh();
    expect($game->screenshots[0]['optimized'])->toHaveKeys(['small', 'default', 'large'])
        ->and($game->screenshots[1]['optimized'])->toEqual($healthyVariants);

    foreach ($game->screenshots[0]['optimized'] as $variant) {
        expect(Storage::disk('public')->exists($variant['path']))->toBeTrue();
    }
});

test('repair images requires a selector and validates quality', function () {
    $this->artisan('games:repair-images')
        ->expectsOutputToContain('You must provide either --game-id, --game-name, or --all option')
        ->assertFailed();

    $this->artisan('games:repair-images', ['--all' => true, '--quality' => 101])
        ->expectsOutputToContain('Quality must be between 0 and 100.')
        ->assertFailed();
});

/**
 * @param  array<int, string>  $variants
 * @return array<string, array<string, mixed>>
 */
function repairImageVariants(string $prefix, array $variants): array
{
    return collect($variants)->mapWithKeys(fn (string $variant): array => [
        $variant => [
            'path' => "{$prefix}_{$variant}.webp",
            'width' => 100,
            'height' => 100,
            'mime_type' => 'image/webp',
        ],
    ])->all();
}

/** @param array<string, array<string, mixed>> $variants */
function repairStoreVariants(array $variants): void
{
    foreach ($variants as $variant) {
        Storage::disk('public')->put($variant['path'], 'processed image');
    }
}

function repairImagePayload(): string
{
    $image = imagecreatetruecolor(800, 400);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}
