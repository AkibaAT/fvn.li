<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Traits\SelectsGames;
use App\Models\Game;
use App\Services\GameImageIntegrityService;
use App\Services\ImageProcessingService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class RepairGameImages extends Command
{
    use SelectsGames;

    protected $signature = 'games:repair-images
        {--game-id= : ID of the specific game to inspect}
        {--game-name= : Name (or part of name) of the game(s) to inspect}
        {--all : Inspect all visible games with image sources}
        {--dry-run : Report invalid processed images without changing anything}
        {--quality=80 : WebP quality used when regenerating images (0-100)}';

    protected $description = 'Find and regenerate game screenshots and thumbnails whose processed files are missing or empty';

    public function __construct(
        private readonly GameImageIntegrityService $integrityService,
        private readonly ImageProcessingService $imageProcessingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->validateGameSelectionOptions()) {
            return self::FAILURE;
        }

        $quality = (int) $this->option('quality');
        if ($quality < 0 || $quality > 100) {
            $this->error('Quality must be between 0 and 100.');

            return self::FAILURE;
        }

        $query = Game::query()
            ->where('is_visible', true)
            ->where(function (Builder $query): void {
                $query->whereNotNull('thumb_url')
                    ->orWhereNotNull('screenshots');
            });
        $this->applyGameSelectionFilters($query);

        $games = $query->orderBy('id')->get();
        if ($games->isEmpty()) {
            $this->warn('No games found matching the selection criteria.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $invalidGames = 0;
        $repairedGames = 0;
        $failedGames = 0;

        $this->info(sprintf(
            '%s %d game(s) for invalid processed images.',
            $dryRun ? 'Inspecting' : 'Repairing',
            $games->count(),
        ));

        foreach ($games as $game) {
            $screenshotIssues = $this->integrityService->screenshotIssues($game->screenshots);
            $thumbnailIssues = $game->hasThumbnail()
                ? $this->integrityService->thumbnailIssues($game->optimized_thumbnails)
                : [];

            if ($screenshotIssues === [] && $thumbnailIssues === []) {
                continue;
            }

            $invalidGames++;
            $this->newLine();
            $this->warn("{$game->name} (ID: {$game->id}) has invalid processed images:");
            $this->displayIssues($screenshotIssues, $thumbnailIssues);

            if ($dryRun) {
                continue;
            }

            $this->repairGame($game, $screenshotIssues, $thumbnailIssues, $quality);

            $remainingScreenshotIssues = $this->integrityService->screenshotIssues($game->screenshots);
            $remainingThumbnailIssues = $game->hasThumbnail()
                ? $this->integrityService->thumbnailIssues($game->optimized_thumbnails)
                : [];

            if ($remainingScreenshotIssues === [] && $remainingThumbnailIssues === []) {
                $repairedGames++;
                $this->info("Repaired {$game->name}.");
            } else {
                $failedGames++;
                $this->error("{$game->name} still has invalid processed images after repair:");
                $this->displayIssues($remainingScreenshotIssues, $remainingThumbnailIssues);
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Inspection complete: {$invalidGames} of {$games->count()} game(s) need repair.");

            return self::SUCCESS;
        }

        $this->info("Repair complete: {$repairedGames} repaired, {$failedGames} still invalid, {$invalidGames} initially invalid.");

        return $failedGames === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, array<int, string>>  $screenshotIssues
     * @param  array<int, string>  $thumbnailIssues
     */
    private function repairGame(Game $game, array $screenshotIssues, array $thumbnailIssues, int $quality): void
    {
        if ($screenshotIssues !== []) {
            $screenshots = $game->screenshots ?? [];
            foreach (array_keys($screenshotIssues) as $index) {
                unset($screenshots[$index]['optimized']);
            }
            $game->screenshots = array_values($screenshots);

            try {
                $this->imageProcessingService->processGameScreenshots($game, $quality);
            } catch (Exception $e) {
                $this->error("Screenshot repair failed: {$e->getMessage()}");
                Log::error('Processed screenshot repair failed', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($thumbnailIssues !== []) {
            $game->optimized_thumbnails = null;

            try {
                $this->imageProcessingService->processGameThumbnail($game, $quality);
            } catch (Exception $e) {
                $this->error("Thumbnail repair failed: {$e->getMessage()}");
                Log::error('Processed thumbnail repair failed', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $game->save();
    }

    /**
     * @param  array<int, array<int, string>>  $screenshotIssues
     * @param  array<int, string>  $thumbnailIssues
     */
    private function displayIssues(array $screenshotIssues, array $thumbnailIssues): void
    {
        foreach ($screenshotIssues as $index => $issues) {
            foreach ($issues as $issue) {
                $this->line('  screenshot ' . ($index + 1) . ": {$issue}");
            }
        }

        foreach ($thumbnailIssues as $issue) {
            $this->line("  thumbnail: {$issue}");
        }
    }
}
