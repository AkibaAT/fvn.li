<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshFeedlessGames extends Command
{
    protected $signature = 'games:refresh-feedless';
    protected $description = 'Refresh version information for feedless games';

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    public function handle(): int
    {
        $this->info('Starting version refresh for feedless games');

        try {
            // Get all visible feedless games
            $games = Game::query()
                ->where('is_visible', true)
                ->where('is_feedless', true)
                ->orderBy('id')
                ->get();

            $this->info("Found {$games->count()} feedless games to process");

            foreach ($games as $game) {
                $this->info("Processing game: {$game->name}");

                try {
                    $game->refreshVersion();
                    $game->error = null;
                    $game->save();

                    $this->info("✓ Successfully refreshed {$game->name}");
                } catch (Exception $exception) {
                    $this->error("× Error refreshing {$game->name}: {$exception->getMessage()}");
                    Log::error("Feedless game refresh failed for {$game->name}", [
                        'exception' => $exception,
                        'game_id' => $game->id,
                    ]);

                    $game->error = $exception->getMessage();
                    $game->save();
                }

                // Rate limiting between games
                if (! $game->is($games->last())) {
                    $this->info('Waiting 10 seconds for rate limiting...');
                    sleep(10);
                }
            }

            $this->info('Refresh process completed');

            return 0;

        } catch (Exception $e) {
            $this->error('Error during refresh process: ' . $e->getMessage());
            Log::error('Feedless games refresh failed', ['exception' => $e]);

            return 1;
        }
    }
}
