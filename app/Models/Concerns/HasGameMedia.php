<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasGameMedia
{
    /**
     * Check if the game has a thumbnail (either thumb_url or screenshots)
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
     * Get the optimized thumbnail URL (fallback to original if not available)
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
            // If no optimized thumbnail exists, try the original thumb_url
            if ($this->thumb_url) {
                return $this->thumb_url;
            }

            // Fallback to first screenshot if no thumbnail is available
            return $this->getFirstScreenshotUrl($variant);
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

        // Fallback to original screenshot URL
        return $firstScreenshot['url'] ?? null;
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

        $screenshots = [];

        foreach ($this->screenshots as $screenshot) {
            $screenshots[] = [
                'url' => $this->getOptimizedOrOriginalUrl($screenshot, 'large'),
                'thumbnail_url' => $this->getOptimizedOrOriginalUrl($screenshot, $variant),
            ];
        }

        return $screenshots;
    }

    /**
     * Get optimized URL for a screenshot or fall back to original
     */
    private function getOptimizedOrOriginalUrl(array $screenshot, string $variant): ?string
    {
        if (isset($screenshot['optimized'][$variant]['path'])) {
            return asset('storage/' . $screenshot['optimized'][$variant]['path']);
        }

        return $screenshot['url'] ?? null;
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

        return $this->getOptimizedOrOriginalUrl($screenshot, $variant);
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
}
