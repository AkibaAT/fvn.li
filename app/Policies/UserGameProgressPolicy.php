<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserGameProgress;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserGameProgressPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the game progress.
     */
    public function update(User $user, UserGameProgress $progress): bool
    {
        // Users can only update their own game progress
        return $user->id === $progress->user_id;
    }
}
