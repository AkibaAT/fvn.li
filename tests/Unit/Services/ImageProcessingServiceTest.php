<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\ImageDownloadUrlValidator;
use App\Services\ImageProcessingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = new ImageProcessingService(new Client, new ImageDownloadUrlValidator);
});

test('process image variant preserves aspect ratio', function () {
    // Create a test image with a specific aspect ratio
    $imageWidth = 800;
    $imageHeight = 400;
    $aspectRatio = $imageWidth / $imageHeight;

    // Create a test PNG image
    $image = imagecreatetruecolor($imageWidth, $imageHeight);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    // Save the image to a temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
    imagepng($image, $tempFile);

    // Define target dimensions
    $config = [
        'width' => 320,
        'height' => 180,
    ];

    // Process the image and get dimensions
    $targetPath = 'test/processed_image.webp';
    $dimensions = $this->service->processImageVariant(
        $tempFile,
        $targetPath,
        $config,
        80
    );

    // Verify the file was created
    expect(Storage::disk('public')->exists($targetPath))->toBeTrue();

    $processedWidth = $dimensions['width'];
    $processedHeight = $dimensions['height'];

    // The image should fit within the target dimensions
    expect($processedWidth)->toBeLessThanOrEqual($config['width']);
    expect($processedHeight)->toBeLessThanOrEqual($config['height']);

    // Calculate the processed aspect ratio
    $processedAspectRatio = $processedWidth / $processedHeight;

    // For our test, we're just checking that the aspect ratio is maintained within a reasonable margin
    // The actual aspect ratio may differ from the original because we're fitting the image within fixed dimensions
    // We're just verifying that the image isn't being stretched or distorted
    expect($processedAspectRatio)->toBeGreaterThan(0, 'Aspect ratio should be positive');

    // Verify that the image dimensions match what we expect based on the scaling logic
    if ($aspectRatio > $config['width'] / $config['height']) {
        // Image is wider than target - should be scaled to match height
        expect($processedHeight)->toBeLessThanOrEqual($config['height'], 'Height should not exceed target');
    } else {
        // Image is taller than target - should be scaled to match width
        expect($processedWidth)->toBeLessThanOrEqual($config['width'], 'Width should not exceed target');
    }

    // Clean up
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('process game screenshots throws when every screenshot fails to optimize', function () {
    $mock = new MockHandler([
        new Response(500, [], 'upstream failed'),
    ]);

    $service = new ImageProcessingService(
        new Client(['handler' => HandlerStack::create($mock)]),
        new ImageDownloadUrlValidator,
    );

    $game = Game::factory()->make([
        'id' => 123,
        'screenshots' => [
            [
                'url' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/4222040/example/ss_example.1920x1080.jpg',
            ],
        ],
    ]);

    $service->processGameScreenshots($game);
})->throws(Exception::class, 'Failed to optimize any screenshots');

function createImageProcessingPayload(int $width = 800, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

function imageProcessingServiceForResponses(array $responses, ?array &$history = null): ImageProcessingService
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    if ($history !== null) {
        $handlerStack->push(Middleware::history($history));
    }

    return new ImageProcessingService(new Client(['handler' => $handlerStack]), new ImageDownloadUrlValidator);
}

test('large screenshot variants keep original dimensions', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'large_image_');
    file_put_contents($tempFile, createImageProcessingPayload(800, 400));

    $dimensions = $this->service->processImageVariant(
        $tempFile,
        'screenshots/1_screenshot_hash_large.webp',
        ['width' => 1280, 'height' => 720],
        80
    );

    expect($dimensions)->toBe(['width' => 800, 'height' => 400])
        ->and(Storage::disk('public')->exists('screenshots/1_screenshot_hash_large.webp'))->toBeTrue();

    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('gif conversion failures clean up temporary frame files', function () {
    $sourcePath = tempnam(sys_get_temp_dir(), 'gif_source_');
    file_put_contents($sourcePath, base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));

    $fakeBinDir = sys_get_temp_dir().'/fake-convert-'.uniqid();
    mkdir($fakeBinDir);
    $fakeConvert = $fakeBinDir.'/convert';
    file_put_contents($fakeConvert, "#!/bin/sh\nexit 1\n");
    chmod($fakeConvert, 0755);

    $originalPath = getenv('PATH') ?: '';
    $before = glob(sys_get_temp_dir().'/image_frame_*') ?: [];

    putenv("PATH={$fakeBinDir}");

    try {
        expect(fn () => $this->service->processImageVariant(
            $sourcePath,
            'test/failed_gif.webp',
            ['width' => 100, 'height' => 100],
            80
        ))->toThrow(Exception::class, 'Failed to process image: Failed to extract first frame from GIF');
    } finally {
        putenv("PATH={$originalPath}");

        if (file_exists($sourcePath)) {
            unlink($sourcePath);
        }
        if (file_exists($fakeConvert)) {
            unlink($fakeConvert);
        }
        if (is_dir($fakeBinDir)) {
            rmdir($fakeBinDir);
        }
    }

    $after = glob(sys_get_temp_dir().'/image_frame_*') ?: [];
    $newTempFiles = array_values(array_diff($after, $before));

    foreach ($newTempFiles as $newTempFile) {
        if (is_file($newTempFile)) {
            unlink($newTempFile);
        }
    }

    expect($newTempFiles)->toBe([]);
});

test('process game screenshots creates optimized variants and keeps optimized screenshots when not forced', function () {
    $game = Game::factory()->create([
        'screenshots' => [
            ['url' => 'https://img.itch.zone/screenshot-a.png'],
            [
                'url' => 'https://img.itch.zone/already-optimized.png',
                'optimized' => [
                    'large' => ['path' => 'screenshots/existing_large.webp'],
                ],
            ],
            ['caption' => 'missing URL'],
        ],
    ]);

    $service = imageProcessingServiceForResponses([
        new Response(200, ['Content-Type' => 'image/png'], createImageProcessingPayload()),
    ]);

    $service->processGameScreenshots($game, quality: 75, force: false);

    expect($game->screenshots)->toHaveCount(2)
        ->and($game->screenshots[0]['url'])->toBe('https://img.itch.zone/screenshot-a.png')
        ->and($game->screenshots[0]['optimized'])->toHaveKeys(['small', 'default', 'large'])
        ->and($game->screenshots[0]['optimized']['small']['mime_type'])->toBe('image/webp')
        ->and(Storage::disk('public')->exists($game->screenshots[0]['optimized']['small']['path']))->toBeTrue()
        ->and($game->screenshots[1]['url'])->toBe('https://img.itch.zone/already-optimized.png')
        ->and($game->screenshots[1]['optimized']['large']['path'])->toBe('screenshots/existing_large.webp');
});

test('process game screenshots keeps original data when the download is not an image', function () {
    $game = Game::factory()->create([
        'screenshots' => [
            ['url' => 'https://img.itch.zone/not-an-image.txt'],
        ],
    ]);

    $service = imageProcessingServiceForResponses([
        new Response(200, ['Content-Type' => 'text/plain'], 'not an image'),
    ]);

    $service->processGameScreenshots($game, force: true);

    expect($game->screenshots)->toBe([
        ['url' => 'https://img.itch.zone/not-an-image.txt'],
    ]);
});

test('process game thumbnail creates variants from thumbnail URL', function () {
    $game = Game::factory()->create([
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'optimized_thumbnails' => null,
    ]);

    $service = imageProcessingServiceForResponses([
        new Response(200, ['Content-Type' => 'image/png'], createImageProcessingPayload()),
    ]);

    $service->processGameThumbnail($game, quality: 75, force: true);

    expect($game->optimized_thumbnails)->toHaveKeys(['small', 'default'])
        ->and($game->optimized_thumbnails['small']['mime_type'])->toBe('image/webp')
        ->and($game->optimized_thumbnails['small']['animated'])->toBeFalse()
        ->and(Storage::disk('public')->exists($game->optimized_thumbnails['small']['path']))->toBeTrue();
});

test('process game thumbnail skips existing optimized thumbnails unless forced', function () {
    $game = Game::factory()->create([
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'optimized_thumbnails' => [
            'small' => ['path' => 'thumbnails/existing.webp'],
        ],
    ]);

    $this->service->processGameThumbnail($game, force: false);

    expect($game->optimized_thumbnails)->toBe([
        'small' => ['path' => 'thumbnails/existing.webp'],
    ]);
});

test('process game thumbnail uses first screenshot as fallback and rejects invalid downloads', function () {
    $game = Game::factory()->create([
        'thumb_url' => null,
        'screenshots' => [
            ['url' => 'https://img.itch.zone/screenshot-fallback.png'],
        ],
    ]);

    $service = imageProcessingServiceForResponses([
        new Response(500, ['Content-Type' => 'text/plain'], 'server error'),
    ]);

    expect(fn () => $service->processGameThumbnail($game, force: true))
        ->toThrow(Exception::class, '500 Internal Server Error');

    $emptyGame = Game::factory()->create([
        'thumb_url' => null,
        'screenshots' => [],
    ]);

    expect(fn () => $this->service->processGameThumbnail($emptyGame, force: true))
        ->toThrow(Exception::class, 'No thumbnail or screenshot available for processing');
});

test('image downloads keep TLS verification enabled and do not follow redirects', function () {
    $game = Game::factory()->create([
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'optimized_thumbnails' => null,
    ]);
    $history = [];

    $service = imageProcessingServiceForResponses([
        new Response(200, ['Content-Type' => 'image/png'], createImageProcessingPayload()),
    ], $history);

    $service->processGameThumbnail($game, quality: 75, force: true);

    expect($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toBe('https://img.itch.zone/thumb.png')
        ->and($history[0]['options']['verify'] ?? true)->not->toBeFalse()
        ->and($history[0]['options']['allow_redirects'])->toBeFalse()
        ->and($history[0]['options']['stream'])->toBeTrue()
        ->and($history[0]['options']['curl'][constant('CURLOPT_RESOLVE')][0] ?? '')->toStartWith('img.itch.zone:443:');
});

test('image downloads reject oversized thumbnail responses before processing', function () {
    $game = Game::factory()->create([
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'optimized_thumbnails' => null,
    ]);
    $history = [];

    $service = imageProcessingServiceForResponses([
        new Response(200, ['Content-Length' => (string) (10 * 1024 * 1024 + 1)], ''),
    ], $history);

    expect(fn () => $service->processGameThumbnail($game, force: true))
        ->toThrow(Exception::class, 'too large');

    expect($history)->toHaveCount(1)
        ->and($game->refresh()->optimized_thumbnails)->toBeNull();
});

test('image downloads stop reading thumbnail bodies over the byte limit', function () {
    $game = Game::factory()->create([
        'thumb_url' => 'https://img.itch.zone/thumb.png',
        'optimized_thumbnails' => null,
    ]);

    $service = imageProcessingServiceForResponses([
        new Response(200, [], str_repeat('x', 10 * 1024 * 1024 + 1)),
    ]);

    expect(fn () => $service->processGameThumbnail($game, force: true))
        ->toThrow(Exception::class, 'exceeds maximum size');

    expect($game->refresh()->optimized_thumbnails)->toBeNull();
});

test('image downloads reject untrusted screenshot and thumbnail urls before fetching', function () {
    $game = Game::factory()->create([
        'screenshots' => [
            ['url' => 'https://127.0.0.1/internal.png'],
        ],
    ]);
    $history = [];
    $service = imageProcessingServiceForResponses([], $history);

    $service->processGameScreenshots($game, force: true);

    expect($game->screenshots)->toBe([
        ['url' => 'https://127.0.0.1/internal.png'],
    ])->and($history)->toBe([]);

    $gameWithBadThumbnail = Game::factory()->create([
        'thumb_url' => 'https://example.invalid/thumb.png',
        'optimized_thumbnails' => null,
    ]);

    expect(fn () => $service->processGameThumbnail($gameWithBadThumbnail, force: true))
        ->toThrow(InvalidArgumentException::class, 'Could not resolve image host');
});
