<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\GameArchiveOptimizationService;

final class RecordingButlerArchiveOptimizer extends GameArchiveOptimizationService
{
    public function __construct(private object $recorder) {}

    public function optimizeStoredArchive(
        int $gameId,
        int $versionId,
        bool $dryRun = true,
        bool $replace = false,
        bool $force = false,
        bool $validate = true,
        ?callable $progress = null
    ): array {
        $this->recorder->calls[] = [
            'game_id' => $gameId,
            'version_id' => $versionId,
            'dry_run' => $dryRun,
            'replace' => $replace,
            'force' => $force,
            'validate' => $validate,
        ];

        return $this->recorder->results[$versionId] ?? [
            'status' => 'optimized',
            'optimized_path' => "/tmp/optimized-{$versionId}.zip",
            'saved_bytes' => 1,
        ];
    }
}
