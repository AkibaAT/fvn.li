<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\GameArchiveService;
use Illuminate\Console\Command;

class CleanupGameDownloads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:cleanup-downloads
                            {--game-id= : Clean up downloads for a specific game}
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
        $gameId = $this->option('game-id');
        $all = $this->option('all');

        if (! $gameId && ! $all) {
            $this->error('Please specify either --game-id or --all option');

            return 1;
        }

        if ($gameId) {
            // Clean up for a specific game
            $game = Game::find($gameId);
            if (! $game) {
                $this->error("Game with ID {$gameId} not found");

                return 1;
            }

            $this->info("Cleaning up old downloads for game: {$game->name} (ID: {$game->id})");
            $archiveService->cleanupOldVersionDownloads($game->id);
            $this->info('Cleanup completed successfully');
        } else {
            // Clean up for all games
            $this->info('Cleaning up old downloads for all games...');
            $count = $archiveService->cleanupAllOldVersionDownloads();
            $this->info("Cleanup completed successfully for {$count} games");
        }

        return 0;
    }
}
