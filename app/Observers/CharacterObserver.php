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
        Log::debug('Character updated', ['character_id' => $character->character_id]);
        // If display names changed, update related dialogue texts in search index
        if ($character->isDirty('display_names') || $character->isDirty('display_name_corrections')) {
            Log::debug('Character display names changed, dispatching re-index job', ['character_id' => $character->character_id]);

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

                    Log::debug('Re-indexing dialogue texts for character change', [
                        'character_id' => $characterId,
                        'games_count' => $gameIds->count(),
                    ]);

                    // Re-index dialogue texts for each affected game
                    foreach ($gameIds as $gameId) {
                        GameDialogueText::deleteSearchDocumentsForGame((int) $gameId);
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

            Log::debug('Character re-index job dispatched', ['character_id' => $character->character_id]);
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
            Log::debug('Finding games with dialogue from deleted character', ['character_id' => $character->character_id]);

            // Find all games that have dialogue from this character
            $gameIds = DB::table('version_dialogue_lines')
                ->where('character_id', $character->id)
                ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
                ->distinct()
                ->pluck('game_versions.game_id');

            if ($gameIds->isEmpty()) {
                Log::debug('No games found with dialogue from deleted character', ['character_id' => $character->character_id]);

                return;
            }

            Log::debug('Re-indexing dialogue texts for deleted character', [
                'character_id' => $character->character_id,
                'games_count' => $gameIds->count(),
            ]);

            // Re-index dialogue texts for each affected game
            $totalIndexed = 0;
            foreach ($gameIds as $gameId) {
                GameDialogueText::deleteSearchDocumentsForGame((int) $gameId);
                $dialogueTexts = GameDialogueText::getForGame($gameId);
                if ($dialogueTexts->isNotEmpty()) {
                    $dialogueTexts->chunk(500)->each(function ($chunk) {
                        $chunk->searchable();
                    });
                    $totalIndexed += $dialogueTexts->count();
                }
            }

            Log::debug('Re-indexed dialogue texts for deleted character', [
                'character_id' => $character->character_id,
                'entries_reindexed' => $totalIndexed,
            ]);

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
