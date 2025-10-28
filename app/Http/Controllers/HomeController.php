<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
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

        $teasers = [
            'recentlyAdded' => $this->getGameTeasers('first_visible_at', 'desc', 4),
            'recentlyUpdated' => $this->getGameTeasers('latest_version_published_at', 'desc', 4),
            'mostPopular' => $this->getGameTeasers('trending', 'desc', 4),
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
        ]);
    }

    private function getGameTeasers(string $sortField, string $sortDirection = 'desc', int $limit = 4): array
    {
        $query = Game::query()
            ->select([
                'games.*',
                'latest_versions.published_at as latest_version_published_at',
                'latest_versions.id as latest_version_id',
                'latest_versions.devlog as devlog',
                'latest_versions.is_windows as is_windows',
                'latest_versions.is_linux as is_linux',
                'latest_versions.is_mac as is_mac',
                'latest_versions.is_android as is_android',
                'latest_versions.is_web as is_web',
                'english_stats.words as english_word_count',
                DB::raw('(
                    SELECT JSON_AGG(
                        JSON_BUILD_OBJECT(
                            \'iso_code\', l.id,
                            \'ref_name\', l.ref_name,
                            \'flag_code\', l.flag_code
                        ) ORDER BY l.ref_name)
                        FROM version_supported_languages vsl
                        JOIN iso_639_3_languages l ON l.id = vsl.iso_code
                        WHERE vsl.game_version_id = latest_versions.id
                        AND vsl.is_available = true
                    ) as supported_languages'),
            ])
            ->leftJoin('game_versions as latest_versions', function ($join) {
                $join->on('games.id', '=', 'latest_versions.game_id')
                    ->where('latest_versions.is_latest', true);
            })
            ->leftJoin('version_language_stats as english_stats', function ($join) {
                $join->on('latest_versions.id', '=', 'english_stats.game_version_id')
                    ->where('english_stats.iso_code', '=', 'eng');
            })
            ->leftJoinSub(
                DB::table('click_stats')
                    ->selectRaw('COUNT(*) as trending_score, game_id')
                    ->where('type', 'page_view')
                    ->where('clicked_at', '>=', DB::raw("NOW() - INTERVAL '14 days'"))
                    ->groupBy('game_id'),
                'trending',
                function ($join) {
                    $join->on('games.id', '=', 'trending.game_id');
                }
            )
            ->addSelect(DB::raw('COALESCE(trending.trending_score, 0) as trending_score'))
            ->where('is_visible', true);

        $column = match ($sortField) {
            'latest_version_published_at' => 'latest_versions.published_at',
            'english_word_count' => 'english_stats.words',
            'trending' => 'trending_score',
            'rating_count' => 'games.rating_count',
            'rating' => 'games.rating_score',
            default => "games.{$sortField}"
        };

        $query->orderByRaw("{$column} {$sortDirection} NULLS LAST")
            ->orderBy('games.id', 'asc'); // Secondary sort for consistent ordering

        $games = $query->limit($limit)->get();

        $games->load(['gameJams', 'tags']);

        foreach ($games as $game) {
            $game->supported_languages = collect($game->supported_languages);
        }

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

        return $games->toArray();
    }
}
