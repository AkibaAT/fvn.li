<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveService;
use App\Services\GameStatsService;

test('reprocess current game archive imports stats from stored archive for latest version only', function () {
    $game = Game::factory()->create([
        'name' => 'Current Archive',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now()->subDay(),
        'is_latest' => false,
    ]);
    $currentVersion = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.1',
        'published_at' => now(),
    ]);

    $archiveRecorder = (object) [
        'storedArchive' => '/tmp/current-archive.zip',
        'stats' => ['languages' => []],
        'getStoredArchiveCalls' => [],
        'processArchiveCalls' => [],
    ];
    $statsRecorder = (object) [
        'saveVersionStatsCalls' => [],
    ];

    $this->app->instance(GameArchiveService::class, new ReprocessRecordingGameArchiveService($archiveRecorder));
    $this->app->instance(GameStatsService::class, new ReprocessRecordingGameStatsService($statsRecorder));

    $this->artisan('games:reprocess-current-archive', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('Imported stats for current version 1.1')
        ->assertExitCode(0);

    expect($archiveRecorder->getStoredArchiveCalls)->toBe([
        [$game->id, $currentVersion->id],
    ]);
    expect($archiveRecorder->processArchiveCalls)->toBe([
        ['/tmp/current-archive.zip'],
    ]);
    expect($statsRecorder->saveVersionStatsCalls)->toHaveCount(1);
    expect($statsRecorder->saveVersionStatsCalls[0]['version_id'])->toBe($currentVersion->id);
    expect($statsRecorder->saveVersionStatsCalls[0]['stats'])->toBe(['languages' => []]);
});

test('reprocess current game archive skips when current version has no stored archive', function () {
    $game = Game::factory()->create([
        'name' => 'No Archive',
        'game_engine' => "Ren'Py",
        'is_visible' => true,
    ]);
    $currentVersion = GameVersion::factory()->latest()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);

    $archiveRecorder = (object) [
        'storedArchive' => null,
        'stats' => ['languages' => []],
        'getStoredArchiveCalls' => [],
        'processArchiveCalls' => [],
    ];
    $statsRecorder = (object) [
        'saveVersionStatsCalls' => [],
    ];

    $this->app->instance(GameArchiveService::class, new ReprocessRecordingGameArchiveService($archiveRecorder));
    $this->app->instance(GameStatsService::class, new ReprocessRecordingGameStatsService($statsRecorder));

    $this->artisan('games:reprocess-current-archive', [
        '--game-id' => $game->id,
    ])
        ->expectsOutputToContain('No stored archive found for current version 1.0')
        ->assertExitCode(0);

    expect($archiveRecorder->getStoredArchiveCalls)->toBe([
        [$game->id, $currentVersion->id],
    ]);
    expect($archiveRecorder->processArchiveCalls)->toBe([]);
    expect($statsRecorder->saveVersionStatsCalls)->toBe([]);
});

class ReprocessRecordingGameArchiveService extends GameArchiveService
{
    public function __construct(
        private readonly object $recorder
    ) {}

    public function getStoredArchive(int $gameId, int $versionId): ?string
    {
        $this->recorder->getStoredArchiveCalls[] = [$gameId, $versionId];

        return $this->recorder->storedArchive;
    }

    public function processArchive(string $archivePath): ?array
    {
        $this->recorder->processArchiveCalls[] = [$archivePath];

        return $this->recorder->stats;
    }
}

readonly class ReprocessRecordingGameStatsService extends GameStatsService
{
    public function __construct(
        private object $recorder
    ) {}

    public function saveVersionStats(
        GameVersion $version,
        array $stats,
        string $defaultLanguage = 'eng',
        ?Game $game = null
    ): void {
        $this->recorder->saveVersionStatsCalls[] = [
            'version_id' => $version->id,
            'stats' => $stats,
            'default_language' => $defaultLanguage,
            'game_id' => $game?->id,
        ];
    }
}
