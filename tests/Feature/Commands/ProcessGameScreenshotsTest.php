<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Console\Commands\ProcessGameScreenshots;
use App\Models\Game;
use App\Services\ImageProcessingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessGameScreenshotsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Screenshot variants configuration from ProcessGameScreenshots
     */
    private const VARIANTS = [
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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test that the command correctly processes screenshots using the image processing service
     *
     * @test
     */
    public function process_screenshots_command(): void
    {
        // Create a test image with specific dimensions
        $imageWidth = 800;
        $imageHeight = 400;

        // Create a test PNG image
        $image = imagecreatetruecolor($imageWidth, $imageHeight);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        // Save the image to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
        imagepng($image, $tempFile);
        imagedestroy($image);

        // Read the image data
        $imageData = file_get_contents($tempFile);

        // Create a mock HTTP client that returns our test image
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'image/png'], $imageData),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Create a game with a screenshot
        $game = Game::factory()->create([
            'is_visible' => true,
            'screenshots' => [
                [
                    'url' => 'https://example.com/test-image.png',
                ],
            ],
        ]);

        // Create and run the command with our mocked client and image processing service
        $imageProcessingService = $this->app->make(ImageProcessingService::class);
        $command = new ProcessGameScreenshots($client, $imageProcessingService);
        $this->app->instance(ProcessGameScreenshots::class, $command);

        $this->artisan('games:process-screenshots', [
            '--game-id' => $game->id,
            '--force' => true,
        ])->assertExitCode(0);

        // Refresh the game from the database
        $game->refresh();

        // Check that the screenshots were processed
        $this->assertNotEmpty($game->screenshots[0]['optimized']);

        // Verify that all variants were created
        foreach (array_keys(self::VARIANTS) as $variant) {
            $this->assertArrayHasKey($variant, $game->screenshots[0]['optimized'],
                "Variant {$variant} was not created");

            $data = $game->screenshots[0]['optimized'][$variant];

            // Verify the path exists
            $this->assertTrue(Storage::disk('public')->exists($data['path']),
                "File for variant {$variant} does not exist");

            // Verify the metadata is correct
            $this->assertArrayHasKey('width', $data, "Width not set for variant {$variant}");
            $this->assertArrayHasKey('height', $data, "Height not set for variant {$variant}");
            $this->assertArrayHasKey('mime_type', $data, "MIME type not set for variant {$variant}");
            $this->assertEquals('image/webp', $data['mime_type'], "MIME type is not webp for variant {$variant}");
        }

        // Clean up
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
