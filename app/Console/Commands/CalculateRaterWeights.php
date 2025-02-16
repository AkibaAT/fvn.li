<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculateRaterWeights extends Command
{
    protected $signature = 'raters:calculate-weights {--force : Recalculate weights for all raters}';
    protected $description = 'Calculate rater rating weights based on entropy and rating count';

    public function handle(): void
    {
        // Get global statistics first
        $globalStats = DB::selectOne('
            WITH rater_counts AS (
                SELECT rater_id, COUNT(*) as rating_count
                FROM ratings
                WHERE is_visible = true
                GROUP BY rater_id
            )
            SELECT
                (SELECT COUNT(*) FROM ratings WHERE is_visible = true) as total_ratings,
                COUNT(*) as total_raters,
                AVG(rating_count) as mean_ratings_per_rater,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY rating_count) as median_ratings_per_rater
            FROM rater_counts
        ');

        // Calculate minimum rating threshold using both mean and median
        $meanBasedThreshold = ceil($globalStats->mean_ratings_per_rater * 0.25);
        $medianBasedThreshold = ceil($globalStats->median_ratings_per_rater * 0.5);
        $minRatingThreshold = max(5, min($meanBasedThreshold, $medianBasedThreshold));

        // Get raters that need processing
        $query = DB::table('raters')
            ->when(! $this->option('force'), function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('weight_calculated_at')
                        ->orWhereExists(function ($sq) {
                            $sq->from('ratings')
                                ->whereColumn('ratings.rater_id', 'raters.id')
                                ->whereNull('ratings.processed_at');
                        });
                });
            });

        $totalRaters = $query->count();
        $this->info("Processing weights for {$totalRaters} raters...");
        $bar = $this->output->createProgressBar($totalRaters);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        $query->orderBy('id')->chunk(100, function ($raters) use ($minRatingThreshold, $bar, &$processedCount, &$errorCount) {
            foreach ($raters as $rater) {
                try {
                    // Get rating distribution
                    $stats = DB::selectOne('
                        SELECT
                            COUNT(*) as total_ratings,
                            COUNT(CASE WHEN rating = 1 THEN 1 END) as rating_1,
                            COUNT(CASE WHEN rating = 2 THEN 1 END) as rating_2,
                            COUNT(CASE WHEN rating = 3 THEN 1 END) as rating_3,
                            COUNT(CASE WHEN rating = 4 THEN 1 END) as rating_4,
                            COUNT(CASE WHEN rating = 5 THEN 1 END) as rating_5
                        FROM ratings
                        WHERE rater_id = ? AND is_visible = true
                    ', [$rater->id]);

                    // Calculate entropy-based weight
                    $alpha = 1; // Laplace smoothing factor
                    $counts = [
                        $stats->rating_1 + $alpha,
                        $stats->rating_2 + $alpha,
                        $stats->rating_3 + $alpha,
                        $stats->rating_4 + $alpha,
                        $stats->rating_5 + $alpha,
                    ];

                    $total = array_sum($counts);
                    $entropy = 0;

                    foreach ($counts as $count) {
                        $p = $count / $total;
                        $entropy += $p * log($p);
                    }

                    $entropy = -$entropy;
                    $maxEntropy = log(5);
                    $entropyWeight = $entropy / $maxEntropy;

                    // Calculate rating count weight using a sigmoid function
                    $ratingCountWeight = 1 / (1 + exp(-0.1 * ($stats->total_ratings - $minRatingThreshold)));

                    // Combine weights
                    $weight = $entropyWeight * $ratingCountWeight;

                    // Additional penalty for very low rating counts
                    if ($stats->total_ratings < $minRatingThreshold) {
                        $weight *= ($stats->total_ratings / $minRatingThreshold);
                    }

                    // Apply suspicious penalty if needed
                    if ($rater->is_suspicious) {
                        $weight *= 0.1;
                    }

                    DB::transaction(function () use ($rater, $weight, $entropyWeight, $ratingCountWeight) {
                        // Update rater weight
                        DB::table('raters')
                            ->where('id', $rater->id)
                            ->update([
                                'weight' => $weight,
                                'entropy_score' => $entropyWeight,
                                'rating_count_score' => $ratingCountWeight,
                                'weight_calculated_at' => now(),
                            ]);

                        // Mark all ratings as processed
                        DB::table('ratings')
                            ->where('rater_id', $rater->id)
                            ->update(['processed_at' => now()]);
                    });

                    $processedCount++;
                    $bar->advance();

                } catch (Throwable $e) {
                    $errorCount++;
                    Log::error("Error calculating weight for rater {$rater->id}: " . $e->getMessage());
                }
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Weight calculation completed:');
        $this->info("- Processed: {$processedCount} raters");
        $this->info("- Errors: {$errorCount} raters");
    }
}
