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
     * Clear all optimized screenshots
     */
    public function clearOptimizedScreenshots(): void
    {
        if (empty($this->optimized_screenshots)) {
            return;
        }

        foreach ($this->optimized_screenshots as $optimizedScreenshot) {
            if (isset($optimizedScreenshot['optimized'])) {
                foreach ($optimizedScreenshot['optimized'] as $variant) {
                    if (isset($variant['path'])) {
                        Storage::disk('public')->delete($variant['path']);
                    }
                }
            }
        }

        // Clear the optimized screenshots data
        $this->optimized_screenshots = null;
        $this->save();
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

        // If we have optimized screenshots, use them
        if (isset($this->optimized_screenshots[0]['optimized'][$variant]['path'])) {
            return asset('storage/' . $this->optimized_screenshots[0]['optimized'][$variant]['path']);
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

        foreach (array_keys($this->screenshots) as $index) {
            $screenshots[] = [
                'url' => $this->getScreenshotUrl($index, 'large'),
                'thumbnail_url' => $this->getScreenshotUrl($index, $variant),
            ];
        }

        return $screenshots;
    }

    /**
     * Get a screenshot URL by variant
     */
    public function getScreenshotUrl(int $index = 0, string $variant = 'default'): ?string
    {
        if (empty($this->screenshots) || ! isset($this->screenshots[$index])) {
            return null;
        }

        if (! isset($this->optimized_screenshots[$index]['optimized'][$variant])) {
            return $this->screenshots[$index]['url'] ?? null;
        }

        $path = $this->optimized_screenshots[$index]['optimized'][$variant]['path'];

        return asset('storage/' . $path);
    }
}
