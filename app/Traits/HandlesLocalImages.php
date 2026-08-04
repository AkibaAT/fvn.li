<?php

declare(strict_types=1);

namespace App\Traits;

use Exception;

trait HandlesLocalImages
{
    protected function isLocalThumbnail(string $url): bool
    {
        $appUrl = config('app.url');

        return str_contains($url, '/storage/') || str_starts_with($url, $appUrl);
    }

    /**
     * Convert a local asset URL to filesystem path
     */
    protected function getLocalThumbnailPath(string $url): ?string
    {
        try {
            if (str_contains($url, '/storage/')) {
                $storagePath = substr($url, strpos($url, '/storage/') + 9);
                $fullPath = storage_path('app/public/' . $storagePath);

                return $fullPath;
            }

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
            report($e);

            return null;
        }
    }
}
