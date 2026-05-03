<?php

use App\ValueObjects\Upload;
use Illuminate\Support\Collection;

function uploadValueObject(array $overrides = []): Upload
{
    return new Upload(
        id: $overrides['id'] ?? 1,
        filename: $overrides['filename'] ?? 'game-linux.zip',
        displayName: $overrides['display_name'] ?? 'Game Linux',
        md5Hash: $overrides['md5_hash'] ?? 'hash',
        updatedAt: $overrides['updated_at'] ?? '2026-05-01 10:00:00',
        buildId: $overrides['build_id'] ?? 10,
        buildUpdatedAt: $overrides['build_updated_at'] ?? '2026-05-01 11:00:00',
        userVersion: $overrides['user_version'] ?? '1.5c',
        traits: $overrides['traits'] ?? ['p_linux'],
        type: $overrides['type'] ?? ''
    );
}

test('upload value object builds from arrays and serializes back to itch metadata shape', function () {
    $upload = Upload::fromArray([
        'filename' => 'build.tar.gz',
        'display_name' => 'Linux Build',
        'md5_hash' => 'abc123',
        'updated_at' => '2026-05-01 10:00:00',
        'build_id' => '42',
        'build_updated_at' => '2026-05-01 12:00:00',
        'user_version' => '2.0',
        'traits' => ['p_linux'],
        'type' => '',
    ], 99);

    expect($upload->id)->toBe(99)
        ->and($upload->buildId)->toBe(42)
        ->and($upload->isProcessable())->toBeTrue()
        ->and($upload->isLinux())->toBeTrue()
        ->and($upload->hasLinuxFileName())->toBeTrue()
        ->and($upload->isZip())->toBeFalse()
        ->and($upload->toArray())->toMatchArray([
            'filename' => 'build.tar.gz',
            'display_name' => 'Linux Build',
            'md5_hash' => 'abc123',
            'updated_at' => '2026-05-01 10:00:00',
            'build_id' => 42,
            'build_updated_at' => '2026-05-01 12:00:00',
            'user_version' => '2.0',
            'traits' => ['p_linux'],
            'type' => '',
        ]);
});

test('upload processability rejects web books mac builds and non archives', function () {
    expect(uploadValueObject(['type' => 'html'])->isProcessable())->toBeFalse()
        ->and(uploadValueObject(['type' => 'book'])->isProcessable())->toBeFalse()
        ->and(uploadValueObject(['filename' => 'game-mac.zip', 'traits' => ['p_osx']])->isProcessable())->toBeFalse()
        ->and(uploadValueObject(['filename' => 'readme.txt'])->isProcessable())->toBeFalse()
        ->and(uploadValueObject(['filename' => 'game.tar.bz2'])->isProcessable())->toBeTrue()
        ->and(uploadValueObject(['filename' => 'game.apk', 'traits' => ['p_android']])->isAndroid())->toBeTrue()
        ->and(uploadValueObject(['filename' => 'game-pc.zip', 'display_name' => null, 'traits' => []])->hasPcFileName())->toBeTrue();
});

test('upload sorting prefers current processable linux builds and version suffixes', function () {
    $olderWindows = uploadValueObject([
        'id' => 1,
        'filename' => 'game-pc.zip',
        'display_name' => 'PC Build',
        'updated_at' => '2026-05-01 10:00:00',
        'build_updated_at' => '2026-05-01 10:00:00',
        'user_version' => '1.5',
        'traits' => ['p_windows'],
    ]);
    $linuxSameBatch = uploadValueObject([
        'id' => 2,
        'filename' => 'game-linux.tar.gz',
        'display_name' => 'Linux Build',
        'updated_at' => '2026-05-02 10:00:00',
        'build_updated_at' => '2026-05-02 10:00:00',
        'user_version' => '1.5c',
        'traits' => ['p_linux'],
    ]);
    $newerMac = uploadValueObject([
        'id' => 3,
        'filename' => 'game-mac.zip',
        'updated_at' => '2026-05-03 10:00:00',
        'build_updated_at' => '2026-05-03 10:00:00',
        'traits' => ['p_osx'],
    ]);

    $sorted = Upload::sort(collect([$olderWindows, $newerMac, $linuxSameBatch]))->values();

    expect($sorted)->toHaveCount(2)
        ->and($sorted[0]->id)->toBe(2)
        ->and(Upload::getBest(collect([$olderWindows, $linuxSameBatch]))->id)->toBe(2)
        ->and($linuxSameBatch->compareTo($olderWindows))->toBeLessThan(0);
});

test('upload collection conversion and odd version fallback paths are covered', function () {
    $uploads = Upload::fromCollection(new Collection([
        10 => [
            'filename' => 'view',
            'display_name' => 'Strange build',
            'updated_at' => '2026-05-01 10:00:00',
            'user_version' => 'release candidate',
            'traits' => ['p_linux'],
        ],
        11 => [
            'filename' => 'archive.zip',
            'display_name' => 'Archive',
            'updated_at' => '2026-05-02 10:00:00',
            'build_id' => null,
            'user_version' => 'also odd',
            'traits' => ['p_windows'],
        ],
    ]));

    expect($uploads)->toHaveCount(2)
        ->and($uploads[10]->id)->toBe(10)
        ->and($uploads[11]->buildId)->toBeNull()
        ->and($uploads[10]->isProcessable())->toBeFalse()
        ->and($uploads[11]->isProcessable())->toBeTrue();

    expect($uploads[11]->compareTo(uploadValueObject([
        'filename' => 'other.zip',
        'user_version' => 'not semver',
        'traits' => ['p_windows'],
    ])))->toBeInt();
});
