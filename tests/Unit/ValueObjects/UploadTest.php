<?php

declare(strict_types=1);

use App\ValueObjects\Upload;

function uploadValueObjectForTest(array $overrides): Upload
{
    return Upload::fromArray(array_merge([
        'filename' => 'game-1.0-pc.zip',
        'display_name' => null,
        'md5_hash' => null,
        'updated_at' => '2025-09-20T22:25:28Z',
        'build_id' => null,
        'build_updated_at' => null,
        'user_version' => null,
        'traits' => ['p_windows', 'p_linux'],
        'type' => 'default',
    ], $overrides), 1);
}

test('mac only platform uploads are not processable', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'Chapter_2_teaser-mac.zip',
        'traits' => ['p_osx'],
    ]);

    expect($upload->isProcessable())->toBeFalse();
});

test('best upload ignores mac candidate and selects windows or linux archive', function () {
    $uploads = collect([
        uploadValueObjectForTest([
            'filename' => 'Chapter_2_teaser-mac.zip',
            'traits' => ['p_osx'],
        ]),
        uploadValueObjectForTest([
            'filename' => 'Chapter_2_teaser-win.zip',
            'traits' => ['p_windows'],
        ]),
    ]);

    expect(Upload::getBest($uploads)?->filename)->toBe('Chapter_2_teaser-win.zip');
});

test('android archives are not processable for stats extraction', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'whodunnit-android.zip',
        'traits' => ['p_android'],
    ]);

    expect($upload->isProcessable())->toBeFalse();
});

test('android filenames are not processable even without platform traits', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'whodunnit-android.zip',
        'traits' => [],
    ]);

    expect($upload->isProcessable())->toBeFalse();
});

test('combined desktop archives remain processable even when they include mac', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'whodunnit-win-linux-mac.zip',
        'traits' => ['p_windows', 'p_linux', 'p_osx'],
    ]);

    expect($upload->isProcessable())->toBeTrue();
});

test('best upload prefers combined desktop archive over newer android archive', function () {
    $uploads = collect([
        uploadValueObjectForTest([
            'filename' => 'whodunnit-win-linux-mac.zip',
            'updated_at' => '2026-06-19T19:14:18Z',
            'build_updated_at' => '2026-06-19T19:14:18Z',
            'traits' => ['p_windows', 'p_linux', 'p_osx'],
        ]),
        uploadValueObjectForTest([
            'filename' => 'whodunnit-android.zip',
            'updated_at' => '2026-06-19T20:13:00Z',
            'build_updated_at' => '2026-06-19T21:30:47Z',
            'traits' => ['p_android'],
        ]),
    ]);

    expect(Upload::getBest($uploads)?->filename)->toBe('whodunnit-win-linux-mac.zip');
});

test('untagged zip archives are processable as a final fallback', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'mystery-build.zip',
        'traits' => [],
    ]);

    expect($upload->isProcessable())->toBeTrue();
});

test('untagged non zip archives are not processable fallback candidates', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'mystery-build.tar.bz2',
        'traits' => [],
    ]);

    expect($upload->isProcessable())->toBeFalse();
});

test('best upload priority is linux then windows then untagged zip before recency', function () {
    $uploads = collect([
        uploadValueObjectForTest([
            'filename' => 'game-generic.zip',
            'updated_at' => '2026-06-21T00:00:00Z',
            'traits' => [],
        ]),
        uploadValueObjectForTest([
            'filename' => 'game-windows.zip',
            'updated_at' => '2026-06-20T00:00:00Z',
            'traits' => ['p_windows'],
        ]),
        uploadValueObjectForTest([
            'filename' => 'game-linux.tar.bz2',
            'updated_at' => '2026-06-19T00:00:00Z',
            'traits' => ['p_linux'],
        ]),
    ]);

    expect(Upload::getBest($uploads)?->filename)->toBe('game-linux.tar.bz2');
});

test('linux and pc archives remain processable', function (array $data) {
    expect(uploadValueObjectForTest($data)->isProcessable())->toBeTrue();
})->with([
    'linux tar bz2' => [[
        'filename' => 'Game-1.0-linux.tar.bz2',
        'traits' => ['p_linux'],
    ]],
    'pc zip' => [[
        'filename' => 'Game-1.0-pc.zip',
        'traits' => ['p_windows', 'p_linux'],
    ]],
]);

test('demo uploads are detected from trait and common names', function (array $data) {
    expect(uploadValueObjectForTest($data)->isDemo())->toBeTrue();
})->with([
    'demo trait' => [[
        'traits' => ['p_windows', 'demo'],
    ]],
    'demo filename' => [[
        'filename' => 'Game-1.0-demo-pc.zip',
    ]],
    'free version display name' => [[
        'display_name' => 'Free Version',
    ]],
]);

test('non demo uploads are not marked as demo', function () {
    expect(uploadValueObjectForTest([
        'filename' => 'Game-1.0-pc.zip',
        'display_name' => 'Windows + Linux',
    ])->isDemo())->toBeFalse();
});
