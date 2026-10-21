<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Rating;
use App\Models\User;
use App\Models\VnList;
use App\Services\GamePageCacheService;
use App\Services\HomePageCacheService;
use App\Services\RatingStatsCacheService;
use App\Services\VnListCacheService;

class UserObserver
{
    public function created(User $user): void
    {
        $user->initializeDefaultLists();

        HomePageCacheService::clearStats();
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged(['name', 'avatar'])) {
            app(VnListCacheService::class)->clearPublicListsCache();
            RatingStatsCacheService::clear();
            GamePageCacheService::invalidateMany(Rating::query()
                ->where('user_id', $user->id)
                ->orWhereHas('rater', fn ($rater) => $rater->where('user_id', $user->id))
                ->distinct()
                ->pluck('game_id'));
        }
    }

    public function deleting(User $user): void
    {
        $user->vnLists()->where('is_public', true)->each(fn (VnList $list) => $list->invalidateGamePages());
    }

    public function deleted(User $user): void
    {
        app(VnListCacheService::class)->clearPublicListsCache();
        RatingStatsCacheService::clear();
    }
}
