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
        {--results : Force fetching of results pages even for ongoing jams}';

    protected $description = 'Fetch additional details for game jams';

    public function __construct(
        private readonly Client $httpClient
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = GameJam::query();

        // Process specific game jam if ID is provided
        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        // Process specific game jam if URL is provided
        elseif ($url = $this->option('url')) {
            $query->where('url', $url);
        }

        // Process only game jams that need details
        elseif (! $this->option('all')) {
            $query->where('needs_details_fetch', true);
        }

        // Apply limit
        $limit = (int) $this->option('limit');
        $gameJams = $query->limit($limit)->get();

        $count = $gameJams->count();
        if ($count === 0) {
            $this->info('No game jams to process.');

            return 0;
        }

        $this->info("Processing {$count} game jams...");

        $successCount = 0;
        $failCount = 0;

        foreach ($gameJams as $i => $gameJam) {
            $this->info(sprintf("\nProcessing game jam %d/%d: %s", $i + 1, $count, $gameJam->name));

            try {
                // Check if we should force fetch results
                $forceResults = $this->option('results');

                // Fetch details from the game jam page
                $success = $gameJam->fetchDetailsFromUrl($this->httpClient);

                // If we're forcing results and the fetch was successful, fetch the results page
                if ($success && $forceResults) {
                    $this->info('Forcing fetch of results page...');
                    $gameJam->fetchResultsPage($this->httpClient);
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
