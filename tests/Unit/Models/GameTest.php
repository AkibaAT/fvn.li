<?php

declare(strict_types=1);

use App\Models\Game;

beforeEach(function () {
    $this->game = new Game;

    // Make protected methods accessible
    $reflection = new ReflectionClass($this->game);

    $this->parseSemanticVersion = $reflection->getMethod('parseSemanticVersion');
    $this->parseSemanticVersion->setAccessible(true);

    $this->isProbableVersion = $reflection->getMethod('isProbableVersion');
    $this->isProbableVersion->setAccessible(true);

    $this->extractVersion = $reflection->getMethod('extractVersion');
    $this->extractVersion->setAccessible(true);
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
    ],
    'from display name explicit' => [
        [
            'build' => [],
            'display_name' => 'Game Version 2.0',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
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
    ],
    'from filename build number' => [
        [
            'build' => [],
            'display_name' => 'Game Release',
            'filename' => 'game-build23.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
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
    ],
    'from filename version with suffix' => [
        [
            'build' => [],
            'display_name' => 'PC and LINUX Versions',
            'filename' => 'ExtracurricularActivities-1.183pub-pc.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '1.183',
    ],
    'fallback to date' => [
        [
            'build' => [],
            'display_name' => 'Game Release',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
        '2024.01.17',
    ],
    'prioritize build version over display name' => [
        [
            'build' => ['user_version' => '2.0.0'],
            'display_name' => 'Version 1.0',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
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
    ],
    'reject suspicious version in build' => [
        [
            'build' => ['user_version' => '9999.0'],
            'display_name' => 'Version 1.0',
            'filename' => 'game.zip',
            'updated_at' => '2024-01-17T00:00:00Z',
        ],
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

test('extract version', function (array $upload, string $expected) {
    $result = $this->extractVersion->invoke($this->game, $upload);
    expect($result)->toBe($expected);
})->with('version extractions');
