<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClickStat;
use App\Models\Game;
use App\Services\HomePageCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefreshTrendingScores extends Command
{
    protected $signature = 'games:refresh-trending-scores';

    protected $description = 'Recalculate decayed game trending scores from recent page views';

    public function handle(): int
    {
        $calculatedAt = now();
        $scores = $this->calculateScores();

        if ($scores->isEmpty() && ! Game::query()->where('trending_score', '>', 0)->exists()) {
            $this->info('No trending score candidates found.');

            return Command::SUCCESS;
        }

        $changedIds = [];

        $scores
            ->chunk(1000)
            ->each(function ($scoreChunk) use ($calculatedAt, &$changedIds) {
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
            });

        Game::query()
            ->where('trending_score', '>', 0)
            ->select(['id', 'trending_score'])
            ->orderBy('id')
            ->chunkById(500, function ($games) use ($scores, $calculatedAt, &$changedIds) {
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
            });

        $changedIds = array_values(array_unique($changedIds));

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
            HomePageCacheService::clearTeasers();
        }

        foreach (array_chunk($visibleChangedIds, 1000) as $visibleChangedIdChunk) {
            Game::query()
                ->whereIn('id', $visibleChangedIdChunk)
                ->with(['tags', 'gameJams', 'gameVersions'])
                ->chunkById(100, function ($games) {
                    $games->searchable();
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
        return DB::table('click_stats')
            ->where('type', ClickStat::TYPE_PAGE_VIEW)
            ->where('clicked_at', '>=', DB::raw("NOW() - INTERVAL '14 days'"))
            ->selectRaw('game_id, ROUND(COALESCE(SUM(EXP(-0.099 * EXTRACT(EPOCH FROM (NOW() - clicked_at)) / 86400)), 0))::integer as score')
            ->groupBy('game_id')
            ->pluck('score', 'game_id');
    }
}
