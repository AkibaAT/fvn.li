<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasGameMedia
{
    /**
     * Check if the game has a source image that can be processed into a thumbnail.
     */
    public function hasThumbnail(): bool
    {
        return ! empty($this->thumb_url) || ! empty($this->screenshots);
    }

    /**
     * Get the effective thumbnail URL (thumb_url or first screenshot)
     */
    public function getEffectiveThumbnailUrl(): ?string
    {
        return $this->thumb_url ?: ($this->screenshots[0]['url'] ?? null);
    }

    /**
     * Clear all optimized thumbnails
     */
    public function clearOptimizedThumbnails(): void
    {
        if ($this->optimized_thumbnails) {
            foreach ($this->optimized_thumbnails as $variant) {
                if (isset($variant['path'])) {
                    Storage::disk('public')->delete($variant['path']);
                }
            }

            // Clear the thumbnails data
            $this->optimized_thumbnails = null;
            $this->save();
        }
    }

    /**
     * Clear all optimized screenshots from the screenshots array
     */
    public function clearOptimizedScreenshots(): void
    {
        if (empty($this->screenshots)) {
            return;
        }

        $updatedScreenshots = [];
        $hasOptimized = false;

        foreach ($this->screenshots as $screenshot) {
            // Delete the optimized files from storage
            if (isset($screenshot['optimized'])) {
                $hasOptimized = true;
                foreach ($screenshot['optimized'] as $variant) {
                    if (isset($variant['path'])) {
                        Storage::disk('public')->delete($variant['path']);
                    }
                }
            }

            // Keep only the original URL, remove optimized data
            $updatedScreenshots[] = ['url' => $screenshot['url']];
        }

        if ($hasOptimized) {
            $this->screenshots = $updatedScreenshots;
            $this->save();
        }
    }

    /**
     * Get the optimized thumbnail URL.
     */
    public function getOptimizedThumbnailUrlAttribute(): ?string
    {
        return $this->getThumbnailUrl('default');
    }

    /**
     * Get the URL for a thumbnail variant
     */
    public function getThumbnailUrl(string $variant = 'default'): ?string
    {
        if (! isset($this->optimized_thumbnails[$variant], $this->optimized_thumbnails[$variant]['path'])) {
            return null;
        }

        $path = $this->optimized_thumbnails[$variant]['path'];

        return asset('storage/' . $path);
    }

    /**
     * Get the first screenshot URL as a fallback thumbnail
     */
    public function getFirstScreenshotUrl(string $variant = 'default'): ?string
    {
        if (empty($this->screenshots) || ! isset($this->screenshots[0])) {
            return null;
        }

        $firstScreenshot = $this->screenshots[0];

        // If we have optimized data for this variant, use it
        if (isset($firstScreenshot['optimized'][$variant]['path'])) {
            return asset('storage/' . $firstScreenshot['optimized'][$variant]['path']);
        }

        return null;
    }

    /**
     * Resolve a screenshots array into frontend-ready URLs.
     *
     * @param  array<int, array<string, mixed>>  $screenshots
     * @return array<int, array<string, mixed>>
     */
    public function resolveScreenshots(array $screenshots, string $thumbnailVariant = 'default', string $displayVariant = 'large'): array
    {
        if (empty($screenshots)) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $screenshot) use ($thumbnailVariant, $displayVariant): ?array {
            $displayUrl = $this->getOptimizedScreenshotUrl($screenshot, $displayVariant);
            $thumbnailUrl = $this->getOptimizedScreenshotUrl($screenshot, $thumbnailVariant);

            if (! $displayUrl || ! $thumbnailUrl) {
                return null;
            }

            return [
                'url' => $displayUrl,
                'thumbnail_url' => $thumbnailUrl,
                'original_url' => $displayUrl,
            ];
        }, $screenshots)));
    }

    /**
     * Get all screenshots
     *
     * @param  string  $variant  The variant of the screenshot to get (small, default, large)
     * @return array The screenshots with optimized URLs if available
     */
    public function getScreenshots(string $variant = 'default'): array
    {
        if (empty($this->screenshots)) {
            return [];
        }

        return $this->resolveScreenshots($this->screenshots, $variant, 'large');
    }

    /**
     * Get a screenshot URL by index and variant
     */
    public function getScreenshotUrl(int $index = 0, string $variant = 'default'): ?string
    {
        if (empty($this->screenshots) || ! isset($this->screenshots[$index])) {
            return null;
        }

        $screenshot = $this->screenshots[$index];

        return $this->getOptimizedScreenshotUrl($screenshot, $variant);
    }

    /**
     * Check if a screenshot has been optimized
     */
    public function isScreenshotOptimized(int $index = 0): bool
    {
        if (empty($this->screenshots) || ! isset($this->screenshots[$index])) {
            return false;
        }

        return isset($this->screenshots[$index]['optimized']) && ! empty($this->screenshots[$index]['optimized']);
    }

    /**
     * Get an optimized screenshot URL. Imported screenshots must not fall back to remote originals.
     */
    private function getOptimizedScreenshotUrl(array $screenshot, string $variant): ?string
    {
        if (isset($screenshot['optimized'][$variant]['path'])) {
            return asset('storage/' . $screenshot['optimized'][$variant]['path']);
        }

        return null;
    }
}
