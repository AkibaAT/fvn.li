<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\GameVersionAnalyzer;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnalyzeGames extends Command
{
    private const int MAX_ATTEMPTS_PER_GAME = 3;
    private const int RETRY_DELAY = 30;
    private const int REQUEST_DELAY = 10;
    protected $signature = 'games:analyze
        {--game-id= : Specific game ID to analyze}
        {--status= : Filter by game status (comma-separated)}
        {--force : Force reanalysis even if no changes detected}';

    protected $description = 'Analyze games for version updates and statistics';

    public function handle(GameVersionAnalyzer $analyzer): int
    {
        $query = Game::query()->where('is_visible', true);

        // Apply filters
        if ($gameId = $this->option('game-id')) {
            $query->where('id', $gameId);
        }

        if ($status = $this->option('status')) {
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        } else {
            $query->whereNotIn('status', ['Abandoned', 'Canceled']);
        }

        $games = $query->orderBy('id')->get();
        $force = (bool) $this->option('force');

        $bar = $this->output->createProgressBar(count($games));
        $bar->start();

        $failedGames = [];

        foreach ($games as $game) {
            $attempt = 1;
            $success = false;

            while ($attempt <= self::MAX_ATTEMPTS_PER_GAME && ! $success) {
                try {
                    $this->info("\nAnalyzing {$game->name}...");
                    $analyzer->refreshVersion($game, $force);
                    $game->error = null;
                    $game->save();
                    $success = true;
                } catch (Exception $e) {
                    Log::error("Game analysis failed for {$game->name} (attempt {$attempt}): " . $e->getMessage(), [
                        'exception' => $e,
                        'game_id' => $game->id,
                        'attempt' => $attempt,
                    ]);

                    if ($attempt < self::MAX_ATTEMPTS_PER_GAME) {
                        $delay = self::RETRY_DELAY * $attempt;
                        $this->warn("Retrying {$game->name} in {$delay} seconds...");
                        sleep($delay);
                        $attempt++;
                    } else {
                        $this->error("Failed to analyze {$game->name} after {$attempt} attempts.");
                        $game->error = $e->getMessage();
                        $game->save();
                        $failedGames[] = $game->name;
                    }
                }
            }

            $bar->advance();

            // Add delay between games to avoid rate limiting
            if (! $this->option('game-id')) {
                sleep(self::REQUEST_DELAY);
            }
        }

        $bar->finish();

        if (! empty($failedGames)) {
            $this->error("\nThe following games failed to analyze:");
            foreach ($failedGames as $gameName) {
                $this->line(" - {$gameName}");
            }

            return self::FAILURE;
        }

        $this->info("\nAnalysis complete!");

        return self::SUCCESS;
    }
}
