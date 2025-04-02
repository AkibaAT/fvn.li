<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotificationPreferences;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDashboardController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $connectedProviders = $user->socialAccounts->pluck('provider_name')->toArray();

        // Get social account information
        $socialAccounts = $user->socialAccounts->mapWithKeys(function ($account) {
            $displayName = null;
            $avatar = null;

            if ($account->provider_data) {
                switch ($account->provider_name) {
                    case 'discord':
                        $displayName = $account->provider_data['global_name'] ?? $account->provider_data['username'] ?? null;
                        $avatar = isset($account->provider_data['avatar'])
                            ? "https://cdn.discordapp.com/avatars/{$account->provider_data['id']}/{$account->provider_data['avatar']}.png"
                            : null;
                        break;
                    case 'google':
                        $displayName = $account->provider_data['given_name'] ?? null;
                        $avatar = $account->provider_data['picture'] ?? null;
                        break;
                    case 'steam':
                        $displayName = $account->provider_data['personaname'] ?? null;
                        $avatar = $account->provider_data['avatarfull'] ?? null;
                        break;
                    case 'telegram':
                        $displayName = $account->provider_data['first_name'] .
                            (isset($account->provider_data['last_name']) ? ' ' . $account->provider_data['last_name'] : '');
                        $avatar = $account->provider_data['photo_url'] ?? null;
                        break;
                    case 'itchio':
                        $displayName = $account->provider_data['username'] ?? null;
                        $avatar = $account->provider_data['cover_url'] ?? null;
                        break;
                }
            }

            return [$account->provider_name => [
                'display_name' => $displayName,
                'avatar' => $avatar,
            ]];
        })->toArray();

        return view('users.dashboard.show', [
            'user' => $user,
            'connectedProviders' => $connectedProviders,
            'socialAccounts' => $socialAccounts,
        ]);
    }

    /**
     * Export all user data in JSON format.
     */
    public function exportData(): StreamedResponse
    {
        $user = Auth::user();

        // Load all necessary relationships
        $user->load([
            'socialAccounts',
            'vnLists.entries.game',
            'gameProgress.game',
            'gameProgress.gameVersion',
            'notificationHistory.game',
            'notificationHistory.gameVersion',
        ]);

        $exportData = [
            'user' => [
                'name' => $user->name,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
            ],
            'social_accounts' => $user->socialAccounts->map(function ($account) {
                // Filter sensitive data from provider_data
                $providerData = $account->provider_data;
                $safeProviderData = [];

                if ($providerData) {
                    switch ($account->provider_name) {
                        case 'discord':
                            $safeProviderData = [
                                'username' => $providerData['username'] ?? null,
                                'global_name' => $providerData['global_name'] ?? null,
                                'avatar' => isset($providerData['avatar'])
                                    ? "https://cdn.discordapp.com/avatars/{$providerData['id']}/{$providerData['avatar']}.png"
                                    : null,
                            ];
                            break;
                        case 'google':
                            $safeProviderData = [
                                'given_name' => $providerData['given_name'] ?? null,
                                'picture' => $providerData['picture'] ?? null,
                            ];
                            break;
                        case 'steam':
                            $safeProviderData = [
                                'personaname' => $providerData['personaname'] ?? null,
                                'avatarfull' => $providerData['avatarfull'] ?? null,
                            ];
                            break;
                        case 'telegram':
                            $safeProviderData = [
                                'first_name' => $providerData['first_name'] ?? null,
                                'photo_url' => $providerData['photo_url'] ?? null,
                            ];
                            break;
                    }
                }

                return [
                    'provider_name' => $account->provider_name,
                    'created_at' => $account->created_at,
                    'provider_data' => $safeProviderData,
                ];
            })->values()->toArray(),
            'vn_lists' => $user->vnLists->map(function ($list) {
                return [
                    'name' => $list->name,
                    'description' => $list->description,
                    'type' => $list->type,
                    'is_default' => $list->is_default,
                    'is_public' => $list->is_public,
                    'created_at' => $list->created_at,
                    'entries' => $list->entries->map(function ($entry) {
                        return [
                            'game' => [
                                'name' => $entry->game->name,
                                'url' => $entry->game->url,
                                'status' => $entry->game->status,
                            ],
                            'sort_order' => $entry->sort_order,
                            'receive_updates' => $entry->receive_updates,
                            'created_at' => $entry->created_at,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray(),
            'game_progress' => $user->gameProgress->map(function ($progress) {
                return [
                    'game' => [
                        'name' => $progress->game->name,
                        'url' => $progress->game->url,
                        'status' => $progress->game->status,
                    ],
                    'version' => $progress->gameVersion ? [
                        'version' => $progress->gameVersion->version,
                        'published_at' => $progress->gameVersion->published_at,
                    ] : null,
                    'status' => $progress->status,
                    'started_at' => $progress->started_at,
                    'completed_at' => $progress->completed_at,
                    'personal_notes' => $progress->personal_notes,
                    'created_at' => $progress->created_at,
                    'updated_at' => $progress->updated_at,
                ];
            })->values()->toArray(),
            'notification_history' => $user->notificationHistory->map(function ($notification) {
                return [
                    'type' => $notification->type,
                    'success' => $notification->success,
                    'meta_data' => $notification->meta_data,
                    'created_at' => $notification->created_at,
                    'game' => $notification->game ? [
                        'name' => $notification->game->name,
                        'url' => $notification->game->url,
                    ] : null,
                    'version' => $notification->gameVersion ? [
                        'version' => $notification->gameVersion->version,
                        'published_at' => $notification->gameVersion->published_at,
                    ] : null,
                ];
            })->values()->toArray(),
        ];

        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 'user-data-export.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function deleteAccount()
    {
        $user = Auth::user();

        // Start a transaction to ensure all related data is deleted
        DB::transaction(function () use ($user) {
            // Delete all user's data
            $user->socialAccounts()->delete();
            $user->vnLists()->delete();
            $user->gameProgress()->delete();
            $user->notificationHistory()->delete();

            // Finally delete the user
            $user->delete();
        });

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('games.index')
            ->with('success', 'Your account has been successfully deleted.');
    }

    public function mergeSocialAccounts(string $provider)
    {
        $user = Auth::user();

        // Store the current user ID for merging later
        session(['merging_user_id' => $user->id]);

        // Redirect to the provider's OAuth page
        if ($provider === 'telegram') {
            return redirect()->route('auth.telegram');
        }

        return redirect()->route('auth.redirect', ['provider' => $provider]);
    }

    public function disconnectSocialAccount(string $provider)
    {
        $user = Auth::user();

        // Count total connected providers
        $connectedProvidersCount = $user->socialAccounts()->count();

        // Don't allow disconnecting the last provider
        if ($connectedProvidersCount <= 1) {
            return redirect()->route('user.dashboard.show')
                ->with('error', 'Cannot disconnect your last social account. Delete your account instead if you wish to completely disconnect.');
        }

        // Delete the social account
        $user->socialAccounts()
            ->where('provider_name', $provider)
            ->delete();

        return redirect()->route('user.dashboard.show')
            ->with('success', 'Successfully disconnected ' . ucfirst($provider) . ' account.');
    }

    /**
     * Update user's notification preferences.
     */
    public function updateNotificationPreferences(Request $request)
    {
        $user = Auth::user();
        Log::info('Updating notification preferences', [
            'user_id' => $user->id,
            'request_data' => $request->all(),
            'has_discord' => $request->has('discord_notifications_enabled'),
            'has_browser' => $request->has('browser_notifications_enabled'),
            'discord_value' => $request->input('discord_notifications_enabled'),
            'browser_value' => $request->input('browser_notifications_enabled'),
            'digest_value' => $request->input('notification_digest'),
        ]);

        try {
            // Convert checkbox values to boolean before validation
            $request->merge([
                'discord_notifications_enabled' => $request->has('discord_notifications_enabled'),
                'browser_notifications_enabled' => $request->has('browser_notifications_enabled'),
            ]);

            $validated = $request->validate([
                'discord_notifications_enabled' => 'boolean',
                'browser_notifications_enabled' => 'boolean',
                'notification_digest' => 'required|in:asap,daily,weekly',
            ]);

            Log::info('Validated data', ['validated' => $validated]);

            // Use a transaction to ensure data consistency
            DB::beginTransaction();
            try {
                // Update or create preferences
                $preferences = $user->notificationPreferences()->updateOrCreate(
                    ['user_id' => $user->id],
                    $validated
                );

                Log::info('Updated preferences', ['preferences' => $preferences->toArray()]);

                // Verify the data was saved
                $savedPreferences = UserNotificationPreferences::where('user_id', $user->id)->first();
                Log::info('Verified saved preferences', ['saved_preferences' => $savedPreferences ? $savedPreferences->toArray() : null]);

                DB::commit();

                return response()->json(['success' => true]);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Error updating notification preferences', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
