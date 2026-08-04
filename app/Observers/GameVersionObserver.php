<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GameDialogueText;
use App\Models\GameVersion;
use App\Services\GameSearchRefreshService;
use Exception;
use Illuminate\Support\Facades\Log;

class GameVersionObserver
{
    public function saved(GameVersion $gameVersion): void
    {
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

        if ($isLatest && $gameVersion->wasChanged([
            'is_latest',
            'published_at',
            'is_windows',
            'is_linux',
            'is_mac',
            'is_android',
            'is_web',
        ])) {
            GameSearchRefreshService::refreshForLatestVersion($gameVersion, 'game_version_saved');
        }
    }

    /**
     * Reindex all dialogue texts for a specific game.
     */
    protected function reindexGameDialogueTexts(int $gameId): void
    {
        try {
            GameDialogueText::deleteSearchDocumentsForGame($gameId);

            $indexed = GameDialogueText::indexSearchDocumentsForGame($gameId);

            if ($indexed === 0) {
                Log::debug('No dialogue texts found for game reindex; stale documents were removed', ['game_id' => $gameId]);

                return;
            }

            Log::debug('Reindexing dialogue text entries for game', [
                'game_id' => $gameId,
                'entries_count' => $indexed,
            ]);

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
