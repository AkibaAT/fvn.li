<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\ManagesFlareSolverrSession;
use App\Console\Traits\SelectsGames;
use App\Models\Game;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshFeedlessGames extends Command
{
    use ManagesFlareSolverrSession;
    use SelectsGames;

    protected $signature = 'games:refresh-feedless
        {--game-id= : ID of the specific game to refresh}
        {--game-name= : Name (or part of name) of the game(s) to refresh}
        {--all : Refresh all visible feedless games}';

    protected $description = 'Refresh version information for feedless games';

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    public function handle(): int
    {
        return $this->executeWithFlareSolverrSession(function () {
            return $this->executeRefresh();
        });
    }

    /**
     * Execute the refresh logic
     *
     * @throws GuzzleException
     * @throws Throwable
     */
    private function executeRefresh(): int
    {
        // Validate that we have at least one game selection option
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        $this->info('Starting version refresh for feedless games');

        try {
            // Build query for games
            $query = Game::query()
                ->where('is_visible', true)
                ->where('is_suspended', false)
                ->where('is_feedless', true)
                ->orderBy('id');

            // Apply game selection filters
            $this->applyGameSelectionFilters($query);

            $games = $query->get();

            // Display selected games
            $this->displaySelectedGames($games);

            if ($games->isEmpty()) {
                return 1;
            }

            foreach ($games as $game) {
                $this->info("Processing game: {$game->name}");

                try {
                    $game->refreshVersion();
                    $game->error = null;
                    $game->save();

                    $this->info("✓ Successfully refreshed {$game->name}");
                } catch (Exception $exception) {
                    $this->error("× Error refreshing {$game->name}: {$exception->getMessage()}");
                    Log::error("Feedless game refresh failed for {$game->name}", [
                        'exception' => $exception,
                        'game_id' => $game->id,
                    ]);

                    $game->error = $exception->getMessage();
                    $game->save();
                }

                // Rate limiting between games
                if (! $game->is($games->last())) {
                    $this->info('Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }
            }

            $this->info('Refresh process completed');

            return 0;

        } catch (Exception $e) {
            $this->error('Error during refresh process: ' . $e->getMessage());
            Log::error('Feedless games refresh failed', ['exception' => $e]);

            return 1;
        }
    }
}
