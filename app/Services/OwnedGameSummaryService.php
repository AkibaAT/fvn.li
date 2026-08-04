<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClickStat;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

class OwnedGameSummaryService
{
    public function username(User $user): ?string
    {
        return $user->getItchioUsername();
    }

    public function games(User $user): Collection
    {
        if (! $this->username($user)) {
            return collect();
        }

        return $user->getOwnedGames()->map(fn ($game) => [
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'thumb_url' => $game->getThumbnailUrl(),
            'platform' => $game->platform,
            'has_additional_links' => $game->hasAdditionalLinks(),
        ])->values();
    }

    public function clickStats(Collection $games): array
    {
        if ($games->isEmpty()) {
            return [];
        }

        try {
            return ClickStat::getMultipleGameStats($games->pluck('id')->all(), now()->subDays(30));
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }
}
