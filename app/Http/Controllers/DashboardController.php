<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdditionRequest;
use App\Models\BugReport;
use App\Models\ClickStat;
use App\Models\NotificationQueue;
use App\Models\User;
use App\Services\GameFilterService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function dashboard(): Response
    {
        $authId = Auth::id();
        if (! $authId) {
            return Inertia::render('auth/login', [
                'metaTags' => [
                    'title' => 'Log in',
                    'description' => 'Log in to your FVN.li account to track your visual novel progress, create reading lists, and connect with the community.',
                    'structuredData' => [
                        '@type' => 'WebPage',
                        'name' => 'Log in',
                        'description' => 'Log in to your FVN.li account to track your visual novel progress',
                        'url' => route('login'),
                    ],
                ],
            ]);
        }
        $user = User::findOrFail($authId);

        $connectedProviders = $user->socialAccounts()->pluck('provider_name')->toArray();

        $socialAccounts = $this->getSocialAccountsData($user);

        $itchioAccount = $user->socialAccounts()->where('provider_name', 'itchio')->first();
        $itchioUsername = $itchioAccount?->provider_data['username'] ?? (method_exists($user,
            'getItchioUsername') ? $user->getItchioUsername() : null);

        // My Games data
        $ownedGames = collect();
        $clickStats = [];
        if ($itchioUsername && method_exists($user, 'getOwnedGames')) {
            $ownedGames = $user->getOwnedGames()->map(function ($g) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'slug' => $g->slug,
                    'thumb_url' => method_exists($g, 'getThumbnailUrl') ? $g->getThumbnailUrl() : null,
                    'platform' => $g->platform,
                    'has_additional_links' => method_exists($g,
                        'hasAdditionalLinks') ? $g->hasAdditionalLinks() : ! empty($g->additional_links),
                ];
            })->values();

            if (class_exists(ClickStat::class) && $ownedGames->isNotEmpty()) {
                $gameIds = $ownedGames->pluck('id')->toArray();
                try {
                    $clickStats = ClickStat::getMultipleGameStats($gameIds, now()->subDays(30));
                } catch (Throwable $e) {
                    $clickStats = [];
                }
            }
        }

        // VN Addition Requests
        $recentRequests = [];
        if (class_exists(AdditionRequest::class)) {
            $recentRequests = $user->additionRequests()
                ->with(['game', 'reviewer'])
                ->orderBy('addition_request_users.created_at', 'desc')
                ->take(20)
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'game_url' => $request->game_url,
                        'platform' => $request->platform,
                        'status' => $request->status,
                        'status_label' => $request->status_label,
                        'status_color' => $request->status_color,
                        'created_at' => $request->created_at?->toISOString(),
                        'reviewed_at' => $request->reviewed_at?->toISOString(),
                        'game' => $request->game ? [
                            'id' => $request->game->id,
                            'name' => $request->game->name,
                            'slug' => $request->game->slug,
                        ] : null,
                        'reviewer' => $request->reviewer ? [
                            'name' => $request->reviewer->name,
                        ] : null,
                    ];
                })
                ->values();
        }

        $notificationPreferences = $user->notificationPreferences()->first() ?? $user->notificationPreferences()->create([
            'browser_notifications_enabled' => false,
            'discord_notifications_enabled' => false,
            'notification_digest' => 'asap',
        ]);

        // Search preferences data
        $ignoredGames = $user->ignoredGames()
            ->select('games.id', 'games.name', 'games.slug', 'games.thumb_url', 'games.optimized_thumbnails', 'games.platform')
            ->orderBy('user_ignored_games.created_at', 'desc')
            ->get()
            ->map(function ($game) {
                return [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'thumb_url' => $game->getThumbnailUrl(),
                    'optimized_thumbnails' => $game->optimized_thumbnails,
                    'platform' => $game->platform,
                ];
            });

        $ignoredGamesCount = $user->ignoredGames()->count();

        // Get user's active bug reports (not closed by user) with unread admin reply counts
        $activeBugReports = BugReport::where('user_id', $user->id)
            ->where('is_closed', false)
            ->withCount(['comments as unread_count' => function ($query) {
                $query->where('is_from_admin', true)->where('is_read', false);
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'page_title' => $report->page_title,
                    'description' => $report->description,
                    'status' => $report->status,
                    'status_label' => $report->status_label,
                    'status_color' => $report->status_color,
                    'unread_count' => $report->unread_count,
                    'created_at' => $report->created_at->toISOString(),
                ];
            });

        $totalUnreadBugReportReplies = $activeBugReports->sum('unread_count');

        // Discord bot installation info
        $discordBotEnabled = (bool) config('services.discord.bot_enabled');
        $hasDiscordAccount = $discordBotEnabled && in_array('discord', $connectedProviders);
        $discordClientId = config('services.discord.client_id');
        $discordBotInstallUrl = $discordBotEnabled && $discordClientId
            ? "https://discord.com/oauth2/authorize?client_id={$discordClientId}&integration_type=1&scope=applications.commands"
            : null;

        // Get the most recent Discord notification status
        $lastDiscordNotification = null;
        if ($hasDiscordAccount) {
            $lastNotification = NotificationQueue::where('user_id', $user->id)
                ->where('channel', 'discord')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastNotification) {
                $lastDiscordNotification = [
                    'status' => $lastNotification->status,
                    'error' => $lastNotification->error,
                    'processedAt' => $lastNotification->processed_at?->toISOString(),
                    'createdAt' => $lastNotification->created_at->toISOString(),
                ];
            }
        }

        return Inertia::render('dashboard/index', [
            'user' => $user,
            'connectedProviders' => $connectedProviders,
            'socialAccounts' => $socialAccounts,
            'itchioData' => [
                'username' => $itchioUsername,
            ],
            'myGames' => $ownedGames,
            'myGamesClickStats' => $clickStats,
            'notificationPreferences' => [
                'browser_notifications_enabled' => $notificationPreferences->browser_notifications_enabled,
                'discord_notifications_enabled' => $notificationPreferences->discord_notifications_enabled,
                'notification_digest' => $notificationPreferences->notification_digest,
            ],
            'discordInfo' => $discordBotEnabled ? [
                'hasAccount' => $hasDiscordAccount,
                'botInstallUrl' => $discordBotInstallUrl,
                'lastNotification' => $lastDiscordNotification,
            ] : null,
            'recentRequests' => $recentRequests,
            'ignoredGames' => $ignoredGames,
            'ignoredGamesCount' => $ignoredGamesCount,
            'languagePreferences' => $user->preferences?->preferred_languages ?? [],
            'availableLanguages' => GameFilterService::getOptions()['languages'] ?? [],
            'excludedTagPreferences' => $user->preferences?->excluded_tags ?? [],
            'availableTags' => GameFilterService::getOptions()['tags'] ?? [],
            'activeBugReports' => $activeBugReports,
            'totalUnreadBugReportReplies' => $totalUnreadBugReportReplies,
            'vapidPublicKey' => config('webpush.vapid_public_key') ?? config('webpush.vapid.public_key'),
            'metaTags' => [
                'title' => 'Dashboard',
                'description' => 'Manage your FVN.li account, track visual novel progress, organize reading lists, and control notification preferences.',
                'image' => asset(config('social.images.default')),
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => 'Dashboard',
                    'description' => 'Manage your FVN.li account and visual novel tracking',
                    'url' => route('dashboard'),
                ],
            ],
        ]);
    }

    private function getSocialAccountsData(User $user): array
    {
        return $user->socialAccounts()->get()->mapWithKeys(function ($account) {
            $displayName = null;
            $avatar = null;

            if ($account->provider_data) {
                switch ($account->provider_name) {
                    case 'discord':
                        $displayName = $account->provider_data['global_name'] ?? $account->provider_data['username'] ?? null;
                        $avatar = isset($account->provider_data['id'], $account->provider_data['avatar'])
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

            return [
                $account->provider_name => [
                    'display_name' => $displayName,
                    'avatar' => $avatar,
                ],
            ];
        })->toArray();
    }
}
