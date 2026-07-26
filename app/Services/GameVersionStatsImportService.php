<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use App\Support\Stats\StatsPayload;
use App\Support\Stats\StatsPayloadFactory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GameVersionStatsImportService
{
    public function __construct(
        private readonly GameStatsService $statsService
    ) {}

    /**
     * Import stats from a file in storage
     *
     * @param  string  $filePath  Path to the stats file in storage
     * @param  GameVersion  $version  The game version to import stats for
     *
     * @throws Exception
     */
    public function importFromStorage(string $filePath, GameVersion $version): void
    {
        if (! Storage::exists($filePath)) {
            throw new Exception('The uploaded file could not be found in storage.');
        }

        $this->importFromLocalFile(Storage::path($filePath), $version);
    }

    /**
     * Import stats from a local file path
     *
     * @param  string  $filePath  Path to the stats file on the local filesystem
     * @param  GameVersion  $version  The game version to import stats for
     *
     * @throws Exception
     */
    public function importFromLocalFile(string $filePath, GameVersion $version): void
    {
        if (! file_exists($filePath)) {
            throw new Exception("Stats file not found: {$filePath}");
        }

        // Read through the same streaming path the analyzer output uses, so an
        // uploaded file for a large game imports on the same memory budget.
        $this->persist(StatsPayloadFactory::fromFile($filePath), $version);
    }

    /**
     * @throws Exception
     */
    private function persist(StatsPayload $payload, GameVersion $version): void
    {
        if ($payload->languages() === []) {
            throw new Exception('Invalid stats file format. The file must describe at least one language.');
        }

        DB::beginTransaction();

        try {
            $this->statsService->saveVersionStats(
                $version,
                $payload,
                $version->game->source_language_id ?? 'eng',
                $version->game
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
