<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Games\GamesVersionController;
use App\Models\AdditionRequest;
use App\Models\BugReport;
use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Services\AdditionRequestService;
use App\Services\GameFilterService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

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
                    'thumb_url' => method_exists($g, 'getThumbnailUrl') ? $g->getThumbnailUrl() : $g->thumb_url,
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
                    'thumb_url' => $game->thumb_url,
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
        $hasDiscordAccount = in_array('discord', $connectedProviders);
        $discordClientId = config('services.discord.client_id');
        $discordBotInstallUrl = $discordClientId
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
            'discordInfo' => [
                'hasAccount' => $hasDiscordAccount,
                'botInstallUrl' => $discordBotInstallUrl,
                'lastNotification' => $lastDiscordNotification,
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

        // Discord bot installation info
        $hasDiscordAccount = $user->socialAccounts()->where('provider_name', 'discord')->exists();
        $discordClientId = config('services.discord.client_id');
        $discordBotInstallUrl = $discordClientId
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

        return response()->json([
            'success' => true,
            'preferences' => [
                'browser_notifications_enabled' => (bool) $preferences->browser_notifications_enabled,
                'discord_notifications_enabled' => (bool) $preferences->discord_notifications_enabled,
                'notification_digest' => $preferences->notification_digest,
            ],
            'discordInfo' => [
                'hasAccount' => $hasDiscordAccount,
                'botInstallUrl' => $discordBotInstallUrl,
                'lastNotification' => $lastDiscordNotification,
            ],
            'vapidPublicKey' => config('webpush.vapid_public_key') ?? config('webpush.vapid.public_key'),
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
            'notification_digest' => 'in:asap,daily,weekly,monthly',
        ]);

        $preferences = $user->notificationPreferences()->first();
        if (! $preferences) {
            $preferences = $user->notificationPreferences()->create([
                'browser_notifications_enabled' => false,
                'discord_notifications_enabled' => false,
                'notification_digest' => 'asap',
            ]);
        }

        $preferences->update($data);

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

    public function submitAdditionRequest(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);
        $service = new AdditionRequestService;

        // Parse URLs from the request
        $urls = $service->parseUrls((string) $request->input('urls', ''));

        if (empty($urls)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid URLs provided',
                'errors' => [
                    'urls' => 'Please enter at least one valid game URL.',
                ],
            ], 422);
        }

        $result = $service->submitRequests($user, $urls);

        // Format the result to include success key
        $success = $result['success_count'] > 0;
        $message = $success
            ? "Successfully submitted {$result['success_count']} request(s)."
            : 'No requests were submitted.';

        if ($result['duplicate_count'] > 0) {
            $message .= " {$result['duplicate_count']} duplicate(s) were ignored.";
        }

        if ($result['invalid_count'] > 0) {
            $message .= " {$result['invalid_count']} invalid URL(s) were ignored.";
        }

        if ($result['already_exists_count'] > 0) {
            $message .= " {$result['already_exists_count']} game(s) already exist on the site.";
        }

        if (! empty($result['errors'])) {
            $message .= ' Errors: '.implode(', ', $result['errors']);
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'result' => $result,
            'errors' => $result['errors'] ?? [],
        ], $success ? 200 : 422);
    }

    public function getUserAdditionRequests(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        // Get status from request, default to null for all statuses
        $status = $request->input('status');

        // Map 'processing' and 'completed' to 'approved' for compatibility
        if ($status === 'processing' || $status === 'completed') {
            $status = 'approved';
        }

        // Use the relationship directly to avoid the service method that might be causing issues
        $query = $user->additionRequests();

        if ($status && in_array($status, AdditionRequest::getStatuses())) {
            $query->where('addition_requests.status', $status);
        }

        $requests = $query->with(['game', 'reviewer'])->orderBy('addition_request_users.created_at', 'desc')->get();

        // Format the response to match what the frontend expects
        return response()->json([
            'success' => true,
            'requests' => $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'game_url' => $request->game_url,
                    'platform' => $request->platform,
                    'status' => $request->status,
                    'status_label' => $request->status_label,
                    'status_color' => $request->status_color,
                    'submitted_at' => $request->created_at->toISOString(),
                    'created_at' => $request->created_at->toISOString(),
                    'reviewed_at' => $request->reviewed_at?->toISOString(),
                    'processed_at' => $request->reviewed_at?->toISOString(),
                    'game' => $request->game ? [
                        'id' => $request->game->id,
                        'name' => $request->game->name,
                        'slug' => $request->game->slug,
                    ] : null,
                    'reviewer' => $request->reviewer ? [
                        'name' => $request->reviewer->name,
                    ] : null,
                ];
            }),
        ]);
    }

    public function cancelAdditionRequest(AdditionRequest $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);
        $service = new AdditionRequestService;
        $result = $service->cancelUserRequest($user, $request);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function getUserGameStats(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $itchioAccount = $user->socialAccounts()->where('provider_name', 'itchio')->first();
        $itchioUsername = $itchioAccount
            ? (method_exists($user,
                'getItchioUsername') ? $user->getItchioUsername() : ($itchioAccount->provider_data['username'] ?? null))
            : null;

        $ownedGamesCount = 0;
        $gamesWithLinksCount = 0;

        if ($itchioUsername && method_exists($user, 'getOwnedGames')) {
            $ownedGames = $user->getOwnedGames();
            $ownedGamesCount = $ownedGames->count();
            $gamesWithLinksCount = $ownedGames->filter(function ($game) {
                return $game->hasAdditionalLinks();
            })->count();
        }

        // Personal reading stats
        $progressStats = UserGameProgress::where('user_id', $authId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'reading' THEN 1 ELSE 0 END) as reading,
                SUM(CASE WHEN status = 'plan_to_read' THEN 1 ELSE 0 END) as plan_to_read,
                SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
                SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped
            ")
            ->first();

        // Reviews count
        $reviewsCount = Rating::where('user_id', $authId)->count();

        // Games completed per month (last 12 months)
        $monthlyCompletions = UserGameProgress::where('user_id', $authId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subMonths(12))
            ->selectRaw("TO_CHAR(completed_at, 'YYYY-MM') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Top tags from completed/reading games
        $userGameIds = UserGameProgress::where('user_id', $authId)
            ->whereIn('status', ['completed', 'reading'])
            ->pluck('game_id');

        $topTags = [];
        if ($userGameIds->isNotEmpty()) {
            $topTags = DB::table('game_tag')
                ->join('tags', 'tags.id', '=', 'game_tag.tag_id')
                ->whereIn('game_tag.game_id', $userGameIds)
                ->selectRaw('tags.name, COUNT(*) as count')
                ->groupBy('tags.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'name')
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'stats' => [
                'itchioUsername' => $itchioUsername,
                'ownedGamesCount' => $ownedGamesCount,
                'gamesWithLinksCount' => $gamesWithLinksCount,
                'progress' => [
                    'total' => (int) ($progressStats->total ?? 0),
                    'completed' => (int) ($progressStats->completed ?? 0),
                    'reading' => (int) ($progressStats->reading ?? 0),
                    'plan_to_read' => (int) ($progressStats->plan_to_read ?? 0),
                    'on_hold' => (int) ($progressStats->on_hold ?? 0),
                    'dropped' => (int) ($progressStats->dropped ?? 0),
                    'total_hours' => 0.0,
                ],
                'reviewsCount' => $reviewsCount,
                'monthlyCompletions' => $monthlyCompletions,
                'topTags' => $topTags,
            ],
        ]);
    }

    public function exportUserData(): StreamedResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            abort(401, 'Unauthenticated');
        }
        $user = User::findOrFail($authId);

        // Enhanced profile data with complete social account information
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toISOString(),
            'providers' => $user->socialAccounts()->pluck('provider_name')->values(),
        ];

        // Complete social account data
        $socialAccounts = $user->socialAccounts()->get()->map(function ($account) {
            return [
                'id' => $account->id,
                'provider_name' => $account->provider_name,
                'provider_id' => $account->provider_id,
                'provider_data' => $account->provider_data,
                'created_at' => $account->created_at?->toISOString(),
                'updated_at' => $account->updated_at?->toISOString(),
            ];
        })->values();

        $lists = (VnList::where('user_id', $user->id)
            ->with([
                'entries' => function ($q) {
                    $q->with([
                        'game' => function ($gq) {
                            $gq->select('id', 'name', 'slug', 'is_visible');
                        },
                    ]);
                    $q->orderBy('sort_order');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get())->map(function ($l) {
                return [
                    'id' => $l->id,
                    'name' => $l->name,
                    'description' => $l->description,
                    'type' => $l->type,
                    'is_public' => (bool) $l->is_public,
                    'is_default' => (bool) $l->is_default,
                    'created_at' => $l->created_at?->toISOString(),
                    'updated_at' => $l->updated_at?->toISOString(),
                    'entries' => $l->entries->map(function ($e) {
                        return [
                            'id' => $e->id,
                            'game_id' => $e->game_id,
                            'sort_order' => (int) $e->sort_order,
                            'private_notes' => $e->private_notes,
                            'created_at' => $e->created_at?->toISOString(),
                            'updated_at' => $e->updated_at?->toISOString(),
                            'game' => $e->game ? [
                                'id' => $e->game->id,
                                'name' => $e->game->name,
                                'slug' => $e->game->slug,
                                'is_visible' => (bool) $e->game->is_visible,
                            ] : null,
                        ];
                    })->values(),
                ];
            })->values();

        $ratings = (Rating::where('user_id', $user->id)
            ->orderBy('published_at', 'desc')
            ->get([
                'id', 'game_id', 'rating', 'is_reviewed', 'published_at', 'created_at', 'updated_at', 'review',
            ]))->map(function ($r) {
                return [
                    'id' => $r->id,
                    'game_id' => $r->game_id,
                    'rating' => $r->rating,
                    'is_reviewed' => (bool) $r->is_reviewed,
                    'content' => $r->review,
                    'published_at' => $r->published_at?->toISOString(),
                    'created_at' => $r->created_at?->toISOString(),
                    'updated_at' => $r->updated_at?->toISOString(),
                ];
            })->values();

        // Game progress data
        $gameProgress = UserGameProgress::where('user_id', $user->id)
            ->with([
                'game' => function ($q) {
                    $q->select('id', 'name', 'slug');
                }, 'gameVersion' => function ($q) {
                    $q->select('id', 'version', 'published_at');
                },
            ])
            ->get()
            ->map(function ($progress) {
                return [
                    'id' => $progress->id,
                    'game_id' => $progress->game_id,
                    'game_version_id' => $progress->game_version_id,
                    'status' => $progress->status,
                    'progress' => $progress->progress,
                    'personal_notes' => $progress->personal_notes,
                    'started_at' => $progress->started_at?->toISOString(),
                    'completed_at' => $progress->completed_at?->toISOString(),
                    'receive_updates' => (bool) $progress->receive_updates,
                    'created_at' => $progress->created_at?->toISOString(),
                    'updated_at' => $progress->updated_at?->toISOString(),
                    'game' => $progress->game ? [
                        'id' => $progress->game->id,
                        'name' => $progress->game->name,
                        'slug' => $progress->game->slug,
                    ] : null,
                    'game_version' => $progress->gameVersion ? [
                        'id' => $progress->gameVersion->id,
                        'version' => $progress->gameVersion->version,
                        'published_at' => $progress->gameVersion->published_at?->toISOString(),
                    ] : null,
                ];
            })->values();

        // Notification preferences
        $notificationPreferences = $user->notificationPreferences()
            ->get()
            ->map(function ($preference) {
                return [
                    'id' => $preference->id,
                    'browser_notifications_enabled' => (bool) $preference->browser_notifications_enabled,
                    'discord_notifications_enabled' => (bool) $preference->discord_notifications_enabled,
                    'notification_digest' => $preference->notification_digest,
                    'created_at' => $preference->created_at?->toISOString(),
                    'updated_at' => $preference->updated_at?->toISOString(),
                ];
            })->values();

        // Notification history
        $notificationHistory = NotificationHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'created_at' => $notification->created_at?->toISOString(),
                ];
            })->values();

        // Ignored games
        $ignoredGames = $user->ignoredGames()
            ->orderBy('user_ignored_games.created_at', 'desc')
            ->get()
            ->map(function ($game) {
                return [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'platform' => $game->platform,
                    'ignored_at' => $game->pivot->created_at?->toISOString(),
                ];
            })->values();

        $filename = 'user-data-'.($user->name ? preg_replace('/[^a-z0-9\-]+/i', '-',
            strtolower($user->name)) : 'export').'-'.now()->format('Ymd-His').'.zip';

        return new StreamedResponse(function () use (
            $profile,
            $socialAccounts,
            $lists,
            $gameProgress,
            $notificationPreferences,
            $notificationHistory,
            $ignoredGames
        ) {
            $tmp = fopen('php://temp', 'w+');
            $zip = new ZipArchive;
            $status = $zip->open(stream_get_meta_data($tmp)['uri'], ZipArchive::OVERWRITE);
            if ($status !== true) {
                $path = tempnam(sys_get_temp_dir(), 'expzip');
                $zip = new ZipArchive;
                if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Unable to create ZIP archive');
                }

                $this->addExportFilesToZip(
                    $zip,
                    $profile,
                    $socialAccounts,
                    $lists,
                    $gameProgress,
                    $notificationPreferences,
                    $notificationHistory,
                    $ignoredGames
                );
                $zip->close();

                $out = fopen($path, 'rb');
                stream_copy_to_stream($out, fopen('php://output', 'wb'));
                fclose($out);
                @unlink($path);

                return;
            }

            $this->addExportFilesToZip(
                $zip,
                $profile,
                $socialAccounts,
                $lists,
                $gameProgress,
                $notificationPreferences,
                $notificationHistory,
                $ignoredGames
            );
            $zip->close();
            rewind($tmp);
            stream_copy_to_stream($tmp, fopen('php://output', 'wb'));
            fclose($tmp);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        // Validate password confirmation for non-AJAX requests only (SPA will handle confirmation UI)
        if (! ($request->expectsJson() || $request->ajax())) {
            $request->validate([
                'password' => ['required', 'current_password'],
            ]);
        }
        DB::transaction(function () use ($user, $userId) {
            // Anonymize audit logs (GDPR compliance - retain for legal purposes)
            try {
                $anonymized = ChangeLog::anonymizeUserData($userId);
                Log::info('Anonymized audit logs during account deletion',
                    ['user_id' => $userId, 'anonymized_count' => $anonymized]);
            } catch (Throwable $e) {
                Log::warning('Failed to anonymize audit logs', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }

            // Anonymize click statistics (GDPR compliance - retain for analytics)
            try {
                $ok = ClickStat::anonymizePersonalDataForUser($userId);
                Log::info('Anonymized click statistics during account deletion',
                    ['user_id' => $userId, 'anonymized' => $ok]);
            } catch (Throwable $e) {
                Log::warning('Failed to anonymize click stats', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }

            // Reassign addition request reviews to anonymous system user (ID 1)
            DB::table('addition_requests')
                ->where('reviewed_by', $userId)
                ->update(['reviewed_by' => 1]);

            // Reset custom game pages to itch.io synced state
            DB::table('games')
                ->where('custom_page_updated_by', $userId)
                ->update([
                    'custom_page_updated_by' => null,
                    'has_custom_page' => false,
                    'custom_name' => null,
                    'custom_description' => null,
                    'custom_screenshots' => null,
                    'custom_assets' => null,
                    'custom_css' => null,
                    'custom_tags' => [],
                    'custom_page_updated_at' => null,
                ]);

            // Delete user data (cascade deletes will handle related records)
            $user->socialAccounts()->delete();
            if ($user->notificationPreferences) {
                $user->notificationPreferences()->delete();
            }
            $user->pushSubscriptions()->delete();
            $user->vnLists()->each(function ($list) {
                $list->entries()->delete();
                $list->delete();
            });
            $user->gameProgress()->delete();
            $user->notificationHistory()->delete();
            $user->ignoredGames()->detach(); // Remove all ignored games relationships

            DB::table('vn_list_entries')
                ->whereIn('vn_list_id', DB::table('vn_lists')->where('user_id', $userId)->select('id'))
                ->delete();
            DB::table('vn_lists')->where('user_id', $userId)->delete();
            DB::table('user_game_progress')->where('user_id', $userId)->delete();
            DB::table('notification_history')->where('user_id', $userId)->delete();
            DB::table('user_ignored_games')->where('user_id', $userId)->delete();

            // Finally delete the user account
            $user->delete();
            DB::table('users')->where('id', $userId)->delete();
        });

        // Logout the user
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        // For AJAX/JSON requests, return JSON instead of redirect
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Your account has been successfully deleted.',
            ]);
        }

        return Redirect::route('home')
            ->with('success', 'Your account has been successfully deleted.');
    }

    /**
     * Merge social accounts.
     */
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

    /**
     * Disconnect a social account.
     */
    public function disconnectSocialAccount(Request $request, string $provider)
    {
        $user = Auth::user();

        // Count total connected providers
        $connectedProvidersCount = $user->socialAccounts()->count();

        // Don't allow disconnecting the last provider
        if ($connectedProvidersCount <= 1) {
            return redirect()->route('dashboard')
                ->with('error',
                    'Cannot disconnect your last social account. Delete your account instead if you wish to completely disconnect.');
        }

        // Delete the social account
        $user->socialAccounts()
            ->where('provider_name', $provider)
            ->delete();

        // For XHR/JSON requests, return JSON instead of redirect
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully disconnected '.ucfirst($provider).' account.',
                'provider' => $provider,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Successfully disconnected '.ucfirst($provider).' account.');
    }

    /**
     * Show digest notifications for a specific date
     *
     * @return Response
     */
    public function showDigestNotifications(string $date)
    {
        // Validate the date format
        try {
            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (Exception $e) {
            abort(404, 'Invalid date format. Please use YYYY-MM-DD format.');
        }

        // Get notifications for the specified date
        $authId = Auth::id();
        if (! $authId) {
            abort(401, 'Unauthenticated');
        }

        $notifications = NotificationHistory::where('user_id', $authId)
            ->whereDate('created_at', $carbonDate)
            ->orderBy('created_at', 'desc')
            ->get();

        // Check if user has any notifications for this date
        if ($notifications->isEmpty()) {
            // Check if there are any notifications for this date at all
            $hasAnyNotifications = NotificationHistory::whereDate('created_at', $carbonDate)->exists();

            return Inertia::render('dashboard/digest-notifications', [
                'date' => $date,
                'formattedDate' => $carbonDate->format('F j, Y'),
                'notifications' => [],
                'hasNotifications' => false,
                'hasAnyNotifications' => $hasAnyNotifications,
                'metaTags' => [
                    'title' => "Notification Digest - {$carbonDate->format('F j, Y')}",
                    'description' => $notifications->isNotEmpty()
                        ? "View your notification digest for {$carbonDate->format('F j, Y')}. ".
                          "Contains {$notifications->count()} notifications about your tracked visual novels."
                        : "No notifications found for {$carbonDate->format('F j, Y')}.",
                    'structuredData' => [
                        '@type' => 'WebPage',
                        'name' => "Notification Digest - {$carbonDate->format('F j, Y')}",
                        'description' => $notifications->isNotEmpty()
                            ? "Your notification digest for {$carbonDate->format('F j, Y')}"
                            : "No notifications for {$carbonDate->format('F j, Y')}",
                        'url' => route('user.notifications.digest', $date),
                    ],
                ],
            ]);
        }

        return Inertia::render('dashboard/digest-notifications', [
            'date' => $date,
            'formattedDate' => $carbonDate->format('F j, Y'),
            'notifications' => $notifications,
            'hasNotifications' => true,
            'hasAnyNotifications' => true,
            'metaTags' => [
                'title' => "Notification Digest - {$carbonDate->format('F j, Y')}",
            ],
        ]);
    }

    /**
     * Get version comparison data for dashboard
     */
    public function getVersionComparison(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'gameId' => ['required', 'exists:games,id'],
            'fromVersionId' => ['required', 'exists:game_versions,id'],
            'toVersionId' => ['required', 'exists:game_versions,id'],
        ]);

        // Get the game and verify user has access to it
        $game = Game::findOrFail($request->gameId);

        // Check if user has this game in their lists or has rated it
        $user = User::findOrFail($authId);
        $hasAccess = $user->vnLists()
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            })
            ->exists() ||
            $user->ratings()
                ->where('game_id', $game->id)
                ->where('is_visible', true)
                ->exists();

        if (! $hasAccess) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this game'], 403);
        }

        // Use the existing GamesVersionController method to get the comparison data
        $versionController = app(GamesVersionController::class);
        $comparisonData = $versionController->compareVersions($request, $game);

        return $comparisonData;
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
                        $displayName = $account->provider_data['first_name'].
                            (isset($account->provider_data['last_name']) ? ' '.$account->provider_data['last_name'] : '');
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

    private function addExportFilesToZip(
        ZipArchive $zip,
        array $profile,
        Collection $socialAccounts,
        Collection $lists,
        Collection $gameProgress,
        Collection $notificationPreferences,
        Collection $notificationHistory,
        Collection $ignoredGames
    ): void {
        $zip->addFromString('profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('social_accounts.json',
            json_encode($socialAccounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('lists.json', json_encode($lists, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('game_progress.json',
            json_encode($gameProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('notification_preferences.json',
            json_encode($notificationPreferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('notification_history.json',
            json_encode($notificationHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $profileCsv = fopen('php://temp', 'w+');
        fputcsv($profileCsv, ['id', 'name', 'email', 'created_at', 'providers'], ',', '"', '\\');
        fputcsv($profileCsv, [
            $profile['id'],
            $profile['name'],
            $profile['email'],
            $profile['created_at'],
            implode('|', $profile['providers']->toArray()),
        ], ',', '"', '\\');
        rewind($profileCsv);
        $zip->addFromString('profile.csv', stream_get_contents($profileCsv));
        fclose($profileCsv);

        $listsCsv = fopen('php://temp', 'w+');
        fputcsv($listsCsv,
            ['id', 'name', 'description', 'type', 'is_public', 'is_default', 'created_at', 'updated_at', 'entry_count'],
            ',', '"', '\\');
        foreach ($lists as $l) {
            fputcsv($listsCsv, [
                $l['id'],
                $l['name'],
                $l['description'],
                $l['type'],
                $l['is_public'] ? 1 : 0,
                $l['is_default'] ? 1 : 0,
                $l['created_at'],
                $l['updated_at'],
                is_countable($l['entries']) ? count($l['entries']) : 0,
            ], ',', '"', '\\');
        }
        rewind($listsCsv);
        $zip->addFromString('lists.csv', stream_get_contents($listsCsv));
        fclose($listsCsv);

        $entriesCsv = fopen('php://temp', 'w+');
        fputcsv($entriesCsv, [
            'list_id', 'entry_id', 'game_id', 'game_name', 'game_slug', 'sort_order', 'private_notes', 'created_at',
            'updated_at',
        ], ',', '"', '\\');
        foreach ($lists as $l) {
            foreach ($l['entries'] as $e) {
                fputcsv($entriesCsv, [
                    $l['id'],
                    $e['id'],
                    $e['game_id'],
                    $e['game']['name'] ?? null,
                    $e['game']['slug'] ?? null,
                    $e['sort_order'],
                    $e['private_notes'],
                    $e['created_at'],
                    $e['updated_at'],
                ], ',', '"', '\\');
            }
        }
        rewind($entriesCsv);
        $zip->addFromString('list_entries.csv', stream_get_contents($entriesCsv));
        fclose($entriesCsv);

        // Ratings CSV removed

        // Social accounts CSV
        $socialAccountsCsv = fopen('php://temp', 'w+');
        fputcsv($socialAccountsCsv, ['id', 'provider_name', 'provider_id', 'created_at', 'updated_at'], ',', '"', '\\');
        foreach ($socialAccounts as $sa) {
            fputcsv($socialAccountsCsv, [
                $sa['id'],
                $sa['provider_name'],
                $sa['provider_id'],
                $sa['created_at'],
                $sa['updated_at'],
            ], ',', '"', '\\');
        }
        rewind($socialAccountsCsv);
        $zip->addFromString('social_accounts.csv', stream_get_contents($socialAccountsCsv));
        fclose($socialAccountsCsv);

        // Game progress CSV
        $gameProgressCsv = fopen('php://temp', 'w+');
        fputcsv($gameProgressCsv, [
            'id', 'game_id', 'game_name', 'game_version_id', 'version', 'status', 'progress', 'personal_notes',
            'started_at', 'completed_at', 'receive_updates', 'created_at', 'updated_at',
        ], ',', '"', '\\');
        foreach ($gameProgress as $gp) {
            fputcsv($gameProgressCsv, [
                $gp['id'],
                $gp['game_id'],
                $gp['game']['name'] ?? null,
                $gp['game_version_id'],
                $gp['game_version']['version'] ?? null,
                $gp['status'],
                $gp['progress'],
                $gp['personal_notes'],
                $gp['started_at'],
                $gp['completed_at'],
                $gp['receive_updates'] ? 1 : 0,
                $gp['created_at'],
                $gp['updated_at'],
            ], ',', '"', '\\');
        }
        rewind($gameProgressCsv);
        $zip->addFromString('game_progress.csv', stream_get_contents($gameProgressCsv));
        fclose($gameProgressCsv);

        // Notification preferences CSV
        $notificationPreferencesCsv = fopen('php://temp', 'w+');
        fputcsv($notificationPreferencesCsv, [
            'id', 'browser_notifications_enabled', 'discord_notifications_enabled', 'notification_digest', 'created_at',
            'updated_at',
        ], ',', '"', '\\');
        foreach ($notificationPreferences as $np) {
            fputcsv($notificationPreferencesCsv, [
                $np['id'],
                $np['browser_notifications_enabled'] ? 1 : 0,
                $np['discord_notifications_enabled'] ? 1 : 0,
                $np['notification_digest'],
                $np['created_at'],
                $np['updated_at'],
            ], ',', '"', '\\');
        }
        rewind($notificationPreferencesCsv);
        $zip->addFromString('notification_preferences.csv', stream_get_contents($notificationPreferencesCsv));
        fclose($notificationPreferencesCsv);

        // Notification history CSV
        $notificationHistoryCsv = fopen('php://temp', 'w+');
        fputcsv($notificationHistoryCsv, ['id', 'type', 'message', 'created_at'], ',', '"', '\\');
        foreach ($notificationHistory as $nh) {
            fputcsv($notificationHistoryCsv, [
                $nh['id'],
                $nh['type'],
                $nh['message'],
                $nh['created_at'],
            ], ',', '"', '\\');
        }
        rewind($notificationHistoryCsv);
        $zip->addFromString('notification_history.csv', stream_get_contents($notificationHistoryCsv));
        fclose($notificationHistoryCsv);

        // Ignored games JSON and CSV
        $zip->addFromString('ignored_games.json',
            json_encode($ignoredGames, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $ignoredGamesCsv = fopen('php://temp', 'w+');
        fputcsv($ignoredGamesCsv, ['id', 'name', 'slug', 'platform', 'ignored_at'], ',', '"', '\\');
        foreach ($ignoredGames as $ig) {
            fputcsv($ignoredGamesCsv, [
                $ig['id'],
                $ig['name'],
                $ig['slug'],
                $ig['platform'],
                $ig['ignored_at'],
            ], ',', '"', '\\');
        }
        rewind($ignoredGamesCsv);
        $zip->addFromString('ignored_games.csv', stream_get_contents($ignoredGamesCsv));
        fclose($ignoredGamesCsv);
    }
}
