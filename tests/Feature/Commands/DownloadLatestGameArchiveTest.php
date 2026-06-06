<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameVersionArchiveRepositoryService;

beforeEach(function () {
    $this->repositoryRecorder = (object) [
        'persistStoredArchiveCalls' => [],
        'result' => [
            'status' => 'persisted',
            'target' => 'fvn-li/archive-test:main',
            'build_id' => 123,
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

test('download latest game archive stores best processable upload for selected game', function () {
    $game = Game::factory()->create([
        'name' => 'Archive Test',
        'url' => ['itch_io' => 'https://creator.itch.io/archive-test'],
        'uploads' => [
            10 => [
                'filename' => 'archive-test-web.zip',
                'display_name' => 'Web build',
                'md5_hash' => 'web',
                'updated_at' => '2026-04-01 00:00:00',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => '2.0',
                'traits' => [],
                'type' => 'html',
            ],
            20 => [
                'filename' => 'archive-test-win.zip',
                'display_name' => 'Windows',
                'md5_hash' => 'win',
                'updated_at' => '2026-04-02 00:00:00',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => '2.0',
                'traits' => ['p_windows'],
                'type' => 'default',
            ],
            30 => [
                'filename' => 'archive-test-linux.tar.bz2',
                'display_name' => 'Linux',
                'md5_hash' => 'linux',
                'updated_at' => '2026-04-03 00:00:00',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => '2.0',
                'traits' => ['p_linux'],
                'type' => 'default',
            ],
        ],
    ]);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '2.0',
    ]);

    $recorder = (object) [
        'storedArchive' => null,
        'getStoredArchiveCalls' => [],
        'downloadAndStoreCalls' => [],
    ];
    $archiveService = new RecordingGameArchiveService($recorder);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:download-latest', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('Stored archive for version 2.0')
        ->assertExitCode(0);

    expect($recorder->getStoredArchiveCalls)->toBe([
        [$game->id, $version->id],
    ]);
    expect($recorder->downloadAndStoreCalls)->toBe([
        [
            'https://creator.itch.io/archive-test',
            'archive-test-linux.tar.bz2',
            30,
            $game->id,
            $version->id,
            false,
        ],
    ]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([
        [$game->id, $version->id, false],
    ]);
});

test('download latest game archive prefers newer upload when version compare would rank older file higher', function () {
    $game = Game::factory()->create([
        'name' => 'Version Shape Test',
        'url' => ['itch_io' => 'https://creator.itch.io/version-shape-test'],
        'uploads' => [
            16052873 => [
                'filename' => 'game-0.25-pc.zip',
                'display_name' => 'Windows and Linux',
                'md5_hash' => 'old',
                'updated_at' => '2026-01-06 05:28:44',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => null,
                'traits' => ['p_linux'],
                'type' => 'default',
            ],
            16624241 => [
                'filename' => 'game-0.5.5-pc.zip',
                'display_name' => 'Windows and Linux',
                'md5_hash' => 'new',
                'updated_at' => '2026-02-27 07:03:42',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => null,
                'traits' => ['p_windows', 'p_linux'],
                'type' => 'default',
            ],
        ],
    ]);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '0.5.5',
    ]);

    $recorder = (object) [
        'storedArchive' => null,
        'getStoredArchiveCalls' => [],
        'downloadAndStoreCalls' => [],
    ];
    $archiveService = new RecordingGameArchiveService($recorder);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:download-latest', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('Selected upload from database file ID 16624241')
        ->assertExitCode(0);

    expect($recorder->getStoredArchiveCalls)->toBe([
        [$game->id, $version->id],
    ]);
    expect($recorder->downloadAndStoreCalls)->toBe([
        [
            'https://creator.itch.io/version-shape-test',
            'game-0.5.5-pc.zip',
            16624241,
            $game->id,
            $version->id,
            false,
        ],
    ]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([
        [$game->id, $version->id, false],
    ]);
});

test('download latest game archive treats near timestamps as same release and prefers linux upload', function () {
    $game = Game::factory()->create([
        'name' => 'Release Batch Test',
        'url' => ['itch_io' => 'https://creator.itch.io/release-batch-test'],
        'uploads' => [
            100 => [
                'filename' => 'release-batch-linux.zip',
                'display_name' => 'Linux',
                'md5_hash' => 'linux',
                'updated_at' => '2026-04-01 10:00:00',
                'build_id' => null,
                'build_updated_at' => '2026-04-01 10:00:00',
                'user_version' => '1.1.4',
                'traits' => ['p_linux'],
                'type' => 'default',
            ],
            200 => [
                'filename' => 'release-batch-win.zip',
                'display_name' => 'Windows',
                'md5_hash' => 'win',
                'updated_at' => '2026-04-01 10:00:31',
                'build_id' => null,
                'build_updated_at' => '2026-04-01 10:00:31',
                'user_version' => '1.1.4',
                'traits' => ['p_windows'],
                'type' => 'default',
            ],
        ],
    ]);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.1.4',
    ]);

    $recorder = (object) [
        'storedArchive' => null,
        'getStoredArchiveCalls' => [],
        'downloadAndStoreCalls' => [],
    ];
    $archiveService = new RecordingGameArchiveService($recorder);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:download-latest', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('Selected upload from database file ID 100')
        ->assertExitCode(0);

    expect($recorder->getStoredArchiveCalls)->toBe([
        [$game->id, $version->id],
    ]);
    expect($recorder->downloadAndStoreCalls)->toBe([
        [
            'https://creator.itch.io/release-batch-test',
            'release-batch-linux.zip',
            100,
            $game->id,
            $version->id,
            false,
        ],
    ]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([
        [$game->id, $version->id, false],
    ]);
});

test('download latest game archive redownloads existing archive when forced', function () {
    $game = Game::factory()->create([
        'name' => 'Already Stored',
        'url' => ['itch_io' => 'https://creator.itch.io/already-stored'],
        'uploads' => [
            20 => [
                'filename' => 'already-stored.zip',
                'display_name' => 'Windows',
                'md5_hash' => 'win',
                'updated_at' => '2026-04-02 00:00:00',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => '1.0',
                'traits' => ['p_windows'],
                'type' => 'default',
            ],
        ],
    ]);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    $recorder = (object) [
        'storedArchive' => '/tmp/already-stored.zip',
        'getStoredArchiveCalls' => [],
        'downloadAndStoreCalls' => [],
    ];
    $archiveService = new RecordingGameArchiveService($recorder);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:download-latest', [
        '--game-id' => $game->id,
        '--force' => true,
    ])
        ->expectsOutputToContain('Selected upload from database file ID 20')
        ->expectsOutputToContain('Stored archive for version 1.0')
        ->assertExitCode(0);

    expect($recorder->getStoredArchiveCalls)->toBe([
        [$game->id, $version->id],
    ]);
    expect($recorder->downloadAndStoreCalls)->toBe([
        [
            'https://creator.itch.io/already-stored',
            'already-stored.zip',
            20,
            $game->id,
            $version->id,
            true,
        ],
    ]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([
        [$game->id, $version->id, true],
    ]);
});

