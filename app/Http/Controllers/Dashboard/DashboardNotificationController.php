<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\DiscordUserInstallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardNotificationController extends Controller
{
    private const USER_INSTALL_SESSION_KEY = 'discord_user_install';

    public function getNotificationPreferences(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $preferences = $user->notificationPreferences()->first();
        if (! $preferences) {
            $preferences = $user->notificationPreferences()->create([
                'browser_notifications_enabled' => false,
                'discord_notifications_enabled' => false,
                'notification_digest' => 'asap',
            ]);
        }

        return response()->json([
            'success' => true,
            'preferences' => [
                'browser_notifications_enabled' => (bool) $preferences->browser_notifications_enabled,
                'discord_notifications_enabled' => (bool) $preferences->discord_notifications_enabled,
                'notification_digest' => $preferences->notification_digest,
            ],
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $data = $request->validate([
            'browser_notifications_enabled' => 'boolean',
            'discord_notifications_enabled' => 'boolean',
            'notification_digest' => 'in:asap,daily,weekly',
        ]);

        $preferences = $user->notificationPreferences()->firstOrCreate([], [
            'browser_notifications_enabled' => false,
            'discord_notifications_enabled' => false,
            'notification_digest' => 'asap',
        ]);
        $enablingDiscord = ($data['discord_notifications_enabled'] ?? false)
            && ! $preferences->discord_notifications_enabled;

        if ($enablingDiscord && ! $user->socialAccounts()->where('provider_name', 'discord')->exists()) {
            return response()->json(['success' => false, 'message' => 'Link your Discord account before enabling direct messages.'], 422);
        }

        $preferences->update($data);
        if ($enablingDiscord) {
            $preferences->markDiscordUnverified();
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'preferences' => [
                'browser_notifications_enabled' => (bool) $preferences->browser_notifications_enabled,
                'discord_notifications_enabled' => (bool) $preferences->discord_notifications_enabled,
                'notification_digest' => $preferences->notification_digest,
            ],
        ]);
    }

    /**
     * Send the signed-in account to Discord to authorize the user install.
     */
    public function redirectToDiscordUserInstall(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->socialAccounts()->where('provider_name', 'discord')->exists(), 404);

        $state = Str::random(40);
        $request->session()->put(self::USER_INSTALL_SESSION_KEY, [
            'state' => $state,
            'user_id' => $user->id,
        ]);

        return redirect()->away('https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.discord.client_id'),
            'integration_type' => 1,
            'scope' => 'applications.commands identify',
            'response_type' => 'code',
            'redirect_uri' => route('dashboard.discord.user-install.callback'),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * Record the authorization, confirming it landed on the Discord account
     * that is actually linked here.
     */
    public function handleDiscordUserInstallCallback(Request $request, DiscordUserInstallService $installs): RedirectResponse
    {
        $install = $request->session()->pull(self::USER_INSTALL_SESSION_KEY);
        $user = $request->user();
        $back = redirect()->route('dashboard');

        if (! is_array($install) || ! $user || ($install['user_id'] ?? null) !== $user->id) {
            return $back->with('error', 'The Discord authorization session expired. Please try again.');
        }

        if ($request->filled('error')) {
            return $back->with('error', 'Discord authorization was cancelled.');
        }

        if (! hash_equals((string) ($install['state'] ?? ''), (string) $request->query('state', ''))) {
            return $back->with('error', 'Discord authorization could not be verified. Please try again.');
        }

        $authorizedId = $installs->resolveAuthorizingUserId((string) $request->query('code', ''));

        if ($authorizedId === null) {
            return $back->with('error', 'Discord did not confirm the authorization. Please try again.');
        }

        $linkedId = (string) $user->socialAccounts()->where('provider_name', 'discord')->value('provider_id');

        if ($authorizedId !== $linkedId) {
            return $back->with('error', 'That Discord account is not the one linked here. Sign in to Discord as the linked account and try again.');
        }

        $installs->recordInstalled($user);

        return $back->with('message', 'Discord is authorized. Send a test message to confirm delivery.');
    }
}
