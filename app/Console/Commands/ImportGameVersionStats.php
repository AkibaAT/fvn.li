<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportGameVersionStats extends Command
{
    protected $signature = 'games:import-stats
        {--game-id= : Game ID in the database}
        {--version-id= : Game version ID in the database}
        {--stats-file= : Path to the stats JSON file to import}';

    protected $description = 'Import stats JSON for a given game version';

    public function __construct(
        private readonly GameStatsService $statsService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $gameId = $this->option('game-id');
        $versionId = $this->option('version-id');
        $statsFile = $this->option('stats-file');

        // Validate parameters
        if (! $gameId) {
            $this->error('Game ID is required');

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
            $game = Game::find($gameId);
            if (! $game) {
                $this->error("Game with ID {$gameId} not found");

                return 1;
            }

            // Find the version
            $version = GameVersion::where('id', $versionId)
                ->where('game_id', $gameId)
                ->first();

            if (! $version) {
                $this->error("Version with ID {$versionId} for game {$gameId} not found");

                return 1;
            }

            $this->info("Processing stats for game: {$game->name}, version: {$version->version}");

            // Read and parse the stats file
            $statsContent = File::get($statsFile);
            $stats = json_decode($statsContent, true);

            if (! $stats || ! isset($stats['languages'])) {
                $this->error('Invalid stats file format. The file must contain a "languages" section.');

                return 1;
            }

            DB::beginTransaction();

            try {
                // Clear existing stats for this version
                $this->info('Clearing existing stats...');
                $version->supportedLanguages()->delete();

                // Save the stats
                $this->info('Importing new stats...');
                $this->statsService->saveVersionStats(
                    $version,
                    $stats,
                    $game->source_language_id ?? 'eng',
                    $game
                );

                DB::commit();
                $this->info('✓ Stats imported successfully');

                return 0;
            } catch (Exception $e) {
                DB::rollBack();
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
