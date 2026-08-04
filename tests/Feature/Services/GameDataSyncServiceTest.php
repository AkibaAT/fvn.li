<?php

declare(strict_types=1);

use App\Exceptions\DenKitStashUnavailableException;
use App\Models\Game;
use App\Models\GameJam;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\Tag;
use App\Services\GameArchiveService;
use App\Services\GameDataSyncService;
use App\Services\GameMetadataImageProcessor;
use App\Services\GamePendingAssociationProcessor;
use App\Services\GameVersionArchiveRepositoryService;
use App\Services\ItchGameMetadataRefresher;
use App\Services\ItchHttpClientService;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

function invokeGameDataSyncMethod(GameDataSyncService $service, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($service);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($service, $arguments);
}

function ensureSyncLanguage(string $id, string $name = 'Language'): void
{
    Language::withoutEvents(fn () => Language::firstOrCreate([
        'id' => $id,
    ], [
        'part2b' => $id,
        'part2t' => $id,
        'part1' => substr($id, 0, 2),
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => $name,
        'flag_code' => substr($id, 0, 2),
    ]));
}

it('refreshes base itch metadata and rejects unsupported platforms', function () {
    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 444,
        'initially_published_at' => null,
        'thumb_url' => null,
    ]);
    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/444')
        ->andReturn(new Response(200, [], json_encode([
            'game' => [
                'published_at' => '2024-01-02T03:04:05Z',
                'cover_url' => 'https://img.example/cover.jpg',
            ],
        ])));
    app()->instance(ItchHttpClientService::class, $client);

    app(GameDataSyncService::class)->refreshBaseInfo($game);

    expect($game->initially_published_at?->format('Y-m-d H:i:s'))->toBe('2024-01-02 03:04:05')
        ->and($game->thumb_url)->toBe('https://img.example/cover.jpg');

    $steamGame = Game::factory()->create(['platform' => 'steam']);
    expect(fn () => app(GameDataSyncService::class)->refreshBaseInfo($steamGame))
        ->toThrow(Exception::class, 'Cannot refresh base info for non-itch.io game');
});

it('caches HTTP responses by game url options and anonymous mode and can clear the cache', function () {
    $game = Game::factory()->create();
    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://creator.itch.io/game', ['headers' => ['A' => 'B']], true)
        ->andReturn(new Response(200, [], 'cached body'));
    $client->shouldReceive('get')
        ->once()
        ->with('https://creator.itch.io/game', ['headers' => ['A' => 'B']], true)
        ->andReturn(new Response(200, [], 'fresh body'));
    app()->instance(ItchHttpClientService::class, $client);
    $service = app(GameDataSyncService::class);

    expect(invokeGameDataSyncMethod($service, 'getCachedResponse', [$game, 'https://creator.itch.io/game', ['headers' => ['A' => 'B']], true])['body'])
        ->toBe('cached body')
        ->and(invokeGameDataSyncMethod($service, 'getCachedResponse', [$game, 'https://creator.itch.io/game', ['headers' => ['A' => 'B']], true])['body'])
        ->toBe('cached body');

    $service->clearHttpCache($game);

    expect(invokeGameDataSyncMethod($service, 'getCachedResponse', [$game, 'https://creator.itch.io/game', ['headers' => ['A' => 'B']], true])['body'])
        ->toBe('fresh body');
});

it('extracts devlog links and noindex metadata from itch HTML', function () {
    $game = Game::factory()->create([
        'url' => ['itch_io' => 'https://creator.itch.io/game'],
    ]);
    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], <<<'HTML'
            <html>
                <head><meta name="robots" content="nofollow, noindex"></head>
                <body><section id="devlog"><a href="https://creator.itch.io/game/devlog/1">Update</a></section></body>
            </html>
        HTML));
    app()->instance(ItchHttpClientService::class, $client);
    $service = app(GameDataSyncService::class);

    expect(invokeGameDataSyncMethod($service, 'getDevlogLink', [$game]))
        ->toBe('https://creator.itch.io/game/devlog/1');

    $doc = HTMLDocument::createFromString('<meta name="robots" content="NOINDEX">', LIBXML_NOERROR);
    expect(app(ItchGameMetadataRefresher::class)->hasNoindexTag($doc))->toBeTrue();

    $doc = HTMLDocument::createFromString('<meta name="robots" content="index">', LIBXML_NOERROR);
    expect(app(ItchGameMetadataRefresher::class)->hasNoindexTag($doc))->toBeFalse();
});

