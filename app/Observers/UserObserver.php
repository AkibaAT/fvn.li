<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\HomePageCacheService;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Initialize default lists for new users
        $user->initializeDefaultLists();

        // Clear home page stats for new user count
        HomePageCacheService::clearStats();
    }
}
