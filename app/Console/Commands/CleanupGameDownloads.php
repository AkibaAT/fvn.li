<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\GameArchiveService;
use Illuminate\Console\Command;

class CleanupGameDownloads extends Command
{
    use SelectsGames;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:cleanup-downloads
                            {--game-id= : ID of the specific game to clean up}
                            {--game-name= : Name (or part of name) of the game(s) to clean up}
                            {--all : Clean up downloads for all games}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old game version downloads, keeping only the latest version';

    /**
     * Execute the console command.
     */
    public function handle(GameArchiveService $archiveService): int
    {
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        if ($this->option('all')) {
            // Clean up for all games
            $this->info('Cleaning up old downloads for all games...');
            $count = $archiveService->cleanupAllOldVersionDownloads();
            $this->info("Cleanup completed successfully for {$count} games");
        } else {
            // Clean up for specific game(s)
            $query = Game::query();

            $this->applyGameSelectionFilters($query);

            $games = $query->get();

            // Display selected games
            $this->displaySelectedGames($games);

            if ($games->isEmpty()) {
                return 1;
            }

            foreach ($games as $game) {
                $this->info("Cleaning up old downloads for game: {$game->name} (ID: {$game->id})");
                $archiveService->cleanupOldVersionDownloads($game->id);
            }

            $this->info('Cleanup completed successfully for ' . $games->count() . ' game(s)');
        }

        return 0;
    }
}
