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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReimportGameVersion extends Command
{
    protected $signature = 'game:reimport-version
        {game_id : Game ID in the database}
        {version : Version string to reimport}
        {--timestamp= : Timestamp for published_at (required for new versions, format: YYYY-MM-DD HH:mm:ss)}
        {--archive= : Path to the game archive (if not using stored version)}';

    protected $description = 'Reimport version statistics from a stored or local game archive';

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
        $archivePath = $this->option('archive');
        $timestamp = $this->option('timestamp');

        $this->info("Starting version reimport for Game #{$gameId}, Version: {$versionString}");

        try {
            $game = Game::findOrFail($gameId);

            // Start transaction
            DB::beginTransaction();

            try {
                // Get or create version
                $version = GameVersion::firstOrNew([
                    'game_id' => $game->id,
                    'version' => $versionString,
                ]);

                // For new versions, timestamp is required
                if (! $version->exists && ! $timestamp) {
                    $this->error('Timestamp is required for new versions');
                    return 1;
                }

                // Parse timestamp if provided
                $publishedAt = null;
                if ($timestamp) {
                    try {
                        $publishedAt = new DateTime($timestamp);
                    } catch (Exception $e) {
                        $this->error('Invalid timestamp format. Use YYYY-MM-DD HH:mm:ss');
                        return 1;
                    }
                }

                // If this is a new version, we need some basic metadata
                if (! $version->exists) {
                    $this->info('Creating new version record');
                    $version->fill([
                        'published_at' => $publishedAt,
                        'is_latest' => false, // Don't change latest version status
                    ]);
                    $version->save();
                } else {
                    $this->info('Updating existing version record');
                    if ($publishedAt) {
                        $version->published_at = $publishedAt;
                        $version->save();
                    }
                }

                // Check for stored archive first
                $storedArchivePath = null;
                if (! $archivePath) {
                    $storagePath = "games/{$game->id}/{$version->id}";
                    $files = Storage::files($storagePath);
                    if (! empty($files)) {
                        $storedArchivePath = Storage::path($files[0]);
                        $this->info("Using stored archive: " . basename($storedArchivePath));
                    } else {
                        $this->error("No stored archive found for this version");
                        DB::rollBack();
                        return 1;
                    }
                }

                // Validate archive path
                $finalArchivePath = $storedArchivePath ?? $archivePath;
                if (! file_exists($finalArchivePath)) {
                    $this->error("Archive file not found: {$finalArchivePath}");
                    DB::rollBack();
                    return 1;
                }

                // Extract and process statistics
                $this->info('Processing game archive...');
                $stats = $this->statsService->extractGameStats($finalArchivePath);

                if ($stats) {
                    $this->info('Saving version statistics...');
                    $this->statsService->saveVersionStats($version, $stats, $game->source_language_id);

                    // If using a local archive, store it permanently
                    if ($archivePath && ! $storedArchivePath) {
                        $this->info('Storing archive file...');
                        $this->statsService->storeProcessedFile(
                            $archivePath,
                            basename($archivePath),
                            $game->id,
                            $version->id
                        );
                    }

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
