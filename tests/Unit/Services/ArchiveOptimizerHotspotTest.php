<?php

declare(strict_types=1);

use App\Services\ArchiveMediaOptimizer;
use App\Services\ArchiveOptimizationMetadataService;
use Illuminate\Support\Facades\File;

it('resolves optimized target paths for nested and top level game directories', function () {
    $metadata = [
        'schema' => 'fvn.archive_optimization.v1',
        'original_files' => [],
        'optimized_files' => [
            ['path' => 'MyGame-1.0/game/images/bg.webp'],
            ['path' => 'MyGame-1.0/game/audio/theme.ogg'],
            ['path' => 'game/images/title.webp'],
            ['path' => 'MyGame-1.0/game/nested/game/deep.webp'],
        ],
        'media_replacements' => [
            'images/bg.png' => 'images/bg.webp',
            'audio/theme.wav' => 'audio/theme.ogg',
            'images/title.png' => 'images/title.webp',
            'nested/game/deep.png' => 'nested/game/deep.webp',
            'deep.png' => 'deep.webp',
            'images/absent.png' => 'images/absent.webp',
        ],
    ];

    expect(app(ArchiveOptimizationMetadataService::class)->targetPathsFrom($metadata))->toBe([
        'images/bg.png' => 'MyGame-1.0/game/images/bg.webp',
        'audio/theme.wav' => 'MyGame-1.0/game/audio/theme.ogg',
        'images/title.png' => 'game/images/title.webp',
        'nested/game/deep.png' => 'MyGame-1.0/game/nested/game/deep.webp',
        // Addressed by the suffix after the innermost 'game/' directory.
        'deep.png' => 'MyGame-1.0/game/nested/game/deep.webp',
        // No optimized file corresponds to this replacement.
    ]);
});

it('rewrites script references in a single pass without disturbing unrelated text', function () {
    $directory = storage_path('framework/testing/media-optimizer-' . uniqid());
    File::makeDirectory($directory, 0755, true);

    try {
        File::put("{$directory}/script.rpy", <<<'RPY'
        image bg = "images/bg.png"
        image title = "images/title.png"
        play music "game/audio/theme.wav"
        # ambiguous.png appears under two directories, so bare references stay put
        image a = "one/ambiguous.png"
        image b = "two/ambiguous.png"
        show expression "ambiguous.png"
        $ label = "images/bg.png.backup"
        RPY);
        File::put("{$directory}/untouched.rpy", 'label start:' . "\n" . '    "no assets here"');

        $updated = app(ArchiveMediaOptimizer::class)->replaceScriptReferences($directory, [
            'images/bg.png' => 'images/bg.webp',
            'images/title.png' => 'images/title.webp',
            'audio/theme.wav' => 'audio/theme.ogg',
            'one/ambiguous.png' => 'one/ambiguous.webp',
            'two/ambiguous.png' => 'two/ambiguous.webp',
        ]);

        $contents = File::get("{$directory}/script.rpy");

        expect($updated)->toBe(1)
            ->and($contents)->toContain('"images/bg.webp"')
            ->and($contents)->toContain('"images/title.webp"')
            ->and($contents)->toContain('play music "game/audio/theme.ogg"')
            ->and($contents)->toContain('"one/ambiguous.webp"')
            ->and($contents)->toContain('"two/ambiguous.webp"')
            // Shared basename, so the bare reference is ambiguous and left alone.
            ->and($contents)->toContain('show expression "ambiguous.png"')
            ->and(File::get("{$directory}/untouched.rpy"))->toBe('label start:' . "\n" . '    "no assets here"');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('rewrites a unique basename reference to the optimized basename', function () {
    $directory = storage_path('framework/testing/media-optimizer-basename-' . uniqid());
    File::makeDirectory($directory, 0755, true);

    try {
        File::put("{$directory}/script.rpy", 'image solo = "solo.png"' . "\n" . 'image full = "images/solo.png"');

        $updated = app(ArchiveMediaOptimizer::class)->replaceScriptReferences($directory, [
            'images/solo.png' => 'images/solo.webp',
        ]);

        expect($updated)->toBe(1)
            ->and(File::get("{$directory}/script.rpy"))
            ->toBe('image solo = "solo.webp"' . "\n" . 'image full = "images/solo.webp"');
    } finally {
        File::deleteDirectory($directory);
    }
});
