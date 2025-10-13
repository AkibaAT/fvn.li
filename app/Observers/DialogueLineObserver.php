<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DialogueLine;
use App\Models\UniqueDialogueText;
use Exception;
use Illuminate\Support\Facades\Log;

class DialogueLineObserver
{
    /**
     * Handle the DialogueLine "created" event.
     */
    public function created(DialogueLine $dialogueLine): void
    {
        $this->updateRelatedDialogueText($dialogueLine);
    }

    /**
     * Handle the DialogueLine "updated" event.
     */
    public function updated(DialogueLine $dialogueLine): void
    {
        // If the text_id changed, update both old and new dialogue texts
        if ($dialogueLine->isDirty('text_id')) {
            $oldTextId = $dialogueLine->getOriginal('text_id');
            if ($oldTextId) {
                $this->updateDialogueTextById($oldTextId);
            }
        }

        $this->updateRelatedDialogueText($dialogueLine);
    }

    /**
     * Handle the DialogueLine "deleted" event.
     */
    public function deleted(DialogueLine $dialogueLine): void
    {
        $this->updateRelatedDialogueText($dialogueLine);
    }

    /**
     * Update the related UniqueDialogueText search index.
     */
    private function updateRelatedDialogueText(DialogueLine $dialogueLine): void
    {
        if ($dialogueLine->text_id) {
            $this->updateDialogueTextById($dialogueLine->text_id);
        }
    }

    /**
     * Update dialogue text search index by ID.
     */
    private function updateDialogueTextById(int $textId): void
    {
        try {
            $dialogueText = UniqueDialogueText::find($textId);
            if ($dialogueText && $dialogueText->shouldBeSearchable()) {
                // Use searchable() to update the index
                $dialogueText->searchable();
            }
        } catch (Exception $e) {
            Log::warning('Failed to update dialogue text search index', [
                'text_id' => $textId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
