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

        try {
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

            Log::info('Persisted game version archive to DenKit Stash', [
                'game_id' => $game->id,
                'version_id' => $version->id,
                'status' => $result['status'],
                'target' => $result['target'] ?? null,
                'channel' => $result['channel'] ?? null,
                'build_id' => $result['build_id'] ?? null,
            ]);

            return $result;
        } finally {
            // Runs on every exit, including the early return above and any throw.
            $this->discardLocalArchive($game, $version);
        }
    }

    /**
     * Remove the staged local copy of a version's archive.
     *
     * Local storage is a staging area for the optimizer and a landing spot for
     * restores. Archives live in the stash, and are re-fetchable from the source
     * when they do not, so no local copy outlives the run that created it.
     *
     * @return bool Whether a local copy was removed.
     */
    public function discardLocalArchive(Game $game, GameVersion $version): bool
    {
        $storagePath = "games/{$game->id}/{$version->id}";

        if (! Storage::exists($storagePath)) {
            return false;
        }

        Storage::deleteDirectory($storagePath);

        Log::info('Discarded local game version archive', [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'path' => $storagePath,
        ]);

        return true;
    }
}
