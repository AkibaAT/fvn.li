<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGameJams;
use App\Models\GameJam;
use App\Services\ItchHttpClientService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class FetchGameJamDetails extends Command
{
    use SelectsGameJams;
    protected $signature = 'game-jams:fetch-details
        {--all : Fetch details for all game jams, not just those marked as needing details}
        {--id= : ID of the specific game jam to process}
        {--name= : Name (or part of name) of the game jam(s) to process}
        {--url= : URL of the specific game jam to process}
        {--limit=10 : Limit the number of game jams to process}
        {--results : Force fetching of results pages even for ongoing jams}
        {--max-retries=3 : Maximum number of retries for rate-limited requests}
        {--retry-cooldown=30 : Base cooldown time in seconds between retries (increases with each retry)}';

    protected $description = 'Fetch additional details for game jams. By default, fetches details for game jams with needs_details_fetch=true';

    public function handle(): int
    {
        $query = GameJam::query();
        $forceResults = $this->option('results');

        if ($this->option('id') || $this->option('name') || $this->option('url') || $this->option('all')) {
            $this->applyGameJamSelectionFilters($query);
        }

        // If a specific jam is requested by ID, name, or URL, always fetch results
        if ($this->option('id') || $this->option('name') || $this->option('url')) {
            $forceResults = true;
        }
        // Process only game jams that need details if not using --all
        elseif (! $this->option('all')) {
            $query->where('needs_details_fetch', true);
        } else {
            // When using --all, always fetch results
            $forceResults = true;
        }

        // Apply limit
        $limit = (int) $this->option('limit');
        $query->limit($limit);

        $gameJams = $query->get();

        // Display selected game jams
        $this->displaySelectedGameJams($gameJams);

        if ($gameJams->isEmpty()) {
            return 0;
        }

        $count = $gameJams->count();

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

                // Configure the ItchHttpClientService with the command options
                $itchClient = App::make(ItchHttpClientService::class);
                $itchClient->setMaxRetries((int) $this->option('max-retries'));
                $itchClient->setBaseCooldown((int) $this->option('retry-cooldown'));

                $success = $itchClient->executeWithRetry(
                    function () use ($gameJam) {
                        return $gameJam->fetchDetailsFromUrl();
                    },
                    'Game jam details',
                    fn (string $op) => $this->info("  {$op} processed successfully"),
                    fn (string $op, string $error) => $this->error("  Error during {$op}: {$error}")
                );

                // If we're forcing results and the fetch was successful, fetch the results page with retry logic
                if ($success && $forceResults) {
                    $this->info('Fetching rankings for game jam: ' . $gameJam->name . ' (ID: ' . $gameJam->id . ')');

                    // Get retry settings from command options
                    $maxRetries = (int) $this->option('max-retries');
                    $retryCooldown = (int) $this->option('retry-cooldown');

                    // Fetch rankings directly with the configured retry settings
                    try {
                        $rankingsSuccess = $gameJam->fetchResultsPage($maxRetries, $retryCooldown);
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
}
