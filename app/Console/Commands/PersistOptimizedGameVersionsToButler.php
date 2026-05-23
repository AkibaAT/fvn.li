<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\DenKitStashPersistenceService;
use App\Services\GameArchiveOptimizationService;
use Illuminate\Console\Command;
use Throwable;

class PersistOptimizedGameVersionsToButler extends Command
{
    use SelectsGames;

    protected $signature = 'games:persist-optimized-versions
                            {--game-id= : ID of the specific game to persist}
                            {--game-name= : Name (or part of name) of the game(s) to persist}
                            {--version-id=* : Specific game version ID(s) to persist}
                            {--all : Persist all visible Ren\'Py games}
                            {--channel=main : Butler channel to push into}
                            {--force : Re-push versions even when a completed butler build with the same user version already exists}
                            {--skip-validation : Skip stats extraction validation while creating optimized archives}';

    protected $description = 'Optimize stored game version archives and persist them permanently into the DDEV DenKit Stash';

    public function handle(
        GameArchiveOptimizationService $optimizer,
        DenKitStashPersistenceService $stash
    ): int {
        if (! $this->validateGameSelectionOptions()) {
            return self::FAILURE;
        }

        $versionIds = array_values(array_filter(
            array_map('intval', (array) $this->option('version-id')),
            fn (int $versionId) => $versionId > 0
        ));

        $query = Game::query()
            ->where('is_visible', true)
            ->where('game_engine', "Ren'Py")
            ->with(['gameVersions' => fn ($query) => $query
                ->when($versionIds !== [], fn ($query) => $query->whereIn('id', $versionIds))
                ->reorder()
                ->orderBy('published_at')
                ->orderBy('id')]);
        $this->applyGameSelectionFilters($query);
        $games = $query->get();

        $this->displaySelectedGames($games);
        if ($games->isEmpty()) {
            return self::FAILURE;
        }

        $persisted = 0;
        $skipped = 0;
        $failed = 0;
        $channel = trim((string) $this->option('channel'));
        if ($channel === '') {
            $channel = $stash->defaultChannel();
        }

        foreach ($games as $game) {
            foreach ($game->gameVersions as $version) {
                $label = "{$game->name} {$version->version}";
                try {
                    $this->line("Optimizing {$label}...");
                    $optimization = $optimizer->optimizeStoredArchive(
                        $game->id,
                        $version->id,
                        dryRun: false,
                        replace: false,
                        force: true,
                        validate: ! $this->option('skip-validation'),
                        progress: fn (string $message) => $this->line("  - {$message}")
                    );

                    if ($optimization['status'] !== 'optimized' || ! isset($optimization['optimized_path'])) {
                        $skipped++;
                        $this->warn("Skipped {$label}: " . ($optimization['reason'] ?? 'archive was not optimized'));

                        continue;
                    }

                    $result = $stash->persistOptimizedArchive(
                        $game,
                        $version,
                        $optimization['optimized_path'],
                        $channel,
                        (bool) $this->option('force')
                    );

                    if ($result['status'] === 'skipped') {
                        $skipped++;
                        $this->line("Already persisted {$label} to {$result['target']}");

                        continue;
                    }

                    $persisted++;
                    $build = isset($result['build_id']) ? " build #{$result['build_id']}" : '';
                    $this->info("Persisted {$label} to {$result['target']}{$build}");
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("Failed to persist {$label}: {$e->getMessage()}");
                }
            }
        }

        $this->info("DenKit Stash persistence complete: {$persisted} persisted, {$skipped} skipped, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
