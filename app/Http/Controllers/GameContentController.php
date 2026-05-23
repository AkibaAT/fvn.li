<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\GameDataSyncService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GameContentController extends Controller
{
    /**
     * Update game custom content
     */
    public function updateContent(Game $game, Request $request): JsonResponse
    {
        if (! $this->canEdit($game)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this game.',
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:50000',
        ]);

        $user = Auth::user();

        // Enable custom page if not already enabled
        if (! $game->has_custom_page) {
            $game->enableCustomPage($user);
        }

        // Clean up unused images before updating
        $this->cleanupUnusedImages($game, $validated['content']);

        // Update custom description
        $game->updateCustomPage([
            'description' => $validated['content'],
        ], $user);

        return response()->json([
            'success' => true,
            'message' => 'Content updated successfully.',
            'data' => [
                'content' => $validated['content'],
                'has_custom_page' => true,
            ],
        ]);
    }

    /**
     * Update game custom name
     */
    public function updateName(Game $game, Request $request): JsonResponse
    {
        if (! $this->canEdit($game)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this game.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        // Enable custom page if not already enabled
        if (! $game->has_custom_page) {
            $game->enableCustomPage($user);
        }

        // Update custom name
        $game->updateCustomPage([
            'name' => $validated['name'],
        ], $user);

        // Refresh the model to get the updated effective_name
        $game->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Name updated successfully.',
            'data' => [
                'name' => $validated['name'],
                'effective_name' => $game->effective_name,
                'has_custom_page' => true,
            ],
        ]);
    }

    /**
     * Get both custom and original content for view switching
     */
    public function getContentForView(Game $game): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'has_custom_page' => $game->has_custom_page,
                'current_view_mode' => $game->view_mode,
                'custom_content' => [
                    'name' => $game->custom_name,
                    'description' => $game->custom_description,
                    'screenshots' => $game->custom_screenshots ? $game->resolveScreenshots($game->custom_screenshots) : [],
                ],
                'original_content' => [
                    'name' => $game->name,
                    'description' => $game->full_description,
                    'screenshots' => $game->getScreenshots(),
                ],
                'effective_content' => [
                    'name' => $game->getEffectiveName(),
                    'description' => $game->getEffectiveDescription(),
                    'screenshots' => $game->getEffectiveScreenshots(),
                ],
            ],
        ]);
    }

    /**
     * Set the view mode for this game (what all visitors see)
     */
    public function setViewMode(Game $game, Request $request): JsonResponse
    {
        if (! $this->canEdit($game)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this game.',
            ], 403);
        }

        $validated = $request->validate([
            'view_mode' => 'required|in:custom,original',
        ]);

        // Only allow setting view mode if custom page is enabled
        if (! $game->has_custom_page) {
            return response()->json([
                'success' => false,
                'message' => 'Custom page must be enabled before changing view mode.',
            ], 400);
        }

        $game->update([
            'view_mode' => $validated['view_mode'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'View mode updated successfully.',
            'data' => [
                'view_mode' => $game->view_mode,
                'effective_name' => $game->getEffectiveName(),
                'effective_description' => $game->getEffectiveDescription(),
                'effective_screenshots' => $game->getEffectiveScreenshots(),
            ],
        ]);
    }

    /**
     * Revert game content to original itch.io synced version
     */
    public function revertContent(Game $game, Request $request): JsonResponse
    {
        if (! $this->canEdit($game)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this game.',
            ], 403);
        }

        $user = Auth::user();
        $revertName = $request->boolean('revert_name', false);
        $revertScreenshots = $request->boolean('revert_screenshots', false);
        $revertThumbnail = $request->boolean('revert_thumbnail', false);
        $revertAll = $revertName && $revertScreenshots && $revertThumbnail;

        // If reverting everything, fully disable custom page
        if ($revertAll) {
            // Clean up custom images and screenshots before disabling
            $this->cleanupUnusedImages($game, $game->full_description ?? '');
            if ($game->custom_screenshots) {
                $this->cleanupCustomScreenshots($game);
            }

            $thumbnailUrl = $this->revertThumbnail($game);

            $game->disableCustomPage();
            $game->refresh();

            return response()->json([
                'success' => true,
                'message' => 'All custom content has been removed. The game now shows original itch.io content.',
                'data' => [
                    'name' => $game->name,
                    'effective_name' => $game->effective_name,
                    'content' => $game->full_description,
                    'screenshots' => $game->getScreenshots(),
                    'thumbnail_url' => $thumbnailUrl,
                    'has_custom_page' => false,
                    'is_reverted' => true,
                ],
            ]);
        }

        // Partial revert: enable custom page if not already enabled
        if (! $game->has_custom_page) {
            $game->enableCustomPage($user);
        }

        $updateData = [
            'description' => $game->full_description,
        ];

        // Clean up unused images before reverting (since we're replacing content)
        $this->cleanupUnusedImages($game, $game->full_description ?? '');

        // Revert screenshots if requested
        if ($revertScreenshots && $game->screenshots) {
            $updateData['screenshots'] = $game->screenshots;

            // Clean up custom screenshot files
            $this->cleanupCustomScreenshots($game);
        }

        // Reset custom content to current itch.io synced content
        $game->updateCustomPage($updateData, $user);

        // Revert name if requested (must be done after updateCustomPage to clear custom_name)
        if ($revertName) {
            $game->update(['custom_name' => null]);
        }

        // Handle thumbnail revert if requested (special case since there's no custom thumbnail system)
        $thumbnailUrl = null;
        if ($revertThumbnail) {
            $thumbnailUrl = $this->revertThumbnail($game);
        }

        // Refresh the model to get updated effective values
        $game->refresh();

        return response()->json([
            'success' => true,
            'message' => $revertScreenshots
                ? 'Content and screenshots reverted to itch.io version successfully.'
                : 'Content reverted to itch.io version successfully.',
            'data' => [
                'name' => $revertName ? $game->name : null,
                'effective_name' => $revertName ? $game->effective_name : null,
                'content' => $game->full_description,
                'screenshots' => $revertScreenshots ? $game->getScreenshots() : null,
                'thumbnail_url' => $thumbnailUrl,
                'has_custom_page' => true,
                'is_reverted' => true,
            ],
        ]);
    }

    /**
     * Check if the current user can edit the given game
     */
    public function canEdit(Game $game): bool
    {
        $user = Auth::user();

        return $user && $game->canUserEdit($user);
    }

    /**
     * Clean up unused images for a game when content is updated
     */
    private function cleanupUnusedImages(Game $game, string $newContent): int
    {
        $deletedCount = 0;
        $gameId = $game->id;

        try {
            // Get all images in the game's editor directory
            $editorPath = "editor/{$gameId}";
            $storage = Storage::disk('public');

            if (! $storage->exists($editorPath)) {
                return 0; // No images to clean up
            }

            $allFiles = $storage->allFiles($editorPath);
            $imageFiles = array_filter($allFiles, function ($file) {
                return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            });

            // Extract all image URLs from the new content
            $usedUrls = $this->extractImageUrls($newContent);

            // Convert URLs to file paths
            $usedPaths = [];
            foreach ($usedUrls as $url) {
                // Convert /storage/editor/123/filename.jpg to editor/123/filename.jpg
                $path = str_replace('/storage/', '', $url);
                $usedPaths[] = $path;
            }

            // Delete unused files
            foreach ($imageFiles as $filePath) {
                if (! in_array($filePath, $usedPaths)) {
                    $storage->delete($filePath);
                    $deletedCount++;

                    Log::info('Deleted unused image', [
                        'game_id' => $gameId,
                        'file_path' => $filePath,
                        'reason' => 'not in content',
                    ]);
                }
            }

            // Clean up empty directories
            $this->cleanupEmptyDirectories($storage, $editorPath);

            if ($deletedCount > 0) {
                Log::info('Cleaned up unused images', [
                    'game_id' => $gameId,
                    'deleted_count' => $deletedCount,
                    'remaining_files' => count($usedPaths),
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to cleanup unused images', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $deletedCount;
    }

    /**
     * Extract all image URLs from HTML content
     */
    private function extractImageUrls(string $content): array
    {
        $urls = [];

        // Match img src attributes
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                // Only include URLs that point to our storage
                if (str_contains($url, '/storage/editor/')) {
                    $urls[] = $url;
                }
            }
        }

        // Match style background-image URLs
        if (preg_match_all('/background-image:\s*url\(["\']?([^"\')\s]+)["\']?\)/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (str_contains($url, '/storage/editor/')) {
                    $urls[] = $url;
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Recursively clean up empty directories
     */
    private function cleanupEmptyDirectories($storage, string $path): void
    {
        // Only clean subdirectories, not the main editor directory
        if ($path === 'editor') {
            return;
        }

        $files = $storage->allFiles($path);
        $directories = $storage->allDirectories($path);

        // Remove empty directories (check in reverse order)
        foreach (array_reverse($directories) as $directory) {
            if ($directory === 'editor') {
                continue; // Never remove the main editor directory
            }

            $filesInDir = $storage->files($directory);
            if (empty($filesInDir)) {
                $storage->deleteDirectory($directory);
            }
        }
    }

    /**
     * Clean up custom screenshot files when reverting
     */
    private function cleanupCustomScreenshots(Game $game): void
    {
        try {
            $storage = Storage::disk('public');

            // Get original screenshots to compare against
            $originalScreenshots = $game->screenshots ?: [];
            $originalUrls = array_map(fn ($s) => $s['url'], $originalScreenshots);

            // Get custom screenshots
            $customScreenshots = $game->custom_screenshots ?: [];
            $customUrls = array_map(fn ($s) => $s['url'], $customScreenshots);

            // Find and delete custom screenshot files that aren't in original
            foreach ($customUrls as $customUrl) {
                if (! in_array($customUrl, $originalUrls)) {
                    // Convert URL to storage path
                    $path = str_replace('/storage/', '', $customUrl);

                    if ($storage->exists($path)) {
                        $storage->delete($path);
                        Log::info('Deleted custom screenshot file', [
                            'game_id' => $game->id,
                            'file_path' => $path,
                            'reason' => 'revert to original',
                        ]);
                    }
                }
            }

            // Clean up optimized thumbnails for custom screenshots
            if ($game->custom_screenshots) {
                foreach ($game->custom_screenshots as $screenshot) {
                    if (isset($screenshot['optimized'])) {
                        foreach ($screenshot['optimized'] as $variant) {
                            if (isset($variant['path'])) {
                                $storage->delete($variant['path']);
                            }
                        }
                    }
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to cleanup custom screenshots', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Revert thumbnail to original itch.io version
     * Note: This is a special case since thumbnails don't have a custom system
     */
    private function revertThumbnail(Game $game): ?string
    {
        try {
            // Refresh base info from itch.io to get the original thumbnail
            $syncService = app(GameDataSyncService::class);
            $syncService->refreshBaseInfo($game);

            // Clear optimized thumbnails to force regeneration
            $game->clearOptimizedThumbnails();

            // Refresh the game model to get the updated thumb_url
            $game->refresh();

            Log::info('Reverted thumbnail to itch.io version', [
                'game_id' => $game->id,
                'new_thumb_url' => $game->getThumbnailUrl(),
            ]);

            return $game->getThumbnailUrl();

        } catch (Exception $e) {
            Log::error('Failed to revert thumbnail', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
