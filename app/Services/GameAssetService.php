<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GameAssetService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Upload multiple images for a game's custom page
     */
    public function uploadMultipleImages(Game $game, array $files): array
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploadedImages[] = $this->uploadImage($game, $file);
            }
        }

        return $uploadedImages;
    }

    /**
     * Upload and process an image for a game's custom page
     */
    public function uploadImage(Game $game, UploadedFile $file): array
    {
        $this->validateImage($file);

        $filename = $this->generateUniqueFilename($file);
        $path = "game-assets/{$game->slug}";

        // Store original image
        $originalPath = Storage::disk('public')->putFileAs($path, $file, $filename);

        // Create optimized versions
        $variants = $this->createImageVariants($originalPath, $path, $filename);

        return [
            'original' => $originalPath,
            'variants' => $variants,
            'url' => Storage::disk('public')->url($originalPath),
            'alt' => '',
            'caption' => '',
        ];
    }

    /**
     * Delete an image and all its variants
     */
    public function deleteImage(string $imagePath): bool
    {
        $deleted = Storage::disk('public')->delete($imagePath);

        // Also delete variants
        $this->deleteImageVariants($imagePath);

        return $deleted;
    }

    /**
     * Update image metadata (alt text, caption)
     */
    public function updateImageMetadata(Game $game, string $imagePath, array $metadata): void
    {
        $customAssets = $game->custom_assets ?: [];

        foreach ($customAssets as &$asset) {
            if ($asset['original'] === $imagePath) {
                $asset['alt'] = $metadata['alt'] ?? $asset['alt'];
                $asset['caption'] = $metadata['caption'] ?? $asset['caption'];
                break;
            }
        }

        $game->update(['custom_assets' => $customAssets]);
    }

    /**
     * Get optimized image URL for a specific variant
     */
    public function getImageUrl(array $imageData, string $variant = 'medium'): string
    {
        if (isset($imageData['variants'][$variant])) {
            return Storage::disk('public')->url($imageData['variants'][$variant]);
        }

        return Storage::disk('public')->url($imageData['original']);
    }

    /**
     * Clean up unused assets for a game
     */
    public function cleanupUnusedAssets(Game $game): void
    {
        $usedAssets = collect();

        // Collect assets from custom_assets
        if ($game->custom_assets) {
            foreach ($game->custom_assets as $asset) {
                $usedAssets->push($asset['original']);
                if (isset($asset['variants'])) {
                    $usedAssets = $usedAssets->merge(array_values($asset['variants']));
                }
            }
        }

        // Collect assets referenced in custom_description
        if ($game->custom_description) {
            preg_match_all('/storage\/game-assets\/[^"\'>\s]+/', $game->custom_description, $matches);
            foreach ($matches[0] as $match) {
                $usedAssets->push(str_replace('storage/', '', $match));
            }
        }

        // Get all files in the game's asset directory
        $gameAssetPath = "game-assets/{$game->slug}";
        $allFiles = Storage::disk('public')->files($gameAssetPath);

        // Delete unused files
        foreach ($allFiles as $file) {
            if (! $usedAssets->contains($file)) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Validate uploaded image file
     */
    private function validateImage(UploadedFile $file): void
    {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if ($file->getSize() > $maxSize) {
            throw new Exception('Image file too large. Maximum size is 10MB.');
        }

        if (! in_array($file->getMimeType(), $allowedTypes)) {
            throw new Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }
    }

    /**
     * Generate unique filename for uploaded image
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);

        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Create optimized variants of an uploaded image
     */
    private function createImageVariants(string $originalPath, string $basePath, string $filename): array
    {
        $variants = [];
        $fullPath = Storage::disk('public')->path($originalPath);

        if (! file_exists($fullPath)) {
            return $variants;
        }

        $sizes = [
            'small' => 400,
            'medium' => 800,
            'large' => 1200,
        ];

        foreach ($sizes as $size => $width) {
            try {
                $variantFilename = $this->getVariantFilename($filename, $size);
                $variantPath = "{$basePath}/{$variantFilename}";
                $variantFullPath = Storage::disk('public')->path($variantPath);

                // Create directory if it doesn't exist
                $directory = dirname($variantFullPath);
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $image = $this->imageManager->decodePath($fullPath);
                $image->scaleDown(width: $width);
                $image->save($variantFullPath, quality: 85);

                $variants[$size] = $variantPath;
            } catch (Exception $e) {
                // Log error but don't fail the upload
                Log::warning("Failed to create {$size} variant for {$filename}: " . $e->getMessage());
            }
        }

        return $variants;
    }

    /**
     * Get variant filename
     */
    private function getVariantFilename(string $originalFilename, string $size): string
    {
        $pathInfo = pathinfo($originalFilename);

        return $pathInfo['filename'] . "_{$size}." . $pathInfo['extension'];
    }

    /**
     * Delete all variants of an image
     */
    private function deleteImageVariants(string $originalPath): void
    {
        $pathInfo = pathinfo($originalPath);
        $basePath = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        $sizes = ['small', 'medium', 'large'];

        foreach ($sizes as $size) {
            $variantPath = "{$basePath}/{$filename}_{$size}.{$extension}";
            Storage::disk('public')->delete($variantPath);
        }
    }
}