it('copies language support from previous versions or source language fallback', function () {
    ensureSyncLanguage('eng', 'English');
    ensureSyncLanguage('jpn', 'Japanese');
    $game = Game::factory()->create(['source_language_id' => 'jpn']);
    $previous = GameVersion::factory()->for($game)->create(['published_at' => now()->subDay()]);
    $previous->addSupportedLanguage('eng', false);
    $previous->addSupportedLanguage('jpn', true);
    $target = GameVersion::factory()->for($game)->create(['published_at' => now()]);

    invokeGameDataSyncMethod(app(GameDataSyncService::class), 'copyLanguageSupport', [$game, $target]);

    expect($target->supportedLanguages()->pluck('is_available', 'iso_code')->all())->toBe([
        'eng' => false,
        'jpn' => true,
    ]);

    $otherGame = Game::factory()->create(['source_language_id' => 'jpn']);
    $fallbackVersion = GameVersion::factory()->for($otherGame)->create();
    invokeGameDataSyncMethod(app(GameDataSyncService::class), 'copyLanguageSupport', [$otherGame, $fallbackVersion]);

    expect($fallbackVersion->supportedLanguages()->pluck('iso_code')->all())->toBe(['jpn']);
});

it('processes pending game jams and tags for saved games and leaves unsaved games untouched', function () {
    $service = app(GamePendingAssociationProcessor::class);
    $game = Game::factory()->create();
    $jam = GameJam::create(['name' => 'Jam', 'url' => 'https://itch.io/jam/test']);
    $tag = Tag::create(['name' => 'Drama']);
    $game->pendingGameJamId = [$jam->id];
    $game->pendingTagIds = [$tag->id];

    $service->processGameJams($game);
    $service->processTags($game);

    expect($game->gameJams()->whereKey($jam->id)->exists())->toBeTrue()
        ->and($game->tags()->whereKey($tag->id)->exists())->toBeTrue()
        ->and($game->pendingGameJamId)->toBe([])
        ->and($game->pendingTagIds)->toBe([]);

    $unsaved = new Game(['name' => 'Unsaved']);
    $unsaved->pendingGameJamId = [$jam->id];
    $unsaved->pendingTagIds = [$tag->id];
    $service->processGameJams($unsaved);
    $service->processTags($unsaved);

    expect($unsaved->pendingGameJamId)->toBe([$jam->id])
        ->and($unsaved->pendingTagIds)->toBe([$tag->id]);
});

it('compares screenshot source URLs while ignoring optimized variants', function () {
    $service = app(GameMetadataImageProcessor::class);

    $screenshotsA = [
        ['url' => 'https://img.example/a.png', 'thumbnail_url' => 'cached-a.webp'],
        ['url' => 'https://img.example/b.png', 'thumbnail_url' => 'cached-b.webp'],
    ];
    $screenshotsB = [
        ['url' => 'https://img.example/a.png', 'thumbnail_url' => 'different-a.webp'],
        ['url' => 'https://img.example/b.png', 'thumbnail_url' => 'different-b.webp'],
    ];

    expect($service->screenshotUrlsChanged($screenshotsA, $screenshotsB))->toBeFalse()
        ->and($service->screenshotUrlsChanged($screenshotsA, [['url' => 'https://img.example/c.png']]))->toBeTrue()
        ->and($service->extractScreenshotUrls(null))->toBe([])
        ->and($service->extractScreenshotUrls($screenshotsA))->toBe([
            'https://img.example/a.png',
            'https://img.example/b.png',
        ]);
});

it('retries screenshot processing when optimized variants are missing or incomplete', function () {
    Storage::fake('public');
    $service = app(GameMetadataImageProcessor::class);

    $completeScreenshots = [
        [
            'url' => 'https://img.example/a.png',
            'optimized' => [
                'small' => ['path' => 'screens/a-small.webp'],
                'default' => ['path' => 'screens/a-default.webp'],
                'large' => ['path' => 'screens/a-large.webp'],
            ],
        ],
    ];

    foreach ($completeScreenshots[0]['optimized'] as $variant) {
        Storage::disk('public')->put($variant['path'], 'processed image');
    }

    $missingOptimizedScreenshots = [
        ['url' => 'https://img.example/a.png'],
    ];

    $partialOptimizedScreenshots = [
        [
            'url' => 'https://img.example/a.png',
            'optimized' => [
                'default' => ['path' => 'screens/a-default.webp'],
            ],
        ],
    ];

    expect($service->needsScreenshotProcessing($completeScreenshots, $completeScreenshots))->toBeFalse()
        ->and($service->needsScreenshotProcessing($missingOptimizedScreenshots, $missingOptimizedScreenshots))->toBeTrue()
        ->and($service->needsScreenshotProcessing($partialOptimizedScreenshots, $partialOptimizedScreenshots))->toBeTrue()
        ->and($service->needsScreenshotProcessing($completeScreenshots, [['url' => 'https://img.example/old.png']]))->toBeTrue();
});

