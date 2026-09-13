<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DiscordServer;
use App\Models\User;

class DiscordServerPolicy
{
    public function view(User $user, DiscordServer $server): bool
    {
        $discordIds = $user->socialAccounts()->where('provider_name', 'discord')->pluck('provider_id');
        if ($discordIds->isEmpty()) {
            return false;
        }

        if ($server->owner_user_id === $user->id) {
            return true;
        }

        if ($server->relationLoaded('members')) {
            return $server->members
                ->where('user_id', $user->id)
                ->whereIn('discord_user_id', $discordIds)
                ->where('is_admin', true)
                ->isNotEmpty();
        }

        return $server->members()
            ->where('user_id', $user->id)
            ->whereIn('discord_user_id', $discordIds)
            ->where('is_admin', true)
            ->exists();
    }

    public function update(User $user, DiscordServer $server): bool
    {
        return $this->view($user, $server);
    }

    public function delete(User $user, DiscordServer $server): bool
    {
        return $server->owner_user_id === $user->id
            && $user->socialAccounts()->where('provider_name', 'discord')->exists();
    }
}
