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
            Log::debug('GameVersion became latest, reindexing dialogue texts', [
                'game_version_id' => $gameVersion->id,
                'game_id' => $gameVersion->game_id,
            ]);

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
                Log::debug('No dialogue texts found for game reindex', ['game_id' => $gameId]);

                return;
            }

            Log::debug('Reindexing dialogue text entries for game', [
                'game_id' => $gameId,
                'entries_count' => $dialogueTexts->count(),
            ]);

            // Push to Meilisearch in chunks
            $dialogueTexts->chunk(500)->each(function ($chunk) {
                $chunk->searchable();
            });

            Log::debug('Successfully reindexed dialogue texts for game', ['game_id' => $gameId]);
        } catch (Exception $e) {
            Log::error('Failed to reindex dialogue texts for game', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Log::debug('Failed to reindex dialogue texts for game', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
