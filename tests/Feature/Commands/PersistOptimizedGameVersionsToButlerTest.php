<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameArchiveOptimizationService;
use Tests\Support\RecordingButlerArchiveOptimizer;
use Tests\Support\RecordingDenKitStashPersistenceService;

test('persist optimized versions optimizes every selected version before pushing to butler in version order', function () {
    $game = Game::factory()->create([
        'name' => 'Butler Archive Target',
        'slug' => 'butler-archive-target',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    $newer = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '2.0',
        'published_at' => now(),
    ]);
    $older = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDay(),
    ]);

    $hidden = Game::factory()->create([
        'name' => 'Hidden Target',
        'game_engine' => "Ren'Py",
        'is_visible' => false,
    ]);
    GameVersion::factory()->create([
        'game_id' => $hidden->id,
        'version' => '1.0',
    ]);

    $optimizerRecorder = (object) ['calls' => []];
    $stashRecorder = (object) ['calls' => []];

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new RecordingButlerArchiveOptimizer($optimizerRecorder)
    );
    $this->app->instance(
        DenKitStashPersistenceService::class,
        new RecordingDenKitStashPersistenceService($stashRecorder)
    );

    $this->artisan('games:persist-optimized-versions', [
        '--game-id' => $game->id,
        '--channel' => 'linux',
    ])
        ->expectsOutputToContain('Found 1 game(s)')
        ->expectsOutputToContain('Persisted Butler Archive Target 1.0')
        ->expectsOutputToContain('Persisted Butler Archive Target 2.0')
        ->assertExitCode(0);

    expect($optimizerRecorder->calls)->toBe([
        [
            'game_id' => $game->id,
            'version_id' => $older->id,
            'dry_run' => false,
            'replace' => false,
            'force' => true,
            'validate' => true,
        ],
        [
            'game_id' => $game->id,
            'version_id' => $newer->id,
            'dry_run' => false,
            'replace' => false,
            'force' => true,
            'validate' => true,
        ],
    ]);

    expect($stashRecorder->calls)->toBe([
        [
            'game_id' => $game->id,
            'version_id' => $older->id,
            'archive_path' => "/tmp/optimized-{$older->id}.zip",
            'channel' => 'linux',
            'force' => false,
        ],
        [
            'game_id' => $game->id,
            'version_id' => $newer->id,
            'archive_path' => "/tmp/optimized-{$newer->id}.zip",
            'channel' => 'linux',
            'force' => false,
        ],
    ]);
});

test('persist optimized versions skips non optimized results without pushing originals', function () {
    $game = Game::factory()->create([
        'name' => 'Original Only Target',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    $optimizerRecorder = (object) [
        'calls' => [],
        'results' => [
            $version->id => ['status' => 'skipped', 'reason' => 'No stored archive found'],
        ],
    ];
    $stashRecorder = (object) ['calls' => []];

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new RecordingButlerArchiveOptimizer($optimizerRecorder)
    );
    $this->app->instance(
        DenKitStashPersistenceService::class,
        new RecordingDenKitStashPersistenceService($stashRecorder)
    );

    $this->artisan('games:persist-optimized-versions', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('Skipped Original Only Target 1.0: No stored archive found')
        ->assertExitCode(0);

    expect($stashRecorder->calls)->toBe([]);
});

test('persist optimized versions can target specific version ids', function () {
    $game = Game::factory()->create([
        'name' => 'Scoped Butler Target',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    $first = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDays(2),
    ]);
    $middle = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.1',
        'published_at' => now()->subDay(),
    ]);
    $last = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.2',
        'published_at' => now(),
    ]);

    $optimizerRecorder = (object) ['calls' => []];
    $stashRecorder = (object) ['calls' => []];

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new RecordingButlerArchiveOptimizer($optimizerRecorder)
    );
    $this->app->instance(
        DenKitStashPersistenceService::class,
        new RecordingDenKitStashPersistenceService($stashRecorder)
    );

    $this->artisan('games:persist-optimized-versions', [
        '--game-id' => $game->id,
        '--version-id' => [$last->id, $first->id],
    ])
        ->expectsOutputToContain('Persisted Scoped Butler Target 1.0')
        ->expectsOutputToContain('Persisted Scoped Butler Target 1.2')
        ->doesntExpectOutputToContain('Scoped Butler Target 1.1')
        ->assertExitCode(0);

    expect(array_column($optimizerRecorder->calls, 'version_id'))->toBe([
        $first->id,
        $last->id,
    ]);
    expect(array_column($stashRecorder->calls, 'version_id'))->toBe([
        $first->id,
        $last->id,
    ]);
});
