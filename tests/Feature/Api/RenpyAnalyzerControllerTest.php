<?php

declare(strict_types=1);

use App\Services\RenpyAnalyzerDockerRunner;
use Illuminate\Support\Facades\File;

it('hides the renpy analyzer endpoint unless this container is the analyzer server', function () {
    config(['services.renpy.analyzer_server' => false]);

    $this->postJson('/api/renpy-analyzer/analyze', [
        'archive_path' => '/tmp/game.zip',
    ])->assertNotFound();
});

it('requires the analyzer bearer token', function () {
    config([
        'services.renpy.analyzer_server' => true,
        'services.renpy.analyzer_token' => 'secret',
    ]);

    $this->postJson('/api/renpy-analyzer/analyze', [
        'archive_path' => '/tmp/game.zip',
    ])->assertUnauthorized();
});

it('runs the docker analyzer only for archives under the shared analyzer path', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-controller-'.uniqid());
    File::makeDirectory($sharedPath, 0755, true);
    $archivePath = "{$sharedPath}/game.zip";
    File::put($archivePath, 'archive');

    config([
        'services.renpy.analyzer_server' => true,
        'services.renpy.analyzer_token' => 'secret',
        'services.renpy.analyzer_shared_path' => $sharedPath,
    ]);

    $runner = Mockery::mock(RenpyAnalyzerDockerRunner::class);
    $runner->shouldReceive('analyze')
        ->once()
        ->with(realpath($archivePath))
        ->andReturn([
            'languages' => [
                'eng' => ['blocks' => 1, 'words' => 2],
            ],
        ]);
    $this->app->instance(RenpyAnalyzerDockerRunner::class, $runner);

    try {
        $this->withToken('secret')
            ->postJson('/api/renpy-analyzer/analyze', [
                'archive_path' => $archivePath,
            ])
            ->assertOk()
            ->assertJsonPath('stats.languages.eng.words', 2);

        $outsidePath = storage_path('framework/testing/outside-'.uniqid().'.zip');
        File::put($outsidePath, 'archive');
        $this->withToken('secret')
            ->postJson('/api/renpy-analyzer/analyze', [
                'archive_path' => $outsidePath,
            ])
            ->assertUnprocessable();
    } finally {
        File::deleteDirectory($sharedPath);
        if (isset($outsidePath)) {
            File::delete($outsidePath);
        }
    }
});

it('returns the analyzer extraction diagnostic', function () {
    $sharedPath = storage_path('framework/testing/renpy-analyzer-controller-'.uniqid());
    File::makeDirectory($sharedPath, 0755, true);
    $archivePath = "{$sharedPath}/game.zip";
    File::put($archivePath, 'archive');

    config([
        'services.renpy.analyzer_server' => true,
        'services.renpy.analyzer_token' => 'secret',
        'services.renpy.analyzer_shared_path' => $sharedPath,
    ]);

    $runner = Mockery::mock(RenpyAnalyzerDockerRunner::class);
    $runner->shouldReceive('analyze')
        ->once()
        ->with(realpath($archivePath))
        ->andReturn(null);
    $runner->shouldReceive('getLastError')
        ->once()
        ->andReturn('Analyzer container failed: Stats file not generated');
    $this->app->instance(RenpyAnalyzerDockerRunner::class, $runner);

    try {
        $this->withToken('secret')
            ->postJson('/api/renpy-analyzer/analyze', [
                'archive_path' => $archivePath,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Analyzer container failed: Stats file not generated');
    } finally {
        File::deleteDirectory($sharedPath);
    }
});
