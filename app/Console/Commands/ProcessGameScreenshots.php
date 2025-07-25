<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\ImageProcessingService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessGameScreenshots extends Command
{
    use SelectsGames;
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

    protected $signature = 'games:process-screenshots
        {--force : Process screenshots even if they already exist}
        {--game-id= : ID of the specific game to process}
        {--game-name= : Name (or part of name) of the game(s) to process}
        {--all : Process all visible games with screenshots}
        {--quality=80 : WebP quality (0-100)}';

    protected $description = 'Process and optimize game screenshots';

    public function __construct(
        private readonly Client $httpClient,
        private readonly ImageProcessingService $imageProcessingService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            // Validate that we have at least one game selection option
            if (! $this->validateGameSelectionOptions()) {
                return 1;
            }

            // Build query for games
            $query = Game::query()
                ->where('is_visible', true)
                ->where('is_suspended', false)
                ->whereNotNull('screenshots');

            // Apply game selection filters
            $this->applyGameSelectionFilters($query);

            $games = $query->get();

            // Display selected games
            $this->displaySelectedGames($games);

            if ($games->isEmpty()) {
                return 1;
            }

            $totalGames = $games->count();
            $this->info("Processing {$totalGames} games with screenshots");

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

            try {
                // Download the screenshot
                $this->info('Downloading screenshot...');
                $response = $this->httpClient->get($screenshot['url'], [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'verify' => false,
                ]);

                // Get the content
                $content = $response->getBody()->getContents();

                // Create a temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'screenshot_');
                file_put_contents($tempFile, $content);

                // Skip if already optimized and not forcing
                if (! $force && isset($screenshot['optimized']) && ! empty($screenshot['optimized'])) {
                    $this->info('Screenshot already optimized, skipping (use --force to override)');
                    $updatedScreenshots[] = $screenshot;

                    // Clean up temp file
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }

                    continue;
                }

                // Clean up existing screenshots for this game and index
                $this->cleanupExistingScreenshots($game->id, $index);

                // Generate a unique filename with content checksum
                $baseFilename = $this->generateScreenshotFilename($game, $index, $screenshot['url'], $content);

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
    private function generateScreenshotFilename(Game $game, int $index, string $url, string $fileContent): string
    {
        // Generate a checksum of the file content to ensure cache invalidation when the image changes
        $contentChecksum = substr(md5($fileContent), 0, 8);

        return sprintf(
            '%d_screenshot_%d_%s_%s',
            $game->id,
            $index,
            substr(md5($url), 0, 8),
            $contentChecksum
        );
    }

    /**
     * Clean up existing screenshot files for a game
     */
    private function cleanupExistingScreenshots(int $gameId, int $screenshotIndex): void
    {
        $this->info('Cleaning up existing screenshots...');

        // Get all files in the screenshots directory
        $files = Storage::disk('public')->files(self::SCREENSHOTS_PATH);

        // Pattern to match files for this game ID and screenshot index
        $pattern = "/^{$gameId}_screenshot_{$screenshotIndex}_[a-f0-9]{8}/";

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match($pattern, $filename)) {
                $this->info("Deleting: {$filename}");
                Storage::disk('public')->delete($file);
            }
        }
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
            // Get image info for logging
            $imageInfo = getimagesize($sourcePath);
            $this->info("Processing image: {$imageInfo[0]}x{$imageInfo[1]} pixels");

            // Use the service to process the image
            $this->imageProcessingService->processImageVariant(
                $sourcePath,
                $targetPath,
                $config,
                $quality
            );
        } catch (Exception $e) {
            throw new Exception("Failed to process static image: {$e->getMessage()}");
        }
    }

    /**
     * Get dimensions of an image
     */
    private function getImageDimensions(string $path): array
    {
        return $this->imageProcessingService->getImageDimensions($path);
    }
}
