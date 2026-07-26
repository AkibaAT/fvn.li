<?php

declare(strict_types=1);

use App\Support\Stats\NdjsonStatsPayload;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('does not execute shell scripts from an archive when SDK stats extraction fails', function () {
    $basePath = sys_get_temp_dir() . '/renpy_analyze_archive_test_' . uniqid();
    $sourcePath = "{$basePath}/source";
    $sdkPath = "{$basePath}/sdk";
    $workPath = "{$basePath}/work";
    $archivePath = "{$basePath}/malicious.zip";
    $outputPath = "{$basePath}/stats.ndjson";
    $pwnedPath = "{$workPath}/extract/pwned.txt";

    File::makeDirectory("{$sourcePath}/game", 0755, true);
    File::makeDirectory($sdkPath, 0755, true);
    File::makeDirectory($workPath, 0755, true);

    File::put("{$sourcePath}/game/script.rpy", 'label start:');
    File::put("{$sourcePath}/pwn.sh", <<<'SH'
#!/bin/sh
printf 'executed' > pwned.txt
printf '{"type":"meta","schema":"fvn.renpy_stats.v1"}\n{"type":"languages","key":"en","entry":{"words":1}}\n' > stats.ndjson
SH);

    File::put("{$sdkPath}/renpy.sh", <<<'SH'
#!/bin/sh
exit 1
SH);
    chmod("{$sdkPath}/renpy.sh", 0755);

    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
    $zip->addEmptyDir('game');
    $zip->addFile("{$sourcePath}/game/script.rpy", 'game/script.rpy');
    $zip->addFile("{$sourcePath}/pwn.sh", 'pwn.sh');
    $zip->close();

    try {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/renpy-analyze-archive.php'),
            $archivePath,
            $outputPath,
            $sdkPath,
            resource_path('renpy/json_stats.rpy'),
        ], base_path(), [
            'RENPY_ANALYZER_WORK_DIR' => $workPath,
        ]);
        $process->run();

        expect($process->getExitCode())->toBe(4)
            ->and($process->getErrorOutput())->toContain('Stats file not generated')
            ->and(File::exists($pwnedPath))->toBeFalse()
            ->and(File::exists($outputPath))->toBeFalse();
    } finally {
        if (File::isDirectory($basePath)) {
            File::deleteDirectory($basePath);
        }
    }
});

it('falls back to the bundled launcher when test mode does not produce stats', function () {
    $basePath = sys_get_temp_dir() . '/renpy_analyze_archive_native_test_' . uniqid();
    $sourcePath = "{$basePath}/source";
    $sdkPath = "{$basePath}/sdk";
    $workPath = "{$basePath}/work";
    $archivePath = "{$basePath}/game.zip";
    $outputPath = "{$basePath}/stats.ndjson";

    File::makeDirectory("{$sourcePath}/game", 0755, true);
    File::makeDirectory($sdkPath, 0755, true);
    File::makeDirectory($workPath, 0755, true);

    File::put("{$sourcePath}/game/script.rpy", 'label start:');
    File::put("{$sourcePath}/game.sh", <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> native-attempts.txt
if [ "$#" -gt 0 ]; then
    exit 1
fi
printf '{"type":"meta","schema":"fvn.renpy_stats.v1"}\n{"type":"languages","key":"default","entry":{"blocks":1,"words":3}}\n' > stats.ndjson
SH);
    File::put("{$sdkPath}/renpy.sh", <<<'SH'
#!/bin/sh
exit 1
SH);
    chmod("{$sdkPath}/renpy.sh", 0755);

    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
    $zip->addEmptyDir('game');
    $zip->addFile("{$sourcePath}/game/script.rpy", 'game/script.rpy');
    $zip->addFile("{$sourcePath}/game.sh", 'game.sh');
    $zip->close();

    try {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/renpy-analyze-archive.php'),
            $archivePath,
            $outputPath,
            $sdkPath,
            resource_path('renpy/json_stats.rpy'),
        ], base_path(), [
            'RENPY_ANALYZER_ALLOW_NATIVE' => '1',
            'RENPY_ANALYZER_WORK_DIR' => $workPath,
        ]);
        $process->run();

        expect($process->getExitCode())->toBe(0)
            ->and((new NdjsonStatsPayload($outputPath))->languages())
            ->toMatchArray(['default' => ['blocks' => 1, 'words' => 3, 'characters' => []]])
            ->and(File::get("{$workPath}/extract/native-attempts.txt"))
            ->toBe("game test\n\n");
    } finally {
        if (File::isDirectory($basePath)) {
            File::deleteDirectory($basePath);
        }
    }
});
