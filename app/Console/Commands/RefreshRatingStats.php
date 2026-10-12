<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RatingStatsCacheService;
use Illuminate\Console\Command;

class RefreshRatingStats extends Command
{
    protected $signature = 'ratings:refresh-stats';

    protected $description = 'Recalculate the cached rating counts and global rating statistics';

    public function handle(): int
    {
        foreach ([true, false] as $visibleGamesOnly) {
            $cube = RatingStatsCacheService::refreshCube($visibleGamesOnly);
            $this->info(sprintf(
                'Refreshed rating stats for %s: %d ratings.',
                $visibleGamesOnly ? 'visible games' : 'all games',
                array_sum(array_column($cube['cells'], 'count')),
            ));
        }

        return Command::SUCCESS;
    }
}
