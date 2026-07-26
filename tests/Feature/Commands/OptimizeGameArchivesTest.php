<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveOptimizationService;
use App\Services\GameStatsService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

test('optimize game archives converts loose images and updates rpy references in dry run', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Asset Optimize']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    createOptimizableArchive($game->id, $version->id, 'asset-optimize.zip');

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new GameArchiveOptimizationService(passingArchiveOptimizationStatsService())
    );

    $this->artisan('games:optimize-archives', [
        '--game-id' => $game->id,
        '--dry-run' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('Would optimize Asset Optimize 1.0')
        ->assertExitCode(0);

    Storage::assertExists("games/{$game->id}/{$version->id}/asset-optimize.zip");
    Storage::assertMissing("games/{$game->id}/{$version->id}/asset-optimize.optimized.zip");
});

test('optimize game archives stores optimized archive and preserves original unless replace is requested', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Asset Store']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '2.0',
    ]);

    createOptimizableArchive($game->id, $version->id, 'asset-store.zip');

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new GameArchiveOptimizationService(passingArchiveOptimizationStatsService())
    );

    $this->artisan('games:optimize-archives', [
        '--game-id' => $game->id,
        '--force' => true,
    ])
        ->expectsOutputToContain('Optimized Asset Store 2.0')
        ->assertExitCode(0);

    Storage::assertExists("games/{$game->id}/{$version->id}/asset-store.zip");
    Storage::assertExists("games/{$game->id}/{$version->id}/asset-store.optimized.zip");

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("games/{$game->id}/{$version->id}/asset-store.optimized.zip")))->toBeTrue();

    try {
        expect($zip->locateName('game/images/bg.webp'))->not->toBeFalse()
            ->and($zip->locateName('game/images/bg.png'))->toBeFalse();

        $script = $zip->getFromName('game/script.rpy');
        expect($script)->toContain('images/bg.webp')
            ->not->toContain('images/bg.png');
    } finally {
        $zip->close();
    }
});

test('optimize game archives preserves original zip folder structure', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Nested Asset Store']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '2.1',
    ]);

    createOptimizableArchive($game->id, $version->id, 'nested-asset.zip', 'NestedGame');

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new GameArchiveOptimizationService(passingArchiveOptimizationStatsService())
    );

    $this->artisan('games:optimize-archives', [
        '--game-id' => $game->id,
        '--force' => true,
    ])->assertExitCode(0);

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("games/{$game->id}/{$version->id}/nested-asset.optimized.zip")))->toBeTrue();

    try {
        expect($zip->locateName('NestedGame/game/images/bg.webp'))->not->toBeFalse()
            ->and($zip->locateName('NestedGame/game/images/bg.png'))->toBeFalse()
            ->and($zip->locateName('game/images/bg.webp'))->toBeFalse();

        $script = $zip->getFromName('NestedGame/game/script.rpy');
        expect($script)->toContain('images/bg.webp')
            ->not->toContain('images/bg.png');

        $metadata = json_decode($zip->getFromName('.fvn-archive-metadata.json'), true);
        expect($metadata['schema'])->toBe('fvn.archive_optimization.v1')
            ->and($metadata['original_archive']['filename'])->toBe('nested-asset.zip')
            ->and($metadata['original_archive']['sha256'])->toMatch('/^[a-f0-9]{64}$/');

        $originalFiles = collect($metadata['original_files']);
        expect($originalFiles->pluck('path')->all())->toContain('NestedGame/game/images/bg.png')
            ->and($originalFiles->firstWhere('path', 'NestedGame/game/images/bg.png')['sha256'])->toMatch('/^[a-f0-9]{64}$/')
            ->and($originalFiles->pluck('path')->all())->not->toContain('NestedGame/game/images/bg.webp');
    } finally {
        $zip->close();
    }
});

