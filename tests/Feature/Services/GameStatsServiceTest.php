<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameStatsService;
use App\Services\RenpyStatsSandboxClient;
use App\Support\Stats\ArrayStatsPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function invokeGameStatsServiceMethod(GameStatsService $service, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($service);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($service, $arguments);
}

it('stores a processed archive and removes the temporary source file', function () {
    Storage::fake();

    $tempFile = tempnam(sys_get_temp_dir(), 'stats-archive-');
    file_put_contents($tempFile, 'archive-content');

    app(GameStatsService::class)->storeProcessedFile($tempFile, 'game.zip', 42, 99);

    Storage::assertExists('games/42/99/game.zip');
    expect(File::exists($tempFile))->toBeFalse()
        ->and(Storage::get('games/42/99/game.zip'))->toBe('archive-content');
});

it('throws when the temporary processed file is missing', function () {
    app(GameStatsService::class)->storeProcessedFile('/tmp/fvn-missing-archive.zip', 'game.zip', 42, 99);
})->throws(RuntimeException::class, 'Temporary file no longer exists');

it('rejects unsafe processed archive filenames without deleting the temporary source', function () {
    Storage::fake();

    $tempFile = tempnam(sys_get_temp_dir(), 'stats-archive-');
    file_put_contents($tempFile, 'archive-content');

    try {
        expect(fn () => app(GameStatsService::class)->storeProcessedFile($tempFile, '../target.zip', 42, 99))
            ->toThrow(RuntimeException::class, 'path separators')
            ->and(File::exists($tempFile))->toBeTrue()
            ->and(Storage::exists('games/42/target.zip'))->toBeFalse();
    } finally {
        @unlink($tempFile);
    }
});

it('persists route graph labels edges menu choices variables and variable changes', function () {
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $version->forceFill([
        'route_graph_data' => ['stale' => true],
        'route_graph_unreachable_data' => ['stale' => true],
    ])->save();

    $service = app(GameStatsService::class);

    invokeGameStatsServiceMethod($service, 'saveRouteGraph', [$version, [
        'route_labels' => [
            [
                'name' => 'start',
                'file' => 'script.rpy',
                'line' => 1,
                'returns_to_caller' => true,
            ],
        ],
        'route_edges' => [
            [
                'from_label' => 'start',
                'to_label' => 'good_end',
                'edge_type' => 'jump',
                'condition' => 'points > 3',
                'file' => 'script.rpy',
                'line' => 5,
            ],
        ],
        'route_menu_choices' => [
            [
                'from_label' => 'start',
                'prompt' => 'Choose',
                'prompt_translations' => ['en' => 'Choose'],
                'menu_line' => 6,
                'text' => 'Go',
                'translations' => ['en' => 'Go'],
                'condition' => 'available',
                'enclosing_condition' => 'seen_intro',
                'choice_condition' => 'points > 3',
                'menu_branch' => 'good',
                'menu_condition_stack' => ['seen_intro'],
                'parent_menu_line' => 6,
                'parent_choice_line' => 7,
                'target_label' => 'good_end',
                'edge_type' => 'menu',
                'file' => 'script.rpy',
                'line' => 7,
            ],
        ],
        'route_variables' => [
            ['name' => 'points', 'default_value' => '0', 'type' => 'default', 'file' => 'script.rpy', 'line' => 2],
            ['name' => 'points', 'default_value' => '1', 'type' => 'define', 'file' => 'script.rpy', 'line' => 3],
        ],
        'route_variable_changes' => [
            [
                'label' => 'start',
                'variable' => 'points',
                'operation' => '+=',
                'value' => '1',
                'file' => 'script.rpy',
                'line' => 8,
                'context' => 'choice',
                'condition' => 'available',
                'condition_stack' => ['available'],
            ],
        ],
    ]]);

    expect($version->fresh()->route_graph_data)->toBeNull()
        ->and($version->fresh()->route_graph_unreachable_data)->toBeNull()
        ->and(DB::table('version_route_labels')->where('game_version_id', $version->id)->count())->toBe(1)
        ->and(DB::table('version_route_edges')->where('game_version_id', $version->id)->value('condition'))->toBe('points > 3')
        ->and(DB::table('version_route_menu_choices')->where('game_version_id', $version->id)->value('target_label'))->toBe('good_end')
        ->and(DB::table('version_route_variables')->where('game_version_id', $version->id)->count())->toBe(1)
        ->and(DB::table('version_route_variable_changes')->where('game_version_id', $version->id)->value('operation'))->toBe('+=');
});

