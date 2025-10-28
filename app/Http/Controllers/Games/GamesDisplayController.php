<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use App\Traits\HasSocialMetaTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GamesDisplayController extends Controller
{
    use HasSocialMetaTags;

    /**
     * Display a single game page
     */
    public function show(Game $game): Response
    {
        $game->load([
            'latestVersion.supportedLanguages.language',
            'latestVersion.languageStats.language',
            'tags',
            'gameJams',
        ]);

        // Add tags_list attribute for frontend display
        $game->append('tags_list');

        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->with('rater')
            ->orderByDesc('published_at')
            ->paginate(5);

        $availableRatings = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->distinct()
            ->pluck('rating')
            ->sort()
            ->values()
            ->toArray();

        $gameVersions = $game->gameVersions()
            ->with([
                'supportedLanguages.language',
                'languageStats.language',
            ])
            ->orderBy('published_at', 'desc')
            ->paginate(5, ['*'], 'versionsPage');

        $gameVersions->getCollection()->transform(function ($version) {
            $version->supportedLanguages = $version->supportedLanguages
                ->filter(fn ($sl) => $sl->is_available
                    && $sl->language !== null
                    && !str_starts_with($sl->iso_code, 'q'))
                ->map(fn ($sl) => [
                    'iso_code' => $sl->iso_code,
                    'language' => [
                        'id' => $sl->language->id,
                        'iso_code' => $sl->language->id,
                        'ref_name' => $sl->language->ref_name,
                        'flag_code' => $sl->language->flag_code,
                    ],
                    'is_available' => $sl->is_available,
                ])
                ->values();

            // Transform languageStats to include language data
            // Filter out placeholder 'q' codes and null language relationships
            $version->languageStats = $version->languageStats
                ->filter(fn ($ls) => $ls->language !== null
                    && !str_starts_with($ls->iso_code, 'q'))
                ->map(fn ($ls) => [
                    'words' => $ls->words,
                    'language' => [
                        'id' => $ls->language->id,
                        'iso_code' => $ls->language->id,
                        'ref_name' => $ls->language->ref_name,
                        'flag_code' => $ls->language->flag_code,
                    ],
                ])
                ->values();

            return $version;
        });

        // Get English word count from latest version for game detail section
        $englishStats = null;
        if ($game->latestVersion) {
            $englishLanguageStats = $game->latestVersion->languageStats
                ->where('iso_code', 'eng')
                ->first();

            if ($englishLanguageStats && $englishLanguageStats->language !== null) {
                $englishStats = [
                    'words' => $englishLanguageStats->words,
                    'language' => [
                        'id' => $englishLanguageStats->language->id,
                        'iso_code' => $englishLanguageStats->language->id,
                        'ref_name' => $englishLanguageStats->language->ref_name,
                        'flag_code' => $englishLanguageStats->language->flag_code,
                    ],
                ];
            }
        }

        // Get user's current VN lists to show list membership status
        $userVnLists = [];
        $gameListMembership = [];
        $userProgress = null;
        if (Auth::check()) {
            $userVnLists = VnList::where('user_id', Auth::id())
                ->where('type', 'custom')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'is_public']);

            // Check which lists this game is already in
            $gameListMembership = DB::table('vn_list_entries')
                ->where('game_id', $game->id)
                ->whereIn('vn_list_id', $userVnLists->pluck('id'))
                ->pluck('vn_list_id')
                ->toArray();

            // Load user progress for this game
            $userProgress = DB::table('user_game_progress')
                ->where('user_id', Auth::id())
                ->where('game_id', $game->id)
                ->select('game_id', 'receive_updates')
                ->first();

            // Attach user data to game object (wrap in array to match Eloquent relationship format)
            $game->user_progress = $userProgress ? [$userProgress] : [];

            // Also load list memberships in the format expected by the frontend
            $userListMemberships = DB::table('vn_list_entries')
                ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
                ->where('vn_lists.user_id', Auth::id())
                ->where('vn_list_entries.game_id', $game->id)
                ->select('vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default')
                ->get()
                ->toArray();

            $game->user_list_memberships = $userListMemberships;
        }

        // Prepare social meta tags
        $this->prepareSocialMetaTags($game, $reviews, $englishStats);

        // Track page view
        ClickStat::recordClick(
            gameId: $game->id,
            type: ClickStat::TYPE_PAGE_VIEW,
            sessionId: session()->getId(),
            userId: Auth::id(),
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            referrer: request()->header('referer')
        );

        // Calculate character counts for each version (for dialogue browser links)
        $latestVersion = $game->latestVersion;
        $versionCharacterCounts = [];
        if ($latestVersion) {
            $versionCharacterCounts[$latestVersion->id] = Character::countUniqueCharactersInLanguage(
                $game->id,
                $game->source_language_id,
                $latestVersion->id
            );
        }
        foreach ($gameVersions as $version) {
            if ($latestVersion && $version->id === $latestVersion->id) {
                continue;
            }
            $versionCharacterCounts[$version->id] = Character::countUniqueCharactersInLanguage(
                $game->id,
                $game->source_language_id,
                $version->id
            );
        }

        // Check if file stats exist for each version (to show/hide file stats button)
        $versionHasFileStats = [];
        if ($latestVersion) {
            $versionHasFileStats[$latestVersion->id] = $latestVersion->fileCategories()->exists();
        }
        foreach ($gameVersions as $version) {
            if ($latestVersion && $version->id === $latestVersion->id) {
                continue;
            }
            $versionHasFileStats[$version->id] = $version->fileCategories()->exists();
        }

        // Determine edit permissions
        $user = Auth::user();
        $isOwner = $user && $user->ownsGame($game);
        $isAdmin = $user && $user->is_admin;
        $canEdit = $isOwner || $isAdmin;

        // Get analytics data if user can see it
        $canSeeAnalytics = $isOwner || $isAdmin;
        $clickStats = null;
        $dailyStats = null;

        if ($canSeeAnalytics) {
            try {
                // Get click statistics for the last 30 days
                $since = now()->subDays(30);
                $rawClickStats = ClickStat::getGameStats($game->id, $since);
                $dailyStats = ClickStat::getDailyStats($game->id, 30);

                // Transform custom_links from object to array with link names
                $additionalLinks = $game->getAllAdditionalLinks();
                $customLinksArray = [];

                foreach ($rawClickStats['custom_links'] ?? [] as $linkId => $linkData) {
                    // Find the link name from the game's additional links
                    $linkName = 'Unknown Link';
                    foreach ($additionalLinks as $link) {
                        if (($link['id'] ?? null) === $linkId) {
                            $linkName = $link['name'] ?? 'Unknown Link';
                            break;
                        }
                    }

                    $customLinksArray[] = [
                        'link_id' => $linkId,
                        'link_name' => $linkName,
                        'total_clicks' => $linkData['total_clicks'] ?? 0,
                        'unique_clicks' => $linkData['unique_clicks'] ?? 0,
                        'last_click' => $linkData['last_click'] ?? null,
                    ];
                }

                $clickStats = [
                    'page_views_total' => $rawClickStats['page_views_total'] ?? 0,
                    'page_views_unique' => $rawClickStats['page_views_unique'] ?? 0,
                    'last_page_view' => $rawClickStats['last_page_view'] ?? null,
                    'external_project_total' => $rawClickStats['external_project_total'] ?? 0,
                    'external_project_unique' => $rawClickStats['external_project_unique'] ?? 0,
                    'last_external_project' => $rawClickStats['last_external_project'] ?? null,
                    'custom_links' => $customLinksArray,
                ];
            } catch (\Exception $e) {
                // If analytics fail, just don't show them
                $clickStats = null;
                $dailyStats = null;
            }
        }

        return Inertia::render('games/show', [
            'game' => $game,
            'reviews' => $reviews,
            'availableRatings' => $availableRatings,
            'gameVersions' => $gameVersions,
            'englishStats' => $englishStats,
            'versionCharacterCounts' => $versionCharacterCounts,
            'versionHasFileStats' => $versionHasFileStats,
            'userVnLists' => $userVnLists,
            'gameListMembership' => $gameListMembership,
            'editPermissions' => [
                'canEdit' => $canEdit,
                'hasCustomPage' => (bool) $game->has_custom_page,
                'isOwner' => $isOwner,
                'isAdmin' => $isAdmin,
            ],
            'canSeeAnalytics' => $canSeeAnalytics,
            'clickStats' => $clickStats,
            'dailyStats' => $dailyStats,
            'metaTags' => $this->getMetaTags(),
        ]);
    }

    /**
     * Get game details for API consumption
     */
    public function details(Game $game): JsonResponse
    {
        $game->load([
            'latestVersion.supportedLanguages.language',
            'tags',
            'gameJams',
        ]);

        return response()->json([
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'description' => $game->description,
            'full_description' => $game->full_description,
            'authors' => $game->authors,
            'status' => $game->status,
            'game_engine' => $game->game_engine,
            'is_nsfw' => $game->is_nsfw,
            'is_paid' => $game->is_paid,
            'has_demo' => $game->has_demo,
            'min_price' => $game->min_price,
            'current_price' => $game->current_price,
            'url' => $game->url,
            'thumb_url' => $game->thumb_url,
            'screenshots' => $game->screenshots,
            'additional_links' => $game->additional_links,
            'platforms' => $game->platforms,
            'supported_languages' => $game->getSupportedLanguages(),
            'tags' => $game->tags->pluck('name'),
            'game_jams' => $game->gameJams->map(fn ($jam) => [
                'id' => $jam->id,
                'name' => $jam->name,
                'slug' => $jam->slug,
            ]),
            'rating' => [
                'score' => $game->rating_score,
                'count' => $game->rating_count,
            ],
            'created_at' => $game->created_at,
            'initially_published_at' => $game->initially_published_at,
            'first_visible_at' => $game->first_visible_at,
        ]);
    }

    /**
     * Prepare social meta tags for game page
     */
    private function prepareSocialMetaTags(Game $game, $reviews, ?array $englishStats = null): void
    {
        $title = $game->name;
        $description = $game->description ?: "Discover {$game->name} on fvn.li - Visual Novel Database and Analytics";

        // Use game thumbnail or first screenshot as image
        $image = $game->thumb_url ?: ($game->screenshots[0]['url'] ?? null);

        // Add game-specific info to description
        $metaDescription = $description;
        if ($game->authors) {
            $authors = strip_tags($game->authors);
            $metaDescription .= " by {$authors}";
        }
        if ($game->status) {
            $metaDescription .= " ({$game->status})";
        }

        // Add word count information
        if ($englishStats && isset($englishStats['words']) && $englishStats['words']) {
            $wordCount = number_format($englishStats['words']);
            $metaDescription .= " - {$wordCount} words";
        }

        // Add platform information
        $platforms = [];
        if ($game->latestVersion) {
            if ($game->latestVersion->is_windows) $platforms[] = 'Windows';
            if ($game->latestVersion->is_mac) $platforms[] = 'macOS';
            if ($game->latestVersion->is_linux) $platforms[] = 'Linux';
            if ($game->latestVersion->is_android) $platforms[] = 'Android';
            if ($game->latestVersion->is_web) $platforms[] = 'Web';
        }
        if (!empty($platforms)) {
            $metaDescription .= " - Available on: " . implode(', ', $platforms);
        }

        if ($reviews->total() > 0) {
            $metaDescription .= " - {$reviews->total()} reviews";
        }

        $this->setMetaTags([
            'title' => $title,
            'description' => $metaDescription,
            'image' => $image,
            'url' => route('games.show', $game),
            'type' => 'article',
        ]);
    }
}
