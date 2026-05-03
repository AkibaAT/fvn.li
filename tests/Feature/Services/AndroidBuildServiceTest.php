<?php

declare(strict_types=1);

use App\Models\AndroidBuild;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Services\AndroidBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sdkPath = storage_path('app/testing-renpy-sdk');
    File::deleteDirectory($this->sdkPath);
    File::makeDirectory($this->sdkPath . '/rapt', 0755, true, true);
    File::put($this->sdkPath . '/renpy.sh', "#!/bin/sh\nmkdir -p \"$4\"\nprintf apk > \"$4/fvn-test.apk\"\n");
    chmod($this->sdkPath . '/renpy.sh', 0755);
    config(['services.renpy.sdk_path' => $this->sdkPath]);
});

afterEach(function () {
    File::deleteDirectory($this->sdkPath);
});

function androidBuildVersion(Game $game, array $attributes = []): GameVersion
{
    $version = GameVersion::factory()->create(array_merge([
        'game_id' => $game->id,
        'is_android' => false,
    ], $attributes));

    $version->forceFill(['is_latest' => $attributes['is_latest'] ?? true])->save();

    return $version;
}

function invokeAndroidBuildServiceMethod(AndroidBuildService $service, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($service);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($service, $arguments);
}

it('checks Android build eligibility from engine version and SDK configuration', function () {
    $service = app(AndroidBuildService::class);

    $game = Game::factory()->create(['game_engine' => "Ren'Py"]);
    $version = androidBuildVersion($game, ['is_android' => false]);

    expect($service->isEligibleForAndroidBuild($game, $version))->toBeTrue()
        ->and($service->isEligibleForAndroidBuild($game))->toBeTrue();

    $version->forceFill(['is_android' => true])->save();
    expect($service->isEligibleForAndroidBuild($game, $version))->toBeFalse()
        ->and($service->isEligibleForAndroidBuild($game))->toBeFalse();

    $game->forceFill(['game_engine' => 'Unity'])->save();
    expect($service->isEligibleForAndroidBuild($game, $version))->toBeFalse();

    File::delete($this->sdkPath . '/renpy.sh');
    $game->forceFill(['game_engine' => "Ren'Py"])->save();
    $version->forceFill(['is_android' => false])->save();
    expect($service->isEligibleForAndroidBuild($game, $version))->toBeFalse();
});

it('creates and reuses Android build requests for the authenticated user', function () {
    $service = app(AndroidBuildService::class);
    $user = User::factory()->create();
    $game = Game::factory()->create(['game_engine' => "Ren'Py"]);
    $version = androidBuildVersion($game, ['version' => '1.2.3']);

    expect($service->requestBuild($user, $game, $version, false))->toBeNull();

    $build = $service->requestBuild($user, $game, $version);

    expect($build)->toBeInstanceOf(AndroidBuild::class)
        ->and($build->user_id)->toBe($user->id)
        ->and($build->game_id)->toBe($game->id)
        ->and($build->game_version_id)->toBe($version->id)
        ->and($build->status)->toBe('pending');

    expect($service->requestBuild($user, $game, $version)->is($build))->toBeTrue();

    $build->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    expect($service->requestBuild($user, $game, $version)->is($build))->toBeTrue();
});

it('uses the latest version when requesting a build without an explicit version', function () {
    $service = app(AndroidBuildService::class);
    $user = User::factory()->create();
    $game = Game::factory()->create(['game_engine' => "Ren'Py"]);
    GameVersion::factory()->create(['game_id' => $game->id, 'is_android' => false]);
    $latest = androidBuildVersion($game, ['is_android' => false]);

    $build = $service->requestBuild($user, $game);

    expect($build?->game_version_id)->toBe($latest->id);
});

it('rejects ineligible Android build requests', function () {
    $service = app(AndroidBuildService::class);
    $user = User::factory()->create();
    $game = Game::factory()->create(['game_engine' => 'Unity']);
    $version = androidBuildVersion($game, ['is_android' => false]);

    expect(fn () => $service->requestBuild($user, $game, $version))
        ->toThrow(Exception::class, 'This game is not eligible for Android builds.');
});

it('returns download URLs only for completed builds with stored paths', function () {
    Storage::fake('public');
    $service = app(AndroidBuildService::class);

    $pending = AndroidBuild::factory()->create(['status' => 'pending', 'build_path' => null]);
    $completed = AndroidBuild::factory()->completed()->create([
        'build_path' => 'public/android_builds/1/2/fvn.apk',
    ]);

    expect($service->getDownloadUrl($pending))->toBeNull()
        ->and($service->getDownloadUrl($completed))->toBe(Storage::url('public/android_builds/1/2/fvn.apk'));
});

it('marks builds as failed when no local archive or downloadable upload exists', function () {
    $service = app(AndroidBuildService::class);
    $game = Game::factory()->create([
        'game_engine' => "Ren'Py",
        'uploads' => [],
    ]);
    $version = androidBuildVersion($game);
    $build = AndroidBuild::factory()->create([
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'status' => 'pending',
    ]);

    $keystoreDir = storage_path('app/keystores');
    File::makeDirectory($keystoreDir, 0755, true, true);
    File::put($keystoreDir . '/' . $game->id . '.keystore', 'existing-keystore');

    expect(fn () => $service->processBuild($build))
        ->toThrow(Exception::class, 'No uploads found for this game.');

    $build->refresh();
    expect($build->status)->toBe('failed')
        ->and($build->error_message)->toContain('No uploads found');
});

