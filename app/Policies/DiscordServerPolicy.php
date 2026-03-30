<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DiscordServer;
use App\Models\User;

class DiscordServerPolicy
{
    public function view(User $user, DiscordServer $server): bool
    {
        return $this->canManage($user, $server);
    }

    public function update(User $user, DiscordServer $server): bool
    {
        return $this->view($user, $server);
    }

    public function delete(User $user, DiscordServer $server): bool
    {
        return $server->owner_user_id === $user->id;
    }

    private function canManage(User $user, DiscordServer $server): bool
    {
        if ($server->owner_user_id === $user->id) {
            return true;
        }

        if ($server->relationLoaded('members')) {
            return $server->members
                ->where('user_id', $user->id)
                ->where('is_admin', true)
                ->isNotEmpty();
        }

        return $server->members()
            ->where('user_id', $user->id)
            ->where('is_admin', true)
            ->exists();
    }
}
