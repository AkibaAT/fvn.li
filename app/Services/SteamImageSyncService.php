<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Services\Concerns\ReportsProgress;
use Exception;
use Illuminate\Support\Facades\Log;

class SteamImageSyncService
{
    use ReportsProgress;

    public function processImages(Game $game, ?string $originalThumbUrl, ?array $originalScreenshots): void
    {
        $imageService = app(ImageProcessingService::class);

        if ($this->needsScreenshotProcessing($game->screenshots, $originalScreenshots)) {
            try {
                $this->progress("    [Steam] Screenshots need processing...\n");
                if ($this->screenshotUrlsChanged($game->screenshots, $originalScreenshots)) {
                    $imageService->processGameScreenshots($game);
                } else {
                    $imageService->processGameScreenshots($game, 80, true);
                }
                $this->progress("    [Steam] Screenshots processed successfully\n");
            } catch (Exception $e) {
                Log::error('Failed to process Steam screenshots', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $needsThumbnailProcessing = (
            ($game->thumb_url !== $originalThumbUrl && $game->thumb_url) ||
            ($game->thumb_url && empty($game->optimized_thumbnails))
        );

        if ($needsThumbnailProcessing) {
            $this->processThumbnail($game, $imageService, 'Thumbnail needs processing', 'Thumbnail processed successfully');
        } elseif (! $game->thumb_url && ! empty($game->screenshots) && $this->screenshotUrlsChanged($game->screenshots, $originalScreenshots)) {
            $this->processThumbnail($game, $imageService, 'No thumbnail, processing first screenshot as fallback', 'Thumbnail fallback processed successfully');
        }
    }

    public function screenshotUrlsChanged(?array $screenshots1, ?array $screenshots2): bool
    {
        return $this->extractScreenshotUrls($screenshots1) !== $this->extractScreenshotUrls($screenshots2);
    }

    public function needsScreenshotProcessing(?array $screenshots, ?array $originalScreenshots): bool
    {
        if (empty($screenshots)) {
            return false;
        }

        return $this->screenshotUrlsChanged($screenshots, $originalScreenshots)
            || $this->screenshotsMissingOptimizedVariants($screenshots);
    }

    public function extractScreenshotUrls(?array $screenshots): array
    {
        if (empty($screenshots)) {
            return [];
        }

        $urls = [];
        foreach ($screenshots as $screenshot) {
            if (isset($screenshot['url'])) {
                $urls[] = $screenshot['url'];
            }
        }

        return $urls;
    }

    private function processThumbnail(Game $game, ImageProcessingService $imageService, string $startMessage, string $doneMessage): void
    {
        try {
            $this->progress("    [Steam] {$startMessage}...\n");
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }
            $imageService->processGameThumbnail($game);
            $this->progress("    [Steam] {$doneMessage}\n");
        } catch (Exception $e) {
            Log::error('Failed to process Steam thumbnail', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function screenshotsMissingOptimizedVariants(?array $screenshots): bool
    {
        if (empty($screenshots)) {
            return false;
        }

        foreach ($screenshots as $screenshot) {
            if (empty($screenshot['url'])) {
                continue;
            }

            foreach (['small', 'default', 'large'] as $variant) {
                if (empty($screenshot['optimized'][$variant]['path'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
