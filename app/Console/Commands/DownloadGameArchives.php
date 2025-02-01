<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\GameArchiveService;
use App\Services\ItchAuthService;
use App\ValueObjects\Upload;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DownloadGameArchives extends Command
{
    protected $signature = 'games:download-archives
        {--force : Download even if archive already exists}
        {--game-id= : Download only for specific game ID}';

    protected $description = 'Download game archives for current versions';

    public function __construct(
        private readonly ItchAuthService $authService,
        private readonly GameArchiveService $archiveService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $client = $this->authService->getClient();

            // Build query for games
            $query = Game::query()
                ->where('is_visible', true)
                ->where('game_engine', "Ren'Py")
                ->whereNotNull('uploads')
                ->whereHas('latestVersion');

            // If specific game ID provided, only process that one
            if ($gameId = $this->option('game-id')) {
                $query->where('id', $gameId);
            }

            $games = $query->with('latestVersion')->get();
            $totalGames = $games->count();

            $this->info("Found {$totalGames} games to process");

            foreach ($games as $i => $game) {
                $this->info(sprintf("\nProcessing game %d/%d: %s", $i + 1, $totalGames, $game->name));

                try {
                    // Fetch current uploads from itch.io
                    $response = $client->get("https://api.itch.io/games/{$game->game_id}/uploads");
                    $uploadData = json_decode($response->getBody()->getContents(), true);

                    if (! isset($uploadData['uploads'])) {
                        $this->warn('No uploads found, skipping');

                        continue;
                    }

                    // Create map of current upload data by ID
                    $currentUploads = collect($uploadData['uploads'])->keyBy('id');

                    // Get stored uploads and merge in missing traits
                    $storedUploads = $game->uploads ?: [];
                    $updatedUploads = [];
                    $uploadsChanged = false;

                    foreach ($storedUploads as $uploadId => $uploadInfo) {
                        if (isset($currentUploads[$uploadId])) {
                            $currentUpload = $currentUploads[$uploadId];
                            // Check if we need to update traits or type
                            if (
                                empty($uploadInfo['traits']) ||
                                empty($uploadInfo['type']) ||
                                $uploadInfo['traits'] !== ($currentUpload['traits'] ?? []) ||
                                $uploadInfo['type'] !== ($currentUpload['type'] ?? '')
                            ) {
                                $uploadInfo['traits'] = $currentUpload['traits'] ?? [];
                                $uploadInfo['type'] = $currentUpload['type'] ?? '';
                                $uploadsChanged = true;
                            }
                        }
                        $updatedUploads[$uploadId] = $uploadInfo;
                    }

                    // Save updated uploads if needed
                    if ($uploadsChanged) {
                        $game->uploads = $updatedUploads;
                        $game->save();
                        $this->info('Updated upload traits information');
                    }

                    // Convert stored uploads to Upload objects
                    $uploads = Upload::fromCollection(collect($updatedUploads));

                    // Get the best upload
                    $bestUpload = Upload::getBest($uploads);
                    if (! $bestUpload) {
                        $this->warn('No suitable upload found, skipping');

                        continue;
                    }

                    // Check if we already have this file
                    $hasFile = $this->archiveService->archiveExists(
                        $game->id,
                        $game->latestVersion->id,
                        $bestUpload->filename
                    );

                    if ($hasFile && ! $this->option('force')) {
                        $this->info('Archive already exists, skipping (use --force to override)');

                        continue;
                    }

                    // Download and process the file
                    $this->info('Downloading and processing archive...');
                    $result = $this->archiveService->downloadAndProcess(
                        $game->url,
                        $bestUpload->filename,
                        $bestUpload->id,
                        $game->id,
                        $game->latestVersion->id,
                        $this->option('force')
                    );

                    if (isset($result['stats'])) {
                        $this->info('✓ Archive processed successfully');
                    }

                    // Rate limiting between games
                    if ($i < $totalGames - 1) {
                        $this->info('Waiting 10 seconds for rate limiting...');
                        sleep(10);
                    }

                } catch (Exception|GuzzleException $e) {
                    $this->error("Error processing {$game->name}: {$e->getMessage()}");
                    Log::error("Error downloading archive for game {$game->id}", [
                        'game_id' => $game->id,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }

            $this->info("\nArchive download process completed");

            return 0;

        } catch (Exception $e) {
            $this->error('Error during archive download process: ' . $e->getMessage());
            Log::error('Archive download process failed', ['exception' => $e]);

            return 1;
        }
    }
}
