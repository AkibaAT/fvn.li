<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Character;
use App\Services\ObserverSearchIndexService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterObserver
{
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
                $gameIds = $this->gameIdsForCharacter($characterId);
                if ($gameIds->isNotEmpty()) {
                    app(ObserverSearchIndexService::class)->reindexDialogue(
                        $gameIds,
                        'character change',
                        ['character_id' => $characterId]
                    );
                }
            })->afterCommit();

            Log::debug('Character re-index job dispatched', ['character_id' => $character->character_id]);
        }
    }

    public function deleted(Character $character): void
    {
        $this->updateRelatedDialogueTexts($character);
    }

    private function updateRelatedDialogueTexts(Character $character): void
    {
        $gameIds = $this->gameIdsForCharacter($character->id);
        if ($gameIds->isNotEmpty()) {
            app(ObserverSearchIndexService::class)->reindexDialogue(
                $gameIds,
                'character deletion',
                ['character_id' => $character->id]
            );
        }
    }

    private function gameIdsForCharacter(int $characterId): Collection
    {
        return DB::table('version_dialogue_lines')
            ->where('character_id', $characterId)
            ->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
            ->distinct()
            ->pluck('game_versions.game_id');
    }
}
