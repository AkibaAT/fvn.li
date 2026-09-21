<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Tag;
use App\Services\GamesSearchResultHydrator;
use App\Services\HomePageCacheService;
use App\Services\MeilisearchService;
use App\Support\Seo\MetaTags;
use App\Support\ViewPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const TEASER_LIMIT = 6;

    private const TEASER_SECTIONS = [
        'recentlyAdded' => 'first_visible_at',
        'recentlyUpdated' => 'latest_version_published_at',
        'mostPopular' => 'trending_score',
    ];

    public function __construct(
        private MeilisearchService $meilisearchService
    ) {}

    public function home(): Response
    {
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
        $cacheKey = "home.teasers.distinct.v{$teaserVersion}." . Tag::publicCacheVariant() . '.' . md5(implode(',', $ignoredGameIds));

        $view = ViewPreference::mode(ViewPreference::HOME_COOKIE);

        $sharedTeasers = Cache::remember($cacheKey, now()->addDay(), fn () => $this->getDistinctTeasers($ignoredGameIds));

        $teasers = $this->withCurrentUserTeaserData($sharedTeasers);

        $metaTags = new MetaTags(
            title: 'Furry Visual Novel Database',
            description: sprintf(
                'Discover and rate %d+ furry visual novels with %d+ ratings from our community. Find your next favorite VN with detailed reviews, ratings, and filters.',
                $stats['totalGames'],
                $stats['totalRatings']
            ),
            image: asset(config('social.images.home', config('social.images.default'))),
            url: route('home'),
            structuredData: [
                '@type' => 'WebSite',
                'name' => 'FVN.li',
                'alternateName' => 'Furry Visual Novel Database',
                'url' => url('/') . '/',
            ],
        );

        return Inertia::render('home', [
            'stats' => $stats,
            'teasers' => $teasers,
            'metaTags' => $metaTags->toArray(),
            'ignoredGameIds' => $ignoredGameIds,
            'homeView' => $view,
        ]);
    }

    /**
     * @param  array<int, int>  $ignoredGameIds
     * @return array<string, array<int, Game>>
     */
    private function getDistinctTeasers(array $ignoredGameIds): array
    {
        $teasers = [];
        $shownIds = [];
        $candidateLimit = self::TEASER_LIMIT;

        foreach (self::TEASER_SECTIONS as $section => $sortField) {
            $picked = collect($this->meilisearchService->searchGames(
                query: '',
                filters: [],
                perPage: $candidateLimit,
                page: 1,
                sortField: $sortField,
                sortDirection: 'desc',
                ignoredGameIds: $ignoredGameIds
            )->items())
                ->reject(fn (Game $game) => isset($shownIds[$game->id]))
                ->take(self::TEASER_LIMIT)
                ->values();

            foreach ($picked as $game) {
                $shownIds[$game->id] = true;
            }

            $teasers[$section] = $picked;
            $candidateLimit += self::TEASER_LIMIT;
        }

        $models = (new Game)->newCollection(collect($teasers)->flatten(1)->all());
        app(GamesSearchResultHydrator::class)->hydrateModels($models);

        return array_map(fn ($games) => $games->all(), $teasers);
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
