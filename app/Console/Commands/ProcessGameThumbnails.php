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

class ProcessGameThumbnails extends Command
{
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
            'width' => 128,
            'height' => 101,
        ],
        'default' => [
            'width' => 256,
            'height' => 203,
        ],
    ];

    /**
     * Background color for padding (dark gray)
     */
    private const BACKGROUND_COLOR = '#1a1a1a';

    protected $signature = 'games:process-thumbnails
        {--force : Process thumbnails even if they already exist}
        {--game-id= : Process specific game ID}
        {--quality=80 : WebP quality (0-100)}';

    protected $description = 'Process and optimize game thumbnails';

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
                ->whereNotNull('thumb_url');

            // If specific game ID provided, only process that one
            if ($gameId = $this->option('game-id')) {
                $query->where('id', $gameId);
            }

            $games = $query->get();
            $totalGames = $games->count();

            $this->info("Found {$totalGames} games to process");

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
        $baseFilename = $this->generateThumbnailFilename($game);

        // Skip if files exist and not forcing
        if (! $force && $game->optimized_thumbnails) {
            $this->info('Thumbnails already exist, skipping (use --force to override)');

            return;
        }

        // Download the thumbnail
        $this->info('Downloading thumbnail...');
        $response = $this->httpClient->get($game->thumb_url, [
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
            // Check if image is animated GIF
            $isAnimated = $this->isAnimatedGif($tempFile);
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

                $variantFilename = $baseFilename . "_{$variant}" . ($isAnimated ? '.animated.webp' : '.webp');
                $variantPath = $this->getStoragePath($variantFilename);

                if ($isAnimated) {
                    $this->processAnimatedVariant(
                        $tempFile,
                        $variantPath,
                        $config,
                        $quality
                    );
                } else {
                    $this->processStaticVariant(
                        $tempFile,
                        $variantPath,
                        $config,
                        $quality
                    );
                }

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
                    'animated' => $isAnimated,
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
    private function generateThumbnailFilename(Game $game): string
    {
        return sprintf(
            '%d_%s',
            $game->id,
            substr(md5($game->thumb_url), 0, 8)
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
        $pattern = "/^{$gameId}_[a-f0-9]{8}_/";

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match($pattern, $filename)) {
                $this->info("Deleting: {$filename}");
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Check if an image is an animated GIF
     */
    private function isAnimatedGif(string $path): bool
    {
        // First check if it's a GIF
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        if ($mimeType !== 'image/gif') {
            return false;
        }

        // Check for animation frames
        $frames = 0;
        $handle = fopen($path, 'rb');

        while (! feof($handle) && $frames < 2) {
            $chunk = fread($handle, 1024 * 100); // Read 100KB at a time
            $frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk);
        }

        fclose($handle);

        return $frames > 1;
    }

    /**
     * Get the storage path (relative to disk root)
     */
    private function getStoragePath(string $filename): string
    {
        return self::THUMBNAIL_PATH . '/' . $filename;
    }

    /**
     * Process an animated image variant
     */
    private function processAnimatedVariant(
        string $sourcePath,
        string $destPath,
        array $config,
        int $quality
    ): void {
        // Ensure FFmpeg is available
        if (! $this->isFFmpegAvailable()) {
            throw new Exception('FFmpeg is required for animated image processing');
        }

        // Create output directory if needed
        Storage::disk('public')->makeDirectory(self::THUMBNAIL_PATH);

        // Extract background color components for FFmpeg
        $bgColor = substr(self::BACKGROUND_COLOR, 1); // Remove #

        // Build the complex filter for maintaining aspect ratio with padding
        $filterComplex = sprintf(
            'scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2:color=%s',
            $config['width'],
            $config['height'],
            $config['width'],
            $config['height'],
            $bgColor
        );

        // Build FFmpeg command - importantly including -ignore_loop 0 to preserve GIF looping
        $command = sprintf(
            'ffmpeg -i %s -vf "%s" -c:v libwebp -quality %d ' .
            '-lossless 0 -compression_level 6 -preset picture ' .
            '-loop 0 -threads 4 -an -vsync 0 %s 2>&1',
            escapeshellarg($sourcePath),
            $filterComplex,
            $quality,
            escapeshellarg($this->getRealPath($destPath))
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception('Failed to convert animated image: ' . implode("\n", $output));
        }
    }

    /**
     * Check if FFmpeg is available
     */
    private function isFFmpegAvailable(): bool
    {
        exec('which ffmpeg 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Get the absolute path for the given storage path
     */
    private function getRealPath(string $path): string
    {
        return Storage::disk('public')->path($path);
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
            // Create canvas with desired dimensions and background color
            $canvas = $this->imageManager->create($config['width'], $config['height'])
                ->fill(self::BACKGROUND_COLOR);

            // Load and process source image
            $image = $this->imageManager->read($sourcePath);

            // Verify we got a valid image
            if ($image->width() === 0 || $image->height() === 0) {
                throw new Exception('Invalid image dimensions');
            }

            $this->info("Processing image: {$image->width()}x{$image->height()} pixels");

            // Calculate dimensions to maintain aspect ratio
            $sourceAspect = $image->width() / $image->height();
            $targetAspect = $config['width'] / $config['height'];

            if ($sourceAspect > $targetAspect) {
                // Image is wider than target - scale to match height
                $newHeight = $config['height'];
                $newWidth = intval($newHeight * $sourceAspect);
                $image = $image->scale(height: $newHeight);
            } else {
                // Image is taller than target - scale to match width
                $newWidth = $config['width'];
                $newHeight = intval($newWidth / $sourceAspect);
                $image = $image->scale(width: $newWidth);
            }

            // Calculate position to center the image
            $x = intval(($config['width'] - $image->width()) / 2);
            $y = intval(($config['height'] - $image->height()) / 2);

            // Place resized image onto canvas
            $canvas->place($image, 'center', $x, $y);

            // Encode and save
            Storage::disk('public')->makeDirectory(self::THUMBNAIL_PATH);
            $encoded = $canvas->toWebp($quality);
            Storage::disk('public')->put($destPath, $encoded->toString());
        } catch (Exception $e) {
            throw new Exception("Failed to process static image: {$e->getMessage()}");
        }
    }

    /**
     * Get image dimensions using ImageMagick's identify command
     */
    private function getImageDimensions(string $path): array
    {
        $realPath = $this->getRealPath($path);

        // Use [0] to specify first frame, which works for both animated and static images
        $command = sprintf('identify -format "%%wx%%h" %s[0]', escapeshellarg($realPath));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            throw new Exception('Failed to get image dimensions');
        }

        // identify outputs dimensions in format "widthxheight"
        [$width, $height] = explode('x', $output[0]);

        return [
            'width' => (int) $width,
            'height' => (int) $height,
        ];
    }
}
