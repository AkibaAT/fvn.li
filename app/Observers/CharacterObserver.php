<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Character;
use App\Models\UniqueDialogueText;
use Exception;
use Illuminate\Support\Facades\Log;

class CharacterObserver
{
    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        // If display names changed, update related dialogue texts
        if ($character->isDirty('display_names') || $character->isDirty('display_name_corrections')) {
            $this->updateRelatedDialogueTexts($character);
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
            // Get all unique dialogue texts that have dialogue lines with this character
            $dialogueTextIds = $character->dialogueLines()
                ->distinct()
                ->pluck('text_id')
                ->filter();

            if ($dialogueTextIds->isNotEmpty()) {
                // Update search index for all related dialogue texts
                // Eager load relationships to avoid N+1 queries during indexing
                UniqueDialogueText::whereIn('id', $dialogueTextIds)
                    ->with(['dialogueLines.character', 'dialogueLines.gameVersion.game'])
                    ->chunk(100, function ($dialogueTexts) {
                        $dialogueTexts->searchable();
                    });

                Log::info('Updated dialogue text search indexes for character change', [
                    'character_id' => $character->id,
                    'dialogue_texts_updated' => $dialogueTextIds->count(),
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to update dialogue text search indexes for character change', [
                'character_id' => $character->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
