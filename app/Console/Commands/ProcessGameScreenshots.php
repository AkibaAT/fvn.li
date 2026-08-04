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

class ProcessGameScreenshots extends Command
{
    use SelectsGames;

    private const SCREENSHOTS_PATH = 'screenshots';

    private const DOWNLOAD_OPTIONS = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'allow_redirects' => false,
    ];

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
            // Updated: constrain by max-width 320 while preserving aspect ratio
            'width' => 320,
            'height' => 20000,
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
                ->whereNotNull('screenshots');

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
            $this->info("Processing {$totalGames} games with screenshots");

            foreach ($games as $i => $game) {
                $this->info(sprintf("\nProcessing game %d/%d: %s", $i + 1, $totalGames, $game->name));

                try {
                    $this->processGameScreenshots($game);
                    $this->info('Screenshots processed successfully');
                } catch (Exception $e) {
                    $this->error("Error processing screenshots: {$e->getMessage()}");
                    Log::error('Screenshot processing failed', [
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
            $this->error("{$e->getMessage()}");
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
            $sourceUrl = $screenshot['url'] ?? null;

            if (empty($sourceUrl)) {
                $this->warn("Screenshot {$index} has no URL, skipping");

                continue;
            }

            $this->info("Processing screenshot {$index}: {$sourceUrl}");

            try {
                if (! $force && isset($screenshot['optimized']) && ! empty($screenshot['optimized'])) {
                    $this->info('Screenshot already optimized, skipping (use --force to override)');
                    $updatedScreenshots[] = $screenshot;

                    continue;
                }

                // Download the screenshot
                $this->info('Downloading screenshot...');
                $request = $this->imageUrlValidator->validatedRequest($sourceUrl);
                $response = $this->httpClient->get(
                    $request['url'],
                    array_replace_recursive(self::DOWNLOAD_OPTIONS, $request['options'])
                );

                $content = $response->getBody()->getContents();

                $tempFile = tempnam(sys_get_temp_dir(), 'screenshot_');
                file_put_contents($tempFile, $content);

                // Clean up existing screenshots for this game and URL
                $this->cleanupExistingScreenshots($game->id, $sourceUrl);

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

                $this->info("Downloaded image: {$imageInfo[0]}x{$imageInfo[1]} pixels, type: {$mimeType}");

                $optimizedVariants = [];

                Storage::disk('public')->makeDirectory(self::SCREENSHOTS_PATH);

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

                    $optimizedVariants[$variant] = [
                        'path' => $variantPath,
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'mime_type' => 'image/webp',
                    ];
                }

                $updatedScreenshots[] = [
                    'url' => $sourceUrl,
                    'optimized' => $optimizedVariants,
                ];

                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            } catch (Exception $e) {
                $this->error("Error processing screenshot {$index}: {$e->getMessage()}");
                // Keep the original screenshot data (with any existing optimized data)
                $updatedScreenshots[] = $screenshot;
            }
        }

        $game->screenshots = $updatedScreenshots;
        $game->save();
    }

    /**
     * Clean up existing screenshot files for a game based on URL hash
     */
    private function cleanupExistingScreenshots(int $gameId, string $sourceUrl): void
    {
        $this->info('Cleaning up existing screenshots...');

        $files = Storage::disk('public')->files(self::SCREENSHOTS_PATH);

        $urlHash = substr(md5($sourceUrl), 0, 8);
        $pattern = "/^{$gameId}_screenshot_{$urlHash}_[a-f0-9]{8}/";

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match($pattern, $filename)) {
                $this->info("Deleting: {$filename}");
                Storage::disk('public')->delete($file);
            }
        }
    }

    private function generateScreenshotFilename(Game $game, string $url, string $fileContent): string
    {
        $urlHash = substr(md5($url), 0, 8);

        $contentChecksum = substr(md5($fileContent), 0, 8);

        return sprintf(
            '%d_screenshot_%s_%s',
            $game->id,
            $urlHash,
            $contentChecksum
        );
    }

    private function getStoragePath(string $filename): string
    {
        return self::SCREENSHOTS_PATH . '/' . $filename;
    }

    private function processStaticVariant(
        string $sourcePath,
        string $targetPath,
        array $config,
        int $quality
    ): array {
        try {
            $imageInfo = getimagesize($sourcePath);
            $this->info("Processing image: {$imageInfo[0]}x{$imageInfo[1]} pixels");

            return $this->imageProcessingService->processImageVariant(
                $sourcePath,
                $targetPath,
                $config,
                $quality
            );
        } catch (Exception $e) {
            throw new Exception("Failed to process static image: {$e->getMessage()}");
        }
    }
}
