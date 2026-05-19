<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Exception;
use Illuminate\Support\Facades\Log;

class GameMetadataImageProcessor
{
    public function process(Game $game, ?string $originalThumbUrl, ?array $originalScreenshots, string $logPrefix): void
    {
        $imageService = app(ImageProcessingService::class);

        if ($this->needsScreenshotProcessing($game->screenshots, $originalScreenshots)) {
            try {
                echo "    [{$logPrefix}] Screenshots need processing before save...\n";
                if ($this->screenshotUrlsChanged($game->screenshots, $originalScreenshots)) {
                    $imageService->processGameScreenshots($game);
                } elseif ($this->screenshotsHaveOptimizedVariants($game->screenshots)) {
                    $imageService->processGameScreenshots($game, 80, true);
                } else {
                    $imageService->processGameScreenshots($game);
                }
                echo "    [{$logPrefix}] Screenshots processed successfully\n";
            } catch (Exception $e) {
                Log::error('Failed to process screenshots during metadata refresh', [
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
            $this->processThumbnail($game, $imageService, $logPrefix);
        } elseif (! $game->thumb_url && ! empty($game->screenshots) && $this->screenshotUrlsChanged($game->screenshots, $originalScreenshots)) {
            $this->processThumbnailFallback($game, $imageService, $logPrefix);
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

    private function processThumbnail(Game $game, ImageProcessingService $imageService, string $logPrefix): void
    {
        try {
            echo "    [{$logPrefix}] Thumbnail needs processing...\n";
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }
            $imageService->processGameThumbnail($game);
            echo "    [{$logPrefix}] Thumbnail processed successfully\n";
        } catch (Exception $e) {
            Log::error('Failed to process thumbnail during metadata refresh', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processThumbnailFallback(Game $game, ImageProcessingService $imageService, string $logPrefix): void
    {
        try {
            echo "    [{$logPrefix}] No thumbnail, processing first screenshot as fallback...\n";
            if ($game->optimized_thumbnails) {
                $game->clearOptimizedThumbnails();
            }
            $imageService->processGameThumbnail($game);
            echo "    [{$logPrefix}] Thumbnail fallback processed successfully\n";
        } catch (Exception $e) {
            Log::error('Failed to process thumbnail fallback during metadata refresh', [
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

    private function screenshotsHaveOptimizedVariants(?array $screenshots): bool
    {
        if (empty($screenshots)) {
            return false;
        }

        foreach ($screenshots as $screenshot) {
            if (! empty($screenshot['optimized'])) {
                return true;
            }
        }

        return false;
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
}
