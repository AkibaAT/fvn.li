<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameArchiveService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

function invokeDenKitStashMethod(DenKitStashPersistenceService $service, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($service);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($service, $arguments);
}

it('is enabled when DenKit Stash has an API key', function () {
    Config::set('services.denkit_stash.enabled', true);
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $service = new DenKitStashPersistenceService(Mockery::mock(GameArchiveService::class));

    expect($service->isEnabled())->toBeTrue();
});

it('looks up existing DenKit Stash builds through the HTTP API', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode([
            'build' => [
                'id' => 456,
                'upload_id' => 123,
                'user_version' => '1.2.3',
                'state' => 'completed',
                'created_at' => '2026-05-23T10:00:00Z',
            ],
        ])),
    ]));
    $handlerStack->push(Middleware::history($history));

    $service = new DenKitStashPersistenceService(
        Mockery::mock(GameArchiveService::class),
        new Client(['handler' => $handlerStack])
    );

    expect(invokeDenKitStashMethod($service, 'latestBuildId', ['fvn-li', 'dawn-chorus', 'main', '1.2.3']))->toBe(456);

    expect($history)->toHaveCount(1);
    $request = $history[0]['request'];
    parse_str($request->getUri()->getQuery(), $query);

    expect((string) $request->getUri())->toContain('https://stash.example/wharf/builds/latest')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer secret-key')
        ->and($query)->toMatchArray([
            'target' => 'fvn-li/dawn-chorus',
            'channel' => 'main',
            'user_version' => '1.2.3',
        ]);
});

it('treats missing DenKit Stash builds as absent archives', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $service = new DenKitStashPersistenceService(
        Mockery::mock(GameArchiveService::class),
        new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(404, [], '{"errors":["build not found"]}'),
        ]))])
    );

    expect(invokeDenKitStashMethod($service, 'latestBuildId', ['fvn-li', 'missing', 'main', '9.9.9']))->toBeNull();
});

it('downloads DenKit Stash build archives returned directly with HTTP 200', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $archivePath = storage_path('framework/testing/denkit-direct-' . uniqid() . '.zip');
    $service = new DenKitStashPersistenceService(
        Mockery::mock(GameArchiveService::class),
        new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/zip'], 'zip-bytes'),
        ]))])
    );

    try {
        invokeDenKitStashMethod($service, 'downloadBuildArchive', [4, $archivePath]);

        expect(File::get($archivePath))->toBe('zip-bytes');
    } finally {
        File::delete($archivePath);
    }
});

it('downloads DenKit Stash build archives from JSON URL responses', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $archivePath = storage_path('framework/testing/denkit-json-url-' . uniqid() . '.zip');
    $recorder = (object) [
        'downloadArchiveUrlCalls' => [],
    ];
    $service = new class($recorder, Mockery::mock(GameArchiveService::class), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode(['url' => 'http://rustfs.example/archive.zip']))]))])) extends DenKitStashPersistenceService
    {
        public function __construct(private object $recorder, GameArchiveService $archiveService, Client $httpClient)
        {
            parent::__construct($archiveService, $httpClient);
        }

        protected function downloadArchiveUrl(string $url, string $archivePath): void
        {
            $this->recorder->downloadArchiveUrlCalls[] = [$url, $archivePath];
            File::put($archivePath, 'zip-bytes-from-url');
        }
    };

    try {
        invokeDenKitStashMethod($service, 'downloadBuildArchive', [4, $archivePath]);

        expect($recorder->downloadArchiveUrlCalls)->toBe([
            ['http://rustfs.example/archive.zip', $archivePath],
        ])->and(File::get($archivePath))->toBe('zip-bytes-from-url');
    } finally {
        File::delete($archivePath);
    }
});

it('names restored DenKit archives from optimization metadata', function () {
    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('readArchiveMetadata')
        ->once()
        ->andReturn([
            'schema' => 'fvn.archive_optimization.v1',
            'original_archive' => [
                'filename' => 'PASSWORD-b0.85-linux.tar.bz2',
                'format' => 'tar.bz2',
            ],
        ]);

    $service = new DenKitStashPersistenceService($archiveService);

    expect(invokeDenKitStashMethod($service, 'restoredArchiveFilename', ['/tmp/archive-download.tmp', 4]))
        ->toBe('PASSWORD-b0.85-linux.optimized.tar.bz2');
});

