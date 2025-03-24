<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VnList;
use Illuminate\Auth\Access\HandlesAuthorization;

class VnListPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list.
     */
    public function view(?User $user, VnList $vnList): bool
    {
        // Public lists can be viewed by anyone
        if ($vnList->is_public) {
            return true;
        }

        // Non-public lists can only be viewed by their owners
        return $user && $user->id === $vnList->user_id;
    }

    /**
     * Determine whether the user can update the list.
     */
    public function update(User $user, VnList $vnList): bool
    {
        // Allow updating default lists as long as the user owns them
        return $user->id === $vnList->user_id;
    }

    /**
     * Determine whether the user can delete the list.
     */
    public function delete(User $user, VnList $vnList): bool
    {
        return $user->id === $vnList->user_id && ! $vnList->is_default;
    }
}