it('normalizes ordinary text but strips excessive combining marks', function () {
    $service = app(GameStatsService::class);

    $ordinary = "Cafe\u{0301}";
    $zalgo = "a\u{0301}\u{0302}\u{0303}\u{0304}\u{0305}\u{0306}\u{0307}\u{0308}\u{0309}\u{030A}";

    expect(invokeGameStatsServiceMethod($service, 'processText', [$ordinary]))->toBe("Caf\u{00E9}")
        ->and(invokeGameStatsServiceMethod($service, 'isZalgo', [$ordinary]))->toBeFalse()
        ->and(invokeGameStatsServiceMethod($service, 'isZalgo', [$zalgo]))->toBeTrue()
        ->and(invokeGameStatsServiceMethod($service, 'processText', [$zalgo]))->toBe('a');
});

it('bounds zalgo detection memory for very long combining mark text', function () {
    $service = app(GameStatsService::class);
    $zalgo = 'a' . str_repeat("\u{0301}", 100000);

    $before = memory_get_usage(true);
    $processed = invokeGameStatsServiceMethod($service, 'processText', [$zalgo]);
    $after = memory_get_usage(true);

    expect($processed)->toBe('a')
        ->and($after - $before)->toBeLessThan(8 * 1024 * 1024);
});

it('truncates oversized ordinary dialogue text before storage processing', function () {
    $service = app(GameStatsService::class);
    $text = str_repeat('a', 70000);

    $processed = invokeGameStatsServiceMethod($service, 'processText', [$text]);

    expect(strlen($processed))->toBe(65536);
});

it('creates new characters with all relevant display languages and preserves existing names', function () {
    $game = Game::factory()->create();
    $service = app(GameStatsService::class);

    $newCharacter = invokeGameStatsServiceMethod($service, 'createCharacter', [
        $game->id,
        'akira',
        ['jpn'],
        'deu',
    ]);

    expect($newCharacter)->toBeInstanceOf(Character::class)
        ->and($newCharacter->display_names)->toMatchArray([
            'jpn' => 'akira',
            'eng' => 'akira',
            'deu' => 'akira',
        ]);

    $newCharacter->forceFill(['display_names' => ['eng' => 'Akira']])->save();

    $existing = invokeGameStatsServiceMethod($service, 'createCharacter', [
        $game->id,
        'akira',
        ['jpn'],
        'eng',
    ]);

    expect($existing->id)->toBe($newCharacter->id)
        ->and($existing->display_names)->toMatchArray([
            'eng' => 'Akira',
            'jpn' => 'akira',
        ]);
});

