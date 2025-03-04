<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ItchFollowService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FollowGameCreators extends Command
{
    protected $signature = 'games:follow-creators
        {--limit= : Limit number of games to process}';

    protected $description = 'Follow creators of visible games in our database';

    private ItchFollowService $followService;
    private int $successCount = 0;
    private int $errorCount = 0;

    public function __construct(ItchFollowService $followService)
    {
        parent::__construct();
        $this->followService = $followService;
    }

    public function handle(): int
    {
        $this->info('Starting to follow game creators');

        try {
            // Get visible games
            $query = Game::query()
                ->where('is_visible', true)
                ->whereNotNull('url');

            // Apply limit if specified
            if ($limit = $this->option('limit')) {
                $query->limit((int) $limit);
            }

            $games = $query->get();
            $totalGames = $games->count();

            if ($totalGames === 0) {
                $this->info('No games found to process');

                return 0;
            }

            $this->info("Found {$totalGames} games to process");
            $bar = $this->output->createProgressBar($totalGames);
            $bar->start();

            foreach ($games as $index => $game) {
                try {
                    $success = $this->followService->followCreatorFromGameUrl($game->url);

                    if ($success) {
                        $this->successCount++;
                    } else {
                        $this->errorCount++;
                    }

                } catch (Exception $e) {
                    $this->errorCount++;
                    Log::error("Error following creator for game {$game->id}", [
                        'game_id' => $game->id,
                        'url' => $game->url,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();

                // Add a delay between requests to avoid rate limiting
                if ($index < $totalGames - 1) {
                    sleep(3);
                }
            }

            $bar->finish();
            $this->newLine(2);

            $this->info('Processing complete:');
            $this->info("- Total games processed: {$totalGames}");
            $this->info("- Successful follows: {$this->successCount}");
            $this->info("- Failed follows: {$this->errorCount}");

            return 0;

        } catch (Exception $e) {
            $this->error('Error in follow creators process: ' . $e->getMessage());
            Log::error('Follow creators process failed', ['exception' => $e]);

            return 1;
        }
    }
}
