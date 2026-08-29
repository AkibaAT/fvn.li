<?php

declare(strict_types=1);

use App\Services\GameVersionParser;

test('top level user versions must fit the game version storage limit', function () {
    $parser = new GameVersionParser;

    expect($parser->isProbableVersion('1.1.1.1.1.1.1.1.1.1.1'))->toBeFalse()
        ->and($parser->extractVersion([
            'user_version' => '1.1.1.1.1.1.1.1.1.1.1',
            'display_name' => 'Linux build',
            'filename' => 'game-linux.zip',
            'updated_at' => '2026-05-11T10:00:00Z',
        ], true))->toBe('2026.05.11');
});

test('storage length validation still accepts maximum length versions', function () {
    $parser = new GameVersionParser;
    $version = '11.1.1.1.1.1.1.1.1.1';

    expect(strlen($version))->toBe(20)
        ->and($parser->isProbableVersion($version))->toBeTrue()
        ->and($parser->extractVersion(['user_version' => $version]))->toBe($version);
});

test('authoritative upload versions beat parenthesized display name versions', function () {
    $parser = new GameVersionParser;

    expect($parser->extractVersion([
        'build' => ['user_version' => '2.0.0'],
        'user_version' => null,
        'display_name' => 'Game build (1.0)',
        'filename' => 'game.zip',
        'updated_at' => '2026-05-11T10:00:00Z',
    ]))->toBe('2.0.0')
        ->and($parser->extractVersion([
            'build' => [],
            'user_version' => '2.0.0',
            'display_name' => 'Game build (1.0)',
            'filename' => 'game.zip',
            'updated_at' => '2026-05-11T10:00:00Z',
        ]))->toBe('2.0.0');
});

test('versions in filename parentheses are not truncated at the decimal point', function () {
    $parser = new GameVersionParser;

    expect($parser->extractVersion([
        'filename' => 'Nekojishi-pc(1.06).zip',
        'display_name' => null,
        'user_version' => null,
    ]))->toBe('1.06');
});
