<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\ItchAuthService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AddGamesToCollection extends Command
{
    protected $signature = 'games:add-to-collection
        {--dry-run : Show which games would be added without actually adding them}
        {--limit= : Limit the number of games to process}
        {--skip= : Skip the first N games}';

    protected $description = 'Add all visible games to an itch.io collection';

    private ItchAuthService $authService;

    public function __construct(ItchAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Get the configured collection ID
            $collectionId = config('services.itch.collection_id');

            if (! $collectionId) {
                $this->error('Itch collection ID not configured. Set ITCH_COLLECTION_ID in your .env file.');

                return 1;
            }

            // Query for visible games ordered by initially_published_at
            $query = Game::query()
                ->where('is_visible', true)
                ->orderBy('initially_published_at', 'asc');

            // Apply limit if provided
            if ($limit = $this->option('limit')) {
                $query->limit((int) $limit);
            }

            // Apply skip if provided
            if ($skip = $this->option('skip')) {
                $query->skip((int) $skip);
            }

            $games = $query->get();
            $totalGames = $games->count();

            $this->info("Found {$totalGames} visible games to add to collection");

            // Get an authenticated client
            $client = $this->authService->getClient();
            $csrfToken = $this->authService->getCsrfToken();

            if (!$csrfToken) {
                throw new Exception('Could not extract CSRF token');
            }

            $this->info('CSRF token obtained');

            // Process each game
            $successCount = 0;
            $errorCount = 0;

            $progressBar = $this->output->createProgressBar($totalGames);
            $progressBar->start();

            foreach ($games as $index => $game) {
                $this->info("\nProcessing game " . ($index + 1) . "/{$totalGames}: {$game->name}");

                try {
                    // Skip if it's a dry run
                    if ($this->option('dry-run')) {
                        $this->info('Would add game: ' . $game->name . ' (ID: ' . $game->id . ', URL: ' . $game->url . ')');
                        $successCount++;

                        continue;
                    }

                    // Prepare the add to collection request
                    $response = $client->post($game->url . '/add-to-collection', [
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Referer' => $game->url,
                            'Origin' => 'https://itch.io',
                        ],
                        'form_params' => [
                            'source' => 'game',
                            'csrf_token' => $csrfToken,
                            'add_to' => 'existing',
                            'collection' => [
                                'id' => $collectionId,
                            ],
                        ],
                    ]);

                    // Check for successful response
                    if ($response->getStatusCode() === 200) {
                        $this->info('✓ Added to collection: ' . $game->name);
                        $successCount++;
                    } else {
                        $this->error('× Failed to add to collection: ' . $game->name . ' (Status: ' . $response->getStatusCode() . ')');
                        $errorCount++;
                    }

                } catch (Exception $e) {
                    $this->error('× Error adding game to collection: ' . $e->getMessage());
                    Log::error('Failed to add game to collection', [
                        'game_id' => $game->id,
                        'game_name' => $game->name,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                    $errorCount++;
                }

                // Add a small delay to prevent rate limiting
                sleep(2);
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Summary
            $this->info("\nCollection update completed:");
            $this->info("- Successful: {$successCount} games");
            $this->info("- Failed: {$errorCount} games");

            return $errorCount > 0 ? 1 : 0;

        } catch (Exception|GuzzleException $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Collection update failed', ['exception' => $e]);

            return 1;
        }
    }
}
