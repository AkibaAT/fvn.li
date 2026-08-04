<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\SteamDataSyncService;
use App\Services\SteamReviewImportService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class RefreshSteamGames extends Command
{
    use SelectsGames;

    protected $signature = 'games:refresh-steam
        {--game-id= : ID of the specific game to refresh}
        {--game-name= : Name (or part of name) of the game(s) to refresh}
        {--all : Refresh all visible Steam games}
        {--sort=updated_at : Sort games by field (id, name, created_at, updated_at)}
        {--update-data : Refresh game data (metadata, versions, tags)}
        {--update-reviews : Sync reviews from Steam (fetches available reviews and updates existing)}
        {--force : Force refresh even for abandoned/canceled games}
        {--sleep=10 : Sleep time in seconds between games (to avoid rate limiting)}';

    protected $description = 'Refresh game information and reviews from Steam for specific games or all visible Steam games';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws BindingResolutionException
     */
    public function handle(): int
    {
        return $this->executeRefresh();
    }

    /**
     * Execute the refresh logic
     *
     * @throws BindingResolutionException
     */
    private function executeRefresh(): int
    {
        // Use sync mode for Scout indexing in CLI to avoid queueing
        Config::set('scout.queue', false);

        $force = $this->option('force');
        $refreshAll = $this->option('all');
        $sleepTime = (int) $this->option('sleep');

        // Check if any refresh option was selected
        if (! $this->option('update-data') && ! $this->option('update-reviews')) {
            $this->error('No refresh options selected. Please use at least one of: --update-data, --update-reviews');

            return 1;
        }

        // Validate that we have at least one game selection option
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        // Display refresh options
        if ($refreshAll) {
            $this->info('Starting refresh for all visible Steam games');
        } elseif ($this->option('game-id')) {
            $this->info("Starting refresh for game with ID: {$this->option('game-id')}");
        } elseif ($this->option('game-name')) {
            $this->info("Starting refresh for games matching name: \"{$this->option('game-name')}\"");
        }

        $this->info('Force mode: ' . ($force ? 'Yes' : 'No'));
        $this->info('Options selected:');
        $this->info('- Game Data: ' . ($this->option('update-data') ? 'Yes' : 'No'));
        $this->info('- Reviews: ' . ($this->option('update-reviews') ? 'Yes (upsert available reviews)' : 'No'));
        if ($this->option('update-reviews')) {
            $this->info('  Reviews will be synced without deleting local reviews missing from the Steam API response');
        }
        $this->info('Sleep time between games: ' . $sleepTime . ' seconds');

        // Build query for Steam games
        $query = Game::query()
            ->fromSteam()
            ->where('is_visible', true);

        // Apply game selection filters
        $this->applyGameSelectionFilters($query);

        // Unless forced, exclude abandoned/canceled games
        if (! $force) {
            $query->whereNotIn('status', ['Abandoned', 'Canceled']);
        }

        // Apply sorting
        $sortField = $this->option('sort');
        $allowedSortFields = ['id', 'name', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField);
            $this->info("Sorting by {$sortField}");
        } else {
            $this->warn("Invalid sort field: {$sortField}. Using 'updated_at' instead.");
            $query->orderBy('updated_at');
        }

        $this->info('Executing database query...');
        $games = $query->get();

        // Display selected games
        $this->displaySelectedGames($games);

        if ($games->isEmpty()) {
            return 1;
        }

        // Get services
        $steamDataService = App::make(SteamDataSyncService::class);
        $steamReviewService = App::make(SteamReviewImportService::class);

        foreach ($games as $game) {
            $this->info("\nProcessing game: {$game->name}");
            try {
                $game->error = null;

                // Refresh game data if requested
                if ($this->option('update-data')) {
                    $this->info('→ Refreshing game data from Steam...');

                    try {
                        $steamDataService->loadFullDetails($game);
                        $game->save();
                        $this->info('  Game data updated successfully');
                    } catch (Exception $e) {
                        $this->error("  × Error updating game data: {$e->getMessage()}");
                        Log::error("Steam game data refresh failed for {$game->name}", [
                            'exception' => $e,
                            'game_id' => $game->id,
                        ]);
                    }

                    if ($sleepTime > 0) {
                        $this->info("  Waiting {$sleepTime} seconds for rate limiting...");
                        sleep($sleepTime);
                    }
                }

                // Sync reviews if requested
                if ($this->option('update-reviews')) {
                    $this->info('→ Syncing ALL reviews from Steam...');

                    try {
                        $stats = $steamReviewService->syncAllReviews($game);

                        // Update game rating statistics
                        $steamReviewService->updateGameRatingStats($game);

                        $this->info('  Reviews synced successfully');
                        $this->info("    Fetched: {$stats['fetched']}, Imported: {$stats['imported']}, Updated: {$stats['updated']}, Deleted: {$stats['deleted']}, Skipped: {$stats['skipped']}, Errors: {$stats['errors']}");
                    } catch (Exception $e) {
                        $this->error("  × Error syncing reviews: {$e->getMessage()}");
                        Log::error("Steam review sync failed for {$game->name}", [
                            'exception' => $e,
                            'game_id' => $game->id,
                        ]);
                    }

                    if ($sleepTime > 0) {
                        $this->info("  Waiting {$sleepTime} seconds for rate limiting...");
                        sleep($sleepTime);
                    }
                }

                $this->info("Successfully refreshed {$game->name}");

            } catch (Exception $exception) {
                $this->error("× Error refreshing {$game->name}: {$exception->getMessage()}");
                Log::error("Steam game refresh failed for {$game->name}", [
                    'exception' => $exception,
                    'game_id' => $game->id,
                ]);

                $game->error = $exception->getMessage();
                $game->save();
            }
        }

        $this->info("\nRefresh process completed");

        return 0;
    }
}
