<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Mock the GameStatsService to avoid actual archive processing
    $statsService = $this->createMock(GameStatsService::class);
    $this->archiveService = new GameArchiveService($statsService);

    // Set up the storage facade to use the 'testing' disk
    Storage::fake('local');
});

/**
 * Create a test file for a game version
 */
function createTestFile(int $gameId, int $versionId, string $filename): void
{
    $path = "games/{$gameId}/{$versionId}";
    Storage::makeDirectory($path);
    Storage::put("{$path}/{$filename}", 'Test file content');
}

/**
 * Check if a file exists for a game version
 */
function checkFileExists(int $gameId, int $versionId, string $filename): void
{
    $path = "games/{$gameId}/{$versionId}/{$filename}";
    expect(Storage::exists($path))->toBeTrue("File {$path} does not exist");
}

/**
 * Check if a file does not exist for a game version
 */
function checkFileNotExists(int $gameId, int $versionId, string $filename): void
{
    $path = "games/{$gameId}/{$versionId}/{$filename}";
    expect(Storage::exists($path))->toBeFalse("File {$path} exists but should not");
}

test('cleanup old version downloads', function () {
    // Create a game with multiple versions
    $game = Game::factory()->create();

    // Create three versions for the game
    $version1 = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDays(30),
        'is_latest' => false,
    ]);

    $version2 = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.1',
        'published_at' => now()->subDays(15),
        'is_latest' => false,
    ]);

    $version3 = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.2',
        'published_at' => now(),
        'is_latest' => true,
    ]);

    // Create test files for each version
    createTestFile($game->id, $version1->id, 'game_v1.zip');
    createTestFile($game->id, $version2->id, 'game_v2.zip');
    createTestFile($game->id, $version3->id, 'game_v3.zip');

    // Verify all files exist
    checkFileExists($game->id, $version1->id, 'game_v1.zip');
    checkFileExists($game->id, $version2->id, 'game_v2.zip');
    checkFileExists($game->id, $version3->id, 'game_v3.zip');

    // Run the cleanup method
    $this->archiveService->cleanupOldVersionDownloads($game->id);

    // Verify only the latest version's file still exists
    checkFileNotExists($game->id, $version1->id, 'game_v1.zip');
    checkFileNotExists($game->id, $version2->id, 'game_v2.zip');
    checkFileExists($game->id, $version3->id, 'game_v3.zip');
});

test('cleanup all old version downloads', function () {
    // Create two games with multiple versions each
    $game1 = Game::factory()->create();
    $game2 = Game::factory()->create();

    // Create versions for game 1
    $game1v1 = GameVersion::factory()->create([
        'game_id' => $game1->id,
        'version' => '1.0',
        'published_at' => now()->subDays(10),
        'is_latest' => false,
    ]);

    $game1v2 = GameVersion::factory()->create([
        'game_id' => $game1->id,
        'version' => '1.1',
        'published_at' => now(),
        'is_latest' => true,
    ]);

    // Create versions for game 2
    $game2v1 = GameVersion::factory()->create([
        'game_id' => $game2->id,
        'version' => '1.0',
        'published_at' => now()->subDays(5),
        'is_latest' => false,
    ]);

    $game2v2 = GameVersion::factory()->create([
        'game_id' => $game2->id,
        'version' => '1.1',
        'published_at' => now(),
        'is_latest' => true,
    ]);

    // Create test files for each version
    createTestFile($game1->id, $game1v1->id, 'game1_v1.zip');
    createTestFile($game1->id, $game1v2->id, 'game1_v2.zip');
    createTestFile($game2->id, $game2v1->id, 'game2_v1.zip');
    createTestFile($game2->id, $game2v2->id, 'game2_v2.zip');

    // Run the cleanup method for all games
    $count = $this->archiveService->cleanupAllOldVersionDownloads();

    // Verify the count is correct
    expect($count)->toBe(2);

    // Verify only the latest version's file still exists for each game
    checkFileNotExists($game1->id, $game1v1->id, 'game1_v1.zip');
    checkFileExists($game1->id, $game1v2->id, 'game1_v2.zip');
    checkFileNotExists($game2->id, $game2v1->id, 'game2_v1.zip');
    checkFileExists($game2->id, $game2v2->id, 'game2_v2.zip');
});

test('download filename is resolved from content disposition header', function () {
    $method = new ReflectionMethod($this->archiveService, 'getDownloadFilename');

    $filename = $method->invoke(
        $this->archiveService,
        new Response(200, [
            'Content-Disposition' => 'attachment; filename="RivencliffSunbath-1.1.4-linux.tar.bz2"',
        ]),
        'rivencliff-sunbath-linux.zip'
    );

    expect($filename)->toBe('RivencliffSunbath-1.1.4-linux.tar.bz2');
});

test('download filename supports encoded content disposition filename', function () {
    $method = new ReflectionMethod($this->archiveService, 'getDownloadFilename');

    $filename = $method->invoke(
        $this->archiveService,
        new Response(200, [
            'Content-Disposition' => "attachment; filename*=UTF-8''RivencliffSunbath-1.1.4-linux.tar.bz2",
        ]),
        'rivencliff-sunbath-linux.zip'
    );

    expect($filename)->toBe('RivencliffSunbath-1.1.4-linux.tar.bz2');
});

test('process archive strips file statistics from optimized archives', function () {
    $archivePath = tempnam(sys_get_temp_dir(), 'optimized_archive_').'.zip';
    $zip = new ZipArchive;
    expect($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    try {
        $zip->addFromString('.fvn-archive-metadata.json', json_encode([
            'schema' => 'fvn.archive_optimization.v1',
            'original_archive' => [
                'filename' => 'game.zip',
                'sha256' => str_repeat('a', 64),
            ],
            'original_files' => [
                [
                    'path' => 'game/images/bg.png',
                    'size' => 123,
                    'sha256' => str_repeat('b', 64),
                ],
            ],
        ]));
        $zip->addFromString('game/script.rpy', 'label start:');
    } finally {
        $zip->close();
    }

    $statsService = $this->createMock(GameStatsService::class);
    $statsService->expects($this->once())
        ->method('extractGameStats')
        ->with($archivePath)
        ->willReturn([
            'languages' => ['eng' => ['blocks' => 1, 'words' => 2]],
            'file_statistics' => [
                'summary' => ['total_images' => 1],
                'images' => ['webp' => ['count' => 1, 'total_size' => 42]],
            ],
        ]);

    try {
        $stats = (new GameArchiveService($statsService))->processArchive($archivePath);
    } finally {
        @unlink($archivePath);
    }

    expect($stats)->toHaveKey('languages')
        ->and($stats)->not->toHaveKey('file_statistics');
});
