<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\HomePageCacheService;

class UserObserver
{
    public function created(User $user): void
    {
        $user->initializeDefaultLists();

        HomePageCacheService::clearStats();
    }
}
