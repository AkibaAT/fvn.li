<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class GameMediaOptimizationService
{
    public function optimizedThumbnails(UploadedFile $file, int $gameId): array
    {
        $optimized = [];
        $sizes = [
            'small' => [189, 150],
            'default' => [315, 250],
            'large' => [630, 500],
        ];

        $manager = new ImageManager(new Driver);

        foreach ($sizes as $variant => [$width, $height]) {
            try {
                $image = $manager->decodePath($file->getRealPath());
                $image->cover($width, $height);
                $encoded = $image->encode(new WebpEncoder(quality: 80));

                $optimizedPath = "games/{$gameId}/thumbnails/{$variant}_".time().'.webp';
                Storage::disk('public')->put($optimizedPath, (string) $encoded);

                $optimized[$variant] = [
                    'path' => $optimizedPath,
                    'width' => $width,
                    'height' => $height,
                    'size' => strlen((string) $encoded),
                ];
            } catch (Exception $e) {
                Log::error('Failed to generate optimized thumbnail', [
                    'game_id' => $gameId,
                    'variant' => $variant,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $optimized;
    }

    public function optimizedScreenshots(UploadedFile $file, int $gameId, int $screenshotIndex): array
    {
        $optimized = [];
        $sizes = [
            'small' => [320, 180],
            'default' => [640, 360],
            'large' => [1920, 1080],
        ];

        $manager = new ImageManager(new Driver);

        foreach ($sizes as $variant => [$width, $height]) {
            try {
                $image = $manager->decodePath($file->getRealPath());
                $image->scale($width, $height);
                $encoded = $image->encode(new WebpEncoder(quality: 80));

                $optimizedPath = "games/{$gameId}/screenshots/{$screenshotIndex}_{$variant}_".time().'.webp';
                Storage::disk('public')->put($optimizedPath, (string) $encoded);

                $optimized[$variant] = [
                    'path' => $optimizedPath,
                    'width' => $width,
                    'height' => $height,
                    'size' => strlen((string) $encoded),
                ];
            } catch (Exception $e) {
                Log::error('Failed to generate optimized screenshot', [
                    'game_id' => $gameId,
                    'screenshot_index' => $screenshotIndex,
                    'variant' => $variant,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $optimized;
    }
}
