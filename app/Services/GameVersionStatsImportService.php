<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GameVersionStatsImportService
{
    public function __construct(
        private readonly GameStatsService $statsService
    ) {}

    /**
     * Import stats from a JSON file in storage
     *
     * @param  string  $filePath  Path to the JSON file in storage
     * @param  GameVersion  $version  The game version to import stats for
     *
     * @throws Exception
     */
    public function importFromStorage(string $filePath, GameVersion $version): void
    {
        // Check if the file exists
        if (! Storage::exists($filePath)) {
            throw new Exception('The uploaded file could not be found in storage.');
        }

        // Get the file content directly from storage
        $statsContent = Storage::get($filePath);

        // Process the content
        $this->processStatsContent($statsContent, $version);
    }

    /**
     * Import stats from a local file path
     *
     * @param  string  $filePath  Path to the JSON file on the local filesystem
     * @param  GameVersion  $version  The game version to import stats for
     *
     * @throws Exception
     */
    public function importFromLocalFile(string $filePath, GameVersion $version): void
    {
        // Check if the file exists
        if (! file_exists($filePath)) {
            throw new Exception("Stats file not found: {$filePath}");
        }

        // Get the file content
        $statsContent = file_get_contents($filePath);

        // Process the content
        $this->processStatsContent($statsContent, $version);
    }

    /**
     * Process stats content and save to the database
     *
     * @param  string  $statsContent  JSON content as a string
     * @param  GameVersion  $version  The game version to import stats for
     *
     * @throws Exception
     */
    private function processStatsContent(string $statsContent, GameVersion $version): void
    {
        // Validate that we have a string
        if (! is_string($statsContent) || empty($statsContent)) {
            throw new Exception('The file content is empty or invalid.');
        }

        // Decode JSON with error checking
        $stats = json_decode($statsContent, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }

        // Validate the stats structure
        if (! $stats || ! isset($stats['languages'])) {
            throw new Exception('Invalid stats file format. The file must contain a "languages" section.');
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Clear existing stats for this version
            $version->supportedLanguages()->delete();

            // Save the stats
            $this->statsService->saveVersionStats(
                $version,
                $stats,
                $version->game->source_language_id ?? 'eng',
                $version->game
            );

            // Commit the transaction
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
