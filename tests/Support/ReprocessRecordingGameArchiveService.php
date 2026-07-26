<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\GameArchiveService;
use App\Support\Stats\ArrayStatsPayload;
use App\Support\Stats\StatsPayload;

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

    public function processArchive(string $archivePath): ?StatsPayload
    {
        $this->recorder->processArchiveCalls[] = [$archivePath];

        $stats = $this->recorder->stats;

        return $stats === null ? null : new ArrayStatsPayload($stats);
    }

    public function getLastProcessingError(): ?string
    {
        return $this->recorder->lastProcessingError ?? null;
    }
}
