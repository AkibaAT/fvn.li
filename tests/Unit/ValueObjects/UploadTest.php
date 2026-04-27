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

test('mac platform uploads are not processable even when itch also marks them as linux', function () {
    $upload = uploadValueObjectForTest([
        'filename' => 'Chapter_2_teaser-mac.zip',
        'traits' => ['p_linux', 'p_osx'],
    ]);

    expect($upload->isProcessable())->toBeFalse();
});

test('best upload ignores mac candidate and selects windows or linux archive', function () {
    $uploads = collect([
        uploadValueObjectForTest([
            'filename' => 'Chapter_2_teaser-mac.zip',
            'traits' => ['p_linux', 'p_osx'],
        ]),
        uploadValueObjectForTest([
            'filename' => 'Chapter_2_teaser-win.zip',
            'traits' => ['p_windows'],
        ]),
    ]);

    expect(Upload::getBest($uploads)?->filename)->toBe('Chapter_2_teaser-win.zip');
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
