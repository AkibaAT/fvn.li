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

test('optimize game archives skips rpyc files that fail to decompile', function () {
    Storage::fake('local');

    $game = Game::factory()->create(['name' => 'Partial RPYC']);
    $version = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '3.0',
    ]);

    createArchiveWithMissingRpySources($game->id, $version->id, 'partial-rpyc.zip');

    $oldPath = getenv('PATH') ?: '';
    $binDir = sys_get_temp_dir().'/fake-rpycdec-'.bin2hex(random_bytes(4));
    mkdir($binDir);
    $fakeRpycdec = $binDir.'/rpycdec';
    $fakeUnrpyc = $binDir.'/unrpyc';
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
    file_put_contents($fakeRpycdec, $fakeDecompiler);
    file_put_contents($fakeUnrpyc, $fakeDecompiler);
    chmod($fakeRpycdec, 0755);
    chmod($fakeUnrpyc, 0755);
    putenv('PATH='.$binDir.PATH_SEPARATOR.$oldPath);
    $_SERVER['PATH'] = $binDir.PATH_SEPARATOR.$oldPath;
    $_ENV['PATH'] = $binDir.PATH_SEPARATOR.$oldPath;

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
        putenv('PATH='.$oldPath);
        $_SERVER['PATH'] = $oldPath;
        $_ENV['PATH'] = $oldPath;
        @unlink($fakeRpycdec);
        @unlink($fakeUnrpyc);
        @rmdir($binDir);
    }

    $zip = new ZipArchive;
    expect($zip->open(Storage::path("games/{$game->id}/{$version->id}/partial-rpyc.optimized.zip")))->toBeTrue();

    try {
        expect($zip->locateName('game/good.rpy'))->not->toBeFalse()
            ->and($zip->locateName('game/bad.rpy'))->toBeFalse();

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
        $prefix = $rootFolder === null ? '' : trim($rootFolder, '/').'/';
        $zip->addFromString($prefix.'game/script.rpy', "image bg = \"images/bg.png\"\nlabel start:\n    scene bg\n");
        $zip->addFile($imagePath, $prefix.'game/images/bg.png');
    } finally {
        $zip->close();
        unlink($imagePath);
    }
}

function createOptimizableTarArchive(int $gameId, int $versionId, string $filename, string $rootFolder): void
{
    $storagePath = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($storagePath);

    $sourceDir = sys_get_temp_dir().'/archive_tar_source_'.bin2hex(random_bytes(4));
    $gameDir = $sourceDir.'/'.$rootFolder.'/game';
    mkdir($gameDir.'/images', 0755, true);

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

    copy($imagePath, $gameDir.'/images/bg.png');
    file_put_contents($gameDir.'/script.rpy', "image bg = \"images/bg.png\"\nlabel start:\n    scene bg\n");

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
    } finally {
        $zip->close();
        unlink($imagePath);
    }
}

function passingArchiveOptimizationStatsService(): GameStatsService
{
    return new readonly class extends GameStatsService
    {
        public function extractGameStats(string $archivePath): ?array
        {
            return ['languages' => []];
        }
    };
}
