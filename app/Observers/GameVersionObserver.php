<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GameDialogueText;
use App\Models\GameVersion;
use Exception;
use Illuminate\Support\Facades\Log;

class GameVersionObserver
{
    /**
     * Handle the GameVersion "saved" event.
     * Reindex dialogue texts when a version becomes the latest.
     */
    public function saved(GameVersion $gameVersion): void
    {
        // Check if is_latest changed to true
        $wasLatest = $gameVersion->getOriginal('is_latest');
        $isLatest = $gameVersion->is_latest;

        if ($isLatest && ! $wasLatest) {
            echo "    [Observer] GameVersion became latest, reindexing dialogue texts for game {$gameVersion->game_id}\n";

            // Reindex all dialogue texts for this game
            $this->reindexGameDialogueTexts($gameVersion->game_id);
        }
    }

    /**
     * Reindex all dialogue texts for a specific game.
     */
    protected function reindexGameDialogueTexts(int $gameId): void
    {
        try {
            // Get all dialogue texts for this game and push to Meilisearch
            $dialogueTexts = GameDialogueText::getForGame($gameId);

            if ($dialogueTexts->isEmpty()) {
                echo "    [Observer] No dialogue texts found for game {$gameId}\n";

                return;
            }

            echo "    [Observer] Reindexing {$dialogueTexts->count()} dialogue text entries for game {$gameId}\n";

            // Push to Meilisearch in chunks
            $dialogueTexts->chunk(500)->each(function ($chunk) {
                $chunk->searchable();
            });

            echo "    [Observer] Successfully reindexed dialogue texts for game {$gameId}\n";
        } catch (Exception $e) {
            Log::error('Failed to reindex dialogue texts for game', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            echo "    [Observer] ERROR: Failed to reindex dialogue texts: {$e->getMessage()}\n";
        }
    }
}