test('optimize game archives preserves original tar gzip archive format', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Tar Asset Store']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '2.2',
    ]);

    createOptimizableTarArchive($game->id, $version->id, 'tar-asset.tar.gz', 'TarGame');

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new GameArchiveOptimizationService(passingArchiveOptimizationStatsService())
    );

    $this->artisan('games:optimize-archives', [
        '--game-id' => $game->id,
        '--force' => true,
    ])->assertExitCode(0);

    Storage::assertExists("games/{$game->id}/{$version->id}/tar-asset.tar.gz");
    Storage::assertExists("games/{$game->id}/{$version->id}/tar-asset.optimized.tar.gz");
    Storage::assertMissing("games/{$game->id}/{$version->id}/tar-asset.optimized.zip");

    $list = new Process([
        'tar',
        '-tzf',
        Storage::path("games/{$game->id}/{$version->id}/tar-asset.optimized.tar.gz"),
    ]);
    $list->mustRun();

    expect($list->getOutput())->toContain('TarGame/game/images/bg.webp')
        ->not->toContain('TarGame/game/images/bg.png')
        ->toContain('TarGame/game/script.rpy');
});

test('optimize game archives reuses previous optimized media when source inventory is unchanged', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Inventory Reuse']);
    $older = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDay(),
    ]);
    $newer = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.1',
        'published_at' => now(),
    ]);

    createAudioArchive($game->id, $older->id, 'older-audio.zip', str_repeat('same source audio', 20));
    createAudioArchive($game->id, $newer->id, 'newer-audio.zip', str_repeat('same source audio', 20));

    $oldPath = getenv('PATH') ?: '';
    $binDir = sys_get_temp_dir() . '/fake-ffmpeg-' . bin2hex(random_bytes(4));
    $callLog = $binDir . '/ffmpeg.log';
    mkdir($binDir);
    $fakeFfmpeg = $binDir . '/ffmpeg';
    file_put_contents($fakeFfmpeg, <<<'SH'
#!/bin/sh
target=""
for arg do
  target="$arg"
done
printf '%s\n' "$target" >> "$FFMPEG_CALL_LOG"
printf 'optimized:%s\n' "$FFMPEG_MARKER" > "$target"
SH);
    chmod($fakeFfmpeg, 0755);
    putenv('PATH=' . $binDir . PATH_SEPARATOR . $oldPath);
    putenv('FFMPEG_CALL_LOG=' . $callLog);
    $_SERVER['PATH'] = $binDir . PATH_SEPARATOR . $oldPath;
    $_ENV['PATH'] = $binDir . PATH_SEPARATOR . $oldPath;
    $_SERVER['FFMPEG_CALL_LOG'] = $callLog;
    $_ENV['FFMPEG_CALL_LOG'] = $callLog;

    try {
        putenv('FFMPEG_MARKER=older');
        $_SERVER['FFMPEG_MARKER'] = 'older';
        $_ENV['FFMPEG_MARKER'] = 'older';
        $olderResult = (new GameArchiveOptimizationService(passingArchiveOptimizationStatsService()))
            ->optimizeStoredArchive($game->id, $older->id, dryRun: false, force: true);

        expect($olderResult['status'])->toBe('optimized')
            ->and($olderResult['audio_optimized'])->toBe(1)
            ->and($olderResult['audio_reused'])->toBe(0);

        File::put($callLog, '');
        putenv('FFMPEG_MARKER=newer');
        $_SERVER['FFMPEG_MARKER'] = 'newer';
        $_ENV['FFMPEG_MARKER'] = 'newer';
        $newerResult = (new GameArchiveOptimizationService(passingArchiveOptimizationStatsService()))
            ->optimizeStoredArchive($game->id, $newer->id, dryRun: false, force: true);

        expect($newerResult['status'])->toBe('optimized')
            ->and($newerResult['audio_optimized'])->toBe(1)
            ->and($newerResult['audio_reused'])->toBe(1)
            ->and(trim(File::get($callLog)))->toBe('');
    } finally {
        putenv('PATH=' . $oldPath);
        putenv('FFMPEG_CALL_LOG');
        putenv('FFMPEG_MARKER');
        $_SERVER['PATH'] = $oldPath;
        $_ENV['PATH'] = $oldPath;
        unset($_SERVER['FFMPEG_CALL_LOG'], $_ENV['FFMPEG_CALL_LOG'], $_SERVER['FFMPEG_MARKER'], $_ENV['FFMPEG_MARKER']);
        @unlink($fakeFfmpeg);
        @unlink($callLog);
        @rmdir($binDir);
    }

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("games/{$game->id}/{$newer->id}/newer-audio.optimized.zip")))->toBeTrue();

    try {
        expect($zip->getFromName('game/music/theme.ogg'))->toBe("optimized:older\n")
            ->and($zip->getFromName('game/script.rpy'))->toContain('music/theme.ogg')
            ->not->toContain('music/theme.mp3');

        $metadata = json_decode($zip->getFromName('.fvn-archive-metadata.json'), true);
        expect(collect($metadata['original_files'])->firstWhere('path', 'game/music/theme.mp3')['sha256'])->toMatch('/^[a-f0-9]{64}$/')
            ->and(collect($metadata['optimized_files'])->pluck('path')->all())->toContain('game/music/theme.ogg')
            ->and($metadata['media_replacements'])->toBe(['music/theme.mp3' => 'music/theme.ogg']);
    } finally {
        $zip->close();
    }
});

