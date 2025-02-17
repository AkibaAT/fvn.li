<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculateGameScores extends Command
{
    protected $signature = 'games:calculate-scores {--force}';
    protected $description = 'Calculate scores for all games';

    public function handle(): void
    {
        // First get global statistics
        $globals = DB::selectOne('
            WITH weighted_stats AS (
                SELECT
                    r.rating,
                    COALESCE(rt.weight, 0) as rater_weight
                FROM ratings r
                LEFT JOIN raters rt ON rt.id = r.rater_id
                WHERE r.is_visible = true
            ),
            game_counts AS (
                SELECT COUNT(*) as rating_count
                FROM ratings
                WHERE is_visible = true
                GROUP BY game_id
            ),
            total_weight AS (
                SELECT SUM(rater_weight) as total
                FROM weighted_stats
            )
            SELECT
                (SELECT
                    AVG(CASE
                        WHEN ws.rater_weight > 0 THEN ws.rating * ws.rater_weight / NULLIF(tw.total, 0)
                        ELSE ws.rating
                    END)
                FROM weighted_stats ws, total_weight tw) as global_weighted_mean,
                (SELECT AVG(rating_count) FROM game_counts) as mean_votes_per_game,
                (SELECT PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY rating_count) FROM game_counts) as median_votes_per_game,
                (SELECT COUNT(*) FROM game_counts) as total_rated_games
        ');

        // Use both mean and median for minimum votes calculation
        $meanBasedMinVotes = ceil($globals->mean_votes_per_game * 0.25); // 25% of mean
        $medianBasedMinVotes = ceil($globals->median_votes_per_game * 0.5); // 50% of median
        $minVotes = max(5, min($meanBasedMinVotes, $medianBasedMinVotes));
        $globalMean = $globals->global_weighted_mean;

        // Freeze the list of game IDs to process
        $gameQuery = DB::table('games');
        if (! $this->option('force')) {
            $gameQuery->whereNull('score_calculated_at')
                ->orWhereRaw('score_calculated_at < (SELECT MAX(processed_at) FROM ratings)');
        }
        $gameIds = $gameQuery->orderBy('id')->pluck('id');
        $totalGames = $gameIds->count();

        $this->info("Calculating scores for {$totalGames} games...");
        $bar = $this->output->createProgressBar($totalGames);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        // Process the game IDs in chunks to avoid issues with updating records
        $gameIds->chunk(50)->each(function ($chunk) use ($minVotes, $globalMean, $bar, &$processedCount, &$errorCount) {
            // Retrieve game records for the current chunk
            $games = DB::table('games')
                ->whereIn('id', $chunk->all())
                ->get();

            foreach ($games as $game) {
                DB::beginTransaction();

                try {
                    // Get rating statistics with weighted calculations
                    $stats = DB::selectOne('
                        WITH rating_stats AS (
                            SELECT
                                r.rating,
                                COALESCE(rt.weight, 0) as rater_weight
                            FROM ratings r
                            LEFT JOIN raters rt ON rt.id = r.rater_id
                            WHERE r.game_id = ? AND r.is_visible = true
                        )
                        SELECT
                            COUNT(*) as rating_count,
                            AVG(rating) as average_score,
                            CASE
                                WHEN SUM(rater_weight) > 0 THEN
                                    SUM(rating * rater_weight) / SUM(rater_weight)
                                ELSE
                                    AVG(rating)
                            END as weighted_rating
                        FROM rating_stats
                    ', [$game->id]);

                    if ($stats && $stats->rating_count > 0) {
                        // Calculate Bayesian weighted score
                        $weightedScore =
                            ($stats->rating_count / ($stats->rating_count + $minVotes)) * $stats->weighted_rating +
                            ($minVotes / ($stats->rating_count + $minVotes)) * $globalMean;

                        // Apply a confidence modifier based on rating count
                        $confidenceModifier = 1 - exp(-0.1 * $stats->rating_count);
                        $weightedScore *= $confidenceModifier;
                    } else {
                        $weightedScore = null;
                        $stats->average_score = null;
                        $stats->rating_count = 0;
                    }

                    // Update the game record
                    DB::table('games')
                        ->where('id', $game->id)
                        ->update([
                            'weighted_score' => $weightedScore,
                            'average_score' => $stats->average_score,
                            'rating_count' => $stats->rating_count,
                            'raw_weighted_rating' => $stats->weighted_rating ?? null,
                            'score_calculated_at' => now(),
                        ]);

                    DB::commit();
                    $processedCount++;
                } catch (Throwable $e) {
                    DB::rollBack();
                    $errorCount++;
                    Log::error("Error calculating score for game {$game->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Score calculation completed:');
        $this->info("- Processed: {$processedCount} games");
        $this->info("- Errors: {$errorCount} games");
    }
}
