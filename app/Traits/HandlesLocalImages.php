<?php

declare(strict_types=1);

namespace App\Traits;

use Exception;

trait HandlesLocalImages
{
    /**
     * Check if the URL points to a local cached image
     */
    protected function isLocalThumbnail(string $url): bool
    {
        // Check if it's a local asset URL (contains /storage/ or starts with the app URL)
        $appUrl = config('app.url');

        return str_contains($url, '/storage/') || str_starts_with($url, $appUrl);
    }

    /**
     * Convert a local asset URL to filesystem path
     */
    protected function getLocalThumbnailPath(string $url): ?string
    {
        try {
            // Extract the storage path from the URL
            if (str_contains($url, '/storage/')) {
                $storagePath = substr($url, strpos($url, '/storage/') + 9);
                $fullPath = storage_path('app/public/' . $storagePath);

                return $fullPath;
            }

            // Handle other local URL patterns if needed
            $appUrl = config('app.url');
            if (str_starts_with($url, $appUrl)) {
                $relativePath = substr($url, strlen($appUrl));
                if (str_starts_with($relativePath, '/storage/')) {
                    $storagePath = substr($relativePath, 9);
                    $fullPath = storage_path('app/public/' . $storagePath);

                    return $fullPath;
                }
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }
}
