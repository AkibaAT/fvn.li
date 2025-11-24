<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Character;
use App\Models\GameDialogueText;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterObserver
{
    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        echo "    [CharObserver] Character updated: {$character->character_id}\n";
        // If display names changed, update related dialogue texts in search index
        if ($character->isDirty('display_names') || $character->isDirty('display_name_corrections')) {
            echo "    [CharObserver] Display names changed, dispatching re-index job\n";

            // Dispatch re-indexing after the transaction commits to avoid holding locks
            // while doing expensive search indexing operations
            $characterId = $character->id;
            dispatch(function () use ($characterId) {
                try {
                    // Find all games that have dialogue from this character
                    $gameIds = DB::table('version_dialogue_lines')
                        ->where('character_id', $characterId)
                        ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
                        ->distinct()
                        ->pluck('game_versions.game_id');

                    if ($gameIds->isEmpty()) {
                        return;
                    }

                    echo '    [CharObserver] Re-indexing dialogue texts for ' . $gameIds->count() . " games\n";

                    // Re-index dialogue texts for each affected game
                    foreach ($gameIds as $gameId) {
                        $dialogueTexts = GameDialogueText::getForGame($gameId);
                        if ($dialogueTexts->isNotEmpty()) {
                            $dialogueTexts->chunk(500)->each(function ($chunk) {
                                $chunk->searchable();
                            });
                        }
                    }

                    Log::info('Updated dialogue text search indexes for character change', [
                        'character_id' => $characterId,
                        'games_affected' => $gameIds->count(),
                    ]);
                } catch (Exception $e) {
                    Log::warning('Failed to update dialogue text search indexes for character change', [
                        'character_id' => $characterId,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterCommit();

            echo "    [CharObserver] Re-index job dispatched\n";
        }
    }

    /**
     * Handle the Character "deleted" event.
     */
    public function deleted(Character $character): void
    {
        $this->updateRelatedDialogueTexts($character);
    }

    /**
     * Update all dialogue texts that reference this character.
     */
    private function updateRelatedDialogueTexts(Character $character): void
    {
        try {
            echo "    [CharObserver] Finding games with dialogue from this character\n";

            // Find all games that have dialogue from this character
            $gameIds = DB::table('version_dialogue_lines')
                ->where('character_id', $character->id)
                ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
                ->distinct()
                ->pluck('game_versions.game_id');

            if ($gameIds->isEmpty()) {
                echo "    [CharObserver] No games found with dialogue from this character\n";

                return;
            }

            echo '    [CharObserver] Re-indexing dialogue texts for ' . $gameIds->count() . " games\n";

            // Re-index dialogue texts for each affected game
            $totalIndexed = 0;
            foreach ($gameIds as $gameId) {
                $dialogueTexts = GameDialogueText::getForGame($gameId);
                if ($dialogueTexts->isNotEmpty()) {
                    $dialogueTexts->chunk(500)->each(function ($chunk) {
                        $chunk->searchable();
                    });
                    $totalIndexed += $dialogueTexts->count();
                }
            }

            echo "    [CharObserver] Re-indexed {$totalIndexed} dialogue text entries\n";

            Log::info('Updated dialogue text search indexes for character deletion', [
                'character_id' => $character->id,
                'games_affected' => $gameIds->count(),
                'entries_reindexed' => $totalIndexed,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to update dialogue text search indexes for character deletion', [
                'character_id' => $character->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