test('previous optimized archive metadata cannot reuse paths outside extracted archive', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Traversal Reuse']);
    $older = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDay(),
    ]);
    $newer = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.1',
        'published_at' => now(),
    ]);
    $audioContents = str_repeat('same source audio', 20);
    $secret = "SERVER-LOCAL-SECRET:butler-token=abc123\n";
    $secretPath = storage_path('app/leak-secret.txt');

    File::put($secretPath, $secret);
    createAudioArchive($game->id, $older->id, 'seed.zip', $audioContents);
    createMaliciousOptimizedAudioArchive($game->id, $older->id, 'seed.optimized.zip', $audioContents);
    createAudioArchive($game->id, $newer->id, 'newer-audio.zip', $audioContents);

    $oldPath = getenv('PATH') ?: '';
    $binDir = sys_get_temp_dir() . '/fake-ffmpeg-' . bin2hex(random_bytes(4));
    mkdir($binDir);
    $fakeFfmpeg = $binDir . '/ffmpeg';
    file_put_contents($fakeFfmpeg, <<<'SH'
#!/bin/sh
target=""
for arg do
  target="$arg"
done
printf 'optimized:newer\n' > "$target"
SH);
    chmod($fakeFfmpeg, 0755);
    putenv('PATH=' . $binDir . PATH_SEPARATOR . $oldPath);
    $_SERVER['PATH'] = $binDir . PATH_SEPARATOR . $oldPath;
    $_ENV['PATH'] = $binDir . PATH_SEPARATOR . $oldPath;

    try {
        $result = (new GameArchiveOptimizationService(passingArchiveOptimizationStatsService()))
            ->optimizeStoredArchive($game->id, $newer->id, dryRun: false, force: true);
    } finally {
        putenv('PATH=' . $oldPath);
        $_SERVER['PATH'] = $oldPath;
        $_ENV['PATH'] = $oldPath;
        @unlink($fakeFfmpeg);
        @rmdir($binDir);
        File::delete($secretPath);
    }

    expect($result['status'])->toBe('optimized')
        ->and($result['audio_optimized'])->toBe(1)
        ->and($result['audio_reused'])->toBe(0);

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("games/{$game->id}/{$newer->id}/newer-audio.optimized.zip")))->toBeTrue();

    try {
        expect($zip->getFromName('game/music/theme.ogg'))->toBe("optimized:newer\n")
            ->not->toBe($secret);
    } finally {
        $zip->close();
    }
});

test('tar archive repacking treats option-like top level entries as filenames', function () {
    $sourceDir = sys_get_temp_dir() . '/archive_tar_option_source_' . bin2hex(random_bytes(4));
    $targetPath = sys_get_temp_dir() . '/archive_tar_option_target_' . bin2hex(random_bytes(4)) . '.tar';
    $sentinelName = 'PWNED_BY_TAR_OPTION_TEST_' . bin2hex(random_bytes(4));
    $sentinelPath = base_path($sentinelName);

    File::makeDirectory($sourceDir . '/GoodGame/game', 0755, true);
    File::put($sourceDir . '/GoodGame/game/script.rpy', "label start:\n    return\n");
    File::put($sourceDir . '/--checkpoint=1', '');
    File::put($sourceDir . '/--checkpoint-action=exec=touch ' . $sentinelName, '');

    try {
        $service = new GameArchiveOptimizationService(passingArchiveOptimizationStatsService());
        $method = new ReflectionMethod($service, 'createArchiveFromDirectory');
        $method->invoke($service, $sourceDir, $targetPath, 'malicious-original.tar');

        expect(File::exists($sentinelPath))->toBeFalse();

        $list = new Process(['tar', '-tf', $targetPath]);
        $list->mustRun();

        expect($list->getOutput())->toContain('--checkpoint=1')
            ->toContain('--checkpoint-action=exec=touch ' . $sentinelName)
            ->toContain('GoodGame/game/script.rpy');
    } finally {
        File::deleteDirectory($sourceDir);
        File::delete($targetPath);
        File::delete($sentinelPath);
    }
});

