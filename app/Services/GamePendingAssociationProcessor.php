<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Log;

class GamePendingAssociationProcessor
{
    public function processGameJams(Game $game): void
    {
        if (empty($game->pendingGameJamId)) {
            return;
        }

        if (! $game->exists || ! $game->id) {
            Log::warning('Cannot process pending game jams - game not saved', [
                'game_name' => $game->name,
                'game_id' => $game->id,
                'exists' => $game->exists,
            ]);

            return;
        }

        $associatedGameJam = false;

        foreach ($game->pendingGameJamId as $jamId) {
            if ($game->gameJams()->where('game_jam_id', $jamId)->exists()) {
                continue;
            }

            $game->gameJams()->attach($jamId);
            $associatedGameJam = true;

            Log::info('Associated game with game jam', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'jam_id' => $jamId,
            ]);
        }

        if ($associatedGameJam) {
            GameFilterService::clearCache();

            if ($game->is_visible) {
                $game->loadMissing(['tags', 'gameJams', 'gameVersions']);
                $game->searchable();
            }
        }

        $game->pendingGameJamId = [];
    }

    public function processTags(Game $game): void
    {
        if (empty($game->pendingTagIds)) {
            return;
        }

        if (! $game->exists || ! $game->id) {
            Log::warning('Cannot process pending tags - game not saved', [
                'game_name' => $game->name,
                'game_id' => $game->id,
                'exists' => $game->exists,
            ]);

            return;
        }

        $game->tags()->sync($game->pendingTagIds);

        Log::info('Synced pending tags for game', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'tag_ids' => $game->pendingTagIds,
        ]);

        $game->pendingTagIds = [];
    }
}
