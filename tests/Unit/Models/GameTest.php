<?php

declare(strict_types=1);

use App\Models\Game;

beforeEach(function () {
    $this->game = new Game;

    // Make protected methods accessible
    $reflection = new ReflectionClass($this->game);

    $this->parseSemanticVersion = $reflection->getMethod('parseSemanticVersion');

    $this->isProbableVersion = $reflection->getMethod('isProbableVersion');

    $this->extractVersion = $reflection->getMethod('extractVersion');
});

dataset('semantic versions', [
    'simple version' => ['1.0', [[1, 0], '']],
    'three components' => ['1.2.3', [[1, 2, 3], '']],
    'with v prefix' => ['v2.0.0', [[2, 0, 0], '']],
    'with version prefix' => ['version1.0', [[1, 0], '']],
    'with letter suffix' => ['1.0a', [[1, 0], 'a']],
    'many components' => ['1.2.3.4.5', [[1, 2, 3, 4, 5], '']],
    'invalid format' => ['abc', null],
    'empty string' => ['', null],
    'mixed characters' => ['1.0.abc', null],
]);

dataset('probable versions', [
    'standard version' => ['1.0', true],
    'three components' => ['1.2.3', true],
    'with letter suffix' => ['1.0a', true],
    'reasonable large version' => ['99.99.99', true],
    'year-like first number' => ['2024.1', false],
    'too large first number' => ['3000.1', false],
    'too large component' => ['1.999999.1', false],
    'invalid format' => ['abc', false],
    'empty string' => ['', false],
    'mixed characters' => ['1.0.abc', false],
]);

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
        null,
    ],
]);

test('parse semantic version', function (string $input, ?array $expected) {
    $result = $this->parseSemanticVersion->invoke($this->game, $input);
    expect($result)->toBe($expected);
})->with('semantic versions');

test('is probable version', function (string $input, bool $expected) {
    $result = $this->isProbableVersion->invoke($this->game, $input);
    expect($result)->toBe($expected);
})->with('probable versions');

test('extract version with date fallback', function (array $upload, string $expected) {
    $result = $this->extractVersion->invoke($this->game, $upload, true);
    expect($result)->toBe($expected);
})->with('version extractions');

test('extract version without date fallback', function (array $upload, string $expected, ?string $expectedWithoutDate) {
    $result = $this->extractVersion->invoke($this->game, $upload, false);
    expect($result)->toBe($expectedWithoutDate);
})->with('version extractions');