it('processes a stored RenPy archive into a completed APK build', function () {
    Storage::fake();

    $workDir = storage_path('framework/testing/android-build-process-'.uniqid());
    File::makeDirectory($workDir, 0755, true);

    try {
        $service = app(AndroidBuildService::class);

        $game = Game::factory()->create([
            'game_engine' => "Ren'Py",
            'name' => 'Android Success',
            'slug' => 'android-success',
            'thumb_url' => null,
        ]);
        $version = androidBuildVersion($game, ['version' => '2.5']);

        $archivePath = "{$workDir}/game.zip";
        $zip = new ZipArchive;
        expect($zip->open($archivePath, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('RenpyGame/game/script.rpy', 'label start: return');
        $zip->close();
        Storage::put("games/{$game->id}/{$version->id}/game.zip", file_get_contents($archivePath));

        $build = AndroidBuild::factory()->create([
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'status' => 'pending',
            'build_id' => '00000000-0000-4000-8000-000000000001',
        ]);

        $keystorePath = storage_path("app/keystores/{$game->id}.keystore");
        File::makeDirectory(dirname($keystorePath), 0755, true, true);
        File::put($keystorePath, 'existing-keystore');

        expect($service->processBuild($build))->toBeTrue();

        $build->refresh();
        expect($build->status)->toBe('completed')
            ->and($build->error_message)->toBeNull()
            ->and($build->build_path)->toBe("public/android_builds/{$game->id}/{$version->id}/fvn-li-{$game->slug}-2.5.apk")
            ->and($build->keystore_path)->toBe($keystorePath)
            ->and($build->completed_at)->not->toBeNull()
            ->and(Storage::get($build->build_path))->toBe('apk');
    } finally {
        File::deleteDirectory($workDir);
        File::deleteDirectory(storage_path('app/temp/android_build_00000000-0000-4000-8000-000000000001'));
        if (isset($build)) {
            File::deleteDirectory(storage_path("app/temp/android_build_output_{$build->id}"));
        }
    }
});

it('reuses an existing game keystore and stores generated APK files', function () {
    Storage::fake();
    $service = app(AndroidBuildService::class);
    $game = Game::factory()->create(['slug' => 'android-store-game']);
    $version = androidBuildVersion($game, ['version' => '1.2.3']);
    $build = AndroidBuild::factory()->create([
        'game_id' => $game->id,
        'game_version_id' => $version->id,
    ]);

    $keystorePath = storage_path("app/keystores/{$game->id}.keystore");
    File::makeDirectory(dirname($keystorePath), 0755, true, true);
    File::put($keystorePath, 'existing-keystore');

    expect(invokeAndroidBuildServiceMethod($service, 'getOrCreateKeystore', [$game]))->toBe($keystorePath);

    $apk = tempnam(sys_get_temp_dir(), 'fvn-apk-');
    file_put_contents($apk, 'apk-binary');

    $storedPath = invokeAndroidBuildServiceMethod($service, 'storeApk', [$apk, $game, $version, $build]);

    expect($storedPath)->toBe("public/android_builds/{$game->id}/{$version->id}/fvn-li-{$game->slug}-{$version->version}.apk")
        ->and(Storage::get($storedPath))->toBe('apk-binary');

    @unlink($apk);
});

it('extracts zip archives and locates RenPy game directories', function () {
    $service = app(AndroidBuildService::class);
    $workDir = storage_path('framework/testing/android-build-'.uniqid());
    File::makeDirectory($workDir, 0755, true);

    try {
        $zipPath = "{$workDir}/game.zip";
        $zip = new ZipArchive;
        expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
        $zip->addFromString('Nested/game/script.rpy', 'label start:');
        $zip->close();

        $extractPath = "{$workDir}/extract";
        File::makeDirectory($extractPath, 0755, true);

        invokeAndroidBuildServiceMethod($service, 'extractArchive', [$zipPath, $extractPath]);

        expect(File::exists("{$extractPath}/Nested/game/script.rpy"))->toBeTrue()
            ->and(invokeAndroidBuildServiceMethod($service, 'findGameDirectory', [$extractPath]))->toBe("{$extractPath}/Nested");

        expect(fn () => invokeAndroidBuildServiceMethod($service, 'extractArchive', ["{$workDir}/archive.rar", $extractPath]))
            ->toThrow(RuntimeException::class, 'Unsupported archive format: rar');
    } finally {
        File::deleteDirectory($workDir);
    }
});

it('converts display versions to numeric Android version codes', function () {
    $service = app(AndroidBuildService::class);

    expect(invokeAndroidBuildServiceMethod($service, 'convertVersionToNumeric', ['0.4.1']))->toBe(41)
        ->and(invokeAndroidBuildServiceMethod($service, 'convertVersionToNumeric', ['release-12b']))->toBe(12)
        ->and(invokeAndroidBuildServiceMethod($service, 'convertVersionToNumeric', ['alpha']))->toBe(1);
});

it('skips Android icon generation when thumbnail data is unavailable or invalid', function () {
    $service = app(AndroidBuildService::class);
    $gameDir = storage_path('framework/testing/android-icon-'.uniqid());
    File::makeDirectory($gameDir, 0755, true);

    try {
        $gameWithoutThumbnail = Game::factory()->create(['thumb_url' => null]);
        invokeAndroidBuildServiceMethod($service, 'createAndroidIcon', [$gameWithoutThumbnail, $gameDir]);
        expect(File::exists("{$gameDir}/android-icon_foreground.png"))->toBeFalse();

        $gameWithBadThumbnail = Game::factory()->create(['thumb_url' => 'https://example.invalid/thumb.jpg']);
        invokeAndroidBuildServiceMethod($service, 'createAndroidIcon', [$gameWithBadThumbnail, $gameDir]);
        expect(File::exists("{$gameDir}/android-icon_foreground.png"))->toBeFalse();
    } finally {
        File::deleteDirectory($gameDir);
    }
});
