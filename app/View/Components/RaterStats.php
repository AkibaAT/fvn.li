<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;
use Illuminate\View\View;

class RaterStats extends Component
{
    public array $stats;

    public function __construct(public int $raterId)
    {
        $this->stats = $this->getRatingStats();
    }

    public function render(): View
    {
        return view('ratings.components.rater-stats');
    }

    protected function getRatingStats(): array
    {
        // Get stats for all games
        $allGamesStats = DB::table('ratings')
            ->where('rater_id', $this->raterId)
            ->where('is_visible', true)
            ->select([
                DB::raw('MIN(published_at) as first_rating'),
                DB::raw('MAX(published_at) as latest_rating'),
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN is_reviewed THEN 1 ELSE 0 END) as reviewed_count'),
                DB::raw('AVG(rating) as average_rating'),
                DB::raw('COUNT(DISTINCT game_id) as unique_games'),
            ])
            ->first();

        // Get rating distribution separately
        $allGamesDistribution = DB::table('ratings')
            ->where('rater_id', $this->raterId)
            ->where('is_visible', true)
            ->groupBy('rating')
            ->select(['rating', DB::raw('COUNT(*) as count')])
            ->pluck('count', 'rating')
            ->toArray();

        // Get stats for visible games only
        $visibleGamesStats = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $this->raterId)
            ->where('ratings.is_visible', true)
            ->where('games.is_visible', true)
            ->select([
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN ratings.is_reviewed THEN 1 ELSE 0 END) as reviewed_count'),
                DB::raw('AVG(ratings.rating) as average_rating'),
                DB::raw('COUNT(DISTINCT ratings.game_id) as unique_games'),
            ])
            ->first();

        // Get rating distribution for visible games separately
        $visibleGamesDistribution = DB::table('ratings')
            ->join('games', 'games.id', '=', 'ratings.game_id')
            ->where('ratings.rater_id', $this->raterId)
            ->where('ratings.is_visible', true)
            ->where('games.is_visible', true)
            ->groupBy('ratings.rating')
            ->select(['ratings.rating', DB::raw('COUNT(*) as count')])
            ->pluck('count', 'rating')
            ->toArray();

        // Ensure all ratings 1-5 are represented in distributions
        $allDistribution = $allGamesDistribution;
        $visibleDistribution = $visibleGamesDistribution;

        for ($i = 1; $i <= 5; $i++) {
            $allDistribution[$i] = $allDistribution[$i] ?? 0;
            $visibleDistribution[$i] = $visibleDistribution[$i] ?? 0;
        }
        ksort($allDistribution);
        ksort($visibleDistribution);

        return [
            'first_rating' => $allGamesStats->first_rating,
            'latest_rating' => $allGamesStats->latest_rating,
            'all_games' => [
                'total_ratings' => $allGamesStats->total_ratings,
                'reviewed_count' => $allGamesStats->reviewed_count,
                'review_percentage' => $allGamesStats->total_ratings > 0
                    ? ($allGamesStats->reviewed_count / $allGamesStats->total_ratings * 100)
                    : 0,
                'average_rating' => $allGamesStats->average_rating ?? 0,
                'unique_games' => $allGamesStats->unique_games,
                'rating_distribution' => $allDistribution,
            ],
            'visible_games' => [
                'total_ratings' => $visibleGamesStats->total_ratings,
                'reviewed_count' => $visibleGamesStats->reviewed_count,
                'review_percentage' => $visibleGamesStats->total_ratings > 0
                    ? ($visibleGamesStats->reviewed_count / $visibleGamesStats->total_ratings * 100)
                    : 0,
                'average_rating' => $visibleGamesStats->average_rating ?? 0,
                'unique_games' => $visibleGamesStats->unique_games,
                'rating_distribution' => $visibleDistribution,
            ],
        ];
    }
}
