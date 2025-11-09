<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Character;
use Exception;
use Illuminate\Support\Facades\Log;

class CharacterObserver
{
    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        echo "    [CharObserver] Character updated: {$character->character_id}\n";
        // If display names changed, update related dialogue lines in search index
        if ($character->isDirty('display_names') || $character->isDirty('display_name_corrections')) {
            echo "    [CharObserver] Display names changed, dispatching re-index job\n";

            // Dispatch re-indexing after the transaction commits to avoid holding locks
            // while doing expensive search indexing operations
            $characterId = $character->id;
            dispatch(function () use ($characterId) {
                try {
                    $character = Character::find($characterId);
                    if ($character) {
                        // Re-index all dialogue lines for this character
                        $character->dialogueLines()
                            ->with(['text', 'gameVersion.game'])
                            ->chunk(500, function ($dialogueLines) {
                                $dialogueLines->searchable();
                            });

                        Log::info('Updated dialogue line search indexes for character change', [
                            'character_id' => $characterId,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to update dialogue line search indexes for character change', [
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
        $this->updateRelatedDialogueLines($character);
    }

    /**
     * Update all dialogue lines that reference this character.
     */
    private function updateRelatedDialogueLines(Character $character): void
    {
        try {
            echo "    [CharObserver] Loading dialogue lines for character\n";
            // Get all dialogue lines with this character and update their search index
            // Eager load relationships to avoid N+1 queries during indexing
            $lineCount = 0;
            $character->dialogueLines()
                ->with(['text', 'gameVersion.game'])
                ->chunk(500, function ($dialogueLines) use (&$lineCount) {
                    $count = $dialogueLines->count();
                    $lineCount += $count;
                    echo "    [CharObserver] Re-indexing {$count} dialogue lines (total: {$lineCount})\n";
                    $dialogueLines->searchable();
                });

            Log::info('Updated dialogue line search indexes for character change', [
                'character_id' => $character->id,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to update dialogue line search indexes for character change', [
                'character_id' => $character->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
