<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Services\GameArchiveOptimizationService;
use App\Services\GameArchiveOptimizerDockerRunner;
use App\Services\GameStatsService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('optimizes stored archives through the sandbox runner when configured', function () {
    Storage::fake('local');
    config(['services.archive_optimizer.sandbox_enabled' => true]);

    $game = Game::factory()->create(['name' => 'Sandbox Optimize']);
    $version = GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0',
    ]);
    $storagePath = "games/{$game->id}/{$version->id}";
    Storage::makeDirectory($storagePath);
    Storage::put("{$storagePath}/sandbox.zip", 'raw archive bytes');

    $optimizedPath = tempnam(sys_get_temp_dir(), 'sandbox_optimized_');
    File::put($optimizedPath, 'optimized archive bytes');
    $recorder = (object) ['calls' => []];
    $progress = [];

    try {
        $service = new GameArchiveOptimizationService(
            passingSandboxOptimizationStatsService(),
            new class($recorder, $optimizedPath) extends GameArchiveOptimizerDockerRunner
            {
                public function __construct(private object $recorder, private string $optimizedPath) {}

                public function optimize(string $archivePath, ?string $previousOptimizedArchivePath = null): array
                {
                    $this->recorder->calls[] = [
                        'archive_path' => $archivePath,
                        'previous_optimized_archive_path' => $previousOptimizedArchivePath,
                    ];

                    return [
                        'status' => 'optimized',
                        'optimized_path' => $this->optimizedPath,
                        'optimized_size' => filesize($this->optimizedPath),
                        'saved_bytes' => 1,
                        'rpa_files' => 0,
                        'rpyc_files' => 0,
                        'images_optimized' => 1,
                        'audio_optimized' => 0,
                        'images_reused' => 0,
                        'audio_reused' => 0,
                        'references_updated' => 1,
                        'rpyc_decompile_failed' => 0,
                    ];
                }
            }
        );

        $result = $service->optimizeStoredArchive(
            $game->id,
            $version->id,
            dryRun: false,
            force: true,
            progress: function (string $message) use (&$progress): void {
                $progress[] = $message;
            }
        );

        expect($result['status'])->toBe('optimized')
            ->and($progress)->toContain('Optimizing archive in sandbox')
            ->and($recorder->calls)->toHaveCount(1)
            ->and($recorder->calls[0]['archive_path'])->toBe(Storage::path("{$storagePath}/sandbox.zip"))
            ->and($recorder->calls[0]['previous_optimized_archive_path'])->toBeNull();

        Storage::assertExists("{$storagePath}/sandbox.optimized.zip");
        expect(Storage::get("{$storagePath}/sandbox.optimized.zip"))->toBe('optimized archive bytes')
            ->and(File::exists($optimizedPath))->toBeFalse();
    } finally {
        File::delete($optimizedPath);
    }
});

function passingSandboxOptimizationStatsService(): GameStatsService
{
    return new readonly class extends GameStatsService
    {
        public function extractGameStats(string $archivePath): ?array
        {
            return ['languages' => []];
        }
    };
}
