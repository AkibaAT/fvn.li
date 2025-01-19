<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;
use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReimportGameVersion extends Command
{
    protected $signature = 'game:reimport-version
        {game_id : Game ID in the database}
        {version : Version string to reimport}
        {archive : Path to the game archive}';

    protected $description = 'Reimport version statistics from a local game archive';

    private GameStatsService $statsService;

    public function __construct(GameStatsService $statsService)
    {
        parent::__construct();
        $this->statsService = $statsService;
    }

    public function handle(): int
    {
        $gameId = $this->argument('game_id');
        $versionString = $this->argument('version');
        $archivePath = $this->argument('archive');

        $this->info("Starting version reimport for Game #{$gameId}, Version: {$versionString}");

        try {
            // Validate input
            if (! file_exists($archivePath)) {
                throw new Exception("Archive file not found: {$archivePath}");
            }

            $game = Game::findOrFail($gameId);

            // Start transaction
            DB::beginTransaction();

            try {
                // Get or create version
                $version = GameVersion::firstOrNew([
                    'game_id' => $game->id,
                    'version' => $versionString,
                ]);

                // If this is a new version, we need some basic metadata
                if (! $version->exists) {
                    $this->info('Creating new version record');
                    $version->fill([
                        'published_at' => new DateTime,
                        'is_latest' => false, // Don't change latest version status
                    ]);
                    $version->save();
                } else {
                    $this->info('Updating existing version record');
                }

                // Extract and process statistics
                $this->info('Processing game archive...');
                $stats = $this->statsService->extractGameStats($archivePath);

                if ($stats) {
                    $this->info('Saving version statistics...');
                    $this->statsService->saveVersionStats($version, $stats, $game->source_language_id);
                    $this->info('Statistics saved successfully');
                } else {
                    $this->warn('No statistics could be extracted from the archive');
                }

                DB::commit();
                $this->info('Version reimport completed successfully');

                return 0;

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            $this->error('Error during reimport: ' . $e->getMessage());
            Log::error('Version reimport failed', [
                'game_id' => $gameId,
                'version' => $versionString,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return 1;
        }
    }
}
