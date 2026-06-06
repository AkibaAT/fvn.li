<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ImageProcessingService
{
    private const SCREENSHOTS_PATH = 'screenshots';

    private const THUMBNAIL_PATH = 'thumbnails';

    private const DOWNLOAD_OPTIONS = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'allow_redirects' => false,
        'stream' => true,
    ];

    private const MAX_DOWNLOAD_BYTES = 10 * 1024 * 1024;

    private const MAX_IMAGE_PIXELS = 40_000_000;

    private const DOWNLOAD_CHUNK_BYTES = 8192;

    private const SCREENSHOT_VARIANTS = [
        'small' => [
            'width' => 320,
            'height' => 180,
        ],
        'default' => [
            'width' => 320,
            'height' => 20000,
        ],
        'large' => [
            'width' => 1280,
            'height' => 720,
        ],
    ];

    private const THUMBNAIL_VARIANTS = [
        'small' => [
            'width' => 158,
            'height' => 125,
        ],
        'default' => [
            'width' => 315,
            'height' => 250,
        ],
    ];

    private const VALID_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private readonly ImageManager $imageManager;

    public function __construct(
        private readonly Client $httpClient,
        private readonly ImageDownloadUrlValidator $imageUrlValidator
    ) {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Process an image variant while maintaining aspect ratio
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  string  $targetPath  Path where the processed image should be saved
     * @param  array  $config  Configuration with width and height
     * @param  int  $quality  WebP quality (0-100)
     * @param  string  $diskName  Storage disk name
     * @return array Array with width and height of the processed image
     *
     * @throws Exception
     */
    public function processImageVariant(
        string $sourcePath,
        string $targetPath,
        array $config,
        int $quality,
        string $diskName = 'public'
    ): array {
        $tempJpg = null;

        try {
            // For GIFs, first extract the first frame using ImageMagick to reduce memory usage
            $imageInfo = getimagesize($sourcePath);
            $mimeType = $imageInfo['mime'];

            if ($mimeType === 'image/gif') {
                $tempJpg = tempnam(sys_get_temp_dir(), 'image_frame_');
                if ($tempJpg === false) {
                    throw new Exception('Failed to create temporary frame file');
                }

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

            // Load source image
            $image = $this->imageManager->decodePath($sourcePath);

            // Verify we got a valid image
            if ($image->width() === 0 || $image->height() === 0) {
                throw new Exception('Invalid image dimensions');
            }

            // Special handling for the "large" variant - keep original resolution
            $variantName = basename(dirname($targetPath)) === 'screenshots' ?
                basename($targetPath, '.webp') : '';

            // Check if this is the large variant (ends with _large.webp)
            $isLargeVariant = str_ends_with($variantName, '_large');

            // For large variant, keep the original resolution, just convert to WebP
            if ($isLargeVariant) {
                // No resizing for large variant - keep original resolution
            } else {
                // For other variants, scale to fit within target dimensions
                $widthRatio = $config['width'] / $image->width();
                $heightRatio = $config['height'] / $image->height();

                // Use the smaller ratio to ensure the image fits within the target dimensions
                $ratio = min($widthRatio, $heightRatio);

                $newWidth = intval($image->width() * $ratio);
                $newHeight = intval($image->height() * $ratio);

                // Resize the image while maintaining aspect ratio
                $image->resize($newWidth, $newHeight);
            }

            // Capture dimensions before encoding
            $finalWidth = $image->width();
            $finalHeight = $image->height();

            // Save as WebP
            Storage::disk($diskName)->put(
                $targetPath,
                (string) $image->encode(new WebpEncoder(quality: $quality))
            );

            return [
                'width' => $finalWidth,
                'height' => $finalHeight,
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to process image: {$e->getMessage()}");
        } finally {
            // Clean up temporary frame file if it exists, including convert failures.
            if (is_string($tempJpg) && file_exists($tempJpg)) {
                unlink($tempJpg);
            }
        }
    }

    /**
     * Process all screenshots for a game
     * Downloads and creates optimized variants for all screenshots
     *
     * @param  Game  $game  The game to process screenshots for
     * @param  int  $quality  WebP quality (0-100)
     * @param  bool  $force  Force reprocessing even if already optimized
     *
     * @throws Exception|GuzzleException
     */
    public function processGameScreenshots(Game $game, int $quality = 80, bool $force = false): void
    {
        if (empty($game->screenshots)) {
            Log::info('No screenshots to process', ['game_id' => $game->id]);

            return;
        }

        echo '    [Images] Processing ' . count($game->screenshots) . " screenshots\n";

        $updatedScreenshots = [];
        $processableScreenshots = 0;
        $optimizedScreenshots = 0;

        foreach ($game->screenshots as $index => $screenshot) {
            $sourceUrl = $screenshot['url'] ?? null;

            if (empty($sourceUrl)) {
                echo "    [Images] Screenshot {$index} has no URL, skipping\n";

                continue;
            }

            $processableScreenshots++;
            echo "    [Images] Processing screenshot {$index}: {$sourceUrl}\n";

            try {
                // Skip if already optimized and not forcing
                if (! $force && isset($screenshot['optimized']) && ! empty($screenshot['optimized'])) {
                    echo "    [Images] Screenshot already optimized, skipping\n";
                    $updatedScreenshots[] = $screenshot;
                    $optimizedScreenshots++;

                    continue;
                }

                // Download the screenshot
                echo "    [Images] Downloading screenshot...\n";
                $response = $this->downloadImage($sourceUrl);

                $content = $this->readDownloadContent($response);
                $tempFile = tempnam(sys_get_temp_dir(), 'screenshot_');
                file_put_contents($tempFile, $content);

                // Clean up existing screenshots for this game and URL
                $this->cleanupExistingScreenshots($game->id, $sourceUrl);

                // Generate a unique filename based on URL and content
                $baseFilename = $this->generateScreenshotFilename($game, $sourceUrl, $content);

                // Verify it's a valid image
                $imageInfo = getimagesize($tempFile);
                if ($imageInfo === false) {
                    throw new Exception('Invalid image file');
                }

                $mimeType = $imageInfo['mime'];
                if (! in_array($mimeType, self::VALID_MIME_TYPES)) {
                    throw new Exception("Unsupported image type: {$mimeType}");
                }

                $this->assertAcceptableImageDimensions($imageInfo);

                echo "    [Images] Downloaded image: {$imageInfo[0]}x{$imageInfo[1]} pixels, type: {$mimeType}\n";

                // Process variants
                $optimizedVariants = [];
                Storage::disk('public')->makeDirectory(self::SCREENSHOTS_PATH);

                foreach (self::SCREENSHOT_VARIANTS as $variant => $config) {
                    echo "    [Images] Processing {$variant} variant...\n";

                    $variantFilename = $baseFilename . "_{$variant}.webp";
                    $variantPath = $this->getStoragePath($variantFilename, self::SCREENSHOTS_PATH);

                    $dimensions = $this->processImageVariant($tempFile, $variantPath, $config, $quality);

                    $optimizedVariants[$variant] = [
                        'path' => $variantPath,
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'mime_type' => 'image/webp',
                    ];
                }

                // Store the screenshot with optimized data embedded
                $updatedScreenshots[] = [
                    'url' => $sourceUrl,
                    'optimized' => $optimizedVariants,
                ];
                $optimizedScreenshots++;

                unlink($tempFile);
            } catch (Exception $e) {
                echo "    [Images] Error processing screenshot {$index}: {$e->getMessage()}\n";
                Log::error('Screenshot processing failed', [
                    'game_id' => $game->id,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
                // Keep original screenshot data (with any existing optimized data)
                $updatedScreenshots[] = $screenshot;
            }
        }

        // Update the game with processed screenshots
        $game->screenshots = $updatedScreenshots;

        if ($processableScreenshots > 0 && $optimizedScreenshots === 0) {
            throw new Exception('Failed to optimize any screenshots');
        }
    }

    /**
     * Process thumbnail for a game
     * Downloads and creates optimized variants
     *
     * @param  Game  $game  The game to process thumbnail for
     * @param  int  $quality  WebP quality (0-100)
     * @param  bool  $force  Force reprocessing even if already optimized
     *
     * @throws Exception|GuzzleException
     */
    public function processGameThumbnail(Game $game, int $quality = 80, bool $force = false): void
    {
        // Determine the source URL for the thumbnail
        $sourceUrl = $game->getEffectiveThumbnailUrl();

        if (! $sourceUrl) {
            throw new Exception('No thumbnail or screenshot available for processing');
        }

        $isUsingScreenshotFallback = ! $game->thumb_url && ! empty($game->screenshots);

        if ($isUsingScreenshotFallback) {
            echo "    [Images] No thumbnail found, using first screenshot as fallback...\n";
        }

        // Skip if files exist and not forcing
        if (! $force && $game->optimized_thumbnails) {
            echo "    [Images] Thumbnails already exist, skipping\n";

            return;
        }

        // Download the thumbnail
        echo "    [Images] Downloading thumbnail...\n";
        $response = $this->downloadImage($sourceUrl);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to download thumbnail: HTTP {$response->getStatusCode()}");
        }

        $content = $this->readDownloadContent($response);
        if (empty($content)) {
            throw new Exception('Downloaded content is empty');
        }

        // Generate a unique filename with content checksum
        $baseFilename = $this->generateThumbnailFilename($game, $content, $sourceUrl);

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'thumb_');
        file_put_contents($tempFile, $content);

        if (! file_exists($tempFile) || filesize($tempFile) === 0) {
            throw new Exception('Failed to save downloaded content');
        }

        try {
            $imageInfo = getimagesize($tempFile);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }

            $mimeType = $imageInfo['mime'];
            if (! in_array($mimeType, self::VALID_MIME_TYPES)) {
                throw new Exception("Invalid image mime type: {$mimeType}");
            }

            $this->assertAcceptableImageDimensions($imageInfo);

            echo "    [Images] Downloaded image: {$imageInfo[0]}x{$imageInfo[1]} pixels, type: {$mimeType}\n";

            // Clear out any existing thumbnails for this game
            $this->cleanupExistingThumbnails($game->id);

            $thumbnails = [];
            Storage::disk('public')->makeDirectory(self::THUMBNAIL_PATH);

            // Clear existing thumbnails if any
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }

            // Process each variant
            foreach (self::THUMBNAIL_VARIANTS as $variant => $config) {
                echo "    [Images] Processing {$variant} variant...\n";

                $variantFilename = $baseFilename . "_{$variant}.webp";
                $variantPath = $this->getStoragePath($variantFilename, self::THUMBNAIL_PATH);

                $dimensions = $this->processImageVariant($tempFile, $variantPath, $config, $quality);

                $thumbnails[$variant] = [
                    'path' => $this->getStoragePath($variantFilename, self::THUMBNAIL_PATH),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'mime_type' => 'image/webp',
                    'animated' => false,
                ];
            }

            // Update game record with all variants
            $game->optimized_thumbnails = $thumbnails;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function downloadImage(string $sourceUrl): ResponseInterface
    {
        $request = $this->imageUrlValidator->validatedRequest($sourceUrl);

        return $this->httpClient->get(
            $request['url'],
            array_replace_recursive(self::DOWNLOAD_OPTIONS, $request['options'])
        );
    }

    private function readDownloadContent(ResponseInterface $response): string
    {
        $contentLength = $response->getHeaderLine('Content-Length');

        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > self::MAX_DOWNLOAD_BYTES) {
            throw new Exception(sprintf(
                'Downloaded image is too large: %d bytes exceeds %d byte limit',
                (int) $contentLength,
                self::MAX_DOWNLOAD_BYTES
            ));
        }

        return $this->readLimitedBody($response->getBody());
    }

    private function readLimitedBody(StreamInterface $body): string
    {
        $content = '';

        while (! $body->eof()) {
            $content .= $body->read(self::DOWNLOAD_CHUNK_BYTES);

            if (strlen($content) > self::MAX_DOWNLOAD_BYTES) {
                throw new Exception(sprintf(
                    'Downloaded image exceeds maximum size of %d bytes',
                    self::MAX_DOWNLOAD_BYTES
                ));
            }
        }

        return $content;
    }

    private function assertAcceptableImageDimensions(array $imageInfo): void
    {
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $pixels = $width * $height;

        if ($width <= 0 || $height <= 0 || $pixels > self::MAX_IMAGE_PIXELS) {
            throw new Exception(sprintf(
                'Image dimensions are too large: %dx%d exceeds %d pixel limit',
                $width,
                $height,
                self::MAX_IMAGE_PIXELS
            ));
        }
    }

    /**
     * Generate a unique filename for a screenshot based on URL and content
     */
    private function generateScreenshotFilename(Game $game, string $url, string $fileContent): string
    {
        // Use URL hash as the primary identifier (stable across updates)
        $urlHash = substr(md5($url), 0, 8);

        // Generate a checksum of the file content to ensure cache invalidation when the image changes
        $contentChecksum = substr(md5($fileContent), 0, 8);

        return sprintf(
            '%d_screenshot_%s_%s',
            $game->id,
            $urlHash,
            $contentChecksum
        );
    }

    /**
     * Generate a unique filename for a game's thumbnail
     */
    private function generateThumbnailFilename(Game $game, string $fileContent, string $sourceUrl): string
    {
        $contentChecksum = substr(md5($fileContent), 0, 8);

        return sprintf(
            '%d_%s_%s',
            $game->id,
            substr(md5($sourceUrl), 0, 8),
            $contentChecksum
        );
    }

    /**
     * Get the storage path for a file
     */
    private function getStoragePath(string $filename, string $basePath): string
    {
        return $basePath . '/' . $filename;
    }

    /**
     * Clean up existing screenshot files for a game based on URL hash
     */
    private function cleanupExistingScreenshots(int $gameId, string $sourceUrl): void
    {
        $files = Storage::disk('public')->files(self::SCREENSHOTS_PATH);

        // Generate the URL hash prefix to match files for this game and URL
        $urlHash = substr(md5($sourceUrl), 0, 8);
        $pattern = "/^{$gameId}_screenshot_{$urlHash}_[a-f0-9]{8}/";

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match($pattern, $filename)) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Clean up any existing thumbnail files for a game
     */
    private function cleanupExistingThumbnails(int $gameId): void
    {
        $files = Storage::disk('public')->files(self::THUMBNAIL_PATH);
        $pattern = "/^{$gameId}_[a-f0-9]{8}/";

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match($pattern, $filename)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
