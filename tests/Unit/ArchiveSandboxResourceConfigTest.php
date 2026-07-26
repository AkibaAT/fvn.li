<?php

declare(strict_types=1);

/**
 * The sandbox resource ceilings are pinned so they cannot drift upward silently.
 *
 * Memory is sized to measured peaks with headroom. On the largest known game the
 * analyzer peaks at 2.2 GB when a release has to be built from source, and
 * app-side persistence at 200 MB. The optimizer ceiling is the highest because
 * it compiles the rewritten scripts by running the game, a short spike that
 * reaches 3.6 GB on the largest known game. Its headroom is wider than the
 * others because the work directory's page cache is charged to the same limit.
 * A game that needs materially more than these ceilings
 * indicates something holding a whole payload in memory, which is worth
 * investigating rather than accommodating.
 *
 * Timeouts and tmpfs sizes are governed by archive size and media transcoding,
 * so they are sized independently of memory.
 */
it('keeps archive sandbox defaults sized to measured peaks', function () {
    $services = file_get_contents(base_path('config/services.php'));
    $ddevStatsRunner = file_get_contents(base_path('.ddev/docker-compose.stats-runner.yaml'));
    $ddevArchiveOptimizer = file_get_contents(base_path('.ddev/docker-compose.denkit-stash.yaml'));
    $productionCompose = file_get_contents(base_path('docker/production/docker-compose.yml'));

    expect($services)
        ->toContain("env('RENPY_ANALYZER_TIMEOUT', 900)")
        ->toContain("env('RENPY_ANALYZER_MEMORY', '3g')")
        ->toContain("env('ARCHIVE_OPTIMIZER_TIMEOUT', 1800)")
        ->toContain("env('ARCHIVE_OPTIMIZER_MEMORY', '6g')");

    expect($ddevStatsRunner)
        ->toContain('RENPY_ANALYZER_TIMEOUT: ${RENPY_ANALYZER_TIMEOUT:-900}')
        ->toContain('RENPY_ANALYZER_MEMORY: ${RENPY_ANALYZER_MEMORY:-3g}')
        ->toContain('RENPY_ANALYZER_MEMORY: ${RENPY_ANALYZER_MEMORY:-3g}');

    expect($ddevArchiveOptimizer)
        ->toContain('ARCHIVE_OPTIMIZER_TIMEOUT: ${ARCHIVE_OPTIMIZER_TIMEOUT:-1800}')
        ->toContain('ARCHIVE_OPTIMIZER_MEMORY: ${ARCHIVE_OPTIMIZER_MEMORY:-6g}');

    expect($productionCompose)
        ->toContain('RENPY_ANALYZER_TIMEOUT=${RENPY_ANALYZER_TIMEOUT:-900}')
        ->toContain('RENPY_ANALYZER_MEMORY=${RENPY_ANALYZER_MEMORY:-3g}')
        ->toContain('ARCHIVE_OPTIMIZER_TIMEOUT=${ARCHIVE_OPTIMIZER_TIMEOUT:-1800}')
        ->toContain('ARCHIVE_OPTIMIZER_MEMORY=${ARCHIVE_OPTIMIZER_MEMORY:-6g}');
});

/**
 * The stats pipeline is expected to fit within this ceiling for any game size,
 * so it is pinned rather than treated as a tunable.
 */
it('keeps the application memory limit at 1G', function () {
    expect(file_get_contents(base_path('docker/app/php.ini')))
        ->toContain('memory_limit = 1G');
});
