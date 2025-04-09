<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProcessGameScreenshots extends Command
{
    private const SCREENSHOTS_PATH = 'screenshots';
    private const VALID_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Define screenshot variants with their configurations
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

    /**
     * Background color for padding (dark gray)
     */
    private const BACKGROUND_COLOR = '#1a1a1a';

    protected $signature = 'games:process-screenshots
        {--force : Process screenshots even if they already exist}
        {--game-id= : Process specific game ID}
        {--quality=80 : WebP quality (0-100)}';

    protected $description = 'Process and optimize game screenshots';

    private readonly ImageManager $imageManager;

    public function __construct(
        private readonly Client $httpClient
    ) {
        parent::__construct();
        $this->imageManager = new ImageManager(new Driver);
    }

    public function handle(): int
    {
        try {
            // Build query for games
            $query = Game::query()
                ->where('is_visible', true)
                ->whereNotNull('screenshots');

            // If specific game ID provided, only process that one
            if ($gameId = $this->option('game-id')) {
                $query->where('id', $gameId);
            }

            $games = $query->get();
            $totalGames = $games->count();

            $this->info("Found {$totalGames} games with screenshots to process");

            foreach ($games as $i => $game) {
                $this->info(sprintf("\nProcessing game %d/%d: %s", $i + 1, $totalGames, $game->name));

                try {
                    $this->processGameScreenshots($game);
                    $this->info('✓ Screenshots processed successfully');
                } catch (Exception $e) {
                    $this->error("Error processing screenshots: {$e->getMessage()}");
                    Log::error('Screenshot processing failed', [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }

                // Add small delay between downloads
                if ($i < $totalGames - 1) {
                    usleep(250000); // 250ms
                }
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('Screenshot processing command failed', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return 1;
        }
    }

    /**
     * Process screenshots for a specific game
     *
     * @throws Exception|GuzzleException
     */
    private function processGameScreenshots(Game $game): void
    {
        $quality = (int) $this->option('quality');
        $force = $this->option('force');

        if (empty($game->screenshots)) {
            $this->info('No screenshots to process');

            return;
        }

        $this->info('Processing ' . count($game->screenshots) . ' screenshots');

        $updatedScreenshots = [];

        foreach ($game->screenshots as $index => $screenshot) {
            $this->info("Processing screenshot {$index}...");

            // Skip if already optimized and not forcing
            if (! $force && isset($screenshot['optimized']) && ! empty($screenshot['optimized'])) {
                $this->info('Screenshot already optimized, skipping (use --force to override)');
                $updatedScreenshots[] = $screenshot;

                continue;
            }

            // Generate a unique filename
            $baseFilename = $this->generateScreenshotFilename($game, $index, $screenshot['url']);

            try {
                // Download the screenshot
                $this->info('Downloading screenshot...');
                $response = $this->httpClient->get($screenshot['url'], [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'verify' => false,
                ]);

                // Create a temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'screenshot_');
                file_put_contents($tempFile, $response->getBody()->getContents());

                // Verify it's a valid image
                $imageInfo = getimagesize($tempFile);
                if ($imageInfo === false) {
                    throw new Exception('Invalid image file');
                }

                $mimeType = $imageInfo['mime'];
                if (! in_array($mimeType, self::VALID_MIME_TYPES)) {
                    throw new Exception("Unsupported image type: {$mimeType}");
                }

                $this->info("Downloaded image: {$imageInfo[0]}x{$imageInfo[1]} pixels, type: {$mimeType}");

                // Process variants
                $optimizedVariants = [];

                // Ensure the screenshot directory exists
                Storage::disk('public')->makeDirectory(self::SCREENSHOTS_PATH);

                // Process each variant
                foreach (self::VARIANTS as $variant => $config) {
                    $this->info("Processing {$variant} variant...");

                    $variantFilename = $baseFilename . "_{$variant}.webp";
                    $variantPath = $this->getStoragePath($variantFilename);

                    $this->processStaticVariant(
                        $tempFile,
                        $variantPath,
                        $config,
                        $quality
                    );

                    // Verify the file was created
                    if (! Storage::disk('public')->exists($variantPath)) {
                        throw new Exception("Failed to create variant file: {$variantPath}");
                    }

                    // Get dimensions of processed image
                    $dimensions = $this->getImageDimensions($variantPath);

                    // Add to variants array
                    $optimizedVariants[$variant] = [
                        'path' => $variantPath,
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'mime_type' => 'image/webp',
                    ];
                }

                // Update screenshot with optimized variants
                $updatedScreenshot = $screenshot;
                $updatedScreenshot['optimized'] = $optimizedVariants;
                $updatedScreenshots[] = $updatedScreenshot;

                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            } catch (Exception $e) {
                $this->error("Error processing screenshot {$index}: {$e->getMessage()}");
                // Keep the original screenshot data
                $updatedScreenshots[] = $screenshot;
            }
        }

        // Update the game with processed screenshots
        $game->screenshots = $updatedScreenshots;
        $game->save();
    }

    /**
     * Generate a unique filename for a screenshot
     */
    private function generateScreenshotFilename(Game $game, int $index, string $url): string
    {
        return sprintf(
            '%d_screenshot_%d_%s',
            $game->id,
            $index,
            substr(md5($url), 0, 8)
        );
    }

    /**
     * Get the storage path for a screenshot
     */
    private function getStoragePath(string $filename): string
    {
        return self::SCREENSHOTS_PATH . '/' . $filename;
    }

    /**
     * Process a static image variant
     */
    private function processStaticVariant(
        string $sourcePath,
        string $targetPath,
        array $config,
        int $quality
    ): void {
        try {
            // For GIFs, first extract the first frame using ImageMagick to reduce memory usage
            $imageInfo = getimagesize($sourcePath);
            $mimeType = $imageInfo['mime'];

            if ($mimeType === 'image/gif') {
                $tempJpg = tempnam(sys_get_temp_dir(), 'screenshot_frame_');
                // Extract first frame and convert to JPG
                $command = sprintf(
                    'convert %s[0] -background white -flatten %s',
                    escapeshellarg($sourcePath),
                    escapeshellarg($tempJpg)
                );
                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('Failed to extract first frame from GIF');
                }

                // Use the extracted frame as source
                $sourcePath = $tempJpg;
            }

            try {
                // Load and process source image
                $image = $this->imageManager->read($sourcePath);

                // Verify we got a valid image
                if ($image->width() === 0 || $image->height() === 0) {
                    throw new Exception('Invalid image dimensions');
                }

                $this->info("Processing image: {$image->width()}x{$image->height()} pixels");

                // Resize and maintain aspect ratio
                $image->resize($config['width'], $config['height'], function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                // Create a canvas with the target dimensions and background color
                $canvas = $this->imageManager->create($config['width'], $config['height'], self::BACKGROUND_COLOR);

                // Center the image on the canvas
                $canvas->place($image, 'center');

                // Save as WebP
                Storage::disk('public')->put(
                    $targetPath,
                    (string) $canvas->toWebp($quality)
                );
            } finally {
                // Clean up temporary frame file if it exists
                if (isset($tempJpg) && file_exists($tempJpg)) {
                    unlink($tempJpg);
                }
            }
        } catch (Exception $e) {
            throw new Exception("Failed to process static image: {$e->getMessage()}");
        }
    }

    /**
     * Get the absolute path for the given storage path
     */
    private function getRealPath(string $path): string
    {
        return Storage::disk('public')->path($path);
    }

    /**
     * Get dimensions of an image
     */
    private function getImageDimensions(string $path): array
    {
        $fullPath = $this->getRealPath($path);
        $imageInfo = getimagesize($fullPath);

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }
}
