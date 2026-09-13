<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\ManagesFlareSolverrSession;
use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryGameStats extends Command
{
    use ManagesFlareSolverrSession;

    protected $signature = 'games:retry-stats';

    protected $description = 'Retry incomplete statistics for eligible current itch.io versions';

    public function handle(): int
    {
        return $this->executeWithFlareSolverrSession(function (): int {
            $games = Game::query()->fromItchio()
                ->where('is_visible', true)
                ->where('is_paid', false)
                ->where('is_stats_extraction_disabled', false)
                ->where(fn ($query) => $query->whereNull('game_engine')->orWhereIn('game_engine', ['', 'unknown', "Ren'Py"]))
                ->whereHas('latestVersion', fn ($query) => $query->where('version', '!=', 'Unknown')->dueForStatsExtraction())
                ->with('latestVersion')
                ->orderBy('updated_at')
                ->limit(20)
                ->get();

            foreach ($games as $game) {
                $version = $game->latestVersion;
                try {
                    $game->refreshVersion();
                    $game->save();
                    $version->refresh();
                    if ($version->is_latest && $version->newQuery()->whereKey($version->id)->dueForStatsExtraction()->exists()) {
                        $version->recordStatsFailure('The current version has no matching processable upload.');
                    }
                    if ($version->stats_error) {
                        $this->warn("{$game->name} {$version->version}: {$version->stats_error}");
                    }
                } catch (Throwable $exception) {
                    $version->refresh()->recordStatsFailure($exception->getMessage());
                    Log::warning('Game statistics retry failed', ['game_version_id' => $version->id, 'error' => $exception->getMessage()]);
                    $this->warn("{$game->name}: {$exception->getMessage()}");
                }
            }

            return self::SUCCESS;
        });
    }
}
