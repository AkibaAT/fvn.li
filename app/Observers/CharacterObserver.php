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
        // If display names changed, update related dialogue lines in search index
        if ($character->isDirty('display_names') || $character->isDirty('display_name_corrections')) {
            $this->updateRelatedDialogueLines($character);
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
            // Get all dialogue lines with this character and update their search index
            // Eager load relationships to avoid N+1 queries during indexing
            $character->dialogueLines()
                ->with(['text', 'gameVersion.game'])
                ->chunk(500, function ($dialogueLines) {
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
