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

        // Calculate the minimum rating threshold using both mean and median
        $meanBasedThreshold = ceil($globalStats->mean_ratings_per_rater * 0.25);
        $medianBasedThreshold = ceil($globalStats->median_ratings_per_rater * 0.5);
        $minRatingThreshold = max(5, min($meanBasedThreshold, $medianBasedThreshold));

        // Get total count for progress bar
        $totalRaters = DB::table('raters')->count();
        $this->info("Processing weights for approximately {$totalRaters} raters...");
        $bar = $this->output->createProgressBar($totalRaters);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;
        $batchSize = 500; // Smaller batch size to reduce memory usage
        $lastId = 0;

        while (true) {
            // Get a batch of rater IDs using a cursor approach
            $raterBatch = DB::table('raters')
                ->select('id')
                ->where('id', '>', $lastId)
                ->when(! $this->option('force'), function ($query) {
                    return $query->where(function ($q) {
                        $q->whereNull('weight_calculated_at')
                            ->orWhereExists(function ($sq) {
                                $sq->from('ratings')
                                    ->whereColumn('ratings.rater_id', 'raters.id')
                                    ->whereNull('ratings.processed_at');
                            });
                    });
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($raterBatch->isEmpty()) {
                break; // No more raters to process
            }

            // Update the last ID for the next batch
            $lastId = $raterBatch->last()->id;

            // Extract IDs from the batch
            $raterIds = $raterBatch->pluck('id')->all();

            // Free memory
            unset($raterBatch);

            // Fetch the rater records for this batch
            $raters = DB::table('raters')->whereIn('id', $raterIds)->get();

            // Fetch aggregated rating statistics for all raters in the batch
            $ratingStats = DB::table('ratings')
                ->select('rater_id',
                    DB::raw('COUNT(*) as total_ratings'),
                    DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1'),
                    DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2'),
                    DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3'),
                    DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4'),
                    DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5')
                )
                ->whereIn('rater_id', $raterIds)
                ->where('is_visible', true)
                ->groupBy('rater_id')
                ->get()
                ->keyBy('rater_id');

            // Process each rater in the batch
            foreach ($raters as $rater) {
                try {
                    // Retrieve the aggregated statistics or use default zeros if none found
                    $stats = $ratingStats->get($rater->id);
                    if (! $stats) {
                        $stats = (object) [
                            'total_ratings' => 0,
                            'rating_1' => 0,
                            'rating_2' => 0,
                            'rating_3' => 0,
                            'rating_4' => 0,
                            'rating_5' => 0,
                        ];
                    }

                    // Calculate the entropy-based weight
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

                    $ratingCountWeight = 0.5 + (0.5 * (1 - exp(-1.0 * $stats->total_ratings)));

                    // Combine the weights
                    $weight = $entropyWeight * $ratingCountWeight;

                    if ($stats->total_ratings < $minRatingThreshold) {
                        $weight *= 0.8 + (0.2 * $stats->total_ratings / $minRatingThreshold);
                    }

                    // Apply a suspicious penalty if necessary
                    if ($rater->is_suspicious) {
                        $weight *= 0.1;
                    }

                    // Update the rater record and mark ratings as processed within a transaction
                    DB::transaction(function () use ($rater, $weight, $entropyWeight, $ratingCountWeight) {
                        DB::table('raters')
                            ->where('id', $rater->id)
                            ->update([
                                'weight' => $weight,
                                'entropy_score' => $entropyWeight,
                                'rating_count_score' => $ratingCountWeight,
                                'weight_calculated_at' => now(),
                            ]);

                        DB::table('ratings')
                            ->where('rater_id', $rater->id)
                            ->whereNull('processed_at')
                            ->update(['processed_at' => now()]);
                    });

                    $processedCount++;
                } catch (Throwable $e) {
                    $errorCount++;
                    Log::error("Error calculating weight for rater {$rater->id}: " . $e->getMessage());
                }
                $bar->advance();
            }

            // Explicitly free memory after processing each batch
            unset($raters);
            unset($ratingStats);
            unset($raterIds);

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Weight calculation completed:');
        $this->info("- Processed: {$processedCount} raters");
        $this->info("- Errors: {$errorCount} raters");
    }
}