it('marks itch games invisible when version refresh receives a not found response', function () {
    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 987,
        'is_visible' => true,
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/987/uploads')
        ->andReturn(new Response(404, [], '{}'));
    app()->instance(ItchHttpClientService::class, $client);

    app(GameDataSyncService::class)->refreshVersion($game);
    $game->save();

    expect($game->refresh()->is_visible)->toBeFalse()
        ->and($game->gameVersions()->exists())->toBeFalse();
});

it('creates a fallback unknown version when a new itch game has no uploads', function () {
    ensureSyncLanguage('eng', 'English');

    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 654,
        'url' => ['itch_io' => 'https://creator.itch.io/no-uploads'],
        'source_language_id' => 'eng',
        'initially_published_at' => '2024-02-03 04:05:06',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/654/uploads')
        ->andReturn(new Response(200, [], '{}'));
    $client->shouldReceive('get')
        ->once()
        ->with('https://creator.itch.io/no-uploads', [], true)
        ->andReturn(new Response(200, [], '<section id="devlog"><a href="https://creator.itch.io/no-uploads/devlog/1">Update</a></section>'));
    app()->instance(ItchHttpClientService::class, $client);

    app(GameDataSyncService::class)->refreshVersion($game);

    $version = $game->gameVersions()->firstOrFail();

    expect($version->version)->toBe('Unknown')
        ->and($version->is_latest)->toBeTrue()
        ->and($version->devlog)->toBe('https://creator.itch.io/no-uploads/devlog/1')
        ->and($game->refresh()->uploads)->toBe([]);
});

it('creates an itch version from processable uploads and updates existing platform flags', function () {
    ensureSyncLanguage('eng', 'English');

    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 321,
        'url' => ['itch_io' => 'https://creator.itch.io/versioned'],
        'source_language_id' => 'eng',
        'game_engine' => 'Unity',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/321/uploads')
        ->andReturn(new Response(200, [], json_encode([
            'uploads' => [
                [
                    'id' => 10,
                    'filename' => 'Versioned-1.2-linux.tar.bz2',
                    'display_name' => 'Versioned (1.2)',
                    'md5_hash' => 'abc',
                    'updated_at' => '2024-03-04T05:06:07Z',
                    'build_id' => 77,
                    'build' => [
                        'user_version' => '1.2',
                        'updated_at' => '2024-03-04T05:06:08Z',
                    ],
                    'traits' => ['p_linux', 'p_windows'],
                    'type' => 'default',
                ],
                [
                    'id' => 11,
                    'filename' => 'Versioned-web.zip',
                    'display_name' => 'Web build',
                    'md5_hash' => 'web',
                    'updated_at' => '2024-03-04T05:06:09Z',
                    'traits' => [],
                    'type' => 'html',
                ],
            ],
        ])));
    $client->shouldReceive('get')
        ->once()
        ->with('https://creator.itch.io/versioned', [], true)
        ->andReturn(new Response(200, [], '<html><body>No devlog</body></html>'));
    app()->instance(ItchHttpClientService::class, $client);

    app(GameDataSyncService::class)->refreshVersion($game);
    $game->save();

    $version = $game->gameVersions()->firstOrFail();

    expect($version->version)->toBe('1.2')
        ->and($version->is_latest)->toBeTrue()
        ->and($version->is_linux)->toBeTrue()
        ->and($version->is_windows)->toBeTrue()
        ->and($version->is_web)->toBeTrue()
        ->and($version->supportedLanguages()->pluck('iso_code')->all())->toBe(['eng'])
        ->and($game->refresh()->uploads)->toHaveKeys(['10', '11']);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/321/uploads')
        ->andReturn(new Response(200, [], json_encode([
            'uploads' => [
                [
                    'id' => 10,
                    'filename' => 'Versioned-1.2-linux.tar.bz2',
                    'display_name' => 'Versioned (1.2)',
                    'md5_hash' => 'abc',
                    'updated_at' => '2024-03-04T05:06:07Z',
                    'build_id' => 77,
                    'build' => [
                        'user_version' => '1.2',
                        'updated_at' => '2024-03-04T05:06:08Z',
                    ],
                    'traits' => ['p_linux'],
                    'type' => 'default',
                ],
            ],
        ])));
    app()->instance(ItchHttpClientService::class, $client);

    app(GameDataSyncService::class)->refreshVersion($game->refresh());
    $game->save();

    expect($version->refresh()->is_windows)->toBeFalse()
        ->and($version->is_linux)->toBeTrue()
        ->and($version->is_web)->toBeFalse();
});

