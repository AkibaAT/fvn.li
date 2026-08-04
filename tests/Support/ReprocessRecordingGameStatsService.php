<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;
use App\Support\Stats\StatsPayload;

final class ReprocessRecordingGameStatsService extends GameStatsService
{
    public function __construct(
        private object $recorder
    ) {}

    public function saveVersionStats(
        GameVersion $version,
        StatsPayload|array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        $this->recorder->saveVersionStatsCalls[] = [
            'version_id' => $version->id,
            'stats' => $stats,
            'default_language' => $defaultLanguage,
            'game_id' => $game?->id,
        ];
    }
}
