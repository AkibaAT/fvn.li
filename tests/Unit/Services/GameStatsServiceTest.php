<?php

declare(strict_types=1);

use App\Services\GameStatsService;
use Symfony\Component\Process\Process;

test('extract archive handles tar bz2 payload with misleading zip extension', function () {
    $basePath = sys_get_temp_dir().'/game_stats_test_'.uniqid();
    $sourcePath = "{$basePath}/source";
    $extractPath = "{$basePath}/extract";
    $archivePath = "{$basePath}/misleading.zip";

    mkdir("{$sourcePath}/game", 0755, true);
    mkdir($extractPath, 0755, true);
    file_put_contents("{$sourcePath}/game/script.rpy", 'label start:');

    $process = new Process(['tar', '-cjf', $archivePath, '-C', $sourcePath, '.']);
    $process->mustRun();

    try {
        $service = app(GameStatsService::class);
        $method = new ReflectionMethod($service, 'extractArchive');
        $method->invoke($service, $archivePath, $extractPath);

        expect(file_exists("{$extractPath}/game/script.rpy"))->toBeTrue();
    } finally {
        if (is_dir($basePath)) {
            $cleanup = new Process(['rm', '-rf', $basePath]);
            $cleanup->run();
        }
    }
});
