<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameArchiveOptimizationService;
use App\Services\GameVersionArchiveRepositoryService;

test('version archive repository optimizes and persists stored archives to default stash channel', function () {
    $game = Game::factory()->create([
        'name' => 'Repository Target',
        'slug' => 'repository-target',
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);
    $recorder = (object) [
        'optimizerCalls' => [],
        'stashCalls' => [],
    ];

    $service = new GameVersionArchiveRepositoryService(
        new class($recorder) extends GameArchiveOptimizationService
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
                $this->recorder->optimizerCalls[] = [
                    'gameId' => $gameId,
                    'versionId' => $versionId,
                    'dryRun' => $dryRun,
                    'replace' => $replace,
                    'force' => $force,
                    'validate' => $validate,
                ];

                return [
                    'status' => 'optimized',
                    'optimized_path' => "/tmp/optimized-{$versionId}.zip",
                ];
            }
        },
        new class($recorder) extends DenKitStashPersistenceService
        {
            public function __construct(private object $recorder) {}

            public function isAutoPersistEnabled(): bool
            {
                return true;
            }

            public function persistOptimizedArchive(
                Game $game,
                GameVersion $version,
                string $archivePath,
                string $channel = 'main',
                bool $force = false
            ): array {
                $this->recorder->stashCalls[] = [
                    'game_id' => $game->id,
                    'version_id' => $version->id,
                    'archive_path' => $archivePath,
                    'channel' => $channel,
                    'force' => $force,
                ];

                return [
                    'status' => 'persisted',
                    'target' => "fvn-li/{$game->slug}:{$channel}",
                    'channel' => $channel,
                    'build_id' => 123,
                ];
            }
        }
    );

    expect($service->persistStoredArchive($game, $version, true))->toMatchArray([
        'status' => 'persisted',
        'channel' => 'main',
        'build_id' => 123,
    ]);

    expect($recorder->optimizerCalls)->toBe([
        [
            'gameId' => $game->id,
            'versionId' => $version->id,
            'dryRun' => false,
            'replace' => false,
            'force' => true,
            'validate' => true,
        ],
    ]);
    expect($recorder->stashCalls)->toBe([
        [
            'game_id' => $game->id,
            'version_id' => $version->id,
            'archive_path' => "/tmp/optimized-{$version->id}.zip",
            'channel' => 'main',
            'force' => true,
        ],
    ]);
});
