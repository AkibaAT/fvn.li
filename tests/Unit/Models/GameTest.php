<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\ItchHttpClientService;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->game = new Game;

    // Make protected methods accessible
    $reflection = new ReflectionClass($this->game);

    $this->extractVersion = $reflection->getMethod('extractVersion');
});

dataset('version extractions', [
    'from build user_version' => [
        [
            'build' => ['user_version' => '1.2.3'],
            'display_name' => 'Some Game',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '1.2.3',
        '1.2.3',
    ],
    'from user_version' => [
        [
            'build' => [],
            'display_name' => 'Demo – Linux and Windows',
            'filename' => 'alices-day-off-demo-linux-win.zip',
            'updated_at' => '2018-03-01T11:28:24.000000000Z',
            'user_version' => '0.3',
        ],
        '0.3',
        '0.3',
    ],
    'prioritize user_version' => [
        [
            'build' => [],
            'display_name' => 'Demo – Linux 3 and Windows',
            'filename' => 'alices-2-day-off-demo-linux-win.zip',
            'updated_at' => '2018-03-01T11:28:24.000000000Z',
            'user_version' => '0.3',
        ],
        '0.3',
        '0.3',
    ],
    'from display name explicit' => [
        [
            'build' => [],
            'display_name' => 'Game Version 2.0',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2.0',
        '2.0',
    ],
    'from display name implicit' => [
        [
            'build' => [],
            'display_name' => 'Game v1.5',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '1.5',
        '1.5',
    ],
    'from filename build number' => [
        [
            'build' => [],
            'display_name' => 'Game Release',
            'filename' => 'game-build23.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '23',
        '23',
    ],
    'from filename version' => [
        [
            'build' => [],
            'display_name' => 'Game Release',
            'filename' => 'game-1.0.4.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '1.0.4',
        '1.0.4',
    ],
    'from filename version with suffix' => [
        [
            'build' => [],
            'display_name' => 'PC and LINUX Versions',
            'filename' => 'ExtracurricularActivities-1.183pub-pc.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '1.183pub',
        '1.183pub',
    ],
    'fallback to date' => [
        [
            'build' => [],
            'display_name' => 'Game Release',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2024.01.17',
        null,
    ],
    'prioritize build version over display name' => [
        [
            'build' => ['user_version' => '2.0.0'],
            'display_name' => 'Version 1.0',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2.0.0',
        '2.0.0',
    ],
    'prioritize display name over filename' => [
        [
            'build' => [],
            'display_name' => 'Version 2.0',
            'filename' => 'game-1.0.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2.0',
        '2.0',
    ],
    'reject suspicious version in build' => [
        [
            'build' => ['user_version' => '9999.0'],
            'display_name' => 'Version 1.0',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '1.0',
        '1.0',
    ],
    'reject year-like version' => [
        [
            'build' => [],
            'display_name' => 'Version 2024.1',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2024.01.17',
        null, //
    ],
    'prefer version in brackets' => [
        [
            'build' => [],
            'display_name' => 'Chapter 2 (2.2.3) for PC and Linux',
            'filename' => 'ELoNR-2.2.3-pc.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2.2.3',
        '2.2.3',
    ],
    'prefer highest semantic version in display name' => [
        [
            'build' => [],
            'display_name' => 'Chapter 2 for PC and Linux 2.2.4',
            'filename' => 'ELoNR-2.2.3-pc.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2.2.4',
        '2.2.4',
    ],
    'prefer more specific semantic version number in display name' => [
        [
            'build' => [],
            'display_name' => 'Chapter 26 2.2.4 for PC and Linux',
            'filename' => 'ELoNR-pc.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2.2.4',
        '2.2.4',
    ],
]);

test('extract version with date fallback', function (array $upload, string $expected) {
    $result = $this->extractVersion->invoke($this->game, $upload, true);
    expect($result)->toBe($expected);
})->with('version extractions');

test('extract version without date fallback', function (array $upload, string $_, ?string $expectedWithoutDate) {
    $result = $this->extractVersion->invoke($this->game, $upload, false);
    expect($result)->toBe($expectedWithoutDate);
})->with('version extractions');

test('platform flags are updated on latest version when no new version is created', function () {
    $game = Game::factory()->create([
        'itch_id' => 12345,
        'uploads' => [
            '1' => [
                'display_name' => 'Game v1.0',
                'md5_hash' => 'abc123',
                'updated_at' => '2024-01-01T00:00:00Z',
                'build_id' => null,
                'build_updated_at' => null,
                'user_version' => null,
                'filename' => 'game-1.0.zip',
                'traits' => ['p_windows'],
                'type' => 'default',
            ],
        ],
    ]);

    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'is_latest' => true,
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
    ]);

    // Mock the itch.io API response with additional platform support but same version
    // We need to change something to trigger hasChanges = true, so we'll change the MD5 hash
    $mockResponse = new Response(200, [], json_encode([
        'uploads' => [
            [
                'id' => 1,
                'filename' => 'game-1.0.zip',
                'display_name' => 'Game v1.0', // Same version
                'md5_hash' => 'def456', // Changed MD5 hash to trigger update
                'updated_at' => '2024-01-01T00:00:00Z',
                'build_id' => null,
                'build' => [],
                'traits' => ['p_windows', 'p_linux'], // Added Linux support
                'type' => 'default',
            ],
        ],
    ]));

    // Mock the HTTP client
    $mockClient = Mockery::mock(ItchHttpClientService::class);
    $mockClient->shouldReceive('get')
        ->with("https://api.itch.io/games/{$game->itch_id}/uploads")
        ->andReturn($mockResponse);

    $this->app->instance(ItchHttpClientService::class, $mockClient);

    // Call refreshVersion - this should update platform flags without creating a new version
    $game->refreshVersion();

    // Refresh the version from database
    $version->refresh();

    expect($version->is_windows)->toBeTrue()
        ->and($version->is_linux)->toBeTrue()
        ->and($version->is_mac)->toBeFalse()
        ->and($version->is_android)->toBeFalse()
        ->and($version->is_web)->toBeFalse()
        ->and($game->gameVersions()->count())->toBe(1)
        ->and($version->is_latest)->toBeTrue();
});

test('refresh version tolerates browser uploads without filenames', function () {
    $game = Game::factory()->create([
        'itch_id' => 12345,
        'uploads' => [],
        'game_engine' => 'Unity',
    ]);

    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
        'is_latest' => true,
        'is_windows' => true,
        'is_linux' => false,
        'is_mac' => false,
        'is_android' => false,
        'is_web' => false,
    ]);

    $mockResponse = new Response(200, [], json_encode([
        'uploads' => [
            [
                'id' => 1,
                'display_name' => 'Play in browser',
                'md5_hash' => null,
                'updated_at' => '2024-01-01T00:00:00Z',
                'build_id' => null,
                'build' => [],
                'traits' => [],
                'type' => 'html',
            ],
            [
                'id' => 2,
                'filename' => 'rivencliff-1.0-pc.zip',
                'display_name' => 'Rivencliff 1.0 PC',
                'md5_hash' => 'abc123',
                'updated_at' => '2024-01-02T00:00:00Z',
                'build_id' => null,
                'build' => [],
                'traits' => ['p_windows', 'p_linux'],
                'type' => 'default',
            ],
        ],
    ]));

    $mockClient = Mockery::mock(ItchHttpClientService::class);
    $mockClient->shouldReceive('get')
        ->with("https://api.itch.io/games/{$game->itch_id}/uploads")
        ->andReturn($mockResponse);

    $this->app->instance(ItchHttpClientService::class, $mockClient);

    $game->refreshVersion();

    $version->refresh();

    expect($game->uploads[1]['filename'])->toBe('')
        ->and($game->uploads[1]['type'])->toBe('html')
        ->and($game->uploads[2]['filename'])->toBe('rivencliff-1.0-pc.zip')
        ->and($version->is_windows)->toBeTrue()
        ->and($version->is_linux)->toBeTrue()
        ->and($version->is_web)->toBeTrue()
        ->and($game->gameVersions()->count())->toBe(1);
});
