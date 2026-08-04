<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\ImageDownloadUrlValidator;
use App\Services\ImageProcessingService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ProcessGameThumbnails extends Command
{
    use SelectsGames;

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
        private readonly ImageProcessingService $imageProcessingService,
        private readonly ImageDownloadUrlValidator $imageUrlValidator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->imageProcessingService->setProgressReporter(fn (string $message) => $this->line($message));

        try {
            if (! $this->validateGameSelectionOptions()) {
                return 1;
            }

            $query = Game::query()
                ->where('is_visible', true)
                ->where(function ($q) {
                    $q->whereNotNull('thumb_url')
                        ->orWhereNotNull('screenshots');
                });

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
                    $this->info('Thumbnail processed successfully');
                } catch (Exception $e) {
                    $this->error("Error processing thumbnail: {$e->getMessage()}");
                    Log::error('Thumbnail processing failed', [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }

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
        $request = $this->imageUrlValidator->validatedRequest($sourceUrl);
        $response = $this->httpClient->get(
            $request['url'],
            array_replace_recursive(self::DOWNLOAD_OPTIONS, $request['options'])
        );

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to download thumbnail: HTTP {$response->getStatusCode()}");
        }

        $this->assertAcceptableContentLength($response);
        $content = $this->readLimitedBody($response->getBody());
        if (empty($content)) {
            throw new Exception('Downloaded content is empty');
        }

        if (! $force && $game->optimized_thumbnails) {
            $this->info('Thumbnails already exist, skipping (use --force to override)');

            return;
        }

        $baseFilename = $this->generateThumbnailFilename($game, $content, $sourceUrl);

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

            $this->assertAcceptableImageDimensions($imageInfo);

            $this->info("Downloaded image: {$imageInfo[0]}x{$imageInfo[1]} pixels, type: {$mimeType}");

            $this->cleanupExistingThumbnails($game->id);
        } catch (Exception $e) {
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

            Storage::disk('public')->makeDirectory(self::THUMBNAIL_PATH);

            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }

            foreach (self::VARIANTS as $variant => $config) {
                $this->info("Processing {$variant} variant...");

                $variantFilename = $baseFilename . "_{$variant}.webp";
                $variantPath = $this->getStoragePath($variantFilename);

                $dimensions = $this->processStaticVariant(
                    $tempFile,
                    $variantPath,
                    $config,
                    $quality
                );

                $thumbnails[$variant] = [
                    'path' => $this->getStoragePath($variantFilename),
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'mime_type' => 'image/webp',
                    'animated' => false,
                ];
            }

            $game->optimized_thumbnails = $thumbnails;
            $game->save();

        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    private function assertAcceptableContentLength(ResponseInterface $response): void
    {
        $contentLength = $response->getHeaderLine('Content-Length');

        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > self::MAX_DOWNLOAD_BYTES) {
            throw new Exception(sprintf(
                'Downloaded thumbnail is too large: %d bytes exceeds %d byte limit',
                (int) $contentLength,
                self::MAX_DOWNLOAD_BYTES
            ));
        }
    }

    private function readLimitedBody(StreamInterface $body): string
    {
        $content = '';

        while (! $body->eof()) {
            $content .= $body->read(self::DOWNLOAD_CHUNK_BYTES);

            if (strlen($content) > self::MAX_DOWNLOAD_BYTES) {
                throw new Exception(sprintf(
                    'Downloaded thumbnail exceeds maximum size of %d bytes',
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
     * Clean up any existing thumbnail files for a game
     */
    private function cleanupExistingThumbnails(int $gameId): void
    {
        $this->info('Cleaning up existing thumbnails...');

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

    private function getStoragePath(string $filename): string
    {
        return self::THUMBNAIL_PATH . '/' . $filename;
    }

    private function processStaticVariant(
        string $sourcePath,
        string $destPath,
        array $config,
        int $quality
    ): array {
        try {
            $imageInfo = getimagesize($sourcePath);
            $this->info("Processing image: {$imageInfo[0]}x{$imageInfo[1]} pixels");

            return $this->imageProcessingService->processImageVariant(
                $sourcePath,
                $destPath,
                $config,
                $quality
            );
        } catch (Exception $e) {
            throw new Exception("Failed to process static image: {$e->getMessage()}");
        }
    }
}