test('download latest game archive does not select non renpy games', function () {
    $game = Game::factory()->create([
        'name' => 'Unity Game',
        'game_engine' => 'Unity',
        'uploads' => [
            20 => [
                'filename' => 'unity-game.zip',
                'display_name' => 'Windows',
                'md5_hash' => 'win',
                'updated_at' => '2026-04-02 00:00:00',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => '1.0',
                'traits' => ['p_windows'],
                'type' => 'default',
            ],
        ],
    ]);
    GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    $recorder = (object) [
        'storedArchive' => null,
        'getStoredArchiveCalls' => [],
        'downloadAndStoreCalls' => [],
    ];
    $archiveService = new RecordingGameArchiveService($recorder);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:download-latest', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain("No visible Ren'Py itch.io games found matching the selection criteria")
        ->assertExitCode(1);

    expect($recorder->getStoredArchiveCalls)->toBe([]);
    expect($recorder->downloadAndStoreCalls)->toBe([]);
    expect($this->repositoryRecorder->persistStoredArchiveCalls)->toBe([]);
});

test('download latest game archive shows current game progress', function () {
    $game = Game::factory()->create([
        'name' => 'Progress Game',
        'uploads' => [
            20 => [
                'filename' => 'progress-game.zip',
                'display_name' => 'Windows',
                'md5_hash' => 'win',
                'updated_at' => '2026-04-02 00:00:00',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => '1.0',
                'traits' => ['p_windows'],
                'type' => 'default',
            ],
        ],
    ]);
    GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    $recorder = (object) [
        'storedArchive' => null,
        'getStoredArchiveCalls' => [],
        'downloadAndStoreCalls' => [],
    ];
    $archiveService = new RecordingGameArchiveService($recorder);
    $this->app->instance(GameArchiveService::class, $archiveService);

    $this->artisan('games:download-latest', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain("Game 1/1: {$game->name} (ID: {$game->id})")
        ->expectsOutputToContain('Selected upload from database file ID 20')
        ->assertExitCode(0);
});

// phpcs:disable
class RecordingGameArchiveService extends GameArchiveService
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
// phpcs:enable
