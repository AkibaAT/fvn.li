<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReimportGameVersion extends Command
{
    use SelectsGames;

    protected $signature = 'games:reimport-version
        {--game-id= : ID of the specific game to process}
        {--game-name= : Name (or part of name) of the game(s) to process}
        {--all : Process all visible Ren\'Py games}
        {--game-version= : Version string to reimport (requires --game-id)}
        {--timestamp= : Timestamp for published_at (format: YYYY-MM-DD HH:mm:ss)}';

    protected $description = 'Reimport version statistics from stored game archives';

    public function __construct(
        private readonly GameStatsService $statsService,
        private readonly GameArchiveService $archiveService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Use sync mode for Scout indexing in CLI to avoid queueing thousands of dialogue lines
        Config::set('scout.queue', false);

        $versionString = $this->option('game-version');
        $timestamp = $this->option('timestamp');

        // Validate parameters
        if ($versionString && ! $this->option('game-id')) {
            $this->error('A specific game ID (--game-id) must be provided when specifying a version');

            return 1;
        }

        // Validate that we have at least one game selection option
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        try {
            // Build query for games
            $query = Game::query()
                ->where('is_visible', true)
                ->where('game_engine', "Ren'Py")
                ->where('is_stats_extraction_disabled', false);

            // Apply game selection filters
            $this->applyGameSelectionFilters($query);

            // Get games to process
            $games = $query->with([
                'gameVersions' => function ($query) use ($versionString) {
                    if ($versionString) {
                        $query->where('version', $versionString);
                    } else {
                        $query->where('is_latest', true);
                    }
                },
            ])->get();

            // Display selected games
            $this->displaySelectedGames($games);

            if ($games->isEmpty()) {
                return 1;
            }

            $totalGames = $games->count();

            foreach ($games as $i => $game) {
                $this->info(sprintf("\nProcessing game %d/%d: %s", $i + 1, $totalGames, $game->name));

                $versions = $game->gameVersions;
                if ($versions->isEmpty()) {
                    $this->warn('No matching versions found, skipping');

                    continue;
                }

                foreach ($versions as $version) {
                    $this->info("Processing version: {$version->version}");

                    try {
                        DB::beginTransaction();

                        // Parse timestamp if provided
                        $publishedAt = null;
                        if ($timestamp) {
                            try {
                                $publishedAt = new DateTime($timestamp);
                                $version->published_at = $publishedAt;

                                // If updating published_at, we need to re-evaluate is_latest
                                $latestVersion = $game->gameVersions()
                                    ->orderByDesc('published_at')
                                    ->first();

                                if ($latestVersion) {
                                    $game->gameVersions()
                                        ->where('id', '!=', $latestVersion->id)
                                        ->update(['is_latest' => false]);
                                    $latestVersion->is_latest = true;
                                    $latestVersion->save();
                                }
                            } catch (Exception $e) {
                                $this->error('Invalid timestamp format. Use YYYY-MM-DD HH:mm:ss');
                                DB::rollBack();

                                return 1;
                            }
                        }

                        // Get stored archive
                        $storedArchive = $this->archiveService->getStoredArchive($game->id, $version->id);
                        if (! $storedArchive) {
                            $this->warn('No stored archive found for this version, skipping');
                            DB::rollBack();

                            continue;
                        }

                        // Process archive and extract statistics
                        $this->info('Processing game archive...');
                        try {
                            $stats = $this->archiveService->processArchive($storedArchive);
                        } catch (Exception $e) {
                            $this->error('Failed to process archive: '.$e->getMessage());
                            DB::rollBack();

                            continue;
                        }

                        if ($stats) {
                            $this->info('Saving version statistics...');
                            $this->statsService->saveVersionStats($version, $stats, $game->source_language_id);
                            $this->info('✓ Statistics saved successfully');
                        } else {
                            $this->warn('No statistics could be extracted from the archive');
                        }

                        DB::commit();

                    } catch (Exception $e) {
                        DB::rollBack();
                        $this->error("Error processing version {$version->version}: ".$e->getMessage());
                        Log::error('Version reimport failed', [
                            'game_id' => $game->id,
                            'version' => $version->version,
                            'error' => $e->getMessage(),
                            'exception' => $e,
                        ]);
                    }
                }
            }

            $this->info("\nReimport process completed");

            return 0;

        } catch (Exception $e) {
            $this->error('Error during reimport process: '.$e->getMessage());
            Log::error('Version reimport process failed', ['exception' => $e]);

            return 1;
        }
    }
}