it('force reprocesses existing versions from the stored archive repository without downloading', function () {
    ensureSyncLanguage('eng', 'English');

    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 765,
        'url' => ['itch_io' => 'https://creator.itch.io/reprocess'],
        'source_language_id' => 'eng',
        'game_engine' => "Ren'Py",
        'is_paid' => false,
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.2',
    ]);
    $storedArchivePath = storage_path('app/testing/stash-restored.zip');

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/765/uploads')
        ->andReturn(new Response(200, [], json_encode([
            'uploads' => [
                [
                    'id' => 20,
                    'filename' => 'Reprocess-1.2-pc.zip',
                    'display_name' => 'Reprocess 1.2',
                    'md5_hash' => 'force',
                    'updated_at' => '2024-04-05T06:07:08Z',
                    'build_id' => 99,
                    'build' => [
                        'user_version' => '1.2',
                        'updated_at' => '2024-04-05T06:07:09Z',
                    ],
                    'traits' => ['p_windows'],
                    'type' => 'default',
                ],
            ],
        ])));
    app()->instance(ItchHttpClientService::class, $client);

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('downloadAndProcessToTemp')
        ->never();
    $archiveService->shouldReceive('getStoredArchive')
        ->once()
        ->with($game->id, $version->id)
        ->andReturn($storedArchivePath);
    $archiveService->shouldReceive('processArchive')
        ->once()
        ->with($storedArchivePath)
        ->andReturn(null);
    $archiveService->shouldReceive('getLastProcessingError')
        ->once()
        ->andReturn(null);
    $archiveService->shouldReceive('moveFromTempToStorage')
        ->never();
    app()->instance(GameArchiveService::class, $archiveService);

    app(GameDataSyncService::class)->refreshVersion($game, true);

    expect($game->gameVersions()->count())->toBe(1)
        ->and($version->refresh()->is_windows)->toBeTrue();
});

it('force reprocess downloads and persists an existing version when no DenKit archive exists', function () {
    ensureSyncLanguage('eng', 'English');

    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 766,
        'url' => ['itch_io' => 'https://creator.itch.io/reprocess-missing'],
        'source_language_id' => 'eng',
        'game_engine' => "Ren'Py",
        'is_paid' => false,
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.2',
    ]);
    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/766/uploads')
        ->andReturn(new Response(200, [], json_encode([
            'uploads' => [
                [
                    'id' => 20,
                    'filename' => 'Reprocess-1.2-pc.zip',
                    'display_name' => 'Reprocess 1.2',
                    'md5_hash' => 'force-missing',
                    'updated_at' => '2024-04-05T06:07:08Z',
                    'build_id' => 99,
                    'build' => [
                        'user_version' => '1.2',
                        'updated_at' => '2024-04-05T06:07:09Z',
                    ],
                    'traits' => ['p_windows'],
                    'type' => 'default',
                ],
            ],
        ])));
    app()->instance(ItchHttpClientService::class, $client);

    $tempDir = storage_path('framework/testing/reprocess-missing-' . uniqid());
    $tempPath = "{$tempDir}/archive.zip";
    File::ensureDirectoryExists($tempDir);
    File::put($tempPath, 'zip');

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('getStoredArchive')
        ->once()
        ->with($game->id, $version->id)
        ->andReturn(null);
    $archiveService->shouldReceive('getLastArchiveLookupFailure')
        ->once()
        ->andReturn(null);
    $archiveService->shouldReceive('getLastArchiveLookupError')
        ->once()
        ->andReturn('No completed build found for fvn-li/reprocess-missing:main version 1.2');
    $archiveService->shouldReceive('downloadAndProcessToTemp')
        ->once()
        ->with('https://creator.itch.io/reprocess-missing', 'Reprocess-1.2-pc.zip', 20, $game->id)
        ->andReturn([
            'temp_path' => $tempPath,
            'temp_dir' => $tempDir,
            'stats' => null,
            'filename' => 'Reprocess-1.2-pc.zip',
            'upload_id' => 20,
        ]);
    $archiveService->shouldReceive('getLastProcessingError')
        ->once()
        ->andReturn(null);
    $archiveService->shouldReceive('moveFromTempToStorage')
        ->once()
        ->with($tempPath, 'Reprocess-1.2-pc.zip', $game->id, $version->id, false);
    app()->instance(GameArchiveService::class, $archiveService);

    $repository = Mockery::mock(GameVersionArchiveRepositoryService::class);
    $repository->shouldReceive('discardLocalArchive')
        ->once()
        ->with(
            Mockery::on(fn (Game $argument): bool => $argument->is($game)),
            Mockery::on(fn (GameVersion $argument): bool => $argument->is($version))
        );
    $repository->shouldReceive('persistStoredArchive')
        ->once()
        ->with(Mockery::on(fn (Game $argument): bool => $argument->is($game)), Mockery::on(fn (GameVersion $argument): bool => $argument->is($version)), true)
        ->andReturn([
            'status' => 'persisted',
            'target' => 'fvn-li/reprocess-missing:main',
            'channel' => 'main',
            'build_id' => 99,
        ]);
    app()->instance(GameVersionArchiveRepositoryService::class, $repository);

    try {
        app(GameDataSyncService::class)->refreshVersion($game, true);
    } finally {
        File::deleteDirectory($tempDir);
    }

    expect($game->gameVersions()->count())->toBe(1)
        ->and($version->refresh()->is_windows)->toBeTrue();
});