it('restores preserved DenKit archive bodies with their original optimized format', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $sourceDir = storage_path('framework/testing/denkit-preserved-source-' . uniqid());
    $archivePath = storage_path('framework/testing/denkit-preserved-' . uniqid() . '.tar.bz2');
    File::makeDirectory("{$sourceDir}/game", 0755, true);
    File::put("{$sourceDir}/game/script.rpy", 'label start: return');
    File::put("{$sourceDir}/.fvn-archive-metadata.json", json_encode([
        'schema' => 'fvn.archive_optimization.v1',
        'original_archive' => [
            'filename' => 'PASSWORD-b0.85-linux.tar.bz2',
            'format' => 'tar.bz2',
        ],
    ]));

    $process = new Process(['tar', '-cjf', $archivePath, '-C', $sourceDir, '--', 'game', '.fvn-archive-metadata.json']);
    $process->run();
    expect($process->isSuccessful())->toBeTrue();

    $game = Game::factory()->create(['name' => 'Password', 'slug' => 'password']);
    $version = GameVersion::factory()->for($game)->create(['version' => '0.85']);
    $storagePath = "games/{$game->id}/{$version->id}";

    $service = new DenKitStashPersistenceService(
        app(GameArchiveService::class),
        new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['build' => ['id' => 4]])),
            new Response(200, ['Content-Type' => 'application/x-bzip2'], File::get($archivePath)),
        ]))])
    );

    try {
        $result = $service->restorePersistedArchive($game, $version, $storagePath);

        expect($result)->toMatchArray([
            'status' => 'restored',
            'build_id' => 4,
        ]);

        $restoredPath = $result['archive_path'] ?? '';
        expect($restoredPath)->toEndWith('/PASSWORD-b0.85-linux.optimized.tar.bz2')
            ->and(File::exists($restoredPath))->toBeTrue()
            ->and(app(GameArchiveService::class)->readArchiveMetadata($restoredPath)['original_archive']['format'])->toBe('tar.bz2');
    } finally {
        Storage::deleteDirectory($storagePath);
        File::deleteDirectory($sourceDir);
        File::delete($archivePath);
    }
});

it('uses the synchronous butler push result after pushing an optimized archive', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('readArchiveMetadata')
        ->once()
        ->andReturn(['schema' => 'fvn.archive_optimization.v1']);

    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([]));
    $handlerStack->push(Middleware::history($history));

    $service = new class($archiveService, new Client(['handler' => $handlerStack])) extends DenKitStashPersistenceService
    {
        protected function runButlerPush(string $archivePath, string $target, string $userVersion): string
        {
            return json_encode([
                'type' => 'result',
                'value' => [
                    'buildId' => 789,
                    'channel' => 'main',
                    'dryRun' => false,
                    'skipped' => false,
                ],
            ]);
        }
    };

    $archivePath = storage_path('framework/testing/denkit-wait-' . uniqid() . '.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('game/script.rpy', 'label start:');
    $zip->close();

    try {
        $game = Game::factory()->create([
            'name' => 'Password',
            'slug' => 'password',
        ]);
        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
            'version' => '0.85',
        ]);

        expect($service->persistOptimizedArchive($game, $version, $archivePath, 'main', true))->toMatchArray([
            'status' => 'persisted',
            'build_id' => 789,
        ]);
        expect($history)->toBe([]);
    } finally {
        File::delete($archivePath);
    }
});

it('rejects optimized zip archives with unsafe member paths before pushing to butler', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('readArchiveMetadata')
        ->once()
        ->andReturn(['schema' => 'fvn.archive_optimization.v1']);

    $service = new class($archiveService) extends DenKitStashPersistenceService
    {
        public bool $pushed = false;

        protected function runButlerPush(string $archivePath, string $target, string $userVersion): string
        {
            $this->pushed = true;

            return json_encode(['type' => 'result', 'value' => ['buildId' => 1]]);
        }
    };

    $archivePath = storage_path('framework/testing/denkit-unsafe-path-' . uniqid() . '.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('../host-secret.txt', 'secret');
    $zip->close();

    try {
        $game = Game::factory()->create(['name' => 'Unsafe Zip', 'slug' => 'unsafe-zip']);
        $version = GameVersion::factory()->for($game)->create(['version' => '1.0']);

        expect(fn () => $service->persistOptimizedArchive($game, $version, $archivePath, 'main', true))
            ->toThrow(RuntimeException::class, 'unsafe entry path')
            ->and($service->pushed)->toBeFalse();
    } finally {
        File::delete($archivePath);
    }
});

