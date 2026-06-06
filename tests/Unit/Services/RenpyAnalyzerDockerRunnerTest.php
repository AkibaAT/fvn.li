<?php

declare(strict_types=1);

use App\Services\RenpyAnalyzerDockerRunner;
use Illuminate\Support\Facades\File;

it('builds a locked down docker command for per-archive analysis', function () {
    config([
        'services.renpy.analyzer_image' => 'fvn.li:test',
        'services.renpy.analyzer_memory' => '768m',
        'services.renpy.analyzer_cpus' => '0.75',
        'services.renpy.analyzer_pids_limit' => 64,
        'services.renpy.analyzer_tmp_size' => '128m',
        'services.renpy.analyzer_work_size' => '2g',
        'services.renpy.analyzer_php_binary' => 'php8.5',
        'services.renpy.sdk_host_path' => '/host/renpy-sdk',
        'services.renpy.sdk_container_path' => '/opt/renpy-sdk',
    ]);

    $command = (new RenpyAnalyzerDockerRunner)->buildDockerRunCommand(
        'renpy-analyzer-test',
        '/host/work/job',
        'game.zip'
    );

    expect($command)->toContain('--network')
        ->and($command)->toContain('none')
        ->and($command)->toContain('--read-only')
        ->and($command)->toContain('--user')
        ->and($command)->toContain('33:33')
        ->and($command)->toContain('--cap-drop')
        ->and($command)->toContain('ALL')
        ->and($command)->toContain('--security-opt')
        ->and($command)->toContain('no-new-privileges')
        ->and($command)->toContain('--pids-limit')
        ->and($command)->toContain('64')
        ->and($command)->toContain('--cpus')
        ->and($command)->toContain('0.75')
        ->and($command)->toContain('--memory')
        ->and($command)->toContain('768m')
        ->and($command)->toContain('/tmp:rw,nosuid,nodev,noexec,mode=1777,size=128m')
        ->and($command)->toContain('/work:rw,nosuid,nodev,noexec,mode=1777,size=2g')
        ->and($command)->toContain('type=bind,source=/host/work/job/input,target=/input,readonly')
        ->and($command)->toContain('type=bind,source=/host/work/job/output,target=/output')
        ->and($command)->toContain('type=bind,source=/host/renpy-sdk,target=/opt/renpy-sdk,readonly')
        ->and($command)->toContain('/input/renpy-analyze-archive.php')
        ->and($command)->toContain('/input/json_stats.rpy');

    $entrypointIndex = array_search('--entrypoint', $command, true);
    expect($entrypointIndex)->not->toBeFalse()
        ->and($command[$entrypointIndex + 1])->toBe('php8.5');
});

it('cleans up stale analyzer job directories without deleting active or unrelated directories', function () {
    $workDir = storage_path('framework/testing/renpy-runner-work-' . uniqid());
    $oldJobDir = "{$workDir}/renpy-analyzer-old";
    $freshJobDir = "{$workDir}/renpy-analyzer-fresh";
    $unrelatedDir = "{$workDir}/not-analyzer-old";

    File::makeDirectory($oldJobDir, 0755, true);
    File::makeDirectory($freshJobDir, 0755, true);
    File::makeDirectory($unrelatedDir, 0755, true);
    touch($oldJobDir, time() - 7200);
    touch($freshJobDir, time());
    touch($unrelatedDir, time() - 7200);

    config(['services.renpy.analyzer_stale_cleanup_seconds' => 3600]);

    try {
        $method = new ReflectionMethod(RenpyAnalyzerDockerRunner::class, 'cleanupStaleJobDirectories');
        $method->setAccessible(true);
        $method->invoke(new RenpyAnalyzerDockerRunner, $workDir);

        expect(File::exists($oldJobDir))->toBeFalse()
            ->and(File::exists($freshJobDir))->toBeTrue()
            ->and(File::exists($unrelatedDir))->toBeTrue();
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('sanitizes analyzer diagnostics before storing or logging them', function () {
    $method = new ReflectionMethod(RenpyAnalyzerDockerRunner::class, 'sanitizeDiagnosticOutput');
    $method->setAccessible(true);

    $sanitized = $method->invoke(new RenpyAnalyzerDockerRunner, "safe\nbad\0\x1B[31mhidden");

    expect($sanitized)->toBe("safe\nbad[31mhidden");
});
