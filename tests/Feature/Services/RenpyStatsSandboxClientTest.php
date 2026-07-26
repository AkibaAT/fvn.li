<?php

declare(strict_types=1);

use App\Services\RenpyStatsSandboxClient;
use App\Support\Stats\StatsPayload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('copies archives to the shared analyzer path and returns sandbox stats', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-shared-' . uniqid());
    $archivePath = storage_path('framework/testing/source-archive-' . uniqid() . '.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    File::put($archivePath, 'archive');

    config([
        'services.renpy.analyzer_url' => 'http://stats-runner:8080/api/renpy-analyzer/analyze',
        'services.renpy.analyzer_token' => 'test-token',
        'services.renpy.analyzer_shared_path' => $sharedPath,
        'services.renpy.analyzer_stale_cleanup_seconds' => 3600,
    ]);

    $oldRequestDir = "{$sharedPath}/request-old";
    $freshRequestDir = "{$sharedPath}/request-fresh";
    $unrelatedDir = "{$sharedPath}/other-old";
    File::makeDirectory($oldRequestDir, 0755, true);
    File::makeDirectory($freshRequestDir, 0755, true);
    File::makeDirectory($unrelatedDir, 0755, true);
    touch($oldRequestDir, time() - 7200);
    touch($freshRequestDir, time());
    touch($unrelatedDir, time() - 7200);

    // The analyzer writes the document beside the archive and answers with its path.
    Http::fake(function ($request) use ($sharedPath) {
        expect($request->hasHeader('Authorization', 'Bearer test-token'))->toBeTrue();
        $requestArchive = $request['archive_path'];
        expect($requestArchive)->toStartWith($sharedPath)
            ->and(File::exists($requestArchive))->toBeTrue();

        $statsPath = dirname($requestArchive) . '/stats-fake.ndjson';
        File::put($statsPath, implode("\n", [
            json_encode(['type' => 'meta', 'schema' => 'fvn.renpy_stats.v1']),
            json_encode(['type' => 'languages', 'key' => 'eng', 'entry' => ['blocks' => 1, 'words' => 2]]),
        ]) . "\n");

        return Http::response(['stats_path' => $statsPath]);
    });

    $payload = null;

    try {
        $payload = (new RenpyStatsSandboxClient)->extract($archivePath);

        expect($payload)->toBeInstanceOf(StatsPayload::class)
            ->and($payload->languages())->toBe(['eng' => ['blocks' => 1, 'words' => 2, 'characters' => []]])
            ->and(File::exists($sharedPath))->toBeTrue()
            ->and(File::exists($oldRequestDir))->toBeFalse()
            ->and(File::exists($freshRequestDir))->toBeTrue()
            ->and(File::exists($unrelatedDir))->toBeTrue();
    } finally {
        $payload?->release();
        File::delete($archivePath);
        File::deleteDirectory($sharedPath);
    }
});

it('rejects a stats path outside the request directory', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-shared-' . uniqid());
    $archivePath = storage_path('framework/testing/source-archive-' . uniqid() . '.zip');
    $escapedPath = storage_path('framework/testing/escaped-' . uniqid() . '.ndjson');
    File::ensureDirectoryExists(dirname($archivePath));
    File::put($archivePath, 'archive');
    File::put($escapedPath, '{"type":"meta","schema":"fvn.renpy_stats.v1"}' . "\n");

    config([
        'services.renpy.analyzer_url' => 'http://stats-runner:8080/api/renpy-analyzer/analyze',
        'services.renpy.analyzer_token' => 'test-token',
        'services.renpy.analyzer_shared_path' => $sharedPath,
    ]);

    Http::fake([
        'stats-runner:8080/*' => Http::response(['stats_path' => $escapedPath]),
    ]);

    $client = new RenpyStatsSandboxClient;

    try {
        expect($client->extract($archivePath))->toBeNull()
            ->and($client->getLastError())->toBe('Sandbox analyzer returned an unusable stats path')
            ->and(File::exists($escapedPath))->toBeTrue();
    } finally {
        File::delete($archivePath);
        File::delete($escapedPath);
        File::deleteDirectory($sharedPath);
    }
});

it('returns null when the sandbox analyzer is not configured', function () {
    config([
        'services.renpy.analyzer_url' => null,
        'services.renpy.analyzer_token' => null,
    ]);

    $archivePath = storage_path('framework/testing/source-archive-' . uniqid() . '.zip');
    File::put($archivePath, 'archive');

    try {
        expect((new RenpyStatsSandboxClient)->extract($archivePath))->toBeNull();
    } finally {
        File::delete($archivePath);
    }
});

it('keeps the generic sandbox analyzer failure message as the extraction error', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-shared-' . uniqid());
    $archivePath = storage_path('framework/testing/source-archive-' . uniqid() . '.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    File::put($archivePath, 'archive');

    config([
        'services.renpy.analyzer_url' => 'http://stats-runner:8080/api/renpy-analyzer/analyze',
        'services.renpy.analyzer_token' => 'test-token',
        'services.renpy.analyzer_shared_path' => $sharedPath,
    ]);

    Http::fake([
        'stats-runner:8080/*' => Http::response([
            'message' => 'No stats could be extracted',
        ], 422),
    ]);

    $client = new RenpyStatsSandboxClient;

    try {
        expect($client->extract($archivePath))->toBeNull()
            ->and($client->getLastError())->toBe('No stats could be extracted');
    } finally {
        File::delete($archivePath);
        File::deleteDirectory($sharedPath);
    }
});
