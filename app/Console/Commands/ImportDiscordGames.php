<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\PlatformDetectionService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportDiscordGames extends Command
{
    protected $signature = 'games:import-discord {--path=/home/akiba/Projects/fvn/fvn-bot/vns : Path to fvn-bot vns directory} {--dry-run : Preview changes without saving}';

    protected $description = 'Import games from fvn-bot JSON files into fvn.li database';

    public function handle(): int
    {
        $vnsPath = $this->option('path');
        $dryRun = $this->option('dry-run');

        if (! File::isDirectory($vnsPath)) {
            $this->error("Directory not found: {$vnsPath}");

            return 1;
        }

        $this->info('Starting Discord games import...');
        $this->info("Source: {$vnsPath}");
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        $files = File::files($vnsPath);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            try {
                $data = json_decode(File::get($file->getPathname()), true);
                if (! $data || ! isset($data['Page_url'])) {
                    $this->warn("Skipping {$file->getFilename()}: Invalid structure");
                    $skipped++;

                    continue;
                }

                $url = $data['Page_url'];
                $game = Game::where('url', $url)->first();

                if ($game) {
                    // Update existing game with Discord metadata
                    $this->updateGameWithDiscordData($game, $data, $dryRun);
                    $updated++;
                } else {
                    // Create new game from Discord data
                    $this->createGameFromDiscordData($data, $dryRun);
                    $created++;
                }

                $this->line("✓ Processed: {$data['Name']}");
            } catch (Exception $e) {
                $this->error("Error processing {$file->getFilename()}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('Import complete!');
        $this->info("Created: {$created} | Updated: {$updated} | Skipped: {$skipped} | Errors: {$errors}");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes were saved');
        }

        return 0;
    }

    private function updateGameWithDiscordData(Game $game, array $data, bool $dryRun): void
    {
        $updates = [
            'discord_likes' => $data['Likes'] ?? [],
            'discord_dislikes' => $data['Dislikes'] ?? [],
            'abbreviations' => [$data['Name']], // At minimum, add the game name
            'discord_updated_at' => now(),
        ];

        // Update description if Discord has one and fvn.li doesn't
        if (! empty($data['Description']) && empty($game->description)) {
            $updates['description'] = $data['Description'];
        }

        // Update author if Discord has one and fvn.li doesn't
        if (! empty($data['Author_Name']) && empty($game->authors)) {
            $updates['authors'] = $data['Author_Name'];
        }

        // Update status if different
        if (! empty($data['Project_Status']) && $game->status !== $data['Project_Status']) {
            $updates['status'] = $data['Project_Status'];
        }

        if (! $dryRun) {
            $game->update($updates);
            Log::info('Updated game with Discord data', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'updates' => array_keys($updates),
            ]);
        }
    }

    private function createGameFromDiscordData(array $data, bool $dryRun): void
    {
        // Detect platform from URL
        $platformDetectionService = app(PlatformDetectionService::class);
        $platform = $platformDetectionService->detectPlatform($data['Page_url']);

        // Determine content type based on Discord channel
        // The channel_id is stored in the Discord data when imported
        $contentType = $this->determineContentType($data);

        $gameData = [
            'name' => $data['Name'],
            'url' => $data['Page_url'],
            'platform' => $platform,  // ← Explicitly set detected platform
            'description' => $data['Description'] ?? null,
            'authors' => $data['Author_Name'] ?? null,
            'status' => $data['Project_Status'] ?? 'In development',
            'thumb_url' => $data['Thumbnail_url'] ?? null,
            'is_visible' => false, // Default to hidden, let user decide
            'discord_likes' => $data['Likes'] ?? [],
            'discord_dislikes' => $data['Dislikes'] ?? [],
            'abbreviations' => [$data['Name']],
            'content_type' => $contentType,
            'discord_updated_at' => now(),
            'slug' => str($data['Name'])->slug(),
            'itch_id' => 0, // Will be updated when synced with itch.io
        ];

        // Add platform-specific fields
        if ($platform === 'steam') {
            $gameData['steam_app_id'] = $platformDetectionService->extractSteamAppId($data['Page_url']);
        } elseif ($platform !== 'itch_io') {
            $gameData['external_url'] = $data['Page_url'];
        }

        if (! $dryRun) {
            Game::create($gameData);
            Log::info('Created game from Discord data', [
                'game_name' => $gameData['name'],
                'game_url' => $gameData['url'],
                'platform' => $platform,
                'content_type' => $contentType,
            ]);
        }
    }

    /**
     * Determine content type based on Discord channel or data
     * Adjacent games and other content are marked differently in Discord data
     */
    private function determineContentType(array $data): string
    {
        // If the data has a channel_id field, use it to determine type
        if (isset($data['discord_channel_id'])) {
            // This would need to be mapped to actual Discord channel IDs
            // For now, default to visual_novel
            return 'visual_novel';
        }

        // Default to visual_novel for all imported games
        // Content type can be updated later via the API if needed
        return 'visual_novel';
    }
}
