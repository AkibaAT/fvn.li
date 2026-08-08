<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\NotificationQueue;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordUserInstallService
{
    /**
     * Exchange an authorization code and return the Discord id that granted it,
     * or null when Discord does not confirm the grant.
     */
    public function resolveAuthorizingUserId(string $code): ?string
    {
        if ($code === '') {
            return null;
        }

        try {
            $token = Http::asForm()->post('https://discord.com/api/oauth2/token', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('dashboard.discord.user-install.callback'),
            ]);

            if (! $token->successful()) {
                Log::warning('Discord user-install token exchange failed', ['status' => $token->status()]);

                return null;
            }

            $identity = Http::withToken($token->json('access_token'))
                ->get('https://discord.com/api/oauth2/@me');

            if (! $identity->successful()) {
                Log::warning('Discord user-install identity lookup failed', ['status' => $identity->status()]);

                return null;
            }

            $id = $identity->json('user.id');

            return is_string($id) && $id !== '' ? $id : null;
        } catch (\Throwable $exception) {
            Log::error('Discord user-install exchange errored', ['exception' => $exception]);

            return null;
        }
    }

    public function recordInstalled(User $user): void
    {
        $this->preferencesFor($user)?->markDiscordUserInstalled();
    }

    /**
     * Drop the authorization and stop queueing DMs the app can no longer send.
     */
    public function recordUninstalled(User $user): void
    {
        $this->preferencesFor($user)?->markDiscordUninstalled();

        NotificationQueue::query()
            ->where('user_id', $user->id)
            ->where('channel', 'discord')
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'processed_at' => now(),
                'error' => 'discord_undeliverable',
                'batch_key' => null,
            ]);
    }

    public function userForDiscordId(string $discordUserId): ?User
    {
        return SocialAccount::query()
            ->where('provider_name', 'discord')
            ->where('provider_id', $discordUserId)
            ->first()?->user;
    }

    private function preferencesFor(User $user)
    {
        return $user->notificationPreferences()->firstOrCreate([], [
            'browser_notifications_enabled' => false,
            'discord_notifications_enabled' => false,
            'notification_digest' => 'asap',
        ]);
    }
}
