<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\ManagesFlareSolverrSession;
use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\ValueObjects\Upload;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DownloadLatestGameArchive extends Command
{
    use ManagesFlareSolverrSession;
    use SelectsGames;

    protected $signature = 'games:download-latest
                            {--game-id= : ID of the specific game to download}
                            {--game-name= : Name (or part of name) of the game(s) to download}
                            {--all : Download for all itch.io games}
                            {--force : Re-download even when an archive is already stored for the latest version}';

    protected $description = 'Download and keep the latest processable itch.io file for selected games';

    public function handle(GameArchiveService $archiveService): int
    {
        return $this->executeWithFlareSolverrSession(fn () => $this->executeDownload($archiveService));
    }

    private function executeDownload(GameArchiveService $archiveService): int
    {
        if (! $this->validateGameSelectionOptions()) {
            return self::FAILURE;
        }

        $query = $this->buildGameQuery();
        $totalGames = (clone $query)->count();

        if ($totalGames === 0) {
            $this->warn('No visible Ren\'Py itch.io games found matching the selection criteria');

            return self::FAILURE;
        }

        $this->info("Found {$totalGames} visible Ren'Py itch.io game(s) to check");

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;

        $lastId = 0;
        do {
            $games = (clone $query)
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit(100)
                ->get();

            if ($games->isEmpty()) {
                break;
            }

            foreach ($games as $game) {
                $lastId = (int) $game->id;
                $processed++;
                $result = $this->processGame($archiveService, $game, $processed, $totalGames);

                match ($result) {
                    'downloaded' => $downloaded++,
                    'failed' => $failed++,
                    default => $skipped++,
                };
            }
        } while ($games->count() === 100);

        $this->newLine();
        $this->info("Download complete: {$downloaded} downloaded, {$skipped} skipped, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildGameQuery()
    {
        $query = Game::query()
            ->select(['id', 'name', 'status', 'uploads', 'url', 'platform', 'game_engine'])
            ->where('is_visible', true)
            ->where('game_engine', "Ren'Py")
            ->fromItchio();

        return $this->applyGameSelectionFilters($query);
    }

    private function processGame(GameArchiveService $archiveService, Game $game, int $index, int $totalGames): string
    {
        $this->newLine();
        $this->info("Game {$index}/{$totalGames}: {$game->name} (ID: {$game->id})");

        try {
            $version = $this->getLatestVersion($game);
            if (! $version) {
                $this->warn('No latest version found, skipping');

                return 'skipped';
            }

            $bestUpload = $this->getBestUpload($game);
            if (! $bestUpload) {
                $this->warn('No processable uploads found, skipping');

                return 'skipped';
            }

            if (! $this->option('force') && $archiveService->archiveExists($game->id, $version->id, $bestUpload->filename)) {
                $this->line("Archive already stored for version {$version->version}: {$bestUpload->filename}");

                return 'skipped';
            }

            $this->line("Selected upload from database file ID {$bestUpload->id}: {$bestUpload->filename}");
            $this->line('Requesting itch.io download URL...');

            $progressBar = null;
            $lastDownloadedBytes = 0;
            $announcedUnknownSize = false;
            $lastUnknownReportBytes = 0;
            $archivePath = $archiveService->downloadAndStore(
                $game->getPrimaryUrl(),
                $bestUpload->filename,
                $bestUpload->id,
                $game->id,
                $version->id,
                (bool) $this->option('force'),
                function (
                    int $downloadTotal,
                    int $downloadedBytes,
                    int $_uploadTotal,
                    int $_uploadedBytes
                ) use (&$progressBar, &$lastDownloadedBytes, &$announcedUnknownSize, &$lastUnknownReportBytes): void {
                    if ($downloadTotal > 0 && $progressBar === null) {
                        $progressBar = $this->output->createProgressBar($downloadTotal);
                        $progressBar->setFormat(' %current:6s%/%max:6s% bytes [%bar%] %percent:3s%%');
                        $progressBar->start();
                    }

                    if ($progressBar !== null && $downloadedBytes > $lastDownloadedBytes) {
                        $progressBar->setProgress($downloadedBytes);
                        $lastDownloadedBytes = $downloadedBytes;
                    }

                    if ($downloadTotal === 0 && $downloadedBytes > 0) {
                        if (! $announcedUnknownSize) {
                            $this->line('Downloading... remote server did not provide a file size.');
                            $announcedUnknownSize = true;
                        }

                        if ($downloadedBytes - $lastUnknownReportBytes >= 25 * 1024 * 1024) {
                            $this->line(sprintf('Downloaded %.1f MiB...', $downloadedBytes / 1024 / 1024));
                            $lastUnknownReportBytes = $downloadedBytes;
                        }
                    }
                }
            );

            if ($progressBar !== null) {
                $progressBar->finish();
                $this->newLine(2);
            } else {
                $this->line('Download finished.');
            }

            $this->info("Stored archive for version {$version->version}: {$archivePath}");

            return 'downloaded';
        } catch (Exception $e) {
            $this->error("Failed to download archive for {$game->name}: {$e->getMessage()}");
            Log::error('Latest game archive download failed', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return 'failed';
        }
    }

    private function getLatestVersion(Game $game): ?GameVersion
    {
        $version = GameVersion::query()
            ->where('game_id', $game->id)
            ->where('is_latest', true)
            ->first();

        if ($version) {
            return $version;
        }

        return GameVersion::query()
            ->where('game_id', $game->id)
            ->orderBy('published_at', 'desc')
            ->first();
    }

    private function getBestUpload(Game $game): ?Upload
    {
        $uploads = $this->normalizeUploads($game->uploads ?? []);

        return Upload::getBest($uploads);
    }

    /**
     * @param  array<string|int, array<string, mixed>>  $uploads
     * @return Collection<int, Upload>
     */
    private function normalizeUploads(array $uploads): Collection
    {
        return collect($uploads)
            ->map(function (array $upload, int|string $id): ?Upload {
                if (! isset($upload['updated_at'])) {
                    $upload['updated_at'] = now()->toDateTimeString();
                }

                try {
                    return Upload::fromArray($upload, (int) ($upload['id'] ?? $id));
                } catch (Exception $e) {
                    Log::warning('Failed to normalize game upload for archive download', [
                        'upload_id' => $id,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            })
            ->filter()
            ->values();
    }
}
