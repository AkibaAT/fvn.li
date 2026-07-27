<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClickStat;
use App\Models\Game;
use App\Services\HomePageCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshTrendingScores extends Command
{
    protected $signature = 'games:refresh-trending-scores';

    protected $description = 'Recalculate decayed game trending scores from recent page views';

    public function handle(): int
    {
        $calculatedAt = now();

        $this->info(sprintf(
            'Calculating trending scores from unique human visitors in the last %d days...',
            ClickStat::TRENDING_WINDOW_DAYS
        ));
        $scores = $this->calculateScores();
        $this->info(sprintf('Calculated %d candidate trending scores.', $scores->count()));

        if ($scores->isEmpty() && ! Game::query()->where('trending_score', '>', 0)->exists()) {
            $this->info('No trending score candidates found.');

            return Command::SUCCESS;
        }

        $changedIds = [];
        $candidateCount = $scores->count();
        $processedCandidates = 0;

        if ($candidateCount > 0) {
            $this->info(sprintf('Checking %d candidate games for score changes...', $candidateCount));
        }

        $scores
            ->chunk(1000)
            ->each(function ($scoreChunk) use ($calculatedAt, &$changedIds, &$processedCandidates, $candidateCount) {
                Game::query()
                    ->whereIn('id', $scoreChunk->keys())
                    ->select(['id', 'trending_score'])
                    ->orderBy('id')
                    ->chunkById(500, function ($games) use ($scoreChunk, $calculatedAt, &$changedIds) {
                        foreach ($games as $game) {
                            $newScore = (int) ($scoreChunk->get($game->id, 0));

                            if ((int) $game->trending_score === $newScore) {
                                continue;
                            }

                            Game::query()
                                ->whereKey($game->id)
                                ->update([
                                    'trending_score' => $newScore,
                                    'trending_score_calculated_at' => $calculatedAt,
                                ]);

                            $changedIds[] = $game->id;
                        }
                    });

                $processedCandidates += $scoreChunk->count();
                $this->line(sprintf('Checked %d/%d candidate games.', $processedCandidates, $candidateCount));
            });

        $staleScoreCount = Game::query()->where('trending_score', '>', 0)->count();

        if ($staleScoreCount > 0) {
            $this->info(sprintf('Checking %d games with existing scores for reset...', $staleScoreCount));
        }

        $processedStaleScores = 0;

        Game::query()
            ->where('trending_score', '>', 0)
            ->select(['id', 'trending_score'])
            ->orderBy('id')
            ->chunkById(500, function ($games) use ($scores, $calculatedAt, &$changedIds, &$processedStaleScores, $staleScoreCount) {
                foreach ($games as $game) {
                    if ($scores->has($game->id)) {
                        continue;
                    }

                    $newScore = (int) ($scores->get($game->id, 0));

                    if ((int) $game->trending_score === $newScore) {
                        continue;
                    }

                    Game::query()
                        ->whereKey($game->id)
                        ->update([
                            'trending_score' => $newScore,
                            'trending_score_calculated_at' => $calculatedAt,
                        ]);

                    $changedIds[] = $game->id;
                }

                $processedStaleScores += $games->count();
                $this->line(sprintf('Checked %d/%d existing scored games.', $processedStaleScores, $staleScoreCount));
            });

        $changedIds = array_values(array_unique($changedIds));
        $this->info(sprintf('Found %d games with changed trending scores.', count($changedIds)));

        if (empty($changedIds)) {
            $this->info('Trending scores are already current.');

            return Command::SUCCESS;
        }

        $visibleChangedIds = [];

        foreach (array_chunk($changedIds, 1000) as $changedIdChunk) {
            $visibleChangedIds = array_merge(
                $visibleChangedIds,
                Game::query()
                    ->whereIn('id', $changedIdChunk)
                    ->where('is_visible', true)
                    ->pluck('id')
                    ->all()
            );
        }

        if ($visibleChangedIds !== []) {
            $this->info(sprintf('Clearing home page teaser cache for %d visible changed games.', count($visibleChangedIds)));
            HomePageCacheService::clearTeasers();
        }

        $this->info(sprintf('Refreshing search documents for %d visible changed games...', count($visibleChangedIds)));
        $refreshedVisibleGames = 0;

        foreach (array_chunk($visibleChangedIds, 1000) as $visibleChangedIdChunk) {
            Game::query()
                ->whereIn('id', $visibleChangedIdChunk)
                ->with(['tags', 'gameJams', 'gameVersions'])
                ->chunkById(100, function ($games) use (&$refreshedVisibleGames, $visibleChangedIds) {
                    $games->searchable();
                    $refreshedVisibleGames += $games->count();
                    $this->line(sprintf('Queued %d/%d visible games for search refresh.', $refreshedVisibleGames, count($visibleChangedIds)));
                });
        }

        $this->info(sprintf(
            'Updated trending scores for %d games; queued %d visible games for search refresh.',
            count($changedIds),
            count($visibleChangedIds),
        ));

        Log::info('Refreshed game trending scores', [
            'changed_count' => count($changedIds),
            'visible_changed_count' => count($visibleChangedIds),
        ]);

        return Command::SUCCESS;
    }

    private function calculateScores()
    {
        return ClickStat::trendingScores();
    }
}
