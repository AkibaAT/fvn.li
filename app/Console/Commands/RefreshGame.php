<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ItchAuthService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefreshGame extends Command
{
    protected $signature = 'game:refresh
        {name : Part of the game name to search for}
        {--update-version : Refresh version information}
        {--update-info : Refresh base game information}
        {--update-tags : Refresh tags and ratings}
        {--force : Force refresh even for abandoned/canceled games}';

    protected $description = 'Refresh game information from itch.io';

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

        $this->info("Starting refresh for games matching: \"{$searchTerm}\"");
        $this->info('Force mode: ' . ($force ? 'Yes' : 'No'));
        $this->info('Options selected:');
        $this->info('- Version: ' . ($this->option('update-version') ? 'Yes' : 'No'));
        $this->info('- Base Info: ' . ($this->option('update-info') ? 'Yes' : 'No'));
        $this->info('- Tags: ' . ($this->option('update-tags') ? 'Yes' : 'No'));

        // Check if any refresh option was selected
        if (! $this->option('update-version') && ! $this->option('update-info') && ! $this->option('update-tags')) {
            $this->error('No refresh options selected. Please use at least one of: --update-version, --update-info, --update-tags');

            return 1;
        }

        // Build query for games
        $query = Game::query()
            ->where('is_visible', true)
            ->where('name', 'ilike', "%{$searchTerm}%");

        // Unless forced, exclude abandoned/canceled games
        if (! $force) {
            $query->whereNotIn('status', ['Abandoned', 'Canceled']);
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
                    $game->refreshBaseInfo($client);
                    $game->save();
                    $this->info('  Base info updated successfully');
                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                // Refresh tags if requested
                if ($this->option('update-tags')) {
                    $this->info('→ Refreshing tags and ratings...');
                    $game->refreshTagsAndRating($client);
                    $game->save();
                    $this->info('  Tags and ratings updated successfully');
                    $this->info('  Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }

                // Refresh version if requested
                if ($this->option('update-version')) {
                    $this->info('→ Refreshing version information...');
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
                    $this->info('  Version information updated successfully');
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
