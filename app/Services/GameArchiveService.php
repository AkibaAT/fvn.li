<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Support\Stats\StatsPayload;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Service for handling game archive operations
 */
class GameArchiveService
{
    private ?string $lastProcessingError;
    private ?string $lastArchiveLookupError = null;

    public function __construct(
        private readonly GameStatsService $statsService
    ) {
        $this->lastProcessingError = null;
    }

    /**
     * Get the stored archive path for a game version
     */
    public function getStoredArchive(int $gameId, int $versionId): ?string
    {
        $this->lastArchiveLookupError = null;
        $storagePath = $this->getStoragePath($gameId, $versionId);

        $localFiles = array_values(array_filter(
            Storage::files($storagePath),
            fn (string $file): bool => $this->isStoredArchiveFile($file)
        ));

        if (! empty($localFiles)) {
            return Storage::path($localFiles[0]);
        }

        $game = Game::query()->find($gameId);
        $version = GameVersion::query()->where('game_id', $gameId)->find($versionId);

        if ($game && $version) {
            /** @var DenKitStashPersistenceService $stash */
            $stash = app(DenKitStashPersistenceService::class);

            try {
                $restored = $stash->restorePersistedArchive($game, $version, $storagePath);

                if (isset($restored['archive_path'])) {
                    return $restored['archive_path'];
                }

                $this->lastArchiveLookupError = $stash->getLastRestoreDiagnostic();
            } catch (Throwable $throwable) {
                $this->lastArchiveLookupError = $throwable->getMessage();
                Log::warning('Failed to restore game version archive from DenKit Stash', [
                    'game_id' => $gameId,
                    'version_id' => $versionId,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $files = array_values(array_filter(
            Storage::files($storagePath),
            fn (string $file): bool => $this->isStoredArchiveFile($file)
        ));

        if (! empty($files)) {
            return Storage::path($files[0]);
        }

        return null;
    }

    public function getLastArchiveLookupError(): ?string
    {
        return $this->lastArchiveLookupError;
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

        $this->sanitizeDownloadFilename($filename);

        $downloadRequest = $this->resolveItchDownloadRequest($gameUrl, $uploadId, $gameId);
        $downloadUrl = $downloadRequest['url'];

        Log::info('GameArchive: Download URL obtained', [
            'game_id' => $gameId,
            'cdn_url' => parse_url($downloadUrl, PHP_URL_HOST),
        ]);

        $tempDir = $this->createDownloadTempDirectory();

        try {
            $tempFile = $this->createDownloadTempFile($tempDir);

            Log::info('GameArchive: Starting download to temp', [
                'game_id' => $gameId,
                'temp_dir' => $tempDir,
                'temp_file' => $tempFile,
                'api_filename' => $filename,
            ]);

            $downloadClient = new Client([
                'timeout' => 600,  // 10 minutes for the full download
                'connect_timeout' => 30,  // 30 seconds to establish connection
                'allow_redirects' => false,
            ]);
            $downloadResponse = $downloadClient->get($downloadUrl, array_replace_recursive($downloadRequest['options'], [
                'sink' => $tempFile,
            ]));
            if ($downloadResponse->getStatusCode() !== 200) {
                throw new RuntimeException("Archive download returned unexpected HTTP status {$downloadResponse->getStatusCode()}");
            }
            $downloadFilename = $this->getDownloadFilename($downloadResponse, $filename);
            $namedTempFile = $this->tempPathForDownloadFilename($tempDir, $downloadFilename);
            File::move($tempFile, $namedTempFile);
            $tempFile = $namedTempFile;

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
        } catch (Throwable $throwable) {
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            throw $throwable;
        }
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

        $filename = $this->sanitizeDownloadFilename($filename);

        // Get storage path and prepare directory
        $storagePath = $this->getStoragePath($gameId, $versionId);

        Log::info('GameArchive: Moving file from temp to storage', [
            'game_id' => $gameId,
            'version_id' => $versionId,
            'storage_path' => $storagePath,
        ]);

        $finalPath = $this->storeFileAtomically($storagePath, $tempPath, $filename);
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
        bool $force = false,
        ?callable $progress = null
    ): string {
        Log::info('GameArchive: Getting download URL', [
            'game_id' => $gameId,
            'version_id' => $versionId,
            'upload_id' => $uploadId,
            'filename' => $filename,
        ]);

        $this->sanitizeDownloadFilename($filename);

        $downloadRequest = $this->resolveItchDownloadRequest($gameUrl, $uploadId, $gameId);
        $downloadUrl = $downloadRequest['url'];

        Log::info('GameArchive: Download URL obtained', [
            'game_id' => $gameId,
            'cdn_url' => parse_url($downloadUrl, PHP_URL_HOST),
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
                'allow_redirects' => false,
            ]);
            $downloadResponse = $downloadClient->get($downloadUrl, array_replace_recursive($downloadRequest['options'], [
                'sink' => $tempFile,
                'progress' => $progress,
            ]));
            if ($downloadResponse->getStatusCode() !== 200) {
                throw new RuntimeException("Archive download returned unexpected HTTP status {$downloadResponse->getStatusCode()}");
            }
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

            Log::info('GameArchive: Storing file', [
                'game_id' => $gameId,
                'storage_path' => $storagePath,
            ]);

            $finalPath = $this->storeFileAtomically($storagePath, $tempFile, $downloadFilename);
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
            $filename = $this->sanitizeDownloadFilename($filename);

            return Storage::exists("{$storagePath}/{$filename}");
        }

        // If no specific filename, check if directory has any completed archive files.
        return collect(Storage::files($storagePath))
            ->contains(fn (string $file): bool => $this->isStoredArchiveFile($file));
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
                    ->orderBy('published_at', 'desc')
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
     * @return StatsPayload|null Stats reader, or null if extraction failed but shouldn't be treated as an error
     *
     * @throws RuntimeException If the archive file doesn't exist
     */
    public function processArchive(string $archivePath): ?StatsPayload
    {
        $this->lastProcessingError = null;
        if (! File::exists($archivePath)) {
            throw new RuntimeException("Archive file not found: {$archivePath}");
        }

        $metadata = $this->readArchiveMetadata($archivePath);
        $stats = $this->statsService->extractGameStats($archivePath);

        if ($stats !== null && app(ArchiveMetadataReader::class)->isOptimized($metadata)) {
            $stats = $stats->withoutFileStatistics();
            Log::info('GameArchive: Skipping file statistics from optimized archive', [
                'archive_path' => $archivePath,
                'original_archive' => $metadata['original_archive']['filename'] ?? null,
            ]);
        }

        if ($stats === null) {
            $this->lastProcessingError = $this->statsService->getLastExtractionError();
        }

        return $stats;
    }

    public function getLastProcessingError(): ?string
    {
        return $this->lastProcessingError;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readArchiveMetadata(string $archivePath): ?array
    {
        return app(ArchiveMetadataReader::class)->read($archivePath);
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

    public function storeFileAtomically(string $storagePath, string $sourcePath, string $filename): string
    {
        Storage::makeDirectory($storagePath);

        $partFilename = sprintf('.%s.part.%s', $filename, bin2hex(random_bytes(6)));
        $partStoragePath = "{$storagePath}/{$partFilename}";
        $finalPath = Storage::path("{$storagePath}/{$filename}");

        try {
            Storage::putFileAs($storagePath, $sourcePath, $partFilename);
            File::move(Storage::path($partStoragePath), $finalPath);
        } catch (Throwable $throwable) {
            Storage::delete($partStoragePath);

            throw $throwable;
        }

        return $finalPath;
    }

    /**
     * Resolve an itch.io CDN URL for a specific upload.
     *
     * Free name-your-own-price games may reject the legacy direct /file/{id}
     * request until a session has visited the generated download page.
     *
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws BindingResolutionException
     */
    private function resolveItchDownloadUrl(string $gameUrl, int $uploadId, int $gameId): string
    {
        return app(ItchDownloadUrlResolver::class)->resolve($gameUrl, $uploadId, $gameId);
    }

    /**
     * @return array{url: string, options: array<string, mixed>}
     */
    private function resolveItchDownloadRequest(string $gameUrl, int $uploadId, int $gameId): array
    {
        return app(ItchDownloadUrlResolver::class)->resolveRequest($gameUrl, $uploadId, $gameId);
    }

    /**
     * @param  array<string, mixed>  $postData
     * @return array{status?: int, headers?: array<string, mixed>, cookies?: array<int, mixed>, userAgent?: string|null, response?: string}
     *
     * @throws Exception
     */
    private function flareSolverrDownloadRequest(
        FlareSolverrClient $flareSolverr,
        string $method,
        string $url,
        array $postData = [],
        ?CookieJar $cookieJar = null
    ): array {
        return app(ItchDownloadUrlResolver::class)
            ->flareSolverrDownloadRequest($flareSolverr, $method, $url, $postData, $cookieJar);
    }

    private function createBrowserSessionHttpClient(CookieJar $cookieJar, ?string $userAgent): Client
    {
        return app(ItchDownloadUrlResolver::class)->createBrowserSessionHttpClient($cookieJar, $userAgent);
    }

    private function uploadDownloadEndpoint(string $gameUrl, int $uploadId): string
    {
        return app(ItchDownloadUrlResolver::class)->uploadDownloadEndpoint($gameUrl, $uploadId);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDownloadInfo(string $body): array
    {
        return app(ItchDownloadUrlResolver::class)->decodeDownloadInfo($body);
    }

    private function extractCsrfToken(string $html): ?string
    {
        return app(ItchDownloadUrlResolver::class)->extractCsrfToken($html);
    }

    private function extractDownloadUrlEndpoint(string $html): ?string
    {
        return app(ItchDownloadUrlResolver::class)->extractDownloadUrlEndpoint($html);
    }

    private function validateItchControlUrl(string $url, string $gameUrl, string $description): string
    {
        return app(ItchDownloadUrlResolver::class)->validateItchControlUrl($url, $gameUrl, $description);
    }

    private function validateItchFileDownloadUrl(string $url, string $gameUrl, string $description): string
    {
        return app(ItchDownloadUrlResolver::class)->validateItchFileDownloadUrl($url, $gameUrl, $description);
    }

    /**
     * @return array<string, string>
     */
    private function jsonRequestHeaders(string $referer): array
    {
        return app(ItchDownloadUrlResolver::class)->jsonRequestHeaders($referer);
    }

    /**
     * @param  array<string, mixed>  $downloadInfo
     */
    private function downloadUrlErrorMessage(string $message, array $downloadInfo): string
    {
        return app(ItchDownloadUrlResolver::class)->downloadUrlErrorMessage($message, $downloadInfo);
    }

    private function getStoragePath(int $gameId, int $versionId): string
    {
        return "games/{$gameId}/{$versionId}";
    }

    private function isStoredArchiveFile(string $file): bool
    {
        return ! str_starts_with(basename($file), '.');
    }

    private function createDownloadTempDirectory(): string
    {
        return app(ArchiveDownloadPathService::class)->createTempDirectory();
    }

    private function createDownloadTempFile(string $tempDir): string
    {
        return app(ArchiveDownloadPathService::class)->createTempFile($tempDir);
    }

    private function tempPathForDownloadFilename(string $tempDir, string $filename): string
    {
        return app(ArchiveDownloadPathService::class)->tempPathForFilename($tempDir, $filename);
    }

    private function ensurePathIsInsideDirectory(string $path, string $directory): void
    {
        app(ArchiveDownloadPathService::class)->ensurePathIsInsideDirectory($path, $directory);
    }

    private function getDownloadFilename(ResponseInterface $response, string $fallbackFilename): string
    {
        return app(ArchiveDownloadPathService::class)->getDownloadFilename($response, $fallbackFilename);
    }

    private function sanitizeDownloadFilename(string $filename): string
    {
        return app(ArchiveDownloadPathService::class)->sanitizeFilename($filename);
    }
}
