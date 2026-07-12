<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DenKitStashPersistenceService;

final class RecordingDenKitStashPersistenceService extends DenKitStashPersistenceService
{
    public function __construct(private object $recorder) {}

    public function persistOptimizedArchive(
        Game $game,
        GameVersion $version,
        string $archivePath,
        string $channel = 'main',
        bool $force = false
    ): array {
        $this->recorder->calls[] = [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'archive_path' => $archivePath,
            'channel' => $channel,
            'force' => $force,
        ];

        return [
            'status' => 'persisted',
            'target' => 'fvn-li/' . $game->slug . ':' . $channel,
            'channel' => $channel,
            'build_id' => 99 + count($this->recorder->calls),
        ];
    }
}
