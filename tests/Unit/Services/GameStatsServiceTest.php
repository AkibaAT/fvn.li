<?php

declare(strict_types=1);

use App\Services\RenpyStatsLocalExtractor;
use Symfony\Component\Process\Process;

test('extract archive handles tar bz2 payload with misleading zip extension', function () {
    $basePath = sys_get_temp_dir() . '/game_stats_test_' . uniqid();
    $sourcePath = "{$basePath}/source";
    $extractPath = "{$basePath}/extract";
    $archivePath = "{$basePath}/misleading.zip";

    mkdir("{$sourcePath}/game", 0755, true);
    mkdir($extractPath, 0755, true);
    file_put_contents("{$sourcePath}/game/script.rpy", 'label start:');

    $process = new Process(['tar', '-cjf', $archivePath, '-C', $sourcePath, '.']);
    $process->mustRun();

    try {
        app(RenpyStatsLocalExtractor::class)->extractArchive($archivePath, $extractPath);

        expect(file_exists("{$extractPath}/game/script.rpy"))->toBeTrue();
    } finally {
        if (is_dir($basePath)) {
            $cleanup = new Process(['rm', '-rf', $basePath]);
            $cleanup->run();
        }
    }
});

test('translation tree detection ignores RenPy placeholder language directory', function () {
    $basePath = sys_get_temp_dir() . '/game_stats_test_' . uniqid();
    mkdir("{$basePath}/game/tl/None", 0755, true);

    try {
        expect(app(RenpyStatsLocalExtractor::class)->hasTranslationTree($basePath))->toBeFalse();
    } finally {
        if (is_dir($basePath)) {
            $cleanup = new Process(['rm', '-rf', $basePath]);
            $cleanup->run();
        }
    }
});

test('translation tree detection finds real language directories', function () {
    $basePath = sys_get_temp_dir() . '/game_stats_test_' . uniqid();
    mkdir("{$basePath}/game/tl/spanish", 0755, true);

    try {
        expect(app(RenpyStatsLocalExtractor::class)->hasTranslationTree($basePath))->toBeTrue();
    } finally {
        if (is_dir($basePath)) {
            $cleanup = new Process(['rm', '-rf', $basePath]);
            $cleanup->run();
        }
    }
});
