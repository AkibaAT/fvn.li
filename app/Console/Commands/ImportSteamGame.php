<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\PlatformDetectionService;
use App\Services\SteamDataSyncService;
use App\Services\SteamReviewImportService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportSteamGame extends Command
{
    protected $signature = 'games:import-steam
        {url : Steam store page URL (e.g., https://store.steampowered.com/app/123456/Game_Name/)}
        {--no-reviews : Skip importing Steam reviews (default: imports reviews)}
        {--hidden : Keep the game hidden initially (default: visible)}
        {--content-type=visual_novel : Content type (visual_novel, adjacent_game, other_content)}';

    protected $description = 'Import a new game from Steam by providing its store page URL';

    private PlatformDetectionService $platformService;
    private SteamDataSyncService $steamDataService;
    private ?SteamReviewImportService $steamReviewService = null;

    public function __construct(
        PlatformDetectionService $platformService,
        SteamDataSyncService $steamDataService
    ) {
        parent::__construct();
        $this->platformService = $platformService;
        $this->steamDataService = $steamDataService;
    }

    public function handle(): int
    {
        $url = $this->argument('url');
        $withReviews = ! $this->option('no-reviews'); // Default: import reviews
        $isVisible = ! $this->option('hidden'); // Default: visible
        $contentType = $this->option('content-type');

        $this->info('Starting Steam game import...');
        $this->info("URL: {$url}");

        // Validate it's a Steam URL
        if (! str_contains($url, 'steampowered.com')) {
            $this->error('Invalid Steam URL. Must be a store.steampowered.com URL.');
            return 1;
        }

        // Extract Steam App ID
        $appId = $this->platformService->extractSteamAppId($url);
        if (! $appId) {
            $this->error('Could not extract Steam App ID from URL. Expected format: https://store.steampowered.com/app/123456/Game_Name/');
            return 1;
        }

        $this->info("Steam App ID: {$appId}");

        // Check if game already exists
        $existingGame = Game::where('steam_app_id', $appId)->first();
        if ($existingGame) {
            $this->error("Game already exists: {$existingGame->name} (ID: {$existingGame->id})");
            $this->info("URL: " . route('games.show', $existingGame));
            $this->newLine();
            $this->warn('If you want to refresh this game data, use: php artisan games:refresh-steam --game-id=' . $existingGame->id . ' --update-data');
            return 1;
        }

        // Also check by URL (in case steam_app_id wasn't set for some reason)
        $existingByUrl = Game::byUrl($url)->first();
        if ($existingByUrl) {
            $this->error("Game already exists (found by URL): {$existingByUrl->name} (ID: {$existingByUrl->id})");
            $this->info("URL: " . route('games.show', $existingByUrl));
            return 1;
        }

        // Validate content type
        $validContentTypes = ['visual_novel', 'adjacent_game', 'other_content'];
        if (! in_array($contentType, $validContentTypes)) {
            $this->error("Invalid content type: {$contentType}. Valid options: " . implode(', ', $validContentTypes));
            return 1;
        }

        try {
            DB::beginTransaction();

            $this->info('Creating new game record...');

            // Create minimal game record
            $game = Game::create([
                'name' => 'Importing...', // Will be updated by Steam sync
                'url' => ['steam' => $url], // JSONB field with platform-specific URLs
                'platform' => 'steam',
                'steam_app_id' => $appId,
                'status' => 'In development', // Will be updated by Steam sync
                'is_visible' => $isVisible,
                'content_type' => $contentType,
            ]);

            $this->info("✓ Game record created (ID: {$game->id})");

            // Fetch full details from Steam
            $this->info('Fetching game data from Steam...');
            $this->steamDataService->loadFullDetails($game);

            // Clear the temporary slug so it regenerates based on the real game name
            $game->slug = null;
            $game->save();

            $this->info('✓ Game data fetched and saved successfully');

            DB::commit();

            // Display game information
            $this->newLine();
            $this->info('=== Game Imported Successfully ===');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $game->id],
                    ['Name', $game->name],
                    ['Developer', $game->developer ?? 'N/A'],
                    ['Status', $game->status],
                    ['Price', $game->is_paid ? ($game->currency . ' ' . $game->min_price) : 'Free'],
                    ['NSFW', $game->is_nsfw ? 'Yes' : 'No'],
                    ['Has Demo', $game->has_demo ? 'Yes' : 'No'],
                    ['Visible', $game->is_visible ? 'Yes' : 'No'],
                    ['Content Type', $game->content_type],
                    ['URL', route('games.show', $game)],
                ]
            );

            // Import reviews if requested
            if ($withReviews) {
                $this->newLine();
                $this->info('Importing Steam reviews...');

                try {
                    $this->steamReviewService = app(SteamReviewImportService::class);
                    $stats = $this->steamReviewService->syncAllReviews($game);
                    $this->steamReviewService->updateGameRatingStats($game);

                    $this->info('✓ Reviews imported successfully');
                    $this->table(
                        ['Metric', 'Count'],
                        [
                            ['Fetched', $stats['fetched']],
                            ['Imported', $stats['imported']],
                            ['Updated', $stats['updated']],
                            ['Deleted', $stats['deleted']],
                            ['Skipped', $stats['skipped']],
                            ['Errors', $stats['errors']],
                        ]
                    );
                } catch (Exception $e) {
                    $this->error("Failed to import reviews: {$e->getMessage()}");
                    Log::error('Steam review import failed after game creation', [
                        'game_id' => $game->id,
                        'exception' => $e,
                    ]);
                }
            }

            $this->newLine();
            $this->info('Import complete!');

            if ($isVisible) {
                $this->info('Game is now visible in the game list.');
            } else {
                $this->warn('Game is hidden. To make it visible, edit it in the admin panel or run:');
                $this->line("  ddev artisan tinker --execute=\"App\\Models\\Game::find({$game->id})->update(['is_visible' => true]);\"");
            }

            return 0;

        } catch (Exception $e) {
            DB::rollBack();

            $this->error('Failed to import game: ' . $e->getMessage());
            Log::error('Steam game import failed', [
                'url' => $url,
                'app_id' => $appId,
                'exception' => $e,
            ]);

            return 1;
        }
    }
}
