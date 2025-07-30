<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RatingCalculationService;
use Illuminate\Console\Command;

class RecalculateGameRatings extends Command
{
    protected $signature = 'ratings:recalculate';

    protected $description = 'Recalculate rating totals for all games from individual Rating records';

    public function handle(RatingCalculationService $ratingService): int
    {
        $this->info('Starting game rating recalculation...');

        $updatedCount = $ratingService->recalculateAllGameRatings();

        $this->info("Successfully recalculated ratings for {$updatedCount} games.");

        return self::SUCCESS;
    }
}
