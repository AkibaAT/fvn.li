<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ItchAuthService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefreshGames extends Command
{
    protected $signature = 'games:refresh
        {name? : Part of the game name to search for}
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

    private ItchAuthService $authService;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $searchTerm = $this->argument('name');
        $force = $this->option('force');
        $refreshAll = $this->option('all');

        // Check if any refresh option was selected
        if (! $this->option('update-version') && ! $this->option('update-info') && ! $this->option('update-metadata')) {
            $this->error('No refresh options selected. Please use at least one of: --update-version, --update-info, --update-metadata');

            return 1;
        }

        // Check if we have a search term or --all flag
        if (! $searchTerm && ! $refreshAll) {
            $this->error('You must provide either a game name to search for or use the --all flag');

            return 1;
        }

        // Display refresh options
        if ($refreshAll) {
            $this->info('Starting refresh for all visible games');
        } else {
            $this->info("Starting refresh for games matching: \"{$searchTerm}\"");
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

        // Add search term if provided
        if ($searchTerm) {
            $query->where('name', 'ilike', "%{$searchTerm}%");
        }

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
        $matchCount = $games->count();

        if ($matchCount === 0) {
            $this->error("Found no matches for \"{$searchTerm}\"");

            return 1;
        }

        $this->info("Found {$matchCount} matching games:");
        foreach ($games as $game) {
            $this->line("- {$game->name} (ID: {$game->id}, Status: {$game->status})");
        }

        $this->info("\nInitializing itch.io client...");
        try {
            $client = $this->authService->getClient();
        } catch (Exception $e) {
            $this->error('Failed to initialize itch.io client: ' . $e->getMessage());

            return 1;
        }
        $this->info('Client initialized successfully');

        foreach ($games as $game) {
            $this->info("\nProcessing game: {$game->name}");
            try {
                $game->error = null;

                // Refresh base info if requested
                if ($this->option('update-info')) {
                    $this->info('→ Refreshing base info...');

                    $this->executeWithRetry(function () use ($game, $client) {
                        $game->refreshBaseInfo($client);
                        $game->save();
                    }, 'Base info');

                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                // Refresh metadata if requested
                if ($this->option('update-metadata')) {
                    $this->info('→ Refreshing metadata (tags, ratings, descriptions, screenshots, game jams)...');

                    $this->executeWithRetry(function () use ($game, $client) {
                        $game->refreshTagsAndRating($client);
                        $game->save();
                    }, 'Metadata');

                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                // Refresh version if requested
                if ($this->option('update-version')) {
                    $this->info('→ Refreshing version information...');

                    $this->executeWithRetry(function () use ($game, $client, $force) {
                        DB::transaction(function () use ($game, $client, $force) {
                            $game->refreshVersion($client, $force);
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
                            }
                        });
                    }, 'Version information');

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

    /**
     * Execute a function with retry logic for rate limiting
     *
     * @param  callable  $callback  The function to execute
     * @param  string  $operationName  Name of the operation for logging
     * @return mixed The result of the callback function
     *
     * @throws Exception If the operation fails after all retries
     */
    private function executeWithRetry(callable $callback, string $operationName)
    {
        $maxRetries = (int) $this->option('max-retries');
        $baseCooldown = (int) $this->option('retry-cooldown');
        $retryCount = 0;
        $success = false;
        $result = null;

        while (! $success && $retryCount < $maxRetries) {
            try {
                $result = $callback();
                $this->info("  {$operationName} updated successfully");
                $success = true;
            } catch (Exception $e) {
                // Check if it's a rate limiting error (429 Too Many Requests)
                if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                    $retryCount++;
                    $cooldownTime = $baseCooldown * $retryCount; // Increase cooldown with each retry

                    if ($retryCount < $maxRetries) {
                        $this->warn("  Rate limit exceeded. Waiting {$cooldownTime} seconds before retry {$retryCount}/{$maxRetries}...");
                        sleep($cooldownTime);
                    } else {
                        $this->error("  Maximum retries reached. Skipping {$operationName} refresh.");
                        throw $e; // Re-throw to be caught by the outer try-catch
                    }
                } else {
                    // Not a rate limiting error, re-throw
                    throw $e;
                }
            }
        }

        return $result;
    }
}
