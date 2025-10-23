<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\MeilisearchService;
use Illuminate\Support\Facades\Auth;
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
        $stats = [
            'totalGames' => Game::where('is_visible', true)->count(),
            'totalRatings' => DB::table('ratings')
                ->join('games', 'ratings.game_id', '=', 'games.id')
                ->where('games.is_visible', true)
                ->where('ratings.is_visible', true)
                ->count(),
            'totalUsers' => DB::table('users')->count(),
        ];

        // Get ignored game IDs for authenticated users
        $ignoredGameIds = [];
        if (Auth::check()) {
            $ignoredGameIds = Auth::user()->ignoredGames()->pluck('games.id')->toArray();
        }

        $teasers = [
            'recentlyAdded' => $this->getGameTeasers('first_visible_at', 'desc', 4, $ignoredGameIds),
            'recentlyUpdated' => $this->getGameTeasers('latest_version_published_at', 'desc', 4, $ignoredGameIds),
            'mostPopular' => $this->getGameTeasers('trending_score', 'desc', 4, $ignoredGameIds),
        ];

        $metaTags = [
            'title' => 'Furry Visual Novel Database',
            'description' => sprintf(
                'Discover and rate %d+ furry visual novels with %d+ ratings from our community. Find your next favorite VN with detailed reviews, ratings, and filters.',
                $stats['totalGames'],
                $stats['totalRatings']
            ),
            'image' => asset(config('social.images.home', config('social.images.default'))),
        ];

        return Inertia::render('home', [
            'stats' => $stats,
            'teasers' => $teasers,
            'metaTags' => $metaTags,
            'ignoredGameIds' => $ignoredGameIds,
        ]);
    }

    private function getGameTeasers(string $sortField, string $sortDirection = 'desc', int $limit = 4, array $ignoredGameIds = []): array
    {
        // Use Meilisearch - same as games index page
        $paginator = $this->meilisearchService->searchGames(
            query: '',
            filters: [],
            perPage: $limit,
            page: 1,
            sortField: $sortField,
            sortDirection: $sortDirection,
            ignoredGameIds: $ignoredGameIds
        );

        // Return items as array - Scout already loads the models
        // Load user-specific data if authenticated
        if (Auth::check() && $games->count() > 0) {
            $gameIds = $games->pluck('id')->toArray();

            if (! empty($gameIds)) {
                // Load user progress
                $userProgress = DB::table('user_game_progress')
                    ->where('user_id', Auth::id())
                    ->whereIn('game_id', $gameIds)
                    ->select('game_id', 'receive_updates')
                    ->get()
                    ->keyBy('game_id');

                // Load list memberships
                $userListMemberships = DB::table('vn_list_entries')
                    ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
                    ->where('vn_lists.user_id', Auth::id())
                    ->whereIn('vn_list_entries.game_id', $gameIds)
                    ->select('vn_list_entries.game_id', 'vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default')
                    ->get()
                    ->groupBy('game_id');

                // Attach user data to each game object
                foreach ($games as $game) {
                    // Wrap user_progress in array to match Eloquent relationship format
                    $progress = $userProgress->get($game->id);
                    $game->user_progress = $progress ? [$progress] : [];
                    $game->user_list_memberships = $userListMemberships->get($game->id, collect())->toArray();
                }
            }
        }

        return $paginator->items();
    }
}
