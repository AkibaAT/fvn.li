<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Service for handling game archive operations
 */
readonly class GameArchiveService
{
    public function __construct(
        private GameStatsService $statsService
    ) {}

    /**
     * Get the stored archive path for a game version
     */
    public function getStoredArchive(int $gameId, int $versionId): ?string
    {
        $storagePath = $this->getStoragePath($gameId, $versionId);
        $files = Storage::files($storagePath);

        if (empty($files)) {
            return null;
        }

        return Storage::path($files[0]);
    }

    /**
     * Download, store, and process a game archive
     *
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws BindingResolutionException
     */
    public function downloadAndProcess(
        string $gameUrl,
        string $filename,
        int $uploadId,
        int $gameId,
        int $versionId,
        bool $force = false,
        bool $deleteAfterProcessing = false
    ): array {
        // Download and store the archive (force parameter is now passed through)
        $archivePath = $this->downloadAndStore($gameUrl, $filename, $uploadId, $gameId, $versionId, $force);

        // Clean up old version downloads for this game
        $this->cleanupOldVersionDownloads($gameId, $versionId);

        // Process the archive - stats may be null if extraction failed but shouldn't be treated as an error
        $stats = $this->processArchive($archivePath);

        // Delete the archive after processing if requested
        if ($deleteAfterProcessing && File::exists($archivePath)) {
            try {
                File::delete($archivePath);
                $archivePath = null;
            } catch (\Exception $e) {
                // Log the error but don't fail - stats are already processed
                Log::warning('Failed to delete archive after processing', [
                    'archive_path' => $archivePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'archive' => $archivePath,
            'stats' => $stats,
        ];
    }

    /**
     * Download and store a game archive
     *
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws BindingResolutionException
     */
    public function downloadAndStore(
        string $gameUrl,
        string $filename,
        int $uploadId,
        int $gameId,
        int $versionId,
        bool $force = false
    ): string {
        // Get the ItchHttpClientService for itch.io requests
        $itchClient = $this->getItchClient();

        // Get the download URL
        $response = $itchClient->post($gameUrl . '/file/' . $uploadId);
        $downloadInfo = json_decode($response->getBody()->getContents(), true);

        if (! isset($downloadInfo['url'])) {
            throw new RuntimeException('Could not get download URL');
        }

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'game_');
        if ($tempFile === false) {
            throw new RuntimeException('Could not create temporary file');
        }

        try {
            // Create a new client for downloading the file
            // The download URL is typically from a CDN, not itch.io directly
            $downloadClient = new Client;
            $downloadClient->get($downloadInfo['url'], [
                'sink' => $tempFile,
            ]);

            // Get storage path and prepare directory
            $storagePath = $this->getStoragePath($gameId, $versionId);

            // If force is true or the file exists but with a different name,
            // clear the directory first
            if ($force || ($this->archiveExists($gameId, $versionId) &&
                    ! $this->archiveExists($gameId, $versionId, $filename))) {
                // Delete all files in the directory
                foreach (Storage::files($storagePath) as $file) {
                    Storage::delete($file);
                }
            }

            // Ensure directory exists and store file
            Storage::makeDirectory($storagePath);
            Storage::putFileAs($storagePath, $tempFile, $filename);

            return Storage::path("{$storagePath}/{$filename}");

        } finally {
            // Clean up temp file
            if (File::exists($tempFile)) {
                File::delete($tempFile);
            }
        }
    }

    /**
     * Check if an archive exists for the specified game version
     */
    public function archiveExists(int $gameId, int $versionId, ?string $filename = null): bool
    {
        $storagePath = $this->getStoragePath($gameId, $versionId);

        if ($filename) {
            return Storage::exists("{$storagePath}/{$filename}");
        }

        // If no specific filename, check if directory has any files
        return ! empty(Storage::files($storagePath));
    }

    /**
     * Clean up old version downloads for a game, keeping only the latest version
     */
    public function cleanupOldVersionDownloads(int $gameId, ?int $latestVersionId = null): void
    {
        // If no specific latest version ID is provided, find the latest version for this game
        if ($latestVersionId === null) {
            $latestVersion = GameVersion::where('game_id', $gameId)
                ->where('is_latest', true)
                ->first();

            if (! $latestVersion) {
                // If no latest version is found, try to get the most recently published version
                $latestVersion = GameVersion::where('game_id', $gameId)
                    ->orderByDesc('published_at')
                    ->first();

                if (! $latestVersion) {
                    // No versions found for this game
                    return;
                }
            }

            $latestVersionId = $latestVersion->id;
        }

        // Get all version IDs for this game except the latest
        $oldVersionIds = GameVersion::where('game_id', $gameId)
            ->where('id', '!=', $latestVersionId)
            ->pluck('id')
            ->toArray();

        // Delete archives for old versions
        foreach ($oldVersionIds as $versionId) {
            $storagePath = $this->getStoragePath($gameId, $versionId);

            // Check if directory exists
            if (Storage::exists($storagePath)) {
                // Get all files in the directory
                $files = Storage::files($storagePath);

                // Delete each file
                foreach ($files as $file) {
                    Storage::delete($file);
                    Log::info('Deleted old game version archive', [
                        'game_id' => $gameId,
                        'version_id' => $versionId,
                        'file' => $file,
                    ]);
                }

                // Remove the directory if it's empty
                if (empty(Storage::files($storagePath))) {
                    Storage::deleteDirectory($storagePath);
                }
            }
        }
    }

    /**
     * Process statistics from an existing archive
     *
     * @return array|null Stats array or null if extraction failed but shouldn't be treated as an error
     *
     * @throws RuntimeException If the archive file doesn't exist
     */
    public function processArchive(string $archivePath): ?array
    {
        if (! File::exists($archivePath)) {
            throw new RuntimeException("Archive file not found: {$archivePath}");
        }

        return $this->statsService->extractGameStats($archivePath);
    }

    /**
     * Clean up old version downloads for all games
     */
    public function cleanupAllOldVersionDownloads(): int
    {
        $count = 0;
        $games = Game::has('gameVersions')->get();

        foreach ($games as $game) {
            $this->cleanupOldVersionDownloads($game->id);
            $count++;
        }

        return $count;
    }

    private function getStoragePath(int $gameId, int $versionId): string
    {
        return "games/{$gameId}/{$versionId}";
    }

    /**
     * Get the ItchHttpClientService instance
     *
     * @throws BindingResolutionException
     */
    private function getItchClient(): ItchHttpClientService
    {
        return App::make(ItchHttpClientService::class);
    }
}
