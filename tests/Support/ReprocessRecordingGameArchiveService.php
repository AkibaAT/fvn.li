<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\GameArchiveService;

final class ReprocessRecordingGameArchiveService extends GameArchiveService
{
    public function __construct(
        private readonly object $recorder
    ) {}

    public function getStoredArchive(int $gameId, int $versionId): ?string
    {
        $this->recorder->getStoredArchiveCalls[] = [$gameId, $versionId];

        return $this->recorder->storedArchive;
    }

    public function processArchive(string $archivePath): ?array
    {
        $this->recorder->processArchiveCalls[] = [$archivePath];

        return $this->recorder->stats;
    }

    public function getLastProcessingError(): ?string
    {
        return $this->recorder->lastProcessingError ?? null;
    }
}
