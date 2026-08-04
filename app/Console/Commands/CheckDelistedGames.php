<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\ItchHttpClientService;
use Dom\HTMLDocument;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CheckDelistedGames extends Command
{
    use SelectsGames;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:check-delisted
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
    protected $description = 'Check visible games for delisted status by detecting robots noindex meta tag';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->validateGameSelectionOptions()) {
            return 1;
        }

        $this->info('Starting delisted check for games');

        try {
            $query = Game::query()
                ->fromItchio()
                ->where('is_visible', true)
                ->orderBy($this->option('sort'));

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
            $delistedCount = 0;
            $errorCount = 0;

            foreach ($games as $game) {
                $this->info("\nChecking game: {$game->name} (ID: {$game->id})");

                try {
                    $isDelisted = $this->checkGameDelisted($game, $itchClient);

                    if ($isDelisted !== null) {
                        $previousStatus = $game->is_delisted;
                        $game->is_delisted = $isDelisted;
                        $game->save();

                        if ($isDelisted && ! $previousStatus) {
                            $this->warn('Game is now delisted');
                            $delistedCount++;
                        } elseif (! $isDelisted && $previousStatus) {
                            $this->info('Game is no longer delisted');
                        } elseif ($isDelisted) {
                            $this->warn('Game remains delisted');
                        } else {
                            $this->info('Game is not delisted');
                        }

                        $checkedCount++;
                    } else {
                        $this->error('Could not determine delisted status');
                        $errorCount++;
                    }

                    // Rate limiting delay
                    $this->info('  Waiting 5 seconds for rate limiting...');
                    sleep(5);

                } catch (Exception $e) {
                    $this->error("  Error checking game: {$e->getMessage()}");
                    Log::error("Error checking delisted status for game {$game->id}: {$e->getMessage()}");
                    $errorCount++;
                }
            }

            $this->info("\n=== Summary ===");
            $this->info("Games checked: {$checkedCount}");
            $this->info("Newly delisted: {$delistedCount}");
            $this->info("Errors: {$errorCount}");

            return 0;

        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error("CheckDelistedGames command failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Check if a game is delisted by looking for robots noindex meta tag
     *
     * @return bool|null Returns true if delisted, false if not delisted, null if could not determine
     */
    private function checkGameDelisted(Game $game, ItchHttpClientService $itchClient): ?bool
    {
        try {
            $gameUrl = $game->getPrimaryUrl();
            if (! $gameUrl) {
                $this->warn("No URL found for game {$game->name}");

                return null;
            }

            $response = $itchClient->get($gameUrl, [], true);

            if ($response->getStatusCode() !== 200) {
                $this->warn("Received HTTP {$response->getStatusCode()} for {$gameUrl}");

                return null;
            }

            $html = $response->getBody()->getContents();
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            $isDelisted = $this->checkForNoindexTag($doc);

            // Debug: Show information if verbose
            if ($this->getOutput()->isVerbose()) {
                $this->line('Checking for robots noindex meta tag...');
                if ($isDelisted) {
                    $this->line('Found noindex meta tag; game is delisted');
                } else {
                    $this->line('No noindex meta tag found');
                }
            }

            return $isDelisted;

        } catch (Exception $e) {
            $this->error("Exception while fetching {$game->getPrimaryUrl()}: {$e->getMessage()}");

            return null;
        }
    }

    private function checkForNoindexTag(HTMLDocument $doc): bool
    {
        $metaTags = $doc->querySelectorAll('meta[name="robots"]');
        foreach ($metaTags as $meta) {
            $content = strtolower($meta->getAttribute('content') ?? '');
            if (str_contains($content, 'noindex')) {
                return true;
            }
        }

        return false;
    }
}
