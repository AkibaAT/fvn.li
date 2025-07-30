<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\HandlesLocalImages;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class SocialImageService
{
    use HandlesLocalImages;

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Generate a collage image for social media sharing from a collection of games
     *
     * Uses the same thumbnail logic as game cards:
     * 1. Prefers cached/optimized thumbnails from storage when available
     * 2. Falls back to original thumb_url for external images
     * 3. Uses screenshots as final fallback
     *
     * @param  Collection|Arrayable  $games  Collection of Game models
     * @param  string  $cacheKey  Unique cache key for this specific configuration
     * @return string|null URL to the generated collage image, or null if generation failed
     */
    public function generateGameCollage(Collection|Arrayable $games, string $cacheKey): ?string
    {
        // Check if we already have a cached image
        $cachedImagePath = Cache::get("social_image_{$cacheKey}");
        if ($cachedImagePath && Storage::disk('public')->exists($cachedImagePath)) {
            return Storage::disk('public')->url($cachedImagePath);
        }

        // Ensure games is a collection
        if ($games instanceof Collection) {
            $gamesCollection = $games;
        } elseif (method_exists($games, 'getCollection')) {
            $gamesCollection = $games->getCollection();
        } else {
            $gamesCollection = collect($games);
        }

        // Filter games that have thumbnails (using the same logic as game cards)
        $gamesWithThumbs = $gamesCollection->filter(function ($game) {
            // Use the same method as the game cards to get thumbnail URL
            $thumbnailUrl = method_exists($game, 'getThumbnailUrl')
                ? $game->getThumbnailUrl('small')
                : $game->thumb_url;

            return ! empty($thumbnailUrl);
        })->take(9);

        if ($gamesWithThumbs->isEmpty()) {
            return null;
        }

        try {
            // Create the base collage image (1200x630 for optimal social media sharing)
            $collageWidth = 1200;
            $collageHeight = 630;
            $collage = $this->imageManager->create($collageWidth, $collageHeight)->fill('#1f2937'); // Dark gray background

            // Calculate grid layout optimized for square thumbnails
            $thumbsCount = $gamesWithThumbs->count();

            // Determine optimal grid layout for square thumbnails
            if ($thumbsCount <= 4) {
                // 2x2 grid for 1-4 thumbnails
                $cols = 2;
                $rows = 2;
            } elseif ($thumbsCount <= 6) {
                // 3x2 grid for 5-6 thumbnails
                $cols = 3;
                $rows = 2;
            } else {
                // 3x3 grid for 7-9 thumbnails
                $cols = 3;
                $rows = 3;
            }

            // Calculate square thumbnail dimensions with proper spacing
            $spacing = 12; // Increased spacing for better visual separation
            $availableWidth = $collageWidth - ($spacing * ($cols + 1));
            $availableHeight = $collageHeight - 140 - ($spacing * ($rows + 1)); // Reserve space for branding

            // Use the smaller dimension to ensure square thumbnails fit
            $maxThumbSize = min(
                (int) floor($availableWidth / $cols),
                (int) floor($availableHeight / $rows)
            );

            $thumbWidth = $thumbHeight = $maxThumbSize;

            $index = 0;
            foreach ($gamesWithThumbs as $game) {
                if ($index >= 9) {
                    break;
                } // Limit to 9 thumbnails max

                $row = (int) floor($index / $cols);
                $col = $index % $cols;

                // Calculate position with spacing
                $x = (int) ($spacing + ($col * ($thumbWidth + $spacing)));
                $y = (int) ($spacing + ($row * ($thumbHeight + $spacing)));

                try {
                    // Use the same thumbnail URL logic as game cards
                    $thumbnailUrl = method_exists($game, 'getThumbnailUrl')
                        ? $game->getThumbnailUrl('small')
                        : $game->thumb_url;

                    if (! $thumbnailUrl) {
                        continue;
                    }

                    // Check if it's a local cached image or external URL
                    if ($this->isLocalThumbnail($thumbnailUrl)) {
                        // Use local cached image directly
                        $localPath = $this->getLocalThumbnailPath($thumbnailUrl);
                        if ($localPath && file_exists($localPath)) {
                            $thumb = $this->imageManager->read($localPath);
                        } else {
                            continue; // Skip if local file doesn't exist
                        }
                    } else {
                        // Download external image
                        $thumbnailData = $this->downloadImage($thumbnailUrl);
                        if ($thumbnailData) {
                            $thumb = $this->imageManager->read($thumbnailData);
                        } else {
                            continue; // Skip if download failed
                        }
                    }

                    // Resize to fit within the grid cell while maintaining aspect ratio
                    // Calculate the scaling ratio to fit within the cell
                    $widthRatio = $thumbWidth / $thumb->width();
                    $heightRatio = $thumbHeight / $thumb->height();
                    $ratio = min($widthRatio, $heightRatio);

                    $newWidth = intval($thumb->width() * $ratio);
                    $newHeight = intval($thumb->height() * $ratio);

                    // Resize the thumbnail while preserving aspect ratio
                    $thumb = $thumb->resize($newWidth, $newHeight);

                    // Calculate position to center the thumbnail within the grid cell
                    $centerX = $x + intval(($thumbWidth - $newWidth) / 2);
                    $centerY = $y + intval(($thumbHeight - $newHeight) / 2);

                    // Add the thumbnail to the collage, centered in its grid cell
                    $collage->place($thumb, 'top-left', $centerX, $centerY);
                } catch (Exception $e) {
                    // Skip this thumbnail if processing fails
                    continue;
                }

                $index++;
            }

            // Add a subtle overlay with site branding
            $this->addBrandingOverlay($collage, $collageWidth, $collageHeight);

            // Save the collage
            $imagePath = "social-images/collage_{$cacheKey}.webp";
            $encodedImage = $collage->toWebp(85); // 85% quality for good balance of size/quality

            Storage::disk('public')->put($imagePath, $encodedImage);

            // Cache the image path for 6 hours
            Cache::put("social_image_{$cacheKey}", $imagePath, 21600);

            return Storage::disk('public')->url($imagePath);

        } catch (Exception $e) {
            // Log error but don't fail completely
            logger()->warning('Failed to generate social image collage', [
                'error' => $e->getMessage(),
                'cache_key' => $cacheKey,
            ]);

            return null;
        }
    }

    /**
     * Generate a cache key based on the games collection and current filters
     */
    public function generateCacheKey(Collection|Arrayable $games, array $filters = []): string
    {
        // Ensure games is a collection
        if ($games instanceof Collection) {
            $gamesCollection = $games;
        } elseif (method_exists($games, 'getCollection')) {
            $gamesCollection = $games->getCollection();
        } else {
            $gamesCollection = collect($games);
        }

        // Use game IDs and their updated timestamps for cache key
        $gameData = $gamesCollection->take(9)->map(function ($game) {
            $thumbnailUrl = method_exists($game, 'getThumbnailUrl')
                ? $game->getThumbnailUrl('small')
                : $game->thumb_url;

            return [
                'id' => $game->id,
                'updated' => $game->updated_at?->timestamp,
                'thumb' => $thumbnailUrl,
            ];
        })->toArray();

        $keyData = [
            'games' => $gameData,
            'filters' => $filters,
            'version' => 'v5', // Increment this when changing collage generation logic
        ];

        return md5(serialize($keyData));
    }

    /**
     * Clean up old cached social images
     */
    public function cleanupOldImages(): void
    {
        $socialImagesPath = 'social-images';

        if (! Storage::disk('public')->exists($socialImagesPath)) {
            return;
        }

        $files = Storage::disk('public')->files($socialImagesPath);
        $now = now();

        foreach ($files as $file) {
            $lastModified = Storage::disk('public')->lastModified($file);

            // Delete images older than 24 hours
            if ($now->timestamp - $lastModified > 86400) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Download an image from URL
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'FVN.li Social Image Generator/1.0',
                ],
            ]);

            $imageData = file_get_contents($url, false, $context);

            return $imageData ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Add subtle branding overlay
     */
    private function addBrandingOverlay(Image $image, int $width, int $height): void
    {
        // Create a semi-transparent overlay at the bottom - much larger to accommodate big text
        $overlayHeight = 150;
        $overlay = $this->imageManager->create($width, $overlayHeight)->fill('rgba(0, 0, 0, 0.8)');
        $image->place($overlay, 'bottom-left', 0, 0);

        // Add site name/logo text (centered and much larger - 5x bigger than before)
        $image->text('FVN.li', $width / 2, $height - 80, function (FontFactory $font) {
            $font->filename('../resources/fonts/roboto.ttf');
            $font->size(100);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('middle');
        });
    }
}
