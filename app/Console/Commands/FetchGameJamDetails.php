<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameJam;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchGameJamDetails extends Command
{
    protected $signature = 'game-jams:fetch-details
        {--all : Fetch details for all game jams, not just those marked as needing details}
        {--id= : Fetch details for a specific game jam ID}
        {--url= : Fetch details for a game jam with the specified URL}
        {--limit=10 : Limit the number of game jams to process}
        {--results : Force fetching of results pages even for ongoing jams}
        {--max-retries=3 : Maximum number of retries for rate-limited requests}
        {--retry-cooldown=30 : Base cooldown time in seconds between retries (increases with each retry)}';

    protected $description = 'Fetch additional details for game jams';

    public function __construct(
        private readonly Client $httpClient
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = GameJam::query();
        $forceResults = $this->option('results');

        // Process specific game jam if ID is provided
        if ($id = $this->option('id')) {
            $query->where('id', $id);
            // Specific jam requested
            // Always fetch results when a specific jam is requested
            $forceResults = true;
        }

        // Process specific game jam if URL is provided
        elseif ($url = $this->option('url')) {
            $query->where('url', $url);
            // Specific jam requested
            // Always fetch results when a specific jam is requested
            $forceResults = true;
        }

        // Process only game jams that need details
        elseif (! $this->option('all')) {
            $query->where('needs_details_fetch', true);
        } else {
            // When using --all, always fetch results
            $forceResults = true;
        }

        // Apply limit
        $limit = (int) $this->option('limit');
        $gameJams = $query->limit($limit)->get();

        $count = $gameJams->count();
        if ($count === 0) {
            $this->info('No game jams to process.');

            return 0;
        }

        // Display retry settings and whether we're fetching results
        $this->info("Processing {$count} game jams...");
        $this->info('Fetch settings:');
        $this->info('- Fetching rankings: ' . ($forceResults ? 'Yes' : 'No'));
        $this->info('Retry settings:');
        $this->info('- Max retries: ' . $this->option('max-retries'));
        $this->info('- Base cooldown: ' . $this->option('retry-cooldown') . ' seconds');

        $successCount = 0;
        $failCount = 0;

        foreach ($gameJams as $i => $gameJam) {
            $this->info(sprintf("\nProcessing game jam %d/%d: %s", $i + 1, $count, $gameJam->name));

            try {
                // Fetch details from the game jam page with retry logic
                $this->info('Fetching details for game jam: ' . $gameJam->name . ' (ID: ' . $gameJam->id . ')');

                $success = $this->executeWithRetry(function () use ($gameJam) {
                    return $gameJam->fetchDetailsFromUrl($this->httpClient);
                }, 'Game jam details');

                // If we're forcing results and the fetch was successful, fetch the results page with retry logic
                if ($success && $forceResults) {
                    $this->info('Fetching rankings for game jam: ' . $gameJam->name . ' (ID: ' . $gameJam->id . ')');

                    // Get retry settings from command options
                    $maxRetries = (int) $this->option('max-retries');
                    $retryCooldown = (int) $this->option('retry-cooldown');

                    // Fetch rankings directly with the configured retry settings
                    try {
                        $rankingsSuccess = $gameJam->fetchResultsPage($this->httpClient, $maxRetries, $retryCooldown);
                    } catch (Exception $e) {
                        $this->error("Error fetching rankings: {$e->getMessage()}");
                        $rankingsSuccess = false;
                    }

                    if ($rankingsSuccess) {
                        $this->info('✓ Rankings fetched successfully');
                    } else {
                        $this->warn('⚠ No rankings found for this game jam');
                    }

                    // Add a longer delay after processing results to ensure DB transactions are completed
                    if ($i < $count - 1) {
                        $this->info('Waiting 5 seconds before processing next game jam...');
                        sleep(5);
                    }
                }

                if ($success) {
                    $this->info('✓ Details fetched successfully');
                    $gameJam->needs_details_fetch = false;
                    $gameJam->save();
                    $successCount++;
                } else {
                    $this->warn('⚠ Failed to fetch details');
                    $failCount++;
                }
            } catch (Exception $e) {
                $this->error("Error fetching details: {$e->getMessage()}");
                Log::error('Game jam details fetch failed', [
                    'game_jam_id' => $gameJam->id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
                $failCount++;
            }

            // Add small delay between requests
            if ($i < $count - 1) {
                usleep(250000); // 250ms
            }
        }

        $this->info("\nProcessing complete: {$successCount} succeeded, {$failCount} failed");

        return $failCount > 0 ? 1 : 0;
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

        while (! $success && $retryCount <= $maxRetries) {
            try {
                $result = $callback();
                $this->info("  {$operationName} processed successfully");
                $success = true;
            } catch (Exception $e) {
                // Check if it's a rate limiting error (429 Too Many Requests)
                if (strpos($e->getMessage(), '429 Too Many Requests') !== false) {
                    $retryCount++;
                    $cooldownTime = $baseCooldown * $retryCount; // Increase cooldown with each retry

                    if ($retryCount <= $maxRetries) {
                        $this->warn("  Rate limit exceeded. Waiting {$cooldownTime} seconds before retry {$retryCount}/{$maxRetries}...");
                        sleep($cooldownTime);
                    } else {
                        $this->error("  Maximum retries reached. Skipping {$operationName} refresh.");
                        throw $e; // Re-throw to be caught by the outer try-catch
                    }
                } else {
                    // For other errors, log and re-throw immediately
                    $this->error("  Error during {$operationName}: {$e->getMessage()}");
                    throw $e;
                }
            }
        }

        return $result;
    }
}
