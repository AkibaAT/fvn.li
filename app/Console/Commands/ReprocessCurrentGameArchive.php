<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use App\Services\GameVersionArchiveRepositoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReprocessCurrentGameArchive extends Command
{
    use SelectsGames;

    protected $signature = 'games:reprocess-current-archive
                            {--game-id= : ID of the specific game to reprocess}
                            {--game-name= : Name (or part of name) of the game(s) to reprocess}
                            {--all : Reprocess all visible Ren\'Py games with stored current archives}';

    protected $description = 'Import stats for the current game version from its already stored archive';

    public function handle(
        GameArchiveService $archiveService,
        GameStatsService $statsService,
        GameVersionArchiveRepositoryService $repository
    ): int {
        $statsService->setProgressReporter(fn (string $message) => $this->line($message));

        if (! $this->validateGameSelectionOptions()) {
            return self::FAILURE;
        }

        $query = Game::query()
            ->where('is_visible', true)
            ->where('game_engine', "Ren'Py")
            ->with('latestVersion');

        $this->applyGameSelectionFilters($query);

        $games = $query->get();
        $this->displaySelectedGames($games);

        if ($games->isEmpty()) {
            return self::FAILURE;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($games as $game) {
            $this->newLine();
            $this->info("Reprocessing current archive for {$game->name} (ID: {$game->id})");

            try {
                $version = $this->getCurrentVersion($game);
                if (! $version) {
                    $this->warn('No current version found, skipping');
                    $skipped++;

                    continue;
                }

                $archivePath = $archiveService->getStoredArchive($game->id, $version->id);
                if (! $archivePath) {
                    $this->warn("No stored archive found for current version {$version->version}, skipping");
                    $skipped++;

                    continue;
                }

                $stats = $archiveService->processArchive($archivePath);
                if (! $stats) {
                    $this->warn("No stats could be extracted from current version {$version->version}, skipping");
                    if ($archiveService->getLastProcessingError()) {
                        $this->warn('Stats extraction reason: ' . $archiveService->getLastProcessingError());
                    }
                    $skipped++;

                    continue;
                }

                try {
                    $statsService->saveVersionStats($version, $stats, $game->source_language_id ?? 'eng', $game);
                } finally {
                    // Drop the extracted document before moving to the next game.
                    $stats->release();
                }
                $this->info("Imported stats for current version {$version->version}");
                $this->persistStoredArchive($repository, $game, $version);
                $processed++;
            } catch (Throwable $e) {
                $this->error("Failed to reprocess archive for {$game->name}: {$e->getMessage()}");
                Log::error('Current game archive reprocess failed', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Reprocess complete: {$processed} processed, {$skipped} skipped, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function persistStoredArchive(GameVersionArchiveRepositoryService $repository, Game $game, GameVersion $version): void
    {
        $this->line('Optimizing and persisting archive...');
        $result = $repository->persistStoredArchive($game, $version, true);

        if ($result['status'] === 'persisted') {
            $build = isset($result['build_id']) ? " build #{$result['build_id']}" : '';
            $this->info("Persisted optimized archive to DenKit Stash {$result['target']}{$build}");

            return;
        }

        $this->warn('Archive persistence skipped: ' . ($result['reason'] ?? 'already persisted'));
    }

    private function getCurrentVersion(Game $game): ?GameVersion
    {
        if ($game->latestVersion) {
            return $game->latestVersion;
        }

        return $game->gameVersions()
            ->orderBy('published_at', 'desc')
            ->first();
    }
}
