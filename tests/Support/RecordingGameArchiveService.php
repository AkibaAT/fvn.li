<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\GameArchiveService;

final class RecordingGameArchiveService extends GameArchiveService
{
    public function __construct(
        private readonly object $recorder
    ) {}

    public function getStoredArchive(int $gameId, int $versionId): ?string
    {
        $this->recorder->getStoredArchiveCalls[] = [$gameId, $versionId];

        return $this->recorder->storedArchive;
    }

    public function downloadAndStore(
        string $gameUrl,
        string $filename,
        int $uploadId,
        int $gameId,
        int $versionId,
        bool $force = false,
        ?callable $progress = null
    ): string {
        $this->recorder->downloadAndStoreCalls[] = [
            $gameUrl,
            $filename,
            $uploadId,
            $gameId,
            $versionId,
            $force,
        ];

        return "/tmp/{$filename}";
    }
}
