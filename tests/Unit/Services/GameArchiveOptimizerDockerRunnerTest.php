<?php

declare(strict_types=1);

use App\Services\GameArchiveOptimizerDockerRunner;

it('builds a locked down docker command for per-archive optimization', function () {
    config([
        'services.archive_optimizer.image' => 'fvn.li:test',
        'services.archive_optimizer.memory' => '1536m',
        'services.archive_optimizer.cpus' => '0.5',
        'services.archive_optimizer.pids_limit' => 96,
        'services.archive_optimizer.tmp_size' => '256m',
        'services.archive_optimizer.php_binary' => 'php8.5',
        'services.archive_optimizer.app_path' => '/srv/app',
        'services.archive_optimizer.host_app_dir' => '/host/app',
    ]);

    $command = (new GameArchiveOptimizerDockerRunner)->buildDockerRunCommand(
        'archive-optimizer-test',
        '/host/work/job',
        'game.zip',
        'optimized.zip',
        'previous.optimized.zip'
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
        ->and($command)->toContain('96')
        ->and($command)->toContain('--cpus')
        ->and($command)->toContain('0.5')
        ->and($command)->toContain('--memory')
        ->and($command)->toContain('1536m')
        ->and($command)->toContain('/tmp:rw,nosuid,nodev,noexec,mode=1777,size=256m')
        // The staging area is disk-backed; a tmpfs there would count against the
        // container's memory limit while a game extracts into it.
        ->and($command)->not->toContain('/work:rw,nosuid,nodev,noexec,mode=1777,size=3g')
        ->and($command)->toContain('type=bind,source=/host/work/job/work,target=/work')
        ->and($command)->toContain('ARCHIVE_OPTIMIZER_APP_PATH=/srv/app')
        ->and($command)->toContain('ARCHIVE_OPTIMIZER_WORK_DIR=/work')
        // The mounted application directory is read-only and has no storage tree.
        ->and($command)->toContain('VIEW_COMPILED_PATH=/tmp/views')
        ->and($command)->toContain('type=bind,source=/host/work/job/input,target=/input,readonly')
        ->and($command)->toContain('type=bind,source=/host/work/job/output,target=/output')
        ->and($command)->toContain('type=bind,source=/host/app,target=/srv/app,readonly')
        ->and($command)->toContain('/input/archive-optimize.php')
        ->and($command)->toContain('/input/game.zip')
        ->and($command)->toContain('/output/optimized.zip')
        ->and($command)->toContain('/output/result.json')
        ->and($command)->toContain('/input/previous.optimized.zip');

    $entrypointIndex = array_search('--entrypoint', $command, true);
    expect($entrypointIndex)->not->toBeFalse()
        ->and($command[$entrypointIndex + 1])->toBe('php8.5');
});

it('mounts the RenPy SDK so scripts can be recompiled', function () {
    config([
        'services.renpy.sdk_host_path' => '/host/renpy-sdk',
        'services.renpy.sdk_container_path' => '/opt/renpy-sdk',
    ]);

    $command = (new GameArchiveOptimizerDockerRunner)->buildDockerRunCommand(
        'archive-optimizer-test',
        '/host/work/job',
        'game.zip',
        'optimized.zip'
    );

    expect($command)->toContain('type=bind,source=/host/renpy-sdk,target=/opt/renpy-sdk,readonly');
});

it('optimizes without the SDK when none is configured', function () {
    config(['services.renpy.sdk_host_path' => null]);

    $command = (new GameArchiveOptimizerDockerRunner)->buildDockerRunCommand(
        'archive-optimizer-test',
        '/host/work/job',
        'game.zip',
        'optimized.zip'
    );

    $sdkMounts = array_filter($command, fn (string $argument): bool => str_contains($argument, 'renpy-sdk'));

    expect($sdkMounts)->toBe([]);
});
