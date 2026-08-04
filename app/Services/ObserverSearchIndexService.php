<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameDialogueText;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ObserverSearchIndexService
{
    public function reindexGames(Builder|Relation $query, string $reason, array $context): bool
    {
        try {
            $query->where('is_visible', true)
                ->orderBy('games.id')
                ->chunk(100, function (Collection $games): void {
                    $games->searchable();
                });

            Log::info("Updated game search indexes for {$reason}", $context);

            return true;
        } catch (Exception $e) {
            Log::warning("Failed to update game search indexes for {$reason}", [
                ...$context,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function reindexDialogue(Collection $gameIds, string $reason, array $context): int
    {
        try {
            $totalIndexed = 0;
            foreach ($gameIds as $gameId) {
                GameDialogueText::deleteSearchDocumentsForGame((int) $gameId);
                $totalIndexed += GameDialogueText::indexSearchDocumentsForGame((int) $gameId);
            }

            Log::info("Updated dialogue text search indexes for {$reason}", [
                ...$context,
                'games_affected' => $gameIds->count(),
                'entries_reindexed' => $totalIndexed,
            ]);

            return $totalIndexed;
        } catch (Exception $e) {
            Log::warning("Failed to update dialogue text search indexes for {$reason}", [
                ...$context,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
