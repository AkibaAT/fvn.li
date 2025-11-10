<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class MyGamesController extends Controller
{
    public function myGamesIndex(Request $request): Response
    {
        $authId = Auth::id();
        if (! $authId) {
            return Inertia::render('auth/login', [
                'metaTags' => [
                    'title' => 'Log in',
                    'description' => 'Log in to your FVN.li account to track your visual novel progress, create reading lists, and connect with the community.',
                    'structuredData' => [
                        '@type' => 'WebPage',
                        'name' => 'Log in',
                        'description' => 'Log in to your FVN.li account to track your visual novel progress',
                        'url' => route('login'),
                    ],
                ],
            ]);
        }
        $user = User::findOrFail($authId);

        $itchioAccount = $user->socialAccounts()->where('provider_name', 'itchio')->first();
        $itchioUsername = $itchioAccount?->provider_data['username'] ?? (method_exists($user,
            'getItchioUsername') ? $user->getItchioUsername() : null);

        $games = collect();
        $clickStats = [];
        if ($itchioUsername && method_exists($user, 'getOwnedGames')) {
            $games = $user->getOwnedGames()->map(function ($g) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'slug' => $g->slug,
                    'thumb_url' => method_exists($g, 'getThumbnailUrl') ? $g->getThumbnailUrl() : $g->thumb_url,
                    'has_additional_links' => method_exists($g,
                        'hasAdditionalLinks') ? $g->hasAdditionalLinks() : ! empty($g->additional_links),
                ];
            })->values();

            if (class_exists(ClickStat::class) && $games->isNotEmpty()) {
                $gameIds = $games->pluck('id')->toArray();
                try {
                    $since = now()->subDays(30);
                    $clickStats = ClickStat::getMultipleGameStats($gameIds, $since);
                } catch (Throwable $e) {
                    $clickStats = [];
                }
            }
        }

        return Inertia::render('my-games/index', [
            'itchio' => [
                'username' => $itchioUsername,
            ],
            'games' => $games,
            'clickStats' => $clickStats,
            'metaTags' => [
                'title' => 'Manage My Games',
                'description' => $itchioUsername
                    ? "Manage your itch.io games linked to FVN.li. Currently tracking {$games->count()} games from your itch.io account."
                    : 'Connect your itch.io account to manage and track your visual novel games on FVN.li.',
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => 'Manage My Games',
                    'description' => $itchioUsername
                        ? 'Manage your itch.io games linked to FVN.li'
                        : 'Connect your itch.io account to manage your visual novel games',
                    'url' => route('my-games.index'),
                ],
            ],
        ]);
    }

    public function myGamesEdit(Game $game): Response
    {
        $authId = Auth::id();
        if (! $authId) {
            return Inertia::render('auth/login', [
                'metaTags' => [
                    'title' => 'Log in',
                    'description' => 'Log in to your FVN.li account to track your visual novel progress, create reading lists, and connect with the community.',
                    'structuredData' => [
                        '@type' => 'WebPage',
                        'name' => 'Log in',
                        'description' => 'Log in to your FVN.li account to track your visual novel progress',
                        'url' => route('login'),
                    ],
                ],
            ]);
        }
        $user = User::findOrFail($authId);

        if (! method_exists($user, 'ownsGame') || ! $user->ownsGame($game)) {
            abort(403, 'You do not have permission to edit this game.');
        }

        $clickStats = [];
        $dailyStats = [];
        $linkStats = [];
        if (class_exists(ClickStat::class)) {
            try {
                $since = now()->subDays(30);
                $clickStats = ClickStat::getGameStats($game->id, $since);
                $dailyStats = ClickStat::getDailyStats($game->id, 30);
                $linkStats = ClickStat::getLinkStats($game->id, 30);
            } catch (Throwable $e) {
                $clickStats = [];
                $dailyStats = [];
                $linkStats = [];
            }
        }

        $platforms = method_exists($game, 'getAvailablePlatforms')
            ? array_keys(Game::getAvailablePlatforms())
            : ['windows', 'linux', 'mac', 'android', 'web'];

        $editableLinks = method_exists($game, 'getAllAdditionalLinks')
            ? $game->getAllAdditionalLinks()
            : ($game->additional_links ?? []);

        return Inertia::render('my-games/edit', [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'additional_links' => $editableLinks,
            ],
            'platforms' => $platforms,
            'clickStats' => $clickStats,
            'dailyStats' => $dailyStats,
            'linkStats' => $linkStats,
            'metaTags' => [
                'title' => "Edit {$game->name}",
                'description' => "Edit download links and platforms for {$game->name}. Manage additional download links for different platforms to help players find the right version.",
                'image' => method_exists($game, 'getThumbnailUrl') ? $game->getThumbnailUrl('default') : $game->thumb_url,
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => "Edit {$game->name}",
                    'description' => "Edit download links and platforms for {$game->name}",
                    'url' => route('my-games.edit', $game),
                    'mainEntity' => [
                        '@type' => 'SoftwareApplication',
                        'name' => $game->name,
                        'url' => route('games.show', $game->slug),
                        'image' => method_exists($game, 'getThumbnailUrl') ? $game->getThumbnailUrl('default') : $game->thumb_url,
                    ],
                ],
            ],
        ]);
    }

    public function myGamesUpdate(Request $request, Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        if (! $this->canEditGameMedia($user, $game)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'links' => 'nullable|array|max:15',
            'links.*.name' => 'required|string|max:100',
            'links.*.url' => [
                'required',
                'url',
                'max:255',
                function (string $attribute, $value, $fail) {
                    if ($value) {
                        $parsedUrl = parse_url($value);

                        if (isset($parsedUrl['host'])) {
                            $host = $parsedUrl['host'];

                            if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'])) {
                                $fail('The URL cannot point to localhost.');

                                return;
                            }

                            if (filter_var($host, FILTER_VALIDATE_IP)) {
                                if (! filter_var($host, FILTER_VALIDATE_IP,
                                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                                    $fail('The URL cannot point to private or reserved IP addresses.');

                                    return;
                                }
                            }
                        }
                    }
                },
            ],
            'links.*.platform' => 'nullable|string|in:' . implode(',', array_keys(Game::getAvailablePlatforms())),
            'links.*.release_at' => [
                'nullable',
                'date',
                function (string $attribute, $value, $fail) {
                    if ($value) {
                        try {
                            $releaseDate = Carbon::parse($value);
                            $now = Carbon::now();

                            // Allow past dates (for retroactive releases) but warn about very old dates
                            if ($releaseDate->lt($now->copy()->subYears(10))) {
                                $fail('The release date cannot be more than 10 years in the past.');

                                return;
                            }

                            // Allow future dates up to 10 years
                            if ($releaseDate->gt($now->copy()->addYears(10))) {
                                $fail('The release date cannot be more than 10 years in the future.');

                                return;
                            }
                        } catch (Exception $e) {
                            $fail('The release date must be a valid date and time.');
                        }
                    }
                },
            ],
        ]);

        $links = $request->input('links', []);

        // Debug: Log the incoming links data

        $processedLinks = [];
        $existingLinks = method_exists($game, 'getAllAdditionalLinks')
            ? $game->getAllAdditionalLinks()
            : ($game->additional_links ?? []);
        $existingLinksById = collect($existingLinks)->keyBy('id');

        foreach ($links as $index => $link) {
            if (empty($link['name']) || empty($link['url'])) {
                continue;
            }

            $linkId = $link['id'] ?? uniqid();
            $existingLink = $existingLinksById->get($linkId);

            // Handle release_at datetime - convert from user's local time to UTC
            $releaseAt = null;
            if (! empty($link['release_at'])) {
                try {
                    // The user submits their local time (e.g., "2025-10-10T12:54")
                    // We need to convert this to UTC for storage

                    // Parse the input as if it's in UTC first
                    $inputTime = Carbon::parse($link['release_at']);

                    // Since the user meant this as their local time, we need to subtract their timezone offset to get UTC
                    // Get user timezone offset from the form
                    $timezoneOffset = (int) ($request->input('timezone_offset', 0));

                    // Subtract the offset to convert local time to UTC
                    $utcTime = $inputTime->copy()->subHours($timezoneOffset);

                    $releaseAt = $utcTime->toISOString();

                } catch (Exception $e) {
                    // If parsing fails during processing (after validation),
                    // this shouldn't happen but we'll handle it gracefully
                    $releaseAt = null;
                    Log::error('Failed to parse release_at', [
                        'input' => $link['release_at'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Check if the link has been modified
            $hasChanged = ! $existingLink ||
                $existingLink['name'] !== trim($link['name']) ||
                $existingLink['url'] !== filter_var(trim($link['url']), FILTER_SANITIZE_URL) ||
                ($existingLink['platform'] ?? null) !== ($link['platform'] ?? null) ||
                ($existingLink['release_at'] ?? null) !== $releaseAt;

            $processedLinks[] = [
                'id' => $linkId,
                'name' => trim($link['name']),
                'url' => filter_var(trim($link['url']), FILTER_SANITIZE_URL),
                'platform' => $link['platform'] ?? null,
                'sort_order' => $index,
                'release_at' => $releaseAt,
                'last_edited_at' => $hasChanged ? now()->toISOString() : ($existingLink['last_edited_at'] ?? now()->toISOString()),
            ];
        }

        $game->update([
            'additional_links' => $processedLinks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Links updated successfully.',
            'links' => $processedLinks,
        ]);
    }

    /**
     * Upload or update game thumbnail
     */
    public function updateThumbnail(Request $request, Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        if (! $this->canEditGameMedia($user, $game)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $request->validate([
                'thumbnail' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB max
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $file = $request->file('thumbnail');

            // Log file info for debugging
            Log::info('Thumbnail upload attempt', [
                'game_id' => $game->id,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
            ]);

            $path = $file->store("games/{$game->id}/thumbnails", 'public');

            // Generate optimized thumbnails
            $optimizedThumbnails = $this->generateOptimizedThumbnails($file, $path, $game->id);

            // Clean up old optimized thumbnails if they exist
            if ($game->optimized_thumbnails) {
                foreach ($game->optimized_thumbnails as $variant) {
                    if (isset($variant['path'])) {
                        Storage::disk('public')->delete($variant['path']);
                    }
                }
            }

            $game->update([
                'thumb_url' => asset('storage/' . $path),
                'optimized_thumbnails' => $optimizedThumbnails,
                'custom_page_updated_at' => now(),
                'custom_page_updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thumbnail updated successfully.',
                'thumbnail_url' => $game->thumb_url,
                'optimized_thumbnails' => $optimizedThumbnails,
            ]);
        } catch (Exception $e) {
            Log::error('Thumbnail upload failed', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload thumbnail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete game thumbnail
     */
    public function deleteThumbnail(Request $request, Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        if (! $this->canEditGameMedia($user, $game)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            // Clean up optimized thumbnails if they exist
            if ($game->optimized_thumbnails) {
                foreach ($game->optimized_thumbnails as $variant) {
                    if (isset($variant['path'])) {
                        Storage::disk('public')->delete($variant['path']);
                    }
                }
            }

            // Reset thumbnail to original
            $game->update([
                'thumb_url' => null,
                'optimized_thumbnails' => null,
                'custom_page_updated_at' => now(),
                'custom_page_updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thumbnail deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete thumbnail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload new screenshots
     */
    public function uploadScreenshots(Request $request, Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        if (! $this->canEditGameMedia($user, $game)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'screenshots' => 'required|array|min:1|max:20',
            'screenshots.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB max per image
        ]);

        try {
            $screenshots = $game->custom_screenshots ?? $game->screenshots ?? [];
            $newScreenshots = [];

            foreach ($request->file('screenshots') as $index => $file) {
                $path = $file->store("games/{$game->id}/screenshots", 'public');

                // Generate optimized screenshots
                $optimized = $this->generateOptimizedScreenshots($file, $path, $game->id, count($screenshots) + $index);

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

            return response()->json([
                'success' => true,
                'message' => 'Screenshots uploaded successfully.',
                'screenshots' => $allScreenshots,
                'new_screenshots' => $newScreenshots,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload screenshots: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a screenshot
     */
    public function deleteScreenshot(Request $request, Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        if (! $this->canEditGameMedia($user, $game)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'index' => 'required|integer|min:0',
        ]);

        $index = $request->input('index');
        $screenshots = $game->custom_screenshots ?? $game->screenshots ?? [];

        if (! isset($screenshots[$index])) {
            return response()->json([
                'success' => false,
                'message' => 'Screenshot not found.',
            ], 404);
        }

        try {
            $deletedScreenshot = $screenshots[$index];

            // Only delete files if this is a custom screenshot (has optimized field or uploaded_at)
            $isCustomScreenshot = isset($deletedScreenshot['optimized']) || isset($deletedScreenshot['uploaded_at']);

            if ($isCustomScreenshot) {
                // Clean up optimized images
                if (isset($deletedScreenshot['optimized'])) {
                    foreach ($deletedScreenshot['optimized'] as $variant) {
                        if (isset($variant['path'])) {
                            Storage::disk('public')->delete($variant['path']);
                        }
                    }
                }

                // Delete the original file
                if (isset($deletedScreenshot['url'])) {
                    // Extract storage path from asset URL
                    // URL format: http://domain.com/storage/games/{id}/screenshots/filename.jpg
                    // We need: games/{id}/screenshots/filename.jpg
                    $url = $deletedScreenshot['url'];
                    if (str_contains($url, '/storage/')) {
                        $path = substr($url, strpos($url, '/storage/') + strlen('/storage/'));
                        Storage::disk('public')->delete($path);
                    }
                }

                // Delete the thumbnail file if it's different from the original
                if (isset($deletedScreenshot['thumbnail_url']) &&
                    $deletedScreenshot['thumbnail_url'] !== $deletedScreenshot['url']) {
                    $url = $deletedScreenshot['thumbnail_url'];
                    if (str_contains($url, '/storage/')) {
                        $path = substr($url, strpos($url, '/storage/') + strlen('/storage/'));
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            // Remove the screenshot from the array
            array_splice($screenshots, $index, 1);

            $game->update([
                'custom_screenshots' => $screenshots,
                'custom_page_updated_at' => now(),
                'custom_page_updated_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Screenshot deleted successfully.',
                'screenshots' => $screenshots,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete screenshot: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder screenshots
     */
    public function reorderScreenshots(Request $request, Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        if (! $this->canEditGameMedia($user, $game)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'ordered_indices' => 'required|array|min:0',
            'ordered_indices.*' => 'integer|min:0',
        ]);

        try {
            $screenshots = $game->custom_screenshots ?? $game->screenshots ?? [];
            $orderedIndices = $request->input('ordered_indices');

            // Reorder screenshots based on the provided indices
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

            return response()->json([
                'success' => true,
                'message' => 'Screenshots reordered successfully.',
                'screenshots' => $reorderedScreenshots,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder screenshots: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if user can edit game media (owner or admin)
     */
    private function canEditGameMedia(User $user, Game $game): bool
    {
        return $user->is_admin || (method_exists($user, 'ownsGame') && $user->ownsGame($game));
    }

    /**
     * Generate optimized thumbnails for different sizes
     */
    private function generateOptimizedThumbnails($file, string $path, int $gameId): array
    {
        $optimized = [];
        $sizes = [
            'small' => [189, 150],   // 315:250 aspect ratio
            'default' => [315, 250], // 315:250 aspect ratio
            'large' => [630, 500],   // 315:250 aspect ratio
        ];

        $manager = new ImageManager(new Driver);

        foreach ($sizes as $variant => [$width, $height]) {
            try {
                $image = $manager->read($file);
                $image->cover($width, $height);
                $encoded = $image->toWebp(80);

                $optimizedPath = "games/{$gameId}/thumbnails/{$variant}_" . time() . '.webp';
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

    /**
     * Generate optimized screenshots for different sizes
     */
    private function generateOptimizedScreenshots($file, string $path, int $gameId, int $screenshotIndex): array
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
                $image = $manager->read($file);
                $image->scale($width, $height);
                $encoded = $image->toWebp(80);

                $optimizedPath = "games/{$gameId}/screenshots/{$screenshotIndex}_{$variant}_" . time() . '.webp';
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
