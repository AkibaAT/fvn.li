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

class ProcessGameThumbnails extends Command
{
    use SelectsGames;

    private const THUMBNAIL_PATH = 'thumbnails';

    private const VALID_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Define thumbnail variants with their configurations
     */
    private const VARIANTS = [
        'small' => [
            'width' => 158,
            'height' => 125,
        ],
        'default' => [
            'width' => 315,
            'height' => 250,
        ],
    ];

    protected $signature = 'games:process-thumbnails
        {--force : Process thumbnails even if they already exist}
        {--game-id= : ID of the specific game to process}
        {--game-name= : Name (or part of name) of the game(s) to process}
        {--all : Process all visible games with thumbnails or screenshots}
        {--quality=80 : WebP quality (0-100)}';

    protected $description = 'Process and optimize game thumbnails (uses first screenshot as fallback if no thumbnail exists)';

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
                ->where(function ($q) {
                    $q->whereNotNull('thumb_url')
                        ->orWhereNotNull('screenshots');
                });

            // Apply game selection filters
            $this->applyGameSelectionFilters($query);

            // Order by most recently updated first to prioritize newer entries
            $query->orderBy('updated_at', 'desc');

            $games = $query->get();

            // Display selected games
            $this->displaySelectedGames($games);

            if ($games->isEmpty()) {
                return 1;
            }

            $totalGames = $games->count();
            $this->info("Processing {$totalGames} games with thumbnails");

            foreach ($games as $i => $game) {
                $this->info(sprintf("\nProcessing game %d/%d: %s", $i + 1, $totalGames, $game->name));

                try {
                    $this->processGameThumbnail($game);
                    $this->info('✓ Thumbnail processed successfully');
                } catch (Exception $e) {
                    $this->error("Error processing thumbnail: {$e->getMessage()}");
                    Log::error('Thumbnail processing failed', [
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
            $this->error('Error during thumbnail processing: ' . $e->getMessage());
            Log::error('Thumbnail processing failed', ['exception' => $e]);

            return 1;
        }
    }

    /**
     * Process thumbnail for a specific game
     *
     * @throws Exception|GuzzleException
     */
    private function processGameThumbnail(Game $game): void
    {
        $quality = (int) $this->option('quality');
        $force = $this->option('force');

        // Determine the source URL for the thumbnail
        $sourceUrl = $game->getEffectiveThumbnailUrl();

        if (! $sourceUrl) {
            throw new Exception('No thumbnail or screenshot available for processing');
        }

        $isUsingScreenshotFallback = ! $game->thumb_url && ! empty($game->screenshots);

        if ($isUsingScreenshotFallback) {
            $this->info('No thumbnail found, using first screenshot as fallback...');
        }

        // Download the thumbnail
        $this->info('Downloading thumbnail...');
        $response = $this->httpClient->get($sourceUrl, [
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to download thumbnail: HTTP {$response->getStatusCode()}");
        }

        // Get content and check if it's empty
        $content = $response->getBody()->getContents();
        if (empty($content)) {
            throw new Exception('Downloaded content is empty');
        }

        // Skip if files exist and not forcing
        if (! $force && $game->optimized_thumbnails) {
            $this->info('Thumbnails already exist, skipping (use --force to override)');

            return;
        }

        // Generate a unique filename with content checksum
        $baseFilename = $this->generateThumbnailFilename($game, $content, $sourceUrl);

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'thumb_');
        file_put_contents($tempFile, $content);

        // Verify the downloaded file
        if (! file_exists($tempFile) || filesize($tempFile) === 0) {
            throw new Exception('Failed to save downloaded content');
        }

        // Double check file is readable as an image
        try {
            $imageInfo = getimagesize($tempFile);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }

            $mimeType = $imageInfo['mime'];
            if (! in_array($mimeType, self::VALID_MIME_TYPES)) {
                throw new Exception("Invalid image mime type: {$mimeType}");
            }

            $this->info("Downloaded image: {$imageInfo[0]}x{$imageInfo[1]} pixels, type: {$mimeType}");

            // Clear out any existing thumbnails for this game
            $this->cleanupExistingThumbnails($game->id);
        } catch (Exception $e) {
            // Log the first few bytes of the file for debugging
            $fileStart = bin2hex(file_get_contents($tempFile, false, null, 0, 32));
            Log::error('Invalid image file details', [
                'game_id' => $game->id,
                'url' => $game->thumb_url,
                'error' => $e->getMessage(),
                'file_start' => $fileStart,
                'file_size' => filesize($tempFile),
            ]);
            throw new Exception('Invalid or corrupted image file: ' . $e->getMessage());
        }

        try {
            $thumbnails = [];

            // Ensure the thumbnail directory exists
            Storage::disk('public')->makeDirectory(self::THUMBNAIL_PATH);

            // Clear existing thumbnails if any
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }

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

                // Add to thumbnails array
                $thumbnails[$variant] = [
                    'path' => $this->getStoragePath($variantFilename),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'mime_type' => 'image/webp',
                    'animated' => false,
                ];
            }

            // Update database record with all variants
            $game->optimized_thumbnails = $thumbnails;
            $game->save();

        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Generate a unique filename for a game's thumbnail
     */
    private function generateThumbnailFilename(Game $game, string $fileContent, string $sourceUrl): string
    {
        // Generate a checksum of the file content to ensure cache invalidation when the image changes
        $contentChecksum = substr(md5($fileContent), 0, 8);

        return sprintf(
            '%d_%s_%s',
            $game->id,
            substr(md5($sourceUrl), 0, 8),
            $contentChecksum
        );
    }

    /**
     * Clean up any existing thumbnail files for a game
     */
    private function cleanupExistingThumbnails(int $gameId): void
    {
        $this->info('Cleaning up existing thumbnails...');

        // Get all files in the thumbnails directory
        $files = Storage::disk('public')->files(self::THUMBNAIL_PATH);

        // Pattern to match files for this game ID
        $pattern = "/^{$gameId}_[a-f0-9]{8}/";

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match($pattern, $filename)) {
                $this->info("Deleting: {$filename}");
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Get the storage path (relative to disk root)
     */
    private function getStoragePath(string $filename): string
    {
        return self::THUMBNAIL_PATH . '/' . $filename;
    }

    /**
     * Process a static image variant
     */
    private function processStaticVariant(
        string $sourcePath,
        string $destPath,
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
                $destPath,
                $config,
                $quality
            );
        } catch (Exception $e) {
            throw new Exception("Failed to process static image: {$e->getMessage()}");
        }
    }

    /**
     * Get image dimensions using the image processing service
     */
    private function getImageDimensions(string $path): array
    {
        return $this->imageProcessingService->getImageDimensions($path);
    }
}
