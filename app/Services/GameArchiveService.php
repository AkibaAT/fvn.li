<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Service for handling game archive operations
 */
readonly class GameArchiveService
{
    public function __construct(
        private Client $client,
        private GameStatsService $statsService
    ) {}

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
     * Download and store a game archive
     *
     * @throws GuzzleException
     * @throws RuntimeException
     */
    public function downloadAndStore(
        string $gameUrl,
        string $filename,
        int $uploadId,
        int $gameId,
        int $versionId
    ): string {
        // Get the download URL
        $response = $this->client->post($gameUrl . '/file/' . $uploadId);
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
            // Download the file
            $this->client->get($downloadInfo['url'], [
                'sink' => $tempFile,
                'progress' => function ($downloadTotal, $downloadedBytes) {
                    if ($downloadTotal > 0) {
                        $progress = round(($downloadedBytes / $downloadTotal) * 100);
                        info("Download progress: {$progress}%");
                    }
                },
            ]);

            // Store the file
            $storagePath = $this->getStoragePath($gameId, $versionId);
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
     * Process statistics from an existing archive
     *
     * @throws RuntimeException
     */
    public function processArchive(string $archivePath): array
    {
        if (! File::exists($archivePath)) {
            throw new RuntimeException("Archive file not found: {$archivePath}");
        }

        return $this->statsService->extractGameStats($archivePath);
    }

    /**
     * Download, store, and process a game archive
     *
     * @throws GuzzleException
     * @throws RuntimeException
     */
    public function downloadAndProcess(
        string $gameUrl,
        string $filename,
        int $uploadId,
        int $gameId,
        int $versionId,
        bool $force = false
    ): array {
        // Check for existing archive unless forced
        if (! $force) {
            $existingArchive = $this->getStoredArchive($gameId, $versionId);
            if ($existingArchive) {
                return [
                    'archive' => $existingArchive,
                    'stats' => $this->processArchive($existingArchive),
                ];
            }
        }

        // Download and store the archive
        $archivePath = $this->downloadAndStore($gameUrl, $filename, $uploadId, $gameId, $versionId);

        // Process the archive
        return [
            'archive' => $archivePath,
            'stats' => $this->processArchive($archivePath),
        ];
    }

    private function getStoragePath(int $gameId, int $versionId): string
    {
        return "games/{$gameId}/{$versionId}";
    }
}
