<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
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
            'title' => 'FVN.li - Furry Visual Novel Database',
            'description' => sprintf(
                'Discover and rate %d+ furry visual novels with %d+ ratings from our community. Find your next favorite VN with detailed reviews, ratings, and filters.',
                $stats['totalGames'],
                $stats['totalRatings']
            ),
            'image' => asset('images/social-fallback.jpg'),
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

        return $games->toArray();
    }
}
