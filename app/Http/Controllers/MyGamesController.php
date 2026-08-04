<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameMediaEditorService;
use App\Services\OwnedGameSummaryService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MyGamesController extends Controller
{
    public function myGamesIndex(Request $request): Response
    {
        $authId = Auth::id();
        if (! $authId) {
            return $this->loginResponse();
        }
        $user = User::findOrFail($authId);

        $ownedGames = app(OwnedGameSummaryService::class);
        $itchioUsername = $ownedGames->username($user);
        $games = $ownedGames->games($user);
        $clickStats = $ownedGames->clickStats($games);

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
            return $this->loginResponse();
        }
        $user = User::findOrFail($authId);

        if (! $game->canUserEdit($user)) {
            abort(403, 'You do not have permission to edit this game.');
        }

        $clickStats = [];
        $dailyStats = [];
        $linkStats = [];
        try {
            $since = now()->subDays(30);
            $clickStats = ClickStat::getGameStats($game->id, $since);
            $dailyStats = ClickStat::getDailyStats($game->id, 30);
            $linkStats = ClickStat::getLinkStats($game->id, 30);
        } catch (Throwable $exception) {
            report($exception);
        }

        $platforms = array_keys(Game::getAvailablePlatforms());
        $editableLinks = $game->getAllAdditionalLinks();

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
                'image' => $game->getThumbnailUrl('default'),
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => "Edit {$game->name}",
                    'description' => "Edit download links and platforms for {$game->name}",
                    'url' => route('my-games.edit', $game),
                    'mainEntity' => [
                        '@type' => 'SoftwareApplication',
                        'name' => $game->name,
                        'url' => route('games.show', $game->slug),
                        'image' => $game->getThumbnailUrl('default'),
                    ],
                ],
            ],
        ]);
    }

    public function myGamesUpdate(Request $request, Game $game): JsonResponse
    {
        $request->validate([
            'links' => 'nullable|array|max:15',
            'links.*.name' => 'required|string|max:100',
            'links.*.url' => [
                'required',
                'url:http,https',
                'max:255',
                function (string $attribute, $value, $fail) {
                    if ($value) {
                        $parsedUrl = parse_url($value);

                        if (! isset($parsedUrl['scheme']) || ! in_array(strtolower($parsedUrl['scheme']), ['http', 'https'], true)) {
                            $fail('The URL must use http or https.');

                            return;
                        }

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
        $existingLinks = $game->getAllAdditionalLinks();
        $existingLinksById = collect($existingLinks)->keyBy('id');

        foreach ($links as $index => $link) {
            if (empty($link['name']) || empty($link['url'])) {
                continue;
            }

            $linkId = $link['id'] ?? uniqid();
            $existingLink = $existingLinksById->get($linkId);
            $url = $this->sanitizeAdditionalLinkUrl($link['url']);

            $releaseAt = null;
            if (! empty($link['release_at'])) {
                try {
                    // The user submits their local time (e.g., "2025-10-10T12:54")
                    // We need to convert this to UTC for storage

                    $inputTime = Carbon::parse($link['release_at']);

                    // Since the user meant this as their local time, we need to subtract their timezone offset to get UTC
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

            $hasChanged = ! $existingLink ||
                $existingLink['name'] !== trim($link['name']) ||
                $existingLink['url'] !== $url ||
                ($existingLink['platform'] ?? null) !== ($link['platform'] ?? null) ||
                ($existingLink['release_at'] ?? null) !== $releaseAt;

            $processedLinks[] = [
                'id' => $linkId,
                'name' => trim($link['name']),
                'url' => $url,
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
        /** @var User $user */
        $user = $request->user();

        try {
            $request->validate([
                'thumbnail' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB max
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            return response()->json(app(GameMediaEditorService::class)->updateThumbnail(
                $game,
                $user,
                $request->file('thumbnail')
            ));
        } catch (Exception $e) {
            Log::error('Thumbnail upload failed', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->failureMessage('Failed to upload thumbnail.', $e),
            ], 500);
        }
    }

    public function deleteThumbnail(Request $request, Game $game): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            return response()->json(app(GameMediaEditorService::class)->deleteThumbnail($game, $user));
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $this->failureMessage('Failed to delete thumbnail.', $e),
            ], 500);
        }
    }

    /**
     * Upload new screenshots
     */
    public function uploadScreenshots(Request $request, Game $game): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'screenshots' => 'required|array|min:1|max:20',
            'screenshots.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB max per image
        ]);

        try {
            return response()->json(app(GameMediaEditorService::class)->uploadScreenshots(
                $game,
                $user,
                $request->file('screenshots')
            ));
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $this->failureMessage('Failed to upload screenshots.', $e),
            ], 500);
        }
    }

    public function deleteScreenshot(Request $request, Game $game): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'index' => 'required|integer|min:0',
        ]);

        $index = $request->input('index');

        try {
            $result = app(GameMediaEditorService::class)->deleteScreenshot($game, $user, (int) $index);

            return $result
                ? response()->json($result)
                : response()->json([
                    'success' => false,
                    'message' => 'Screenshot not found.',
                ], 404);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $this->failureMessage('Failed to delete screenshot.', $e),
            ], 500);
        }
    }

    /**
     * Reorder screenshots
     */
    public function reorderScreenshots(Request $request, Game $game): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'ordered_indices' => 'required|array|min:0',
            'ordered_indices.*' => 'integer|min:0',
        ]);

        try {
            return response()->json(app(GameMediaEditorService::class)->reorderScreenshots(
                $game,
                $user,
                $request->input('ordered_indices')
            ));
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $this->failureMessage('Failed to reorder screenshots.', $e),
            ], 500);
        }
    }

    /**
     * Redirect the developer (own games) or an admin (any game) to a
     * short-lived presigned URL for the version's optimized archive.
     */
    public function downloadOptimizedArchive(Request $request, Game $game, GameVersion $version): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        if ($version->game_id !== $game->id) {
            abort(404);
        }

        if (! $game->canUserEdit($user)) {
            abort(403, 'You are not allowed to download this game archive.');
        }

        $stash = app(DenKitStashPersistenceService::class);
        if (! $stash->isEnabled()) {
            abort(404, 'Archive storage is not configured.');
        }

        $download = $stash->archiveDownloadUrl($game, $version);
        if ($download === null) {
            abort(404, 'No optimized archive has been persisted for this game version.');
        }

        return redirect()->away($download['url']);
    }

    private function sanitizeAdditionalLinkUrl(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    private function failureMessage(string $message, Exception $exception): string
    {
        return config('app.debug') ? "{$message} {$exception->getMessage()}" : $message;
    }
}
