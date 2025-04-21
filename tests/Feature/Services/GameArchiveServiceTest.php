<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameArchiveServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameArchiveService $archiveService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GameStatsService to avoid actual archive processing
        $statsService = $this->createMock(GameStatsService::class);
        $this->archiveService = new GameArchiveService($statsService);

        // Set up the storage facade to use the 'testing' disk
        Storage::fake('local');
    }

    /**
     * @test
     */
    public function cleanup_old_version_downloads(): void
    {
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
        $this->createTestFile($game->id, $version1->id, 'game_v1.zip');
        $this->createTestFile($game->id, $version2->id, 'game_v2.zip');
        $this->createTestFile($game->id, $version3->id, 'game_v3.zip');

        // Verify all files exist
        $this->checkFileExists($game->id, $version1->id, 'game_v1.zip');
        $this->checkFileExists($game->id, $version2->id, 'game_v2.zip');
        $this->checkFileExists($game->id, $version3->id, 'game_v3.zip');

        // Run the cleanup method
        $this->archiveService->cleanupOldVersionDownloads($game->id);

        // Verify only the latest version's file still exists
        $this->checkFileNotExists($game->id, $version1->id, 'game_v1.zip');
        $this->checkFileNotExists($game->id, $version2->id, 'game_v2.zip');
        $this->checkFileExists($game->id, $version3->id, 'game_v3.zip');
    }

    /**
     * @test
     */
    public function cleanup_all_old_version_downloads(): void
    {
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
        $this->createTestFile($game1->id, $game1v1->id, 'game1_v1.zip');
        $this->createTestFile($game1->id, $game1v2->id, 'game1_v2.zip');
        $this->createTestFile($game2->id, $game2v1->id, 'game2_v1.zip');
        $this->createTestFile($game2->id, $game2v2->id, 'game2_v2.zip');

        // Run the cleanup method for all games
        $count = $this->archiveService->cleanupAllOldVersionDownloads();

        // Verify the count is correct
        $this->assertEquals(2, $count);

        // Verify only the latest version's file still exists for each game
        $this->checkFileNotExists($game1->id, $game1v1->id, 'game1_v1.zip');
        $this->checkFileExists($game1->id, $game1v2->id, 'game1_v2.zip');
        $this->checkFileNotExists($game2->id, $game2v1->id, 'game2_v1.zip');
        $this->checkFileExists($game2->id, $game2v2->id, 'game2_v2.zip');
    }

    /**
     * Create a test file for a game version
     */
    private function createTestFile(int $gameId, int $versionId, string $filename): void
    {
        $path = "games/{$gameId}/{$versionId}";
        Storage::makeDirectory($path);
        Storage::put("{$path}/{$filename}", 'Test file content');
    }

    /**
     * Check if a file exists for a game version
     */
    private function checkFileExists(int $gameId, int $versionId, string $filename): void
    {
        $path = "games/{$gameId}/{$versionId}/{$filename}";
        $this->assertTrue(Storage::exists($path), "File {$path} does not exist");
    }

    /**
     * Check if a file does not exist for a game version
     */
    private function checkFileNotExists(int $gameId, int $versionId, string $filename): void
    {
        $path = "games/{$gameId}/{$versionId}/{$filename}";
        $this->assertFalse(Storage::exists($path), "File {$path} exists but should not");
    }
}
