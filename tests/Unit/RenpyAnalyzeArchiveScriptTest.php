<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('does not execute shell scripts from an archive when SDK stats extraction fails', function () {
    $basePath = sys_get_temp_dir() . '/renpy_analyze_archive_test_' . uniqid();
    $sourcePath = "{$basePath}/source";
    $sdkPath = "{$basePath}/sdk";
    $workPath = "{$basePath}/work";
    $archivePath = "{$basePath}/malicious.zip";
    $outputPath = "{$basePath}/stats.json";
    $pwnedPath = "{$workPath}/extract/pwned.txt";

    File::makeDirectory("{$sourcePath}/game", 0755, true);
    File::makeDirectory($sdkPath, 0755, true);
    File::makeDirectory($workPath, 0755, true);

    File::put("{$sourcePath}/game/script.rpy", 'label start:');
    File::put("{$sourcePath}/pwn.sh", <<<'SH'
#!/bin/sh
printf 'executed' > pwned.txt
printf '{"languages":{"en":{"words":1}}}' > stats.json
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
