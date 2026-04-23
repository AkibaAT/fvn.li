<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\GameArchiveOptimizationService;
use Illuminate\Console\Command;

class OptimizeGameArchives extends Command
{
    use SelectsGames;

    protected $signature = 'games:optimize-archives
                            {--game-id= : ID of the specific game to optimize}
                            {--game-name= : Name (or part of name) of the game(s) to optimize}
                            {--all : Optimize stored archives for all visible Ren\'Py games}
                            {--dry-run : Report savings without storing the optimized archive}
                            {--replace : Delete the original archive after a validated optimized archive is stored}
                            {--force : Keep the optimized archive even if it is not smaller}
                            {--skip-validation : Only allowed with --dry-run; skips stats extraction validation}';

    protected $description = 'Optimize stored Ren\'Py archives by unpacking, optimizing assets, updating script references, and repacking';

    public function handle(GameArchiveOptimizationService $optimizer): int
    {
        if (! $this->validateGameSelectionOptions()) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $replace = (bool) $this->option('replace');
        $validate = ! $this->option('skip-validation');

        if ($this->option('skip-validation') && ! $dryRun) {
            $this->error('--skip-validation can only be used with --dry-run');

            return self::FAILURE;
        }

        if ($replace && $dryRun) {
            $this->error('--replace cannot be combined with --dry-run');

            return self::FAILURE;
        }

        $query = Game::query()
            ->where('is_visible', true)
            ->where('game_engine', "Ren'Py")
            ->with('gameVersions');
        $this->applyGameSelectionFilters($query);
        $games = $query->get();

        $this->displaySelectedGames($games);

        if ($games->isEmpty()) {
            return self::FAILURE;
        }

        $optimized = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        foreach ($games as $game) {
            foreach ($game->gameVersions as $version) {
                try {
                    $this->line("Optimizing {$game->name} {$version->version}...");
                    $result = $optimizer->optimizeStoredArchive(
                        $game->id,
                        $version->id,
                        $dryRun,
                        $replace,
                        (bool) $this->option('force'),
                        $validate,
                        fn (string $message) => $this->line("  - {$message}")
                    );
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Failed to optimize {$game->name} {$version->version}: {$e->getMessage()}");

                    continue;
                }

                if ($result['status'] === 'optimized' || $result['status'] === 'would_optimize') {
                    $optimized++;
                    $savedBytes += $result['saved_bytes'] ?? 0;
                    $prefix = $result['status'] === 'would_optimize' ? 'Would optimize' : 'Optimized';

                    $this->line(sprintf(
                        '%s %s %s: %s -> %s, saved %s (%d RPA, %d RPYC, %d images, %d audio, %d scripts updated)',
                        $prefix,
                        $game->name,
                        $version->version,
                        $this->formatBytes($result['original_size'] ?? 0),
                        $this->formatBytes($result['optimized_size'] ?? 0),
                        $this->formatBytes($result['saved_bytes'] ?? 0),
                        $result['rpa_files'] ?? 0,
                        $result['rpyc_files'] ?? 0,
                        $result['images_optimized'] ?? 0,
                        $result['audio_optimized'] ?? 0,
                        $result['references_updated'] ?? 0
                    ));

                    continue;
                }

                $skipped++;
                $message = "Skipped {$game->name} {$version->version}: ".($result['reason'] ?? 'Skipped');
                if (isset($result['original_size'], $result['optimized_size'])) {
                    $message .= sprintf(
                        ' (%s -> %s, %s %s)',
                        $this->formatBytes($result['original_size']),
                        $this->formatBytes($result['optimized_size']),
                        ($result['saved_bytes'] ?? 0) >= 0 ? 'would save' : 'would grow by',
                        $this->formatBytes(abs($result['saved_bytes'] ?? 0))
                    );
                }

                $this->line($message);
            }
        }

        $this->info(sprintf(
            'Archive optimization complete: %d optimized, %d skipped, %d failed, %s %s',
            $optimized,
            $skipped,
            $failed,
            $dryRun ? 'would save' : 'saved',
            $this->formatBytes($savedBytes)
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return sprintf('%.2f GiB', $bytes / 1024 / 1024 / 1024);
        }

        if ($bytes >= 1024 * 1024) {
            return sprintf('%.2f MiB', $bytes / 1024 / 1024);
        }

        if ($bytes >= 1024) {
            return sprintf('%.2f KiB', $bytes / 1024);
        }

        return "{$bytes} B";
    }
}