it('force reprocess fails when the DenKit Stash lookup errors', function () {
    ensureSyncLanguage('eng', 'English');

    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 767,
        'url' => ['itch_io' => 'https://creator.itch.io/reprocess-unavailable'],
        'source_language_id' => 'eng',
        'game_engine' => "Ren'Py",
        'is_paid' => false,
    ]);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.2',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/767/uploads')
        ->andReturn(new Response(200, [], json_encode([
            'uploads' => [
                [
                    'id' => 20,
                    'filename' => 'Reprocess-1.2-pc.zip',
                    'display_name' => 'Reprocess 1.2',
                    'md5_hash' => 'force-unavailable',
                    'updated_at' => '2024-04-05T06:07:08Z',
                    'build_id' => 99,
                    'build' => [
                        'user_version' => '1.2',
                        'updated_at' => '2024-04-05T06:07:09Z',
                    ],
                    'traits' => ['p_windows'],
                    'type' => 'default',
                ],
            ],
        ])));
    app()->instance(ItchHttpClientService::class, $client);

    $archiveService = Mockery::mock(GameArchiveService::class);
    $archiveService->shouldReceive('getStoredArchive')
        ->once()
        ->with($game->id, $version->id)
        ->andReturn(null);
    $archiveService->shouldReceive('getLastArchiveLookupFailure')
        ->once()
        ->andReturn(new RuntimeException('Failed to query DenKit Stash build lookup: HTTP 401'));
    $archiveService->shouldReceive('downloadAndProcessToTemp')
        ->never();
    app()->instance(GameArchiveService::class, $archiveService);

    expect(fn () => app(GameDataSyncService::class)->refreshVersion($game, true))
        ->toThrow(DenKitStashUnavailableException::class, 'DenKit Stash is unavailable');
});

it('does not persist overlong itch upload user versions', function () {
    ensureSyncLanguage('eng', 'English');

    $game = Game::factory()->create([
        'platform' => 'itch_io',
        'itch_id' => 432,
        'url' => ['itch_io' => 'https://creator.itch.io/overlong-version'],
        'source_language_id' => 'eng',
        'game_engine' => 'Unity',
    ]);

    $client = Mockery::mock(ItchHttpClientService::class);
    $client->shouldReceive('get')
        ->once()
        ->with('https://api.itch.io/games/432/uploads')
        ->andReturn(new Response(200, [], json_encode([
            'uploads' => [
                [
                    'id' => 12,
                    'filename' => 'overlong-linux.tar.bz2',
                    'display_name' => 'Linux Build',
                    'md5_hash' => 'def',
                    'updated_at' => '2024-03-04T05:06:07Z',
                    'build_id' => 88,
                    'build' => [
                        'user_version' => '1.1.1.1.1.1.1.1.1.1.1',
                        'updated_at' => '2024-03-04T05:06:08Z',
                    ],
                    'traits' => ['p_linux'],
                    'type' => 'default',
                ],
            ],
        ])));
    $client->shouldReceive('get')
        ->once()
        ->with('https://creator.itch.io/overlong-version', [], true)
        ->andReturn(new Response(200, [], '<html><body>No devlog</body></html>'));
    app()->instance(ItchHttpClientService::class, $client);

    app(GameDataSyncService::class)->refreshVersion($game);

    $version = $game->gameVersions()->firstOrFail();

    expect($version->version)->toBe('2024.03.04')
        ->and(strlen($version->version))->toBeLessThanOrEqual(20);
});
