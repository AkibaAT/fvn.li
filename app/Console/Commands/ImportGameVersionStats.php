<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameVersionStatsImportService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportGameVersionStats extends Command
{
    use SelectsGames;

    protected $signature = 'games:import-stats
        {--game-id= : ID of the specific game to process}
        {--game-name= : Name of the game to process (only used if game-id is not provided)}
        {--version-id= : Game version ID in the database}
        {--stats-file= : Path to the stats document produced by the analyzer}';

    protected $description = 'Import an analyzer stats document for a given game version';

    public function __construct(
        private readonly GameVersionStatsImportService $importService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Use sync mode for Scout indexing in CLI to avoid queueing thousands of dialogue lines
        Config::set('scout.queue', false);

        $versionId = $this->option('version-id');
        $statsFile = $this->option('stats-file');

        // Validate parameters
        if (! $this->option('game-id') && ! $this->option('game-name')) {
            $this->error('Either game ID or game name is required');

            return 1;
        }

        if (! $versionId) {
            $this->error('Version ID is required');

            return 1;
        }

        if (! $statsFile) {
            $this->error('Stats file path is required');

            return 1;
        }

        // Check if stats file exists
        if (! File::exists($statsFile)) {
            $this->error("Stats file not found: {$statsFile}");

            return 1;
        }

        try {
            // Find the game
            $query = Game::query();

            // Apply game selection filters
            $this->applyGameSelectionFilters($query);

            $game = $query->first();

            if (! $game) {
                $this->error('Game not found with the provided criteria');

                return 1;
            }

            $this->info("Found game: {$game->name} (ID: {$game->id})");
            $gameId = $game->id;

            // Find the version
            $version = GameVersion::where('id', $versionId)
                ->where('game_id', $gameId)
                ->first();

            if (! $version) {
                $this->error("Version with ID {$versionId} for game {$gameId} not found");

                return 1;
            }

            $this->info("Processing stats for game: {$game->name}, version: {$version->version}");

            try {
                // Import the stats using the service
                $this->info('Importing stats...');
                $this->importService->importFromLocalFile($statsFile, $version);

                $this->info('✓ Stats imported successfully');

                return 0;
            } catch (Exception $e) {
                throw $e;
            }
        } catch (Exception $e) {
            $this->error('Error importing stats: ' . $e->getMessage());
            Log::error('Stats import failed', [
                'game_id' => $gameId,
                'version_id' => $versionId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return 1;
        }
    }
}