test('optimize game archives skips archives with symlinked game directories', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Symlinked Game Dir']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '4.0',
    ]);

    $outsideDir = createSymlinkedGameDirectoryArchive($game->id, $version->id, 'symlinked-game.tar');

    try {
        $result = (new GameArchiveOptimizationService(passingArchiveOptimizationStatsService()))
            ->optimizeStoredArchive($game->id, $version->id, dryRun: false, force: true);
        $outsideScriptExistsAfter = File::exists($outsideDir . '/script.rpy');
    } finally {
        File::deleteDirectory($outsideDir);
    }

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('Archive contains linked or special content and cannot be optimized safely')
        ->and($outsideScriptExistsAfter)->toBeTrue();
});

test('optimize game archives skips archives with symlinked media output paths', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Symlinked Media Output']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '4.1',
    ]);

    [$victimPath, $originalContents] = createSymlinkedMediaOutputArchive($game->id, $version->id, 'symlinked-output.tar');

    try {
        $result = (new GameArchiveOptimizationService(passingArchiveOptimizationStatsService()))
            ->optimizeStoredArchive($game->id, $version->id, dryRun: false, force: true);
        $victimContentsAfter = File::get($victimPath);
    } finally {
        File::delete($victimPath);
    }

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('Archive contains linked or special content and cannot be optimized safely')
        ->and($victimContentsAfter)->toBe($originalContents);
});

test('optimize game archives skips rpyc files that fail to decompile', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Partial RPYC']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '3.0',
    ]);

    createArchiveWithMissingRpySources($game->id, $version->id, 'partial-rpyc.zip');

    $oldPath = getenv('PATH') ?: '';
    $binDir = sys_get_temp_dir() . '/fake-unrpyc-' . bin2hex(random_bytes(4));
    mkdir($binDir);
    $fakeUnrpyc = $binDir . '/unrpyc';
    $fakeDecompiler = <<<'SH'
#!/bin/sh
for arg do
  target="$arg"
done
case "$target" in
  *bad.rpyc)
    echo "fake decompile failure" >&2
    exit 1
    ;;
esac
printf 'image recovered = "images/bg.png"\n' > "${target%c}"
SH;
    file_put_contents($fakeUnrpyc, $fakeDecompiler);
    chmod($fakeUnrpyc, 0755);
    putenv('PATH=' . $binDir . PATH_SEPARATOR . $oldPath);
    $_SERVER['PATH'] = $binDir . PATH_SEPARATOR . $oldPath;
    $_ENV['PATH'] = $binDir . PATH_SEPARATOR . $oldPath;

    try {
        $progress = [];
        $result = (new GameArchiveOptimizationService(passingArchiveOptimizationStatsService()))
            ->optimizeStoredArchive(
                $game->id,
                $version->id,
                dryRun: false,
                force: true,
                progress: function (string $message) use (&$progress): void {
                    $progress[] = $message;
                }
            );

        expect($result['status'])->toBe('optimized')
            ->and($result['rpyc_decompile_failed'])->toBe(1)
            ->and($progress)->toContain('Skipped decompiling bad.rpyc: fake decompile failure');
    } finally {
        putenv('PATH=' . $oldPath);
        $_SERVER['PATH'] = $oldPath;
        $_ENV['PATH'] = $oldPath;
        @unlink($fakeUnrpyc);
        @rmdir($binDir);
    }

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("games/{$game->id}/{$version->id}/partial-rpyc.optimized.zip")))->toBeTrue();

    try {
        expect($zip->locateName('game/good.rpy'))->not->toBeFalse()
            ->and($zip->locateName('game/bad.rpy'))->toBeFalse()
            ->and($zip->locateName('game/compiled_python.rpy'))->toBeFalse()
            ->and($zip->locateName('game/compiled_python_ren.py'))->not->toBeFalse();

        $script = $zip->getFromName('game/good.rpy');
        expect($script)->toContain('images/bg.webp')
            ->not->toContain('images/bg.png');
    } finally {
        $zip->close();
    }
});

