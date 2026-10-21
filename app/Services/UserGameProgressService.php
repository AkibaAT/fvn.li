<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\UserGameProgress;
use App\Models\VnList;

class UserGameProgressService
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function save(int $userId, Game $game, array $values): UserGameProgress
    {
        $progress = UserGameProgress::updateOrCreate(['user_id' => $userId, 'game_id' => $game->id], $values);

        if (VnList::query()->where('user_id', $userId)->where('is_public', true)
            ->whereHas('entries', fn ($q) => $q->where('game_id', $game->id))->exists()) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return $progress;
    }
}
