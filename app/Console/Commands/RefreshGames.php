<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\GameArchiveService;
use App\Services\ItchHttpClientService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefreshGames extends Command
{
    use SelectsGames;
    protected $signature = 'games:refresh
        {--game-id= : ID of the specific game to refresh}
        {--game-name= : Name (or part of name) of the game(s) to refresh}
        {--all : Refresh all visible games}
        {--limit=10 : Limit the number of games to process when using --all}
        {--sort=id : Sort games by field (id, name, created_at, updated_at)}
        {--update-version : Refresh version information}
        {--update-info : Refresh base game information}
        {--update-metadata : Refresh metadata (tags, ratings, descriptions, screenshots, game jams)}
        {--force : Force refresh even for abandoned/canceled games}
        {--max-retries=3 : Maximum number of retries for rate-limited requests}
        {--retry-cooldown=30 : Base cooldown time in seconds between retries (increases with each retry)}';

    protected $description = 'Refresh game information from itch.io for specific games or all visible games';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws BindingResolutionException
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $refreshAll = $this->option('all');

        // Check if any refresh option was selected
        if (! $this->option('update-version') && ! $this->option('update-info') && ! $this->option('update-metadata')) {
            $this->error('No refresh options selected. Please use at least one of: --update-version, --update-info, --update-metadata');

            return 1;
        }

        // Validate that we have at least one game selection option
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        // Display refresh options
        if ($refreshAll) {
            $this->info('Starting refresh for all visible games');
        } elseif ($this->option('game-id')) {
            $this->info("Starting refresh for game with ID: {$this->option('game-id')}");
        } elseif ($this->option('game-name')) {
            $this->info("Starting refresh for games matching name: \"{$this->option('game-name')}\"");
        }

        $this->info('Force mode: ' . ($force ? 'Yes' : 'No'));
        $this->info('Options selected:');
        $this->info('- Version: ' . ($this->option('update-version') ? 'Yes' : 'No'));
        $this->info('- Base Info: ' . ($this->option('update-info') ? 'Yes' : 'No'));
        $this->info('- Metadata: ' . ($this->option('update-metadata') ? 'Yes' : 'No'));
        $this->info('Retry settings:');
        $this->info('- Max retries: ' . $this->option('max-retries'));
        $this->info('- Base cooldown: ' . $this->option('retry-cooldown') . ' seconds');

        // Build query for games
        $query = Game::query()
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
            $this->warn("Invalid sort field: {$sortField}. Using 'id' instead.");
            $query->orderBy('id');
        }

        // Apply limit when using --all option
        if ($refreshAll) {
            $limit = (int) $this->option('limit');
            $query->limit($limit);
            $this->info("Limiting to {$limit} games");
        }

        $this->info('Executing database query...');
        $games = $query->get();

        // Display selected games
        $this->displaySelectedGames($games);

        if ($games->isEmpty()) {
            return 1;
        }

        // Configure the ItchHttpClientService with the command options
        $itchClient = App::make(ItchHttpClientService::class);
        $itchClient->setMaxRetries((int) $this->option('max-retries'));
        $itchClient->setBaseCooldown((int) $this->option('retry-cooldown'));
        $this->info('ItchHttpClientService configured successfully');

        foreach ($games as $game) {
            $this->info("\nProcessing game: {$game->name}");
            try {
                $game->error = null;

                // Refresh base info if requested
                if ($this->option('update-info')) {
                    $this->info('→ Refreshing base info...');

                    $itchClient->executeWithRetry(
                        function () use ($game) {
                            $game->refreshBaseInfo();
                            $game->save();
                        },
                        'Base info',
                        fn (string $op) => $this->info("  {$op} updated successfully"),
                        fn (string $op, string $error) => $this->error("  Error updating {$op}: {$error}")
                    );

                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                // Refresh metadata if requested
                if ($this->option('update-metadata')) {
                    $this->info('→ Refreshing metadata (tags, ratings, descriptions, screenshots, game jams)...');

                    $itchClient->executeWithRetry(
                        function () use ($game) {
                            $game->refreshMetadata();
                        },
                        'Metadata',
                        fn (string $op) => $this->info("  {$op} updated successfully"),
                        fn (string $op, string $error) => $this->error("  Error updating {$op}: {$error}")
                    );

                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                // Refresh version if requested
                if ($this->option('update-version')) {
                    $this->info('→ Refreshing version information...');

                    $itchClient->executeWithRetry(
                        function () use ($game, $force) {
                            DB::transaction(function () use ($game, $force) {
                                $game->refreshVersion($force);
                                $game->save();

                                // Ensure only one latest version
                                $latestVersion = $game->gameVersions()
                                    ->orderByDesc('published_at')
                                    ->first();

                                if ($latestVersion) {
                                    $game->gameVersions()
                                        ->where('id', '!=', $latestVersion->id)
                                        ->update(['is_latest' => false]);
                                    $latestVersion->is_latest = true;
                                    $latestVersion->save();

                                    // Clean up old version downloads
                                    $archiveService = App::make(GameArchiveService::class);
                                    $archiveService->cleanupOldVersionDownloads($game->id, $latestVersion->id);
                                }
                            });
                        },
                        'Version information',
                        fn (string $op) => $this->info("  {$op} updated successfully"),
                        fn (string $op, string $error) => $this->error("  Error updating {$op}: {$error}")
                    );

                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                $this->info("✓ Successfully refreshed {$game->name}");

            } catch (Exception $exception) {
                $this->error("× Error refreshing {$game->name}: {$exception->getMessage()}");
                Log::error("Game refresh failed for {$game->name}", [
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