test('optimize game archives only selects visible renpy games', function () {
    Storage::fake('local');

    $visibleRenpy = Game::factory()->create([
        'name' => 'Visible RenPy Archive Target',
        'is_visible' => true,
        'game_engine' => "Ren'Py",
    ]);
    $hiddenRenpy = Game::factory()->create([
        'name' => 'Hidden RenPy Archive Target',
        'is_visible' => false,
        'game_engine' => "Ren'Py",
    ]);
    $visibleUnity = Game::factory()->create([
        'name' => 'Visible Unity Archive Target',
        'is_visible' => true,
        'game_engine' => 'Unity',
    ]);

    foreach ([$visibleRenpy, $hiddenRenpy, $visibleUnity] as $game) {
        $version = GameVersion::factory()->latest()->create([
            'game_id' => $game->id,
            'version' => '1.0',
        ]);

        createOptimizableArchive($game->id, $version->id, "asset-{$game->id}.zip");
    }

    $this->app->instance(
        GameArchiveOptimizationService::class,
        new GameArchiveOptimizationService(passingArchiveOptimizationStatsService())
    );

    $this->artisan('games:optimize-archives', [
        '--all' => true,
        '--dry-run' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('Found 1 game(s)')
        ->expectsOutputToContain('Visible RenPy Archive Target')
        ->doesntExpectOutputToContain('Hidden RenPy Archive Target')
        ->doesntExpectOutputToContain('Visible Unity Archive Target')
        ->assertExitCode(0);
});

function createOptimizableArchive(int $gameId, int $versionId, string $filename, ?string $rootFolder = null): void
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $imagePath = tempnam(sys_get_temp_dir(), 'archive_image_');
    $image = imagecreatetruecolor(512, 512);
    mt_srand(1234);
    for ($y = 0; $y < 512; $y++) {
        for ($x = 0; $x < 512; $x++) {
            imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
    }
    imagepng($image, $imagePath);
    imagedestroy($image);

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("{$storagePath}/{$filename}"), ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    try {
        $prefix = $rootFolder === null ? '' : trim($rootFolder, '/') . '/';
        $zip->addFromString($prefix . 'game/script.rpy', "image bg = \"images/bg.png\"\nlabel start:\n    scene bg\n");
        $zip->addFile($imagePath, $prefix . 'game/images/bg.png');
    } finally {
        $zip->close();
        unlink($imagePath);
    }
}

function createOptimizableTarArchive(int $gameId, int $versionId, string $filename, string $rootFolder): void
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $sourceDir = sys_get_temp_dir() . '/archive_tar_source_' . bin2hex(random_bytes(4));
    $gameDir = $sourceDir . '/' . $rootFolder . '/game';
    mkdir($gameDir . '/images', 0755, true);

    $imagePath = tempnam(sys_get_temp_dir(), 'archive_image_');
    $image = imagecreatetruecolor(512, 512);
    mt_srand(5678);
    for ($y = 0; $y < 512; $y++) {
        for ($x = 0; $x < 512; $x++) {
            imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
    }
    imagepng($image, $imagePath);
    imagedestroy($image);

    copy($imagePath, $gameDir . '/images/bg.png');
    file_put_contents($gameDir . '/script.rpy', "image bg = \"images/bg.png\"\nlabel start:\n    scene bg\n");

    try {
        $process = new Process([
            'tar',
            '-czf',
            Storage::path("{$storagePath}/{$filename}"),
            '-C',
            $sourceDir,
            '.',
        ]);
        $process->mustRun();
    } finally {
        unlink($imagePath);
        File::deleteDirectory($sourceDir);
    }
}

function createSymlinkedGameDirectoryArchive(int $gameId, int $versionId, string $filename): string
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $sourceDir = sys_get_temp_dir() . '/archive_symlink_source_' . bin2hex(random_bytes(4));
    $outsideDir = sys_get_temp_dir() . '/archive_symlink_outside_' . bin2hex(random_bytes(4));
    File::makeDirectory($sourceDir, 0755, true);
    File::makeDirectory($outsideDir, 0755, true);
    File::put($outsideDir . '/script.rpy', "label start:\n    return\n");

    symlink($outsideDir, $sourceDir . '/game');

    try {
        (new Process([
            'tar',
            '-cf',
            Storage::path("{$storagePath}/{$filename}"),
            '-C',
            $sourceDir,
            '.',
        ]))->mustRun();
    } finally {
        File::deleteDirectory($sourceDir);
    }

    return $outsideDir;
}

/**
 * @return array{0: string, 1: string}
 */
function createSymlinkedMediaOutputArchive(int $gameId, int $versionId, string $filename): array
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $sourceDir = sys_get_temp_dir() . '/archive_symlink_output_source_' . bin2hex(random_bytes(4));
    $victimPath = sys_get_temp_dir() . '/archive_symlink_output_victim_' . bin2hex(random_bytes(4)) . '.txt';
    $originalContents = "do not overwrite\n";
    File::makeDirectory($sourceDir . '/game/images', 0755, true);
    File::put($sourceDir . '/game/script.rpy', "image bg = \"images/payload.png\"\nlabel start:\n    scene bg\n");
    File::put($sourceDir . '/game/images/payload.png', 'fake png contents');
    File::put($victimPath, $originalContents);

    symlink($victimPath, $sourceDir . '/game/images/payload.webp');

    try {
        (new Process([
            'tar',
            '-cf',
            Storage::path("{$storagePath}/{$filename}"),
            '-C',
            $sourceDir,
            '.',
        ]))->mustRun();
    } finally {
        File::deleteDirectory($sourceDir);
    }

    return [$victimPath, $originalContents];
}

