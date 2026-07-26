<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameArchiveOptimizationService;
use App\Services\GameVersionArchiveRepositoryService;
use Illuminate\Support\Facades\Storage;

function stageLocalArchive(Game $game, GameVersion $version): string
{
    $path = "games/{$game->id}/{$version->id}";
    Storage::put("{$path}/build.zip", 'archive-bytes');

    return $path;
}

function archiveRepository(array|Throwable $optimization, ?callable $onPush = null): GameVersionArchiveRepositoryService
{
    $optimizer = Mockery::mock(GameArchiveOptimizationService::class);
    $optimizer->shouldReceive('optimizeStoredArchive')->andReturnUsing(function () use ($optimization) {
        if ($optimization instanceof Throwable) {
            throw $optimization;
        }

        return $optimization;
    });

    $stash = Mockery::mock(DenKitStashPersistenceService::class);
    $stash->shouldReceive('isEnabled')->andReturn(true);
    $stash->shouldReceive('defaultChannel')->andReturn('main');
    $stash->shouldReceive('persistOptimizedArchive')->andReturnUsing(function () use ($onPush) {
        if ($onPush) {
            $onPush();
        }

        return ['status' => 'persisted', 'target' => 'fvn-li/game:main', 'channel' => 'main', 'build_id' => 7];
    });

    return new GameVersionArchiveRepositoryService($optimizer, $stash);
}

beforeEach(function () {
    Storage::fake('local');
    $this->game = Game::factory()->create();
    $this->version = GameVersion::factory()->for($this->game)->create(['version' => '1.0']);
});

it('removes the local archive after a successful push', function () {
    $path = stageLocalArchive($this->game, $this->version);

    $result = archiveRepository(['status' => 'optimized', 'optimized_path' => '/tmp/optimized.zip'])
        ->persistStoredArchive($this->game, $this->version);

    expect($result['status'])->toBe('persisted')
        ->and(Storage::exists($path))->toBeFalse();
});

it('removes the local archive when optimization is skipped', function () {
    $path = stageLocalArchive($this->game, $this->version);

    $result = archiveRepository(['status' => 'skipped', 'reason' => 'Optimized archive did not pass stats extraction'])
        ->persistStoredArchive($this->game, $this->version);

    expect($result['status'])->toBe('skipped')
        ->and(Storage::exists($path))->toBeFalse();
});

it('removes the local archive when the push throws', function () {
    $path = stageLocalArchive($this->game, $this->version);

    $repository = archiveRepository(
        ['status' => 'optimized', 'optimized_path' => '/tmp/optimized.zip'],
        onPush: fn () => throw new RuntimeException('butler exploded')
    );

    expect(fn () => $repository->persistStoredArchive($this->game, $this->version))
        ->toThrow(RuntimeException::class, 'butler exploded')
        ->and(Storage::exists($path))->toBeFalse();
});

it('removes the local archive when optimization throws', function () {
    $path = stageLocalArchive($this->game, $this->version);

    $repository = archiveRepository(new RuntimeException('optimizer exploded'));

    expect(fn () => $repository->persistStoredArchive($this->game, $this->version))
        ->toThrow(RuntimeException::class, 'optimizer exploded')
        ->and(Storage::exists($path))->toBeFalse();
});

it('removes the local archive on request without consulting the stash', function () {
    $path = stageLocalArchive($this->game, $this->version);

    $optimizer = Mockery::mock(GameArchiveOptimizationService::class);
    $stash = Mockery::mock(DenKitStashPersistenceService::class);

    $removed = (new GameVersionArchiveRepositoryService($optimizer, $stash))
        ->discardLocalArchive($this->game, $this->version);

    expect($removed)->toBeTrue()
        ->and(Storage::exists($path))->toBeFalse();
});

it('reports nothing removed when no local archive is present', function () {
    $optimizer = Mockery::mock(GameArchiveOptimizationService::class);
    $stash = Mockery::mock(DenKitStashPersistenceService::class);

    expect((new GameVersionArchiveRepositoryService($optimizer, $stash))
        ->discardLocalArchive($this->game, $this->version))->toBeFalse();
});
