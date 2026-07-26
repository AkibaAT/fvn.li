<?php

declare(strict_types=1);

use App\Services\GameArchiveOptimizationService;
use Illuminate\Support\Facades\File;

/**
 * Ren'Py runs the compiled script whenever it is not older than its source, and
 * repacking gives every file the same timestamp. A compiled script left beside a
 * source whose media references were rewritten would therefore keep pointing at
 * files the optimizer has already replaced.
 */
it('drops compiled scripts whose source was rewritten', function () {
    $workDir = storage_path('framework/testing/optimizer-compiled-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $contentDir = "{$gameDir}/game";
    File::makeDirectory("{$contentDir}/images", 0755, true);

    File::put("{$contentDir}/script.rpy", 'image bg = "images/room.png"');
    File::put("{$contentDir}/script.rpyc", 'compiled-referencing-images/room.png');
    File::put("{$contentDir}/untouched.rpy", 'label start:');
    File::put("{$contentDir}/untouched.rpyc", 'compiled-untouched');
    // A compiled script with no source cannot be rewritten, so it stays.
    File::put("{$contentDir}/sourceless.rpyc", 'compiled-only');
    File::put("{$contentDir}/images/room.png", 'png-bytes');

    try {
        $service = app(GameArchiveOptimizationService::class);
        $method = new ReflectionMethod($service, 'removeShadowingCompiledScripts');
        $removed = $method->invoke($service, $contentDir);

        expect($removed)->toBe(2)
            ->and(File::exists("{$contentDir}/script.rpyc"))->toBeFalse()
            ->and(File::exists("{$contentDir}/untouched.rpyc"))->toBeFalse()
            ->and(File::exists("{$contentDir}/sourceless.rpyc"))->toBeTrue()
            // Sources are untouched; only the compiled shadows are removed.
            ->and(File::exists("{$contentDir}/script.rpy"))->toBeTrue()
            ->and(File::exists("{$contentDir}/untouched.rpy"))->toBeTrue();
    } finally {
        File::deleteDirectory($workDir);
    }
});

/**
 * Compiling with the game's own runtime keeps the bytecode matched to the
 * release, and the SDK only stands in when that runtime cannot run.
 */
it('recompiles with the bundled runtime before falling back to the SDK', function () {
    $workDir = storage_path('framework/testing/optimizer-recompile-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $sdkDir = "{$workDir}/renpy-sdk";
    File::makeDirectory("{$gameDir}/game", 0755, true);
    File::makeDirectory($sdkDir, 0755, true);

    File::put("{$gameDir}/My Game.sh", '#!/bin/sh');
    File::put("{$sdkDir}/renpy.sh", '#!/bin/sh');

    config([
        'services.renpy.sdk_container_path' => $sdkDir,
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);
        $method = new ReflectionMethod($service, 'recompileCommands');
        $commands = $method->invoke($service, $gameDir);

        expect($commands)->toHaveCount(3)
            // The launcher name carries a space, so it stays a single argument.
            ->and($commands[0][0])->toBe(["{$gameDir}/My Game.sh", 'game', 'test'])
            ->and($commands[1][0])->toBe(["{$gameDir}/My Game.sh"])
            ->and($commands[2][0])->toBe(["{$sdkDir}/renpy.sh", $gameDir, 'compile']);
    } finally {
        File::deleteDirectory($workDir);
    }
});

/**
 * A Ren'Py run writes logs, a save directory, its token store and the byte-code
 * of the runtime's own modules, none of which belong in the repacked release.
 */
it('keeps the runtime output of the recompile out of the archive', function () {
    $workDir = storage_path('framework/testing/optimizer-debris-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $contentDir = "{$gameDir}/game";
    File::makeDirectory($contentDir, 0755, true);
    File::makeDirectory("{$gameDir}/renpy", 0755, true);
    File::put("{$contentDir}/script.rpy", 'label start:');

    File::put("{$gameDir}/MyGame.sh", <<<'SH'
        #!/bin/sh
        touch game/script.rpyc log.txt errors.txt game/log.txt game/traceback.txt
        mkdir -p game/saves renpy/__pycache__
        touch renpy/__pycache__/main.cpython-39.pyc
        SH);

    config([
        'services.renpy.sdk_container_path' => "{$workDir}/absent",
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);
        $compiled = (new ReflectionMethod($service, 'recompileScripts'))
            ->invoke($service, $gameDir, $contentDir, null);

        expect($compiled)->toBe(1)
            ->and(File::exists("{$contentDir}/script.rpyc"))->toBeTrue()
            ->and(File::exists("{$gameDir}/log.txt"))->toBeFalse()
            ->and(File::exists("{$gameDir}/errors.txt"))->toBeFalse()
            ->and(File::exists("{$contentDir}/log.txt"))->toBeFalse()
            ->and(File::exists("{$contentDir}/traceback.txt"))->toBeFalse()
            ->and(File::exists("{$contentDir}/saves"))->toBeFalse()
            ->and(File::exists("{$gameDir}/renpy/__pycache__"))->toBeFalse()
            ->and(File::exists("{$gameDir}/.recompile-home"))->toBeFalse();
    } finally {
        File::deleteDirectory($workDir);
    }
});

/**
 * Byte-code the release already carries is part of the archive, not debris.
 */
it('leaves byte-code that was already in the archive alone', function () {
    $workDir = storage_path('framework/testing/optimizer-keep-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $contentDir = "{$gameDir}/game";
    File::makeDirectory($contentDir, 0755, true);
    File::makeDirectory("{$gameDir}/lib/__pycache__", 0755, true);
    File::put("{$gameDir}/lib/__pycache__/shipped.cpython-39.pyc", 'shipped');
    File::put("{$contentDir}/script.rpy", 'label start:');

    File::put("{$gameDir}/MyGame.sh", <<<'SH'
        #!/bin/sh
        touch game/script.rpyc lib/__pycache__/fresh.cpython-39.pyc
        SH);

    config([
        'services.renpy.sdk_container_path' => "{$workDir}/absent",
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);
        (new ReflectionMethod($service, 'recompileScripts'))->invoke($service, $gameDir, $contentDir, null);

        expect(File::exists("{$gameDir}/lib/__pycache__/shipped.cpython-39.pyc"))->toBeTrue()
            // Byte-code the run produced inside that directory is still debris.
            ->and(File::exists("{$gameDir}/lib/__pycache__/fresh.cpython-39.pyc"))->toBeFalse();
    } finally {
        File::deleteDirectory($workDir);
    }
});

/**
 * Compiling is an improvement to the archive, not a condition of producing one,
 * so a runtime killed mid-compile must not cost the whole optimization.
 */
it('survives a runtime that is killed while compiling', function () {
    $workDir = storage_path('framework/testing/optimizer-killed-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $contentDir = "{$gameDir}/game";
    File::makeDirectory($contentDir, 0755, true);
    File::put("{$contentDir}/script.rpy", 'label start:');

    // The runtime dies the way an exhausted sandbox kills it.
    File::put("{$gameDir}/MyGame.sh", <<<'SH'
        #!/bin/sh
        kill -9 $$
        SH);

    config([
        'services.renpy.sdk_container_path' => "{$workDir}/absent",
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);
        $compiled = (new ReflectionMethod($service, 'recompileScripts'))
            ->invoke($service, $gameDir, $contentDir, null);

        expect($compiled)->toBe(0)
            // The sources survive, so the archive is still shippable.
            ->and(File::exists("{$contentDir}/script.rpy"))->toBeTrue();
    } finally {
        File::deleteDirectory($workDir);
    }
});

/**
 * A runtime that aborts partway through loading compiles only some of the
 * sources, which is not a usable result.
 */
it('rejects a partial compile so the next runtime is tried', function () {
    $workDir = storage_path('framework/testing/optimizer-partial-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $contentDir = "{$gameDir}/game";
    File::makeDirectory($contentDir, 0755, true);
    File::put("{$contentDir}/script.rpy", 'label start:');
    File::put("{$contentDir}/later.rpy", 'label later:');

    File::put("{$gameDir}/MyGame.sh", <<<'SH'
        #!/bin/sh
        touch game/script.rpyc
        SH);

    config([
        'services.renpy.sdk_container_path' => "{$workDir}/absent",
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);

        expect((new ReflectionMethod($service, 'recompileScripts'))->invoke($service, $gameDir, $contentDir, null))
            ->toBe(0);
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('recompiles with the SDK when the game bundles no runtime', function () {
    $workDir = storage_path('framework/testing/optimizer-nosdk-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    $sdkDir = "{$workDir}/renpy-sdk";
    File::makeDirectory("{$gameDir}/game", 0755, true);
    File::makeDirectory($sdkDir, 0755, true);
    File::put("{$sdkDir}/renpy.sh", '#!/bin/sh');

    config([
        'services.renpy.sdk_container_path' => $sdkDir,
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);
        $method = new ReflectionMethod($service, 'recompileCommands');

        expect($method->invoke($service, $gameDir))->toHaveCount(1);
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('ships sources when neither a bundled runtime nor an SDK is available', function () {
    $workDir = storage_path('framework/testing/optimizer-noruntime-' . uniqid());
    $gameDir = "{$workDir}/MyGame";
    File::makeDirectory("{$gameDir}/game", 0755, true);

    config([
        'services.renpy.sdk_container_path' => "{$workDir}/absent",
        'services.renpy.sdk_host_path' => null,
    ]);

    try {
        $service = app(GameArchiveOptimizationService::class);
        $commands = (new ReflectionMethod($service, 'recompileCommands'))->invoke($service, $gameDir);
        $compiled = (new ReflectionMethod($service, 'recompileScripts'))
            ->invoke($service, $gameDir, "{$gameDir}/game", null);

        expect($commands)->toBe([])
            ->and($compiled)->toBe(0);
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('treats a _ren.py source as a source for its compiled script', function () {
    $workDir = storage_path('framework/testing/optimizer-renpy-' . uniqid());
    $contentDir = "{$workDir}/MyGame/game";
    File::makeDirectory($contentDir, 0755, true);

    File::put("{$contentDir}/helper_ren.py", 'def helper(): pass');
    File::put("{$contentDir}/helper.rpyc", 'compiled');

    try {
        $service = app(GameArchiveOptimizationService::class);
        $method = new ReflectionMethod($service, 'removeShadowingCompiledScripts');

        expect($method->invoke($service, $contentDir))->toBe(1)
            ->and(File::exists("{$contentDir}/helper.rpyc"))->toBeFalse();
    } finally {
        File::deleteDirectory($workDir);
    }
});