function createAudioArchive(int $gameId, int $versionId, string $filename, string $audioContents): void
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("{$storagePath}/{$filename}"), ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    try {
        $zip->addFromString('game/script.rpy', "define audio.theme = \"music/theme.mp3\"\nlabel start:\n    play music theme\n");
        $zip->addFromString('game/music/theme.mp3', $audioContents);
    } finally {
        $zip->close();
    }
}

function createMaliciousOptimizedAudioArchive(int $gameId, int $versionId, string $filename, string $audioContents): void
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $metadata = [
        'schema' => 'fvn.archive_optimization.v1',
        'original_files' => [
            [
                'path' => 'game/music/theme.mp3',
                'size' => strlen($audioContents),
                'sha256' => hash('sha256', $audioContents),
            ],
        ],
        'optimized_files' => [
            [
                'path' => 'game/../../../leak-secret.txt',
                'size' => 10,
                'sha256' => str_repeat('0', 64),
            ],
        ],
        'media_replacements' => [
            'music/theme.mp3' => '../../../leak-secret.txt',
        ],
    ];

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("{$storagePath}/{$filename}"), ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    try {
        $zip->addFromString('game/script.rpy', "define audio.theme = \"music/theme.ogg\"\nlabel start:\n    play music theme\n");
        $zip->addFromString('.fvn-archive-metadata.json', json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } finally {
        $zip->close();
    }
}

function createArchiveWithMissingRpySources(int $gameId, int $versionId, string $filename): void
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $imagePath = tempnam(sys_get_temp_dir(), 'archive_image_');
    $image = imagecreatetruecolor(512, 512);
    mt_srand(4321);
    for ($y = 0; $y < 512; $y++) {
        for ($x = 0; $x < 512; $x++) {
            imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
    }
    imagepng($image, $imagePath);
    imagedestroy($image);

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("{$storagePath}/{$filename}"), ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    try {
        $zip->addFile($imagePath, 'game/images/bg.png');
        $zip->addFromString('game/good.rpyc', 'fake good bytecode');
        $zip->addFromString('game/bad.rpyc', 'fake bad bytecode');
        $zip->addFromString('game/compiled_python.rpyc', 'fake compiled Python bytecode');
        $zip->addFromString('game/compiled_python_ren.py', "init python:\n    pass\n");
    } finally {
        $zip->close();
        unlink($imagePath);
    }
}

function passingArchiveOptimizationStatsService(): GameStatsService
{
    return new readonly class extends GameStatsService
    {
        public function canExtractStats(string $archivePath): bool
        {
            return true;
        }
    };
}
