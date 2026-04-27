<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
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
        Log::info('GameArchive: Starting download and process', [
            'game_id' => $gameId,
            'version_id' => $versionId,
        ]);

        // Download and store the archive (force parameter is now passed through)
        $archivePath = $this->downloadAndStore($gameUrl, $filename, $uploadId, $gameId, $versionId, $force);

        // Clean up old version downloads for this game
        Log::info('GameArchive: Cleaning up old downloads', ['game_id' => $gameId]);
        $this->cleanupOldVersionDownloads($gameId, $versionId);

        // Process the archive - stats may be null if extraction failed but shouldn't be treated as an error
        Log::info('GameArchive: Starting archive processing', [
            'game_id' => $gameId,
            'archive_path' => $archivePath,
        ]);
        $stats = $this->processArchive($archivePath);
        Log::info('GameArchive: Archive processing completed', [
            'game_id' => $gameId,
            'has_stats' => $stats !== null,
        ]);

        // Delete the archive after processing if requested
        if ($deleteAfterProcessing && File::exists($archivePath)) {
            try {
                File::delete($archivePath);
                $archivePath = null;
            } catch (Exception $e) {
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
     * Download and process a game archive to a temporary location
     * This is used when we need to get the stats before creating the version record
     *
     * @return array{temp_path: string, temp_dir: string, stats: array|null, filename: string, upload_id: int}
     *
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws BindingResolutionException
     */
    public function downloadAndProcessToTemp(
        string $gameUrl,
        string $filename,
        int $uploadId,
        int $gameId
    ): array {
        Log::info('GameArchive: Starting download to temp', [
            'game_id' => $gameId,
            'upload_id' => $uploadId,
            'filename' => $filename,
        ]);

        // Get the ItchHttpClientService for itch.io requests
        $itchClient = $this->getItchClient();

        // Get the download URL
        $response = $itchClient->post($gameUrl.'/file/'.$uploadId);
        $downloadInfo = json_decode($response->getBody()->getContents(), true);

        if (! isset($downloadInfo['url'])) {
            throw new RuntimeException('Could not get download URL');
        }

        Log::info('GameArchive: Download URL obtained', [
            'game_id' => $gameId,
            'cdn_url' => parse_url($downloadInfo['url'], PHP_URL_HOST),
        ]);

        $tempDir = sys_get_temp_dir().'/game_'.uniqid();
        if (! File::makeDirectory($tempDir, 0755, true)) {
            throw new RuntimeException('Could not create temporary directory');
        }

        $tempFile = $tempDir.'/download';

        Log::info('GameArchive: Starting download to temp', [
            'game_id' => $gameId,
            'temp_dir' => $tempDir,
            'temp_file' => $tempFile,
            'api_filename' => $filename,
        ]);

        $downloadClient = new Client([
            'timeout' => 600,  // 10 minutes for the full download
            'connect_timeout' => 30,  // 30 seconds to establish connection
        ]);
        $downloadResponse = $downloadClient->get($downloadInfo['url'], [
            'sink' => $tempFile,
        ]);
        $downloadFilename = $this->getDownloadFilename($downloadResponse, $filename);
        $namedTempFile = $tempDir.'/'.$downloadFilename;
        if ($namedTempFile !== $tempFile) {
            File::move($tempFile, $namedTempFile);
            $tempFile = $namedTempFile;
        }

        $fileSize = File::exists($tempFile) ? File::size($tempFile) : 0;
        Log::info('GameArchive: Download to temp completed', [
            'game_id' => $gameId,
            'filename' => $downloadFilename,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
        ]);

        // Process the archive to get stats
        Log::info('GameArchive: Starting archive processing from temp', [
            'game_id' => $gameId,
            'archive_path' => $tempFile,
        ]);
        $stats = $this->processArchive($tempFile);
        Log::info('GameArchive: Archive processing from temp completed', [
            'game_id' => $gameId,
            'has_stats' => $stats !== null,
        ]);

        return [
            'temp_path' => $tempFile,
            'temp_dir' => $tempDir,  // Return this so we can clean up the whole directory
            'stats' => $stats,
            'filename' => $downloadFilename,
            'upload_id' => $uploadId,
        ];
    }

    /**
     * Move a downloaded archive from temp to final storage location
     * This is called after the version record is created
     *
     * @throws RuntimeException
     */
    public function moveFromTempToStorage(
        string $tempPath,
        string $filename,
        int $gameId,
        int $versionId,
        bool $deleteTemp = true
    ): string {
        if (! File::exists($tempPath)) {
            throw new RuntimeException("Temp file not found: {$tempPath}");
        }

        // Get storage path and prepare directory
        $storagePath = $this->getStoragePath($gameId, $versionId);

        Log::info('GameArchive: Moving file from temp to storage', [
            'game_id' => $gameId,
            'version_id' => $versionId,
            'storage_path' => $storagePath,
        ]);

        // Ensure directory exists and store file
        Storage::makeDirectory($storagePath);
        Storage::putFileAs($storagePath, $tempPath, $filename);

        $finalPath = Storage::path("{$storagePath}/{$filename}");
        Log::info('GameArchive: File moved successfully', [
            'game_id' => $gameId,
            'version_id' => $versionId,
            'final_path' => $finalPath,
        ]);

        // Clean up temp file if requested
        if ($deleteTemp && File::exists($tempPath)) {
            File::delete($tempPath);
        }

        return $finalPath;
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
        Log::info('GameArchive: Getting download URL', [
            'game_id' => $gameId,
            'version_id' => $versionId,
            'upload_id' => $uploadId,
            'filename' => $filename,
        ]);

        // Get the ItchHttpClientService for itch.io requests
        $itchClient = $this->getItchClient();

        // Get the download URL
        $response = $itchClient->post($gameUrl.'/file/'.$uploadId);
        $downloadInfo = json_decode($response->getBody()->getContents(), true);

        if (! isset($downloadInfo['url'])) {
            throw new RuntimeException('Could not get download URL');
        }

        Log::info('GameArchive: Download URL obtained', [
            'game_id' => $gameId,
            'cdn_url' => parse_url($downloadInfo['url'], PHP_URL_HOST),
        ]);

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'game_');
        if ($tempFile === false) {
            throw new RuntimeException('Could not create temporary file');
        }

        try {
            Log::info('GameArchive: Starting download', [
                'game_id' => $gameId,
                'temp_file' => $tempFile,
            ]);

            // Create a new client for downloading the file
            // The download URL is typically from a CDN, not itch.io directly
            // Use longer timeouts for potentially large game downloads (10 minutes)
            $downloadClient = new Client([
                'timeout' => 600,  // 10 minutes for the full download
                'connect_timeout' => 30,  // 30 seconds to establish connection
            ]);
            $downloadResponse = $downloadClient->get($downloadInfo['url'], [
                'sink' => $tempFile,
            ]);
            $downloadFilename = $this->getDownloadFilename($downloadResponse, $filename);

            $fileSize = File::exists($tempFile) ? File::size($tempFile) : 0;
            Log::info('GameArchive: Download completed', [
                'game_id' => $gameId,
                'filename' => $downloadFilename,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            ]);

            // Get storage path and prepare directory
            $storagePath = $this->getStoragePath($gameId, $versionId);

            // If force is true or the file exists but with a different name,
            // clear the directory first
            if ($force || ($this->archiveExists($gameId, $versionId) &&
                    ! $this->archiveExists($gameId, $versionId, $downloadFilename))) {
                // Delete all files in the directory
                foreach (Storage::files($storagePath) as $file) {
                    Storage::delete($file);
                }
            }

            // Ensure directory exists and store file
            Log::info('GameArchive: Storing file', [
                'game_id' => $gameId,
                'storage_path' => $storagePath,
            ]);

            Storage::makeDirectory($storagePath);
            Storage::putFileAs($storagePath, $tempFile, $downloadFilename);

            $finalPath = Storage::path("{$storagePath}/{$downloadFilename}");
            Log::info('GameArchive: File stored successfully', [
                'game_id' => $gameId,
                'final_path' => $finalPath,
            ]);

            return $finalPath;

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

    private function getDownloadFilename(ResponseInterface $response, string $fallbackFilename): string
    {
        $contentDisposition = $response->getHeaderLine('Content-Disposition');
        if (preg_match('/filename\\*=UTF-8\'\'([^;]+)/i', $contentDisposition, $matches)) {
            return $this->sanitizeDownloadFilename(rawurldecode(trim($matches[1], " \t\"'")));
        }

        if (preg_match('/filename="([^"]+)"/i', $contentDisposition, $matches) ||
            preg_match('/filename=([^;]+)/i', $contentDisposition, $matches)) {
            return $this->sanitizeDownloadFilename(trim($matches[1], " \t\"'"));
        }

        return $this->sanitizeDownloadFilename($fallbackFilename);
    }

    private function sanitizeDownloadFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));

        return $filename !== '' ? $filename : 'archive';
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
