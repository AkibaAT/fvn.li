<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GameVersionArchiveRepositoryService
{
    public function __construct(
        private readonly GameArchiveOptimizationService $optimizer,
        private readonly DenKitStashPersistenceService $stash
    ) {}

    /**
     * @return array{status: string, reason?: string, target?: string, channel?: string, build_id?: int|null}
     */
    public function persistStoredArchive(Game $game, GameVersion $version, bool $force = false): array
    {
        if (! $this->stash->isEnabled()) {
            return ['status' => 'skipped', 'reason' => 'DenKit Stash is not configured'];
        }

        $optimization = $this->optimizer->optimizeStoredArchive(
            $game->id,
            $version->id,
            dryRun: false,
            replace: false,
            force: true,
            validate: true
        );

        if ($optimization['status'] !== 'optimized' || ! isset($optimization['optimized_path'])) {
            return [
                'status' => 'skipped',
                'reason' => $optimization['reason'] ?? 'archive was not optimized',
            ];
        }

        $result = $this->stash->persistOptimizedArchive(
            $game,
            $version,
            $optimization['optimized_path'],
            $this->stash->defaultChannel(),
            $force
        );

        if ($this->stash->shouldDeleteLocalAfterPush()) {
            Storage::deleteDirectory("games/{$game->id}/{$version->id}");
        }

        Log::info('Persisted game version archive to DenKit Stash', [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'status' => $result['status'],
            'target' => $result['target'] ?? null,
            'channel' => $result['channel'] ?? null,
            'build_id' => $result['build_id'] ?? null,
        ]);

        return $result;
    }
}
