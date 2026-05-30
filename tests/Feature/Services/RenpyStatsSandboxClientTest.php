<?php

declare(strict_types=1);

use App\Services\RenpyStatsSandboxClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('copies archives to the shared analyzer path and returns sandbox stats', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-shared-'.uniqid());
    $archivePath = storage_path('framework/testing/source-archive-'.uniqid().'.zip');
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

    Http::fake(function ($request) use ($sharedPath) {
        expect($request->hasHeader('Authorization', 'Bearer test-token'))->toBeTrue();
        $archivePath = $request['archive_path'];
        expect($archivePath)->toStartWith($sharedPath)
            ->and(File::exists($archivePath))->toBeTrue();

        return Http::response([
            'stats' => [
                'languages' => [
                    'eng' => ['blocks' => 1, 'words' => 2],
                ],
            ],
        ]);
    });

    try {
        expect((new RenpyStatsSandboxClient)->extract($archivePath))->toBe([
            'languages' => [
                'eng' => ['blocks' => 1, 'words' => 2],
            ],
        ])->and(File::exists($sharedPath))->toBeTrue()
            ->and(File::exists($oldRequestDir))->toBeFalse()
            ->and(File::exists($freshRequestDir))->toBeTrue()
            ->and(File::exists($unrelatedDir))->toBeTrue();
    } finally {
        File::delete($archivePath);
        File::deleteDirectory($sharedPath);
    }
});

it('returns null when the sandbox analyzer is not configured', function () {
    config([
        'services.renpy.analyzer_url' => null,
        'services.renpy.analyzer_token' => null,
    ]);

    $archivePath = storage_path('framework/testing/source-archive-'.uniqid().'.zip');
    File::put($archivePath, 'archive');

    try {
        expect((new RenpyStatsSandboxClient)->extract($archivePath))->toBeNull();
    } finally {
        File::delete($archivePath);
    }
});

it('keeps the sandbox analyzer failure diagnostic as the extraction error', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-shared-'.uniqid());
    $archivePath = storage_path('framework/testing/source-archive-'.uniqid().'.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    File::put($archivePath, 'archive');

    config([
        'services.renpy.analyzer_url' => 'http://stats-runner:8080/api/renpy-analyzer/analyze',
        'services.renpy.analyzer_token' => 'test-token',
        'services.renpy.analyzer_shared_path' => $sharedPath,
    ]);

    Http::fake([
        'stats-runner:8080/*' => Http::response([
            'message' => 'Analyzer container failed: Stats file not generated',
        ], 422),
    ]);

    $client = new RenpyStatsSandboxClient;

    try {
        expect($client->extract($archivePath))->toBeNull()
            ->and($client->getLastError())->toBe('Analyzer container failed: Stats file not generated');
    } finally {
        File::delete($archivePath);
        File::deleteDirectory($sharedPath);
    }
});