it('rejects optimized tar archives with symlinks before pushing to butler', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('readArchiveMetadata')
        ->once()
        ->andReturn(['schema' => 'fvn.archive_optimization.v1']);

    $service = new class($archiveService) extends DenKitStashPersistenceService
    {
        public bool $pushed = false;

        protected function runButlerPush(string $archivePath, string $target, string $userVersion): string
        {
            $this->pushed = true;

            return json_encode(['type' => 'result', 'value' => ['buildId' => 1]]);
        }
    };

    $sourceDir = storage_path('framework/testing/denkit-symlink-source-' . uniqid());
    $archivePath = storage_path('framework/testing/denkit-symlink-' . uniqid() . '.tar.gz');
    File::ensureDirectoryExists("{$sourceDir}/game");
    symlink('/etc/hostname', "{$sourceDir}/game/host-secret-link");
    $process = new Process(['tar', '-czf', $archivePath, '-C', $sourceDir, '--', 'game']);
    $process->mustRun();

    try {
        $game = Game::factory()->create(['name' => 'Unsafe Tar', 'slug' => 'unsafe-tar']);
        $version = GameVersion::factory()->for($game)->create(['version' => '1.0']);

        expect(fn () => $service->persistOptimizedArchive($game, $version, $archivePath, 'main', true))
            ->toThrow(RuntimeException::class, 'not a regular file or directory')
            ->and($service->pushed)->toBeFalse();
    } finally {
        File::deleteDirectory($sourceDir);
        File::delete($archivePath);
    }
});

it('rejects optimized archives that exceed the configured expanded size before pushing to butler', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');
    Config::set('services.denkit_stash.max_extracted_bytes', 4);

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('readArchiveMetadata')
        ->once()
        ->andReturn(['schema' => 'fvn.archive_optimization.v1']);

    $service = new class($archiveService) extends DenKitStashPersistenceService
    {
        public bool $pushed = false;

        protected function runButlerPush(string $archivePath, string $target, string $userVersion): string
        {
            $this->pushed = true;

            return json_encode(['type' => 'result', 'value' => ['buildId' => 1]]);
        }
    };

    $archivePath = storage_path('framework/testing/denkit-too-large-' . uniqid() . '.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('game/script.rpy', 'label start:');
    $zip->close();

    try {
        $game = Game::factory()->create(['name' => 'Too Large', 'slug' => 'too-large']);
        $version = GameVersion::factory()->for($game)->create(['version' => '1.0']);

        expect(fn () => $service->persistOptimizedArchive($game, $version, $archivePath, 'main', true))
            ->toThrow(RuntimeException::class, 'configured byte limit')
            ->and($service->pushed)->toBeFalse();
    } finally {
        File::delete($archivePath);
    }
});

it('fails when butler push does not synchronously report a build id', function () {
    Config::set('services.denkit_stash.url', 'https://stash.example');
    Config::set('services.denkit_stash.api_key', 'secret-key');

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('readArchiveMetadata')
        ->once()
        ->andReturn(['schema' => 'fvn.archive_optimization.v1']);

    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler([]));
    $handlerStack->push(Middleware::history($history));

    $service = new class($archiveService, new Client(['handler' => $handlerStack])) extends DenKitStashPersistenceService
    {
        protected function runButlerPush(string $archivePath, string $target, string $userVersion): string
        {
            return json_encode([
                'type' => 'result',
                'value' => [
                    'channel' => 'main',
                    'dryRun' => false,
                    'skipped' => false,
                ],
            ]);
        }
    };

    $archivePath = storage_path('framework/testing/denkit-status-' . uniqid() . '.zip');
    File::ensureDirectoryExists(dirname($archivePath));
    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('game/script.rpy', 'label start:');
    $zip->close();

    try {
        $game = Game::factory()->create([
            'name' => 'Password',
            'slug' => 'password',
        ]);
        $version = GameVersion::factory()->create([
            'game_id' => $game->id,
            'version' => '0.85',
        ]);

        expect(fn () => $service->persistOptimizedArchive($game, $version, $archivePath, 'main', true))
            ->toThrow(RuntimeException::class, 'butler push finished, but did not report a completed build');
        expect($history)->toBe([]);
    } finally {
        File::delete($archivePath);
    }
});
