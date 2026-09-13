<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
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
        }
    }

    public function deleted(User $user): void
    {
        app(VnListCacheService::class)->clearPublicListsCache();
        RatingStatsCacheService::clear();
    }
}
