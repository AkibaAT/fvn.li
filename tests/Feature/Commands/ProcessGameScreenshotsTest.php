<?php

declare(strict_types=1);

use App\Console\Commands\ProcessGameScreenshots;
use App\Models\Game;
use App\Services\ImageDownloadUrlValidator;
use App\Services\ImageProcessingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Screenshot variants configuration from ProcessGameScreenshots
 */
const VARIANTS = [
    'small' => [
        'width' => 320,
        'height' => 180,
    ],
    'default' => [
        'width' => 640,
        'height' => 360,
    ],
    'large' => [
        'width' => 1280,
        'height' => 720,
    ],
];

beforeEach(function () {
    Storage::fake('public');
});

test('process screenshots command', function () {
    $imageWidth = 800;
    $imageHeight = 400;

    $image = imagecreatetruecolor($imageWidth, $imageHeight);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

    $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
    imagepng($image, $tempFile);

    // Read the image data
    $imageData = file_get_contents($tempFile);

    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'image/png'], $imageData),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    $game = Game::factory()->create([
        'is_visible' => true,
        'screenshots' => [
            [
                'url' => 'https://img.itch.zone/test-image.png',
            ],
        ],
    ]);

    $imageProcessingService = $this->app->make(ImageProcessingService::class);
    $command = new ProcessGameScreenshots($client, $imageProcessingService, new ImageDownloadUrlValidator);
    $this->app->instance(ProcessGameScreenshots::class, $command);

    $this->artisan('games:process-screenshots', [
        '--game-id' => $game->id,
        '--force' => true,
    ])->assertExitCode(0);

    // Refresh the game from the database
    $game->refresh();

    expect($game->screenshots[0])->toHaveKey('optimized');

    // Debug the structure
    $optimized = $game->screenshots[0]['optimized'];

    // Verify that all variants were created
    foreach (array_keys(VARIANTS) as $variant) {
        expect(isset($optimized[$variant]))->toBeTrue("Variant {$variant} was not created");

        $data = $optimized[$variant];

        // Verify the path exists
        expect(Storage::disk('public')->exists($data['path']))->toBeTrue("File for variant {$variant} does not exist");

        // Verify the metadata is correct
        expect(array_key_exists('width', $data))->toBeTrue("Width not set for variant {$variant}");
        expect(array_key_exists('height', $data))->toBeTrue("Height not set for variant {$variant}");
        expect(array_key_exists('mime_type', $data))->toBeTrue("MIME type not set for variant {$variant}");
        expect($data['mime_type'])->toBe('image/webp', "MIME type is not webp for variant {$variant}");
    }

    // Clean up
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});
