<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\ValueObjects\Upload;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AndroidBuildArchiveResolver
{
    public function __construct(
        private readonly GameArchiveService $archiveService,
    ) {}

    /**
     * @throws Exception
     */
    public function resolve(Game $game, GameVersion $version): string
    {
        $archivePath = $this->archiveService->getStoredArchive($game->id, $version->id);
        if ($archivePath) {
            return $archivePath;
        }

        Log::info('No local archive found, checking uploads in database', [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'game_name' => $game->name,
        ]);

        $uploads = $this->normalizeUploads($game->uploads ?? [], $game);
        if (empty($uploads)) {
            Log::error('No uploads found for game', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'game_url' => $game->url,
            ]);

            throw new Exception('No uploads found for this game. Please ensure the game has downloadable files on itch.io.');
        }

        $bestUpload = Upload::getBest($this->uploadObjects($uploads));
        if (! $bestUpload) {
            throw new Exception('Could not find a suitable upload for this game version. Please ensure the game has downloadable files on itch.io.');
        }

        Log::info('Selected best upload', [
            'upload_id' => $bestUpload->id,
            'filename' => $bestUpload->filename,
            'traits' => $bestUpload->traits,
            'is_linux' => $bestUpload->isLinux(),
            'is_windows' => $bestUpload->isWindows(),
            'is_mac' => $bestUpload->isMac(),
        ]);

        $result = $this->archiveService->downloadAndProcess(
            $game->url,
            $bestUpload->filename,
            $bestUpload->id,
            $game->id,
            $version->id
        );

        if (empty($result['archive'])) {
            throw new Exception('Failed to download and process the game archive.');
        }

        Log::info('Successfully downloaded and processed game archive', [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'archive_path' => $result['archive'],
        ]);

        return $result['archive'];
    }

    private function normalizeUploads(mixed $uploads, Game $game): array
    {
        Log::info('Raw uploads data from database', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'uploads_type' => gettype($uploads),
            'is_array' => is_array($uploads),
            'is_null' => is_null($uploads),
            'is_empty' => empty($uploads),
        ]);

        if (is_string($uploads) && $uploads !== '') {
            $decodedUploads = json_decode($uploads, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedUploads)) {
                $uploads = $decodedUploads;
                Log::info('Successfully decoded uploads JSON string', ['count' => count($uploads)]);
            } else {
                Log::warning('Failed to decode uploads JSON string', ['json_error' => json_last_error_msg()]);
            }
        } elseif (! is_array($uploads) && $uploads !== null) {
            $uploads = (array) $uploads;
            Log::info('Converted uploads to array', ['count' => count($uploads)]);
        }

        if (! is_array($uploads)) {
            return [];
        }

        Log::info('Available uploads for game', [
            'game_id' => $game->id,
            'uploads_count' => count($uploads),
            'uploads' => array_map(fn ($upload) => [
                'id' => $upload['id'] ?? 'unknown',
                'filename' => $upload['filename'] ?? 'unknown',
                'size' => $upload['size'] ?? 'unknown',
            ], $uploads),
        ]);

        return $uploads;
    }

    private function uploadObjects(array $uploads): Collection
    {
        $uploadObjects = collect();

        foreach ($uploads as $key => $upload) {
            $id = (! isset($upload['id']) || $upload['id'] === 'unknown') && is_numeric($key)
                ? (int) $key
                : ($upload['id'] ?? 0);

            $upload['updated_at'] = $upload['updated_at'] ?? date('Y-m-d H:i:s');

            try {
                $uploadObjects->push(Upload::fromArray($upload, $id));
            } catch (Exception $e) {
                Log::warning('Failed to create Upload object', [
                    'error' => $e->getMessage(),
                    'upload' => $upload,
                ]);
            }
        }

        return $uploadObjects;
    }
}
