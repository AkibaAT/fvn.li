<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GameMediaEditorService
{
    public function __construct(
        private readonly GameMediaOptimizationService $optimizationService,
    ) {}

    public function updateThumbnail(Game $game, User $user, UploadedFile $file): array
    {
        Log::info('Thumbnail upload attempt', [
            'game_id' => $game->id,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
        ]);

        $path = $file->store("games/{$game->id}/thumbnails", 'public');
        $optimizedThumbnails = $this->optimizationService->optimizedThumbnails($file, $game->id);

        $this->deleteOptimizedVariants($game->optimized_thumbnails ?? []);

        $game->update([
            'thumb_url' => asset('storage/' . $path),
            'optimized_thumbnails' => $optimizedThumbnails,
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);

        return [
            'success' => true,
            'message' => 'Thumbnail updated successfully.',
            'thumbnail_url' => $game->thumb_url,
            'optimized_thumbnails' => $optimizedThumbnails,
        ];
    }

    public function deleteThumbnail(Game $game, User $user): array
    {
        $this->deleteOptimizedVariants($game->optimized_thumbnails ?? []);

        $game->update([
            'thumb_url' => null,
            'optimized_thumbnails' => null,
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);

        return [
            'success' => true,
            'message' => 'Thumbnail deleted successfully.',
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function uploadScreenshots(Game $game, User $user, array $files): array
    {
        $screenshots = $game->custom_screenshots ?? $game->screenshots ?? [];
        $newScreenshots = [];

        foreach ($files as $index => $file) {
            $path = $file->store("games/{$game->id}/screenshots", 'public');
            $optimized = $this->optimizationService->optimizedScreenshots(
                $file,
                $game->id,
                count($screenshots) + $index
            );

            $newScreenshots[] = [
                'url' => asset('storage/' . $path),
                'thumbnail_url' => asset('storage/' . $path),
                'optimized' => $optimized,
                'uploaded_at' => now()->toISOString(),
            ];
        }

        $allScreenshots = array_merge($screenshots, $newScreenshots);

        $game->update([
            'custom_screenshots' => $allScreenshots,
            'has_custom_page' => true,
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);

        return [
            'success' => true,
            'message' => 'Screenshots uploaded successfully.',
            'screenshots' => $game->resolveScreenshots($allScreenshots),
            'new_screenshots' => $game->resolveScreenshots($newScreenshots),
        ];
    }

    public function deleteScreenshot(Game $game, User $user, int $index): ?array
    {
        $screenshots = $game->custom_screenshots ?? $game->screenshots ?? [];

        if (! isset($screenshots[$index])) {
            return null;
        }

        $this->deleteScreenshotFiles($screenshots[$index]);
        array_splice($screenshots, $index, 1);

        $game->update([
            'custom_screenshots' => $screenshots,
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);

        return [
            'success' => true,
            'message' => 'Screenshot deleted successfully.',
            'screenshots' => $game->resolveScreenshots($screenshots),
        ];
    }

    /**
     * @param  array<int, int>  $orderedIndices
     */
    public function reorderScreenshots(Game $game, User $user, array $orderedIndices): array
    {
        $screenshots = $game->custom_screenshots ?? $game->screenshots ?? [];
        $reorderedScreenshots = [];

        foreach ($orderedIndices as $index) {
            if (isset($screenshots[$index])) {
                $reorderedScreenshots[] = $screenshots[$index];
            }
        }

        $game->update([
            'custom_screenshots' => $reorderedScreenshots,
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);

        return [
            'success' => true,
            'message' => 'Screenshots reordered successfully.',
            'screenshots' => $game->resolveScreenshots($reorderedScreenshots),
        ];
    }

    private function deleteScreenshotFiles(array $screenshot): void
    {
        $isCustomScreenshot = isset($screenshot['optimized']) || isset($screenshot['uploaded_at']);

        if (! $isCustomScreenshot) {
            return;
        }

        $this->deleteOptimizedVariants($screenshot['optimized'] ?? []);
        $this->deleteStorageAsset($screenshot['url'] ?? null);

        if (($screenshot['thumbnail_url'] ?? null) !== ($screenshot['url'] ?? null)) {
            $this->deleteStorageAsset($screenshot['thumbnail_url'] ?? null);
        }
    }

    private function deleteOptimizedVariants(array $variants): void
    {
        foreach ($variants as $variant) {
            if (isset($variant['path'])) {
                Storage::disk('public')->delete($variant['path']);
            }
        }
    }

    private function deleteStorageAsset(?string $url): void
    {
        if (! $url || ! str_contains($url, '/storage/')) {
            return;
        }

        $path = substr($url, strpos($url, '/storage/') + strlen('/storage/'));
        Storage::disk('public')->delete($path);
    }
}