it('detects archive formats from file signatures and extracts zip archives', function () {
    $service = app(GameStatsService::class);
    $workDir = storage_path('framework/testing/game-stats-' . uniqid());
    File::makeDirectory($workDir, 0755, true);

    try {
        $zipPath = "{$workDir}/game.bin";
        $zip = new ZipArchive;
        expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('game/script.rpy', 'label start:');
        $zip->close();

        $extractPath = "{$workDir}/extract";
        File::makeDirectory($extractPath, 0755, true);

        expect(invokeGameStatsServiceMethod($service, 'detectArchiveFormat', [$zipPath]))->toBe('zip');

        invokeGameStatsServiceMethod($service, 'extractArchive', [$zipPath, $extractPath]);

        expect(File::exists("{$extractPath}/game/script.rpy"))->toBeTrue()
            ->and(invokeGameStatsServiceMethod($service, 'findGameDirectory', [$extractPath]))->toBe($extractPath);

        $unknownPath = "{$workDir}/unknown.dat";
        file_put_contents($unknownPath, 'not-an-archive');
        expect(invokeGameStatsServiceMethod($service, 'detectArchiveFormat', [$unknownPath]))->toBe('dat');
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('extracts game stats from a zipped RenPy game through the public sandbox workflow', function () {
    $workDir = storage_path('framework/testing/game-stats-public-' . uniqid());
    File::makeDirectory($workDir, 0755, true);

    try {
        $archivePath = "{$workDir}/native-game.zip";
        $markerPath = "{$workDir}/native-executed";
        $zip = new ZipArchive;
        expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('RenpyGame/game/script.rpy', 'label start: return');
        $zip->addFromString('RenpyGame/runner.sh', "#!/bin/sh\ntouch " . escapeshellarg($markerPath) . "\n");
        $zip->close();

        $sandboxClient = Mockery::mock(RenpyStatsSandboxClient::class);
        $sandboxClient->shouldReceive('extract')
            ->once()
            ->with($archivePath)
            ->andReturn(new ArrayStatsPayload([
                'languages' => [
                    'eng' => [
                        'blocks' => 7,
                        'words' => 21,
                    ],
                ],
            ]));

        $service = new GameStatsService(sandboxClient: $sandboxClient);

        expect($service->extractGameStats($archivePath)?->languages())->toBe([
            'eng' => ['blocks' => 7, 'words' => 21],
        ])->and(File::exists($markerPath))->toBeFalse();
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('returns null from local trusted archive extraction when no game directory or sdk is available', function () {
    config(['services.renpy.analysis_mode' => 'local_trusted']);

    $service = app(GameStatsService::class);
    $workDir = storage_path('framework/testing/game-stats-public-null-' . uniqid());
    File::makeDirectory($workDir, 0755, true);

    try {
        $emptyArchive = "{$workDir}/empty.zip";
        $zip = new ZipArchive;
        expect($zip->open($emptyArchive, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('readme.txt', 'no game here');
        $zip->close();

        expect($service->extractGameStats($emptyArchive))->toBeNull();

        $renpyArchive = "{$workDir}/renpy.zip";
        $zip = new ZipArchive;
        expect($zip->open($renpyArchive, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('game/script.rpy', 'label start: return');
        $zip->close();

        config(['services.renpy.sdk_path' => null]);

        expect($service->extractGameStats($renpyArchive))->toBeNull();
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('local trusted archive extraction ignores game-provided launchers and uses the sdk only', function () {
    config(['services.renpy.analysis_mode' => 'local_trusted']);

    $service = app(GameStatsService::class);
    $workDir = storage_path('framework/testing/game-stats-local-sdk-' . uniqid());
    $sdkDir = storage_path('framework/testing/renpy-sdk-' . uniqid());
    File::makeDirectory($workDir, 0755, true);
    File::makeDirectory($sdkDir, 0755, true);

    try {
        $archivePath = "{$workDir}/game.zip";
        $markerPath = "{$workDir}/launcher-executed";
        $zip = new ZipArchive;
        expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('RenpyGame/game/script.rpy', 'label start: return');
        $zip->addFromString('RenpyGame/runner.sh', "#!/bin/sh\ntouch " . escapeshellarg($markerPath) . "\n");
        $zip->close();

        $renpy = "{$sdkDir}/renpy.sh";
        file_put_contents($renpy, "#!/bin/sh\ncat > stats.ndjson <<'NDJSON'\n{\"type\":\"meta\",\"schema\":\"fvn.renpy_stats.v1\"}\n{\"type\":\"languages\",\"key\":\"eng\",\"entry\":{\"blocks\":1,\"words\":2}}\nNDJSON\n");
        chmod($renpy, 0755);
        config(['services.renpy.sdk_path' => $sdkDir]);

        expect($service->extractGameStats($archivePath)?->languages())->toBe([
            'eng' => ['blocks' => 1, 'words' => 2, 'characters' => []],
        ])->and(File::exists($markerPath))->toBeFalse();
    } finally {
        File::deleteDirectory($workDir);
        File::deleteDirectory($sdkDir);
    }
});

it('extracts game stats with a configured sdk and reports invalid sdk output', function () {
    $service = app(GameStatsService::class);
    $workDir = storage_path('framework/testing/game-stats-sdk-' . uniqid());
    $sdkDir = storage_path('framework/testing/renpy-sdk-' . uniqid());
    File::makeDirectory("{$workDir}/game", 0755, true);
    File::makeDirectory($sdkDir, 0755, true);

    try {
        $renpy = "{$sdkDir}/renpy.sh";
        file_put_contents($renpy, "#!/bin/sh\ncat > stats.ndjson <<'NDJSON'\n{\"type\":\"meta\",\"schema\":\"fvn.renpy_stats.v1\"}\n{\"type\":\"languages\",\"key\":\"eng\",\"entry\":{\"blocks\":3,\"words\":4}}\nNDJSON\n");
        chmod($renpy, 0755);

        expect(invokeGameStatsServiceMethod($service, 'extractStatsWithSdk', [$workDir, $sdkDir])?->languages())->toBe([
            'eng' => [
                'blocks' => 3,
                'words' => 4,
                'characters' => [],
            ],
        ]);

        File::delete("{$workDir}/stats.ndjson");
        file_put_contents($renpy, "#!/bin/sh\ncat > stats.ndjson <<'NDJSON'\n{\"type\":\"meta\",\"schema\":\"fvn.renpy_stats.v1\"}\nNDJSON\n");

        invokeGameStatsServiceMethod($service, 'extractStatsWithSdk', [$workDir, $sdkDir]);
    } finally {
        File::deleteDirectory($workDir);
        File::deleteDirectory($sdkDir);
    }
})->throws(RuntimeException::class, 'Invalid stats file format');
