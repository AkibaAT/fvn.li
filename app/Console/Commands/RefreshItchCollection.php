<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ItchCollectionService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshItchCollection extends Command
{
    private const int MAX_ATTEMPTS = 3;
    private const int RETRY_DELAY = 30;
    protected $signature = 'itch:collection:refresh';
    protected $description = 'Refresh games from configured itch.io collection';

    public function handle(ItchCollectionService $collectionService): int
    {
        $attempt = 1;

        while ($attempt <= self::MAX_ATTEMPTS) {
            try {
                $this->info('Starting collection refresh...');
                $collectionService->updateWatchlist();
                $this->info('Collection refresh complete!');

                return self::SUCCESS;
            } catch (Exception $e) {
                Log::error("Collection refresh attempt {$attempt} failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'attempt' => $attempt,
                ]);

                if ($attempt < self::MAX_ATTEMPTS) {
                    $delay = self::RETRY_DELAY * $attempt;
                    $this->warn("Retrying in {$delay} seconds...");
                    sleep($delay);
                    $attempt++;
                } else {
                    $this->error('All retry attempts exhausted. Collection refresh failed.');

                    return self::FAILURE;
                }
            }
        }

        return self::FAILURE;
    }
}
