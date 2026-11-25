<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BugReport;
use App\Services\GameFilterService;
use App\Services\OwnedGameSummaryService;
use App\Support\Seo\MetaTags;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OwnedGameSummaryService $ownedGameSummary,
    ) {}

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            return $this->loginResponse();
        }

        $user->load(['socialAccounts', 'preferences', 'notificationPreferences']);

        $connectedProviders = $user->socialAccounts->pluck('provider_name')->all();

        $socialAccounts = $this->getSocialAccountsData($user->socialAccounts);

        $itchioUsername = $this->ownedGameSummary->username($user);
        $ownedGames = $this->ownedGameSummary->games($user);
        $clickStats = $this->ownedGameSummary->clickStats($ownedGames);

        // VN Addition Requests
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

        $notificationPreferences = $user->notificationPreferences ?? $user->notificationPreferences()->create([
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

        $ignoredGamesCount = $ignoredGames->count();

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
            'recentRequests' => $recentRequests,
            'ignoredGames' => $ignoredGames,
            'ignoredGamesCount' => $ignoredGamesCount,
            'languagePreferences' => $user->preferences?->preferred_languages ?? [],
            'availableLanguages' => GameFilterService::getOptions()['languages'] ?? [],
            'excludedTagPreferences' => $user->preferences?->excluded_tags ?? [],
            'availableTags' => GameFilterService::getOptions()['tags'] ?? [],
            'activeBugReports' => $activeBugReports,
            'totalUnreadBugReportReplies' => $totalUnreadBugReportReplies,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'metaTags' => new MetaTags(
                title: 'Dashboard',
                description: 'Manage your FVN.li account, track visual novel progress, organize reading lists, and control notification preferences.',
                image: asset(config('social.images.default')),
                structuredData: [
                    '@type' => 'WebPage',
                    'name' => 'Dashboard',
                    'description' => 'Manage your FVN.li account and visual novel tracking',
                    'url' => route('dashboard'),
                ],
            )->toArray(),
        ]);
    }

    private function getSocialAccountsData(Collection $accounts): array
    {
        return $accounts->mapWithKeys(function ($account) {
            [$displayName, $avatar] = $account->provider_data
                ? match ($account->provider_name) {
                    'discord' => [
                        $account->provider_data['global_name'] ?? $account->provider_data['username'] ?? null,
                        isset($account->provider_data['id'], $account->provider_data['avatar'])
                            ? "https://cdn.discordapp.com/avatars/{$account->provider_data['id']}/{$account->provider_data['avatar']}.png"
                            : null,
                    ],
                    'google' => [
                        $account->provider_data['given_name'] ?? null,
                        $account->provider_data['picture'] ?? null,
                    ],
                    'steam' => [
                        $account->provider_data['personaname'] ?? null,
                        $account->provider_data['avatarfull'] ?? null,
                    ],
                    'telegram' => [
                        ($account->provider_data['first_name'] ?? '') .
                            (isset($account->provider_data['last_name']) ? ' ' . $account->provider_data['last_name'] : ''),
                        $account->provider_data['photo_url'] ?? null,
                    ],
                    'itchio' => [
                        $account->provider_data['display_name'] ?? null,
                        $account->provider_data['cover_url'] ?? null,
                    ],
                    default => [null, null],
                }
            : [null, null];

            return [
                $account->provider_name => [
                    'display_name' => $displayName,
                    'avatar' => $avatar,
                ],
            ];
        })->all();
    }
}
