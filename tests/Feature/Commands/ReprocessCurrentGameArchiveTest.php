<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use App\Services\GameVersionArchiveRepositoryService;
use App\Support\Stats\StatsPayload;
use Tests\Support\ReprocessRecordingGameArchiveService;
use Tests\Support\ReprocessRecordingGameStatsService;

beforeEach(function () {
    $this->repositoryRecorder = (object) [
        'persistStoredArchiveCalls' => [],
        'result' => [
            'status' => 'persisted',
            'target' => 'fvn-li/current-archive:main',
            'build_id' => 456,
        ],
    ];

    $repository = Mockery::mock(GameVersionArchiveRepositoryService::class);
    $repository->shouldReceive('persistStoredArchive')
        ->byDefault()
        ->andReturnUsing(function (Game $game, GameVersion $version, bool $force = false): array {
            $this->repositoryRecorder->persistStoredArchiveCalls[] = [$game->id, $version->id, $force];

            return $this->repositoryRecorder->result;
        });

    $this->app->instance(GameVersionArchiveRepositoryService::class, $repository);
});

test('reprocess current game archive imports stats from stored archive for latest version only', function () {
    $game = Game::factory()->create([
        'name' => 'Current Archive',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDay(),
        'is_latest' => false,
    ]);
    $currentVersion = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.1',
        'published_at' => now(),
    ]);

    $archiveRecorder = (object) [
        'storedArchive' => '/tmp/current-archive.zip',
        'stats' => ['languages' => []],
        'getStoredArchiveCalls' => [],
        'processArchiveCalls' => [],
    ];
    $statsRecorder = (object) [
        'saveVersionStatsCalls' => [],
    ];

    $this->app->instance(GameArchiveService::class, new ReprocessRecordingGameArchiveService($archiveRecorder));
    $this->app->instance(GameStatsService::class, new ReprocessRecordingGameStatsService($statsRecorder));

    $this->artisan('games:reprocess-current-archive', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('Imported stats for current version 1.1')
        ->assertExitCode(0);

    expect($archiveRecorder->getStoredArchiveCalls)->toBe([
        [$game->id, $currentVersion->id],
    ]);
    expect($archiveRecorder->processArchiveCalls)->toBe([
        ['/tmp/current-archive.zip'],
    ]);
    expect($statsRecorder->saveVersionStatsCalls)->toHaveCount(1);
    expect($statsRecorder->saveVersionStatsCalls[0]['version_id'])->toBe($currentVersion->id);
    expect($statsRecorder->saveVersionStatsCalls[0]['stats'])->toBeInstanceOf(StatsPayload::class);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([
        [$game->id, $currentVersion->id, true],
    ]);
});

test('reprocess current game archive skips when current version has no stored archive', function () {
    $game = Game::factory()->create([
        'name' => 'No Archive',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    $currentVersion = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    $archiveRecorder = (object) [
        'storedArchive' => null,
        'stats' => ['languages' => []],
        'getStoredArchiveCalls' => [],
        'processArchiveCalls' => [],
    ];
    $statsRecorder = (object) [
        'saveVersionStatsCalls' => [],
    ];

    $this->app->instance(GameArchiveService::class, new ReprocessRecordingGameArchiveService($archiveRecorder));
    $this->app->instance(GameStatsService::class, new ReprocessRecordingGameStatsService($statsRecorder));

    $this->artisan('games:reprocess-current-archive', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('No stored archive found for current version 1.0')
        ->assertExitCode(0);

    expect($archiveRecorder->getStoredArchiveCalls)->toBe([
        [$game->id, $currentVersion->id],
    ]);
    expect($archiveRecorder->processArchiveCalls)->toBe([]);
    expect($statsRecorder->saveVersionStatsCalls)->toBe([]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([]);
});

test('reprocess current game archive reports stats extraction reason when archive yields no stats', function () {
    $game = Game::factory()->create([
        'name' => 'Broken Archive',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    $currentVersion = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '2.0',
    ]);

    $archiveRecorder = (object) [
        'storedArchive' => '/tmp/broken-archive.zip',
        'stats' => null,
        'lastProcessingError' => 'Analyzer container failed: Failed to open zip archive: 35',
        'getStoredArchiveCalls' => [],
        'processArchiveCalls' => [],
    ];
    $statsRecorder = (object) [
        'saveVersionStatsCalls' => [],
    ];

    $this->app->instance(GameArchiveService::class, new ReprocessRecordingGameArchiveService($archiveRecorder));
    $this->app->instance(GameStatsService::class, new ReprocessRecordingGameStatsService($statsRecorder));

    $this->artisan('games:reprocess-current-archive', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('No stats could be extracted from current version 2.0')
        ->expectsOutputToContain('Stats extraction reason: Analyzer container failed: Failed to open zip archive: 35')
        ->assertExitCode(0);

    expect($archiveRecorder->getStoredArchiveCalls)->toBe([
        [$game->id, $currentVersion->id],
    ]);
    expect($archiveRecorder->processArchiveCalls)->toBe([
        ['/tmp/broken-archive.zip'],
    ]);
    expect($statsRecorder->saveVersionStatsCalls)->toBe([]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([]);
});
