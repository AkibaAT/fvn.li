<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\NotificationHistory;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\UserNotificationPreferences;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
                        $displayName = $account->provider_data['display_name'] ?? null;
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
            'noindex' => true,
            'metaTags' => [
                'title' => 'User Dashboard',
            ],
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

        // Include click statistics for owned games (for developers)
        $itchioUsername = $user->getItchioUsername();
        if ($itchioUsername) {
            $ownedGames = $user->getOwnedGames();
            if ($ownedGames->isNotEmpty()) {
                $gameIds = $ownedGames->pluck('id')->toArray();

                // Get all click statistics for owned games
                $clickStats = ClickStat::whereIn('game_id', $gameIds)
                    ->orderBy('clicked_at', 'desc')
                    ->get();

                $exportData['click_statistics'] = [
                    'summary' => 'Click statistics for games you own/develop',
                    'total_entries' => $clickStats->count(),
                    'games_tracked' => $ownedGames->map(function ($game) use ($clickStats) {
                        $gameStats = $clickStats->where('game_id', $game->id);

                        return [
                            'game_name' => $game->name,
                            'game_url' => $game->url,
                            'total_clicks' => $gameStats->count(),
                            'page_views' => $gameStats->where('type', ClickStat::TYPE_PAGE_VIEW)->count(),
                            'external_project_clicks' => $gameStats->where('type', ClickStat::TYPE_EXTERNAL_PROJECT)->count(),
                            'custom_link_clicks' => $gameStats->where('type', ClickStat::TYPE_CUSTOM_LINK)->count(),
                            'first_tracked' => $gameStats->min('clicked_at'),
                            'last_tracked' => $gameStats->max('clicked_at'),
                        ];
                    })->values()->toArray(),
                    'detailed_logs' => $clickStats->map(function ($stat) use ($ownedGames) {
                        $game = $ownedGames->firstWhere('id', $stat->game_id);

                        return [
                            'game_name' => $game ? $game->name : 'Unknown Game',
                            'type' => $stat->type,
                            'link_id' => $stat->link_id,
                            'clicked_at' => $stat->clicked_at,
                            'referrer' => $stat->referrer,
                            // Note: session_id, ip_address, and user_agent are excluded for privacy
                        ];
                    })->values()->toArray(),
                ];
            }
        }

        // Include audit logs for GDPR compliance (Article 20 - Data Portability)
        if (config('audit.privacy.enable_data_export', true)) {
            $auditExport = ChangeLog::exportUserData($user->id);
            $exportData['audit_logs'] = $auditExport['audit_logs'];
            $exportData['audit_summary'] = [
                'total_entries' => $auditExport['total_entries'],
                'exported_at' => $auditExport['exported_at'],
            ];
        }

        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 'user-data-export.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function deleteAccount()
    {
        $user = Auth::user();

        // Start a transaction to ensure all related data is properly handled
        DB::transaction(function () use ($user) {
            // Handle audit logs for GDPR compliance
            if (config('audit.privacy.enable_data_deletion', true)) {
                // Anonymize audit logs to preserve system audit integrity
                // while removing personal identifiers (GDPR Article 17)
                $anonymizedCount = ChangeLog::anonymizeUserData($user->id);

                Log::info('Anonymized audit logs during account deletion', [
                    'user_id' => $user->id,
                    'anonymized_count' => $anonymizedCount,
                ]);
            }

            // Anonymize click statistics to remove personal identifiers
            // while preserving statistical data for legitimate business interests
            $clickStatsAnonymized = ClickStat::anonymizePersonalData();

            Log::info('Anonymized click statistics during account deletion', [
                'user_id' => $user->id,
                'anonymized_count' => $clickStatsAnonymized,
            ]);

            // Delete all user's personal data
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

        try {
            // Always make sure to properly extract boolean values from checkboxes
            $discordNotificationsEnabled = $request->has('discord_notifications_enabled') &&
                filter_var($request->input('discord_notifications_enabled'), FILTER_VALIDATE_BOOLEAN);

            $browserNotificationsEnabled = $request->has('browser_notifications_enabled') &&
                filter_var($request->input('browser_notifications_enabled'), FILTER_VALIDATE_BOOLEAN);

            $notificationDigest = $request->input('notification_digest');

            Log::info('Notification preferences data', [
                'user_id' => $user->id,
                'raw_data' => $request->all(),
                'discord' => $discordNotificationsEnabled,
                'browser' => $browserNotificationsEnabled,
                'digest' => $notificationDigest,
            ]);

            // Validate inputs
            $validator = Validator::make([
                'discord_notifications_enabled' => $discordNotificationsEnabled,
                'browser_notifications_enabled' => $browserNotificationsEnabled,
                'notification_digest' => $notificationDigest,
            ], [
                'discord_notifications_enabled' => 'boolean',
                'browser_notifications_enabled' => 'boolean',
                'notification_digest' => 'required|in:asap,daily,weekly',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Use a transaction to ensure data consistency
            DB::beginTransaction();

            // Update or create preferences
            $preferences = UserNotificationPreferences::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'discord_notifications_enabled' => $discordNotificationsEnabled,
                    'browser_notifications_enabled' => $browserNotificationsEnabled,
                    'notification_digest' => $notificationDigest,
                ]
            );

            DB::commit();

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating notification preferences', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while updating notification preferences',
            ], 500);
        }
    }

    /**
     * Show digest notifications for a specific date.
     */
    public function showDigestNotifications(string $date): View
    {
        $user = Auth::user();
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $notifications = NotificationHistory::where('user_id', $user->id)
            ->where('type', 'browser')
            ->where('success', true)
            ->whereRaw("CAST(meta_data AS jsonb) @> '{\"digest\": true}'")
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['game' => function ($query) {
                $query->select('id', 'name', 'slug', 'thumb_url', 'optimized_thumbnails');
            }, 'gameVersion'])
            ->get()
            ->groupBy(function ($notification) {
                return $notification->meta_data['digest_type'] ?? 'unknown';
            });

        // Get user's game progress for all games in the notifications
        $gameIds = collect($notifications)->flatMap(function ($digestNotifications) {
            return $digestNotifications->pluck('game_id');
        })->unique()->values()->toArray();

        $userGameProgress = UserGameProgress::where('user_id', $user->id)
            ->whereIn('game_id', $gameIds)
            ->with(['gameVersion'])
            ->get()
            ->keyBy('game_id');

        // Initialize version comparison stats
        $versionComparisonStats = null;

        return view('users.dashboard.digest-notifications', [
            'notifications' => $notifications,
            'date' => $date,
            'userGameProgress' => $userGameProgress,
            'versionComparisonStats' => $versionComparisonStats,
            'metaTags' => [
                'noindex' => true,
                'title' => 'Digest Notifications',
            ],
        ]);
    }

    /**
     * Get version comparison data for a game
     */
    public function getVersionComparison(Request $request): JsonResponse
    {
        $fromVersionId = $request->input('fromVersionId');
        $toVersionId = $request->input('toVersionId');
        $gameId = $request->input('gameId');

        if (! $fromVersionId || ! $toVersionId || ! $gameId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        $game = Game::find($gameId);
        if (! $game) {
            return response()->json(['error' => 'Game not found'], 404);
        }

        $fromVersion = GameVersion::find($fromVersionId);
        $toVersion = GameVersion::find($toVersionId);

        if (! $fromVersion || ! $toVersion) {
            return response()->json(['error' => 'Version not found'], 404);
        }

        // Ensure fromVersion is the older one
        if ($fromVersion->published_at > $toVersion->published_at) {
            // Swap them
            $temp = $fromVersion;
            $fromVersion = $toVersion;
            $toVersion = $temp;
        }

        // Compare character stats
        $fromCharacterStats = $fromVersion->characterStats()
            ->where('iso_code', 'not like', 'q%')
            ->whereExists(function ($query) use ($fromVersion) {
                $query->selectRaw(1)
                    ->from('version_supported_languages')
                    ->where('game_version_id', $fromVersion->id)
                    ->whereColumn('version_supported_languages.iso_code', 'version_character_stats.iso_code')
                    ->where('is_available', true);
            })
            ->with(['character', 'language'])
            ->get();

        $toCharacterStats = $toVersion->characterStats()
            ->where('iso_code', 'not like', 'q%')
            ->whereExists(function ($query) use ($toVersion) {
                $query->selectRaw(1)
                    ->from('version_supported_languages')
                    ->where('game_version_id', $toVersion->id)
                    ->whereColumn('version_supported_languages.iso_code', 'version_character_stats.iso_code')
                    ->where('is_available', true);
            })
            ->with(['character', 'language'])
            ->get();

        // Get unique languages that are available in either version
        $fromLanguages = $fromCharacterStats->pluck('language.id')->unique();
        $toLanguages = $toCharacterStats->pluck('language.id')->unique();
        $allLanguages = $fromLanguages->merge($toLanguages)->unique();

        $languages = [];
        foreach ($allLanguages as $langId) {
            $language = Language::find($langId);
            if ($language) {
                $languages[] = [
                    'id' => $language->id,
                    'name' => $language->name,
                    'flag' => $language->flag,
                ];
            }
        }

        // Get all characters from both versions
        $fromCharacters = $fromCharacterStats->pluck('character.name')->unique();
        $toCharacters = $toCharacterStats->pluck('character.name')->unique();
        $allCharacters = $fromCharacters->merge($toCharacters)->unique()->values()->toArray();

        // Initialize word counts
        $fromWordCounts = [];
        $toWordCounts = [];
        foreach ($allCharacters as $character) {
            $fromWordCounts[$character] = [];
            $toWordCounts[$character] = [];
            foreach ($languages as $lang) {
                $fromWordCounts[$character][$lang['id']] = null;
                $toWordCounts[$character][$lang['id']] = null;
            }
        }

        // Fill in the word counts
        foreach ($fromCharacterStats as $stat) {
            $characterName = $stat->character->name;
            $langId = $stat->language->id;
            $fromWordCounts[$characterName][$langId] = $stat->words;
        }

        foreach ($toCharacterStats as $stat) {
            $characterName = $stat->character->name;
            $langId = $stat->language->id;
            $toWordCounts[$characterName][$langId] = $stat->words;
        }

        // Calculate differences
        $characterDiffs = [];
        $languageTotals = [
            'from' => [],
            'to' => [],
            'diff' => [],
        ];

        foreach ($allCharacters as $character) {
            $characterDiffs[$character] = [];

            foreach ($languages as $lang) {
                $fromCount = $fromWordCounts[$character][$lang['id']];
                $toCount = $toWordCounts[$character][$lang['id']];
                $diff = $fromCount !== null && $toCount !== null ? $toCount - $fromCount : null;

                $characterDiffs[$character][$lang['id']] = [
                    'from' => $fromCount,
                    'to' => $toCount,
                    'diff' => $diff,
                ];

                // Update language totals
                if (! isset($languageTotals['from'][$lang['id']])) {
                    $languageTotals['from'][$lang['id']] = 0;
                }
                if (! isset($languageTotals['to'][$lang['id']])) {
                    $languageTotals['to'][$lang['id']] = 0;
                }
                if (! isset($languageTotals['diff'][$lang['id']])) {
                    $languageTotals['diff'][$lang['id']] = 0;
                }

                if ($fromCount !== null) {
                    $languageTotals['from'][$lang['id']] += $fromCount;
                }
                if ($toCount !== null) {
                    $languageTotals['to'][$lang['id']] += $toCount;
                }
                if ($diff !== null) {
                    $languageTotals['diff'][$lang['id']] += $diff;
                }
            }
        }

        // Sort characters
        $sortedCharacters = $allCharacters;
        sort($sortedCharacters, SORT_NATURAL | SORT_FLAG_CASE);

        // Compare file stats
        $fromFileCategories = $fromVersion->fileCategories()->with('fileTypes')->get();
        $toFileCategories = $toVersion->fileCategories()->with('fileTypes')->get();

        $fileCategoryComparisons = [];

        // Get unique categories
        $allCategories = $fromFileCategories->pluck('category')
            ->merge($toFileCategories->pluck('category'))
            ->unique();

        foreach ($allCategories as $category) {
            $fromCategory = $fromFileCategories->firstWhere('category', $category);
            $toCategory = $toFileCategories->firstWhere('category', $category);

            $categoryComparison = [
                'category' => $category,
                'from' => [
                    'count' => $fromCategory ? $fromCategory->total_count : 0,
                    'size' => $fromCategory ? $fromCategory->total_size : 0,
                ],
                'to' => [
                    'count' => $toCategory ? $toCategory->total_count : 0,
                    'size' => $toCategory ? $toCategory->total_size : 0,
                ],
                'diff' => [
                    'count' => ($toCategory ? $toCategory->total_count : 0) - ($fromCategory ? $fromCategory->total_count : 0),
                    'size' => ($toCategory ? $toCategory->total_size : 0) - ($fromCategory ? $fromCategory->total_size : 0),
                ],
                'fileTypes' => [],
            ];

            // Get unique file types
            $fromFileTypes = $fromCategory ? $fromCategory->fileTypes->pluck('extension')->unique() : collect();
            $toFileTypes = $toCategory ? $toCategory->fileTypes->pluck('extension')->unique() : collect();
            $allFileTypes = $fromFileTypes->merge($toFileTypes)->unique()->values()->toArray();

            foreach ($allFileTypes as $extension) {
                $fromFileType = $fromCategory ? $fromCategory->fileTypes->firstWhere('extension', $extension) : null;
                $toFileType = $toCategory ? $toCategory->fileTypes->firstWhere('extension', $extension) : null;

                $categoryComparison['fileTypes'][$extension] = [
                    'from' => [
                        'count' => $fromFileType ? $fromFileType->count : 0,
                        'size' => $fromFileType ? $fromFileType->size : 0,
                    ],
                    'to' => [
                        'count' => $toFileType ? $toFileType->count : 0,
                        'size' => $toFileType ? $toFileType->size : 0,
                    ],
                    'diff' => [
                        'count' => ($toFileType ? $toFileType->count : 0) - ($fromFileType ? $fromFileType->count : 0),
                        'size' => ($toFileType ? $toFileType->size : 0) - ($fromFileType ? $fromFileType->size : 0),
                    ],
                ];
            }

            $fileCategoryComparisons[] = $categoryComparison;
        }

        $versionComparisonStats = [
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'thumbnail' => $game->thumbnail,
            ],
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'characters' => $sortedCharacters,
            'languages' => $languages,
            'characterDiffs' => $characterDiffs,
            'languageTotals' => $languageTotals,
            'fileCategories' => $fileCategoryComparisons,
        ];

        return response()->json($versionComparisonStats);
    }
}
