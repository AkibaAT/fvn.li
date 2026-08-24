<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\HomePageCacheService;
use App\Services\MeilisearchService;
use App\Support\Seo\MetaTags;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private MeilisearchService $meilisearchService
    ) {}

    public function home(): Response
    {
        // Cache stats indefinitely - cleared by observers when data changes
        $stats = Cache::rememberForever('home.stats', function () {
            return [
                'totalGames' => Game::where('is_visible', true)->count(),
                'totalRatings' => DB::table('ratings')
                    ->join('games', 'ratings.game_id', '=', 'games.id')
                    ->where('games.is_visible', true)
                    ->where('ratings.is_visible', true)
                    ->count(),
                'totalUsers' => DB::table('users')->count(),
            ];
        });

        $ignoredGameIds = [];
        if (Auth::check()) {
            $ignoredGameIds = Auth::user()->ignoredGames()->pluck('games.id')->toArray();
        }

        $teaserVersion = HomePageCacheService::getTeaserVersion();
        $cacheKey = "home.teasers.v{$teaserVersion}." . md5(implode(',', $ignoredGameIds));

        $sharedTeasers = Cache::remember($cacheKey, now()->addDay(), function () use ($ignoredGameIds) {
            return [
                'recentlyAdded' => $this->getGameTeasers('first_visible_at', 'desc', 4, $ignoredGameIds),
                'recentlyUpdated' => $this->getGameTeasers('latest_version_published_at', 'desc', 4, $ignoredGameIds),
                'mostPopular' => $this->getGameTeasers('trending_score', 'desc', 4, $ignoredGameIds),
            ];
        });
        $teasers = $this->withCurrentUserTeaserData($sharedTeasers);

        $metaTags = new MetaTags(
            title: 'Furry Visual Novel Database',
            description: sprintf(
                'Discover and rate %d+ furry visual novels with %d+ ratings from our community. Find your next favorite VN with detailed reviews, ratings, and filters.',
                $stats['totalGames'],
                $stats['totalRatings']
            ),
            image: asset(config('social.images.home', config('social.images.default'))),
        );

        return Inertia::render('home', [
            'stats' => $stats,
            'teasers' => $teasers,
            'metaTags' => $metaTags->toArray(),
            'ignoredGameIds' => $ignoredGameIds,
        ]);
    }

    private function getGameTeasers(string $sortField, string $sortDirection = 'desc', int $limit = 4, array $ignoredGameIds = []): array
    {
        $paginator = $this->meilisearchService->searchGames(
            query: '',
            filters: [],
            perPage: $limit,
            page: 1,
            sortField: $sortField,
            sortDirection: $sortDirection,
            ignoredGameIds: $ignoredGameIds
        );

        $games = $paginator->items();

        if ($paginator->count() > 0) {
            $paginator->getCollection()->load([
                'tags',
                'sourceLanguage',
                'latestVersion.supportedLanguages.language',
                'latestVersion.languageStats',
            ]);

            // Enhance models with data from loaded relationships only (no additional queries)
            foreach ($games as $game) {
                if ($game->latestVersion) {
                    $game->is_windows = $game->latestVersion->is_windows ?? false;
                    $game->is_linux = $game->latestVersion->is_linux ?? false;
                    $game->is_mac = $game->latestVersion->is_mac ?? false;
                    $game->is_android = $game->latestVersion->is_android ?? false;
                    $game->is_web = $game->latestVersion->is_web ?? false;
                    $game->latest_version_id = $game->latestVersion->id;
                    $game->latest_version_published_at = $game->latestVersion->published_at;
                } else {
                    $game->is_windows = false;
                    $game->is_linux = false;
                    $game->is_mac = false;
                    $game->is_android = false;
                    $game->is_web = false;
                    $game->latest_version_id = null;
                    $game->latest_version_published_at = null;
                }

                if ($game->latestVersion && $game->latestVersion->supportedLanguages) {
                    $game->supported_languages = $game->latestVersion->supportedLanguages
                        ->where('is_available', true)
                        ->map(function ($supportedLanguage) {
                            return [
                                'iso_code' => $supportedLanguage->iso_code,
                                'is_available' => $supportedLanguage->is_available,
                                'ref_name' => $supportedLanguage->language?->ref_name,
                                'flag_code' => $supportedLanguage->language?->flag_code,
                            ];
                        })
                        ->values();
                } else {
                    $game->supported_languages = collect();
                }

                if ($game->latestVersion) {
                    $englishStats = $game->latestVersion->languageStats
                        ->where('iso_code', 'eng')
                        ->first();
                    $game->english_word_count = $englishStats?->words;

                    $sourceLanguageId = $game->source_language_id ?? 'eng';
                    if ($sourceLanguageId !== 'eng') {
                        $primaryStats = $game->latestVersion->languageStats
                            ->where('iso_code', $sourceLanguageId)
                            ->first();
                        $game->primary_word_count = $primaryStats?->words;
                    } else {
                        $game->primary_word_count = $game->english_word_count;
                    }
                    $game->primary_language_label = $game->getPrimaryLanguageLabel();
                } else {
                    $game->english_word_count = null;
                    $game->primary_word_count = null;
                    $game->primary_language_label = 'EN';
                }
            }
        }

        return $games;
    }

    private function withCurrentUserTeaserData(array $teasers): array
    {
        $gameIds = collect($teasers)
            ->flatten(1)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userProgress = collect();
        $userListMemberships = collect();

        if (Auth::check() && ! empty($gameIds)) {
            $userProgress = DB::table('user_game_progress')
                ->where('user_id', Auth::id())
                ->whereIn('game_id', $gameIds)
                ->select('game_id', 'receive_updates')
                ->get()
                ->keyBy('game_id');

            $userListMemberships = DB::table('vn_list_entries')
                ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
                ->where('vn_lists.user_id', Auth::id())
                ->whereIn('vn_list_entries.game_id', $gameIds)
                ->select('vn_list_entries.game_id', 'vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default')
                ->get()
                ->groupBy('game_id');
        }

        foreach ($teasers as $section => $games) {
            $teasers[$section] = collect($games)
                ->map(function ($game) use ($userProgress, $userListMemberships) {
                    $game = clone $game;
                    $progress = $userProgress->get($game->id);
                    $game->user_progress = $progress ? [$progress] : [];
                    $game->user_list_memberships = $userListMemberships->get($game->id, collect())->toArray();

                    return $game;
                })
                ->all();
        }

        return $teasers;
    }
}
