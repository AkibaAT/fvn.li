<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

class GameImageIntegrityService
{
    private const SCREENSHOT_VARIANTS = ['small', 'default', 'large'];

    private const THUMBNAIL_VARIANTS = ['small', 'default'];

    /**
     * @param  array<int, array<string, mixed>>|null  $screenshots
     * @return array<int, array<int, string>>
     */
    public function screenshotIssues(?array $screenshots): array
    {
        $issues = [];

        foreach ($screenshots ?? [] as $index => $screenshot) {
            if (empty($screenshot['url'])) {
                continue;
            }

            $optimized = $screenshot['optimized'] ?? null;
            $variantIssues = $this->variantIssues(is_array($optimized) ? $optimized : null, self::SCREENSHOT_VARIANTS);
            if ($variantIssues !== []) {
                $issues[$index] = $variantIssues;
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>|null  $optimizedThumbnails
     * @return array<int, string>
     */
    public function thumbnailIssues(?array $optimizedThumbnails): array
    {
        return $this->variantIssues($optimizedThumbnails, self::THUMBNAIL_VARIANTS);
    }

    /**
     * @param  array<string, mixed>|null  $optimizedVariants
     * @param  array<int, string>  $requiredVariants
     * @return array<int, string>
     */
    private function variantIssues(?array $optimizedVariants, array $requiredVariants): array
    {
        $issues = [];
        $disk = Storage::disk('public');

        foreach ($requiredVariants as $variant) {
            $path = $optimizedVariants[$variant]['path'] ?? null;

            if (! is_string($path) || $path === '') {
                $issues[] = "{$variant}: missing path metadata";

                continue;
            }

            try {
                if (! $disk->exists($path)) {
                    $issues[] = "{$variant}: missing file {$path}";
                } elseif ($disk->size($path) === 0) {
                    $issues[] = "{$variant}: empty file {$path}";
                }
            } catch (Throwable $e) {
                $issues[] = "{$variant}: unreadable file {$path} ({$e->getMessage()})";
            }
        }

        return $issues;
    }
}
