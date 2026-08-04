<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VnList;
use Illuminate\Auth\Access\HandlesAuthorization;

class VnListPolicy
{
    use HandlesAuthorization;

    public function view(?User $user, VnList $vnList): bool
    {
        // Public lists can be viewed by anyone
        if ($vnList->is_public) {
            return true;
        }

        // Non-public lists can only be viewed by their owners
        return $user && $user->id === $vnList->user_id;
    }

    public function update(User $user, VnList $vnList): bool
    {
        // Allow updating default lists as long as the user owns them
        return $user->id === $vnList->user_id;
    }

    public function delete(User $user, VnList $vnList): bool
    {
        return $user->id === $vnList->user_id && ! $vnList->is_default;
    }
}
