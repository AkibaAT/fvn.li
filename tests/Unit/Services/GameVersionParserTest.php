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
