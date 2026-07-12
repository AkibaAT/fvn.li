<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameVersion;
use App\Services\RouteGraphService;
use App\Services\RoutePathCalculator;
use Illuminate\Console\Command;
use Throwable;

class RecomputeRouteGraphs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route-graph:recompute
                            {--version-id=* : Recompute only these game version IDs}
                            {--game-id= : Recompute all versions of a specific game}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute cached route graphs and persisted route paths (e.g. after a graph revision bump)';

    public function handle(): int
    {
        $query = GameVersion::query()
            ->whereHas('routeLabels')
            ->orderBy('id');

        $versionIds = array_filter((array) $this->option('version-id'));
        if ($versionIds !== []) {
            $query->whereIn('id', $versionIds);
        }

        if ($this->option('game-id')) {
            $query->where('game_id', (int) $this->option('game-id'));
        }

        $versions = $query->get();
        if ($versions->isEmpty()) {
            $this->warn('No game versions with route data found for the given selection.');

            return self::FAILURE;
        }

        $this->info("Recomputing route graphs for {$versions->count()} version(s)");

        $failed = 0;
        foreach ($versions as $version) {
            $this->line("Version {$version->id} ({$version->game->name} {$version->version})");

            try {
                app(RouteGraphService::class)->computeAndStore($version);
                app(RoutePathCalculator::class)->calculateAndStore($version);
            } catch (Throwable $throwable) {
                $failed++;
                $this->error("  Failed: {$throwable->getMessage()}");
            }
        }

        if ($failed > 0) {
            $this->error("{$failed} version(s) failed to recompute.");

            return self::FAILURE;
        }

        $this->info('All route graphs and route paths recomputed.');

        return self::SUCCESS;
    }
}
