<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\ItchHttpClientService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CheckSuspendedGames extends Command
{
    use SelectsGames;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:check-suspended
        {--game-id= : ID of the specific game to check}
        {--game-name= : Name (or part of name) of the game(s) to check}
        {--all : Check all visible games}
        {--sort=id : Sort games by field (id, name, created_at, updated_at)}
        {--max-retries=3 : Maximum number of retries for rate-limited requests}
        {--retry-cooldown=30 : Base cooldown time in seconds between retries (increases with each retry)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check visible games for suspension status by fetching their project pages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Validate that we have at least one game selection option
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        $this->info('Starting suspension check for games');

        try {
            // Build query for games - only itch.io games for now
            $query = Game::query()
                ->fromItchio()
                ->where('is_visible', true)
                ->orderBy($this->option('sort'));

            // Apply game selection filters
            $this->applyGameSelectionFilters($query);

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

            $checkedCount = 0;
            $suspendedCount = 0;
            $errorCount = 0;

            foreach ($games as $game) {
                $this->info("\nChecking game: {$game->name} (ID: {$game->id})");

                try {
                    $isSuspended = $this->checkGameSuspension($game, $itchClient);

                    if ($isSuspended !== null) {
                        $previousStatus = $game->is_suspended;
                        $game->is_suspended = $isSuspended;
                        $game->save();

                        if ($isSuspended && ! $previousStatus) {
                            $this->warn('  → Game is now SUSPENDED');
                            $suspendedCount++;
                        } elseif (! $isSuspended && $previousStatus) {
                            $this->info('  → Game is no longer suspended');
                        } elseif ($isSuspended) {
                            $this->warn('  → Game remains suspended');
                        } else {
                            $this->info('  → Game is not suspended');
                        }

                        $checkedCount++;
                    } else {
                        $this->error('  → Could not determine suspension status');
                        $errorCount++;
                    }

                    // Rate limiting delay
                    $this->info('  Waiting 5 seconds for rate limiting...');
                    sleep(5);

                } catch (Exception $e) {
                    $this->error("  Error checking game: {$e->getMessage()}");
                    Log::error("Error checking suspension for game {$game->id}: {$e->getMessage()}");
                    $errorCount++;
                }
            }

            $this->info("\n=== Summary ===");
            $this->info("Games checked: {$checkedCount}");
            $this->info("Newly suspended: {$suspendedCount}");
            $this->info("Errors: {$errorCount}");

            return 0;

        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error("CheckSuspendedGames command failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Check if a game is suspended by fetching its project page
     *
     * @return bool|null Returns true if suspended, false if not suspended, null if could not determine
     */
    private function checkGameSuspension(Game $game, ItchHttpClientService $itchClient): ?bool
    {
        try {
            $gameUrl = $game->getPrimaryUrl();
            if (! $gameUrl) {
                $this->warn("  → No URL found for game {$game->name}");

                return null;
            }

            // Fetch the game's project page using anonymous client to avoid authentication issues
            $response = $itchClient->get($gameUrl, [], true);

            if ($response->getStatusCode() !== 200) {
                $this->warn("  → Received HTTP {$response->getStatusCode()} for {$gameUrl}");

                return null;
            }

            $html = $response->getBody()->getContents();

            // Check for various suspension messages
            $suspensionMessages = [
                "This game's files have been suspended by an itch.io administrator.",
                'suspended by an itch.io administrator',
                'files have been suspended',
                'This game has been suspended',
                'suspended by itch.io',
                'content has been suspended',
            ];

            $isSuspended = false;
            $foundMessage = null;
            foreach ($suspensionMessages as $message) {
                if (str_contains($html, $message)) {
                    $isSuspended = true;
                    $foundMessage = $message;
                    break;
                }
            }

            // Debug: Show a snippet of the page content if verbose
            if ($this->getOutput()->isVerbose()) {
                $snippet = substr($html, 0, 500);
                $this->line('  → Page content snippet: ' . $snippet);
                $this->line('  → Looking for suspension messages...');
                if ($isSuspended && $foundMessage) {
                    $this->line('  → Found suspension message: ' . $foundMessage);
                } else {
                    $this->line('  → No suspension message found');
                }
            }

            return $isSuspended;

        } catch (Exception $e) {
            $this->error("  → Exception while fetching {$game->url}: {$e->getMessage()}");

            return null;
        }
    }
}
