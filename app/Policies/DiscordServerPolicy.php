<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DiscordServer;
use App\Models\User;

class DiscordServerPolicy
{
    public function view(User $user, DiscordServer $server): bool
    {
        return $server->owner_user_id === $user->id;
    }

    public function update(User $user, DiscordServer $server): bool
    {
        return $this->view($user, $server);
    }

    public function delete(User $user, DiscordServer $server): bool
    {
        return $this->view($user, $server);
    }
}
