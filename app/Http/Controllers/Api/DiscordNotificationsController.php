<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdditionRequest;
use App\Models\DiscordChannelAnnouncement;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\ReviewReport;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DiscordNotificationsController extends Controller
{
    public function getPendingNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $limit = $request->input('limit', 50);
        $batchKey = (string) Str::uuid();

        try {
            $notifications = DB::transaction(function () use ($batchKey, $limit) {
                $notifications = NotificationQueue::query()
                    ->with(['user.socialAccounts', 'user.notificationPreferences', 'game.gameVersions', 'gameVersion'])
                    ->claimable('discord')
                    ->limit($limit)
                    ->lockForUpdate()
                    ->get();

                foreach ($notifications as $notification) {
                    $discordAccount = $notification->user?->socialAccounts->firstWhere('provider_name', 'discord');
                    $isTest = ($notification->payload['type'] ?? null) === 'test';
                    $hasPayload = $isTest || ($notification->game && $notification->gameVersion);
                    $validDiscordId = $discordAccount && $this->isValidDiscordSnowflake((string) $discordAccount->provider_id);

                    if (! $notification->user || ! $hasPayload || ! $validDiscordId) {
                        $notification->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error' => $validDiscordId ? 'notification_mapping_missing' : 'discord_not_linked',
                            'batch_key' => null,
                        ]);

                        continue;
                    }

                    $notification->update(['status' => 'processing', 'batch_key' => $batchKey]);
                }

                return $notifications->filter(fn (NotificationQueue $notification): bool => $notification->status === 'processing')->values();
            });

            $formattedNotifications = $notifications->map(function ($notification) {
                $user = $notification->user;
                $discordAccount = $user->socialAccounts->where('provider_name', 'discord')->first();
                $game = $notification->game;
                $queuedVersion = $notification->gameVersion;

                if (($notification->payload['type'] ?? null) === 'test') {
                    return [
                        'notification_id' => $notification->id,
                        'discord_user_id' => $discordAccount->provider_id,
                        'type' => 'test',
                        'game' => null,
                    ];
                }

                $lastReadVersion = $game->userProgress()
                    ->where('user_id', $user->id)
                    ->orderBy('game_version_id', 'desc')
                    ->first()?->gameVersion;

                $compareToVersion = $lastReadVersion;
                if (! $compareToVersion) {
                    $compareToVersion = $game->gameVersions->where('id', '!=', $queuedVersion->id)->sortByDesc('published_at')->first();
                }

                $wordCountDiff = null;
                if ($compareToVersion) {
                    $latestStats = $queuedVersion->getStatsForLanguage('eng');
                    $compareStats = $compareToVersion->getStatsForLanguage('eng');

                    if ($latestStats && $compareStats) {
                        $wordCountDiff = $latestStats->words - $compareStats->words;
                    }
                }

                return [
                    'notification_id' => $notification->id,
                    'discord_user_id' => $discordAccount->provider_id,
                    'type' => 'game_update',
                    'game' => [
                        'id' => $game->id,
                        'name' => $game->name,
                        'version' => $queuedVersion->version,
                        'url' => $game->url, // Multi-platform URLs as JSONB object
                        'thumbnail_url' => $game->getThumbnailUrl('small'),
                        'devlog_url' => $queuedVersion->devlog,
                        'published_at' => $queuedVersion->published_at?->timestamp ?? $queuedVersion->created_at->timestamp,
                        'word_count_diff' => $wordCountDiff,
                        'compared_to_version' => $compareToVersion ? [
                            'version' => $compareToVersion->version,
                            'is_last_read' => $compareToVersion->id === $lastReadVersion?->id,
                        ] : null,
                    ],
                    'is_digest' => $notification->meta_data['digest'] ?? false,
                    'digest_type' => $notification->meta_data['digest_type'] ?? null,
                ];
            })->filter()->values();

            return response()->json([
                'notifications' => $formattedNotifications,
                'batch_key' => $batchKey,
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching Discord notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Record the delivery status of Discord notifications.
     */
    public function recordDeliveryStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notifications' => 'required|array',
            'notifications.*.notification_id' => 'required|exists:notification_queue,id',
            'notifications.*.success' => 'required|boolean',
            'notifications.*.error' => 'string|nullable',
            'notifications.*.error_code' => 'nullable',
            'notifications.*.retryable' => 'boolean|nullable',
            'batch_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->input('notifications') as $result) {
                $notification = NotificationQueue::query()
                    ->whereKey($result['notification_id'])
                    ->where('batch_key', $request->input('batch_key'))
                    ->lockForUpdate()
                    ->first();

                if (! $notification) {
                    continue;
                }

                $code = isset($result['error_code']) ? (string) $result['error_code'] : null;
                $undeliverable = in_array($code, ['50007', '50278', '10013'], true);
                $terminal = $result['success'] || $undeliverable || ! ($result['retryable'] ?? false);

                if ($result['success']) {
                    $notification->update(['status' => 'sent', 'processed_at' => now(), 'error' => null, 'batch_key' => null]);
                    $notification->user?->notificationPreferences?->markDiscordDeliverable();
                } elseif ($terminal) {
                    $notification->update([
                        'status' => 'failed',
                        'processed_at' => now(),
                        'error' => $undeliverable ? 'discord_undeliverable' : ($result['error'] ?? 'discord_delivery_failed'),
                        'batch_key' => null,
                        'attempts' => $notification->attempts + 1,
                    ]);

                    if ($undeliverable) {
                        $reason = $code === '10013' ? 'account_missing' : 'cannot_dm';
                        $notification->user?->notificationPreferences?->markDiscordUndeliverable($reason);
                        NotificationQueue::query()
                            ->where('user_id', $notification->user_id)
                            ->where('channel', 'discord')
                            ->whereIn('status', ['pending', 'processing'])
                            ->where('id', '!=', $notification->id)
                            ->update(['status' => 'failed', 'processed_at' => now(), 'error' => 'discord_undeliverable', 'batch_key' => null]);
                    }
                } else {
                    $attempts = $notification->attempts + 1;
                    $terminal = $attempts >= NotificationQueue::MAX_ATTEMPTS;
                    $notification->update([
                        'status' => $terminal ? 'failed' : 'pending',
                        'processed_at' => $terminal ? now() : null,
                        'scheduled_at' => $terminal ? $notification->scheduled_at : now()->addMinutes(NotificationQueue::BACKOFF_MINUTES[$attempts - 1]),
                        'error' => $result['error'] ?? 'discord_delivery_failed',
                        'attempts' => $attempts,
                        'batch_key' => null,
                    ]);
                }

                if (($result['success'] || $notification->fresh()->status === 'failed') && $notification->game_id && $notification->game_version_id) {
                    NotificationHistory::record([
                        'user_id' => $notification->user_id,
                        'game_id' => $notification->game_id,
                        'game_version_id' => $notification->game_version_id,
                        'type' => 'discord',
                        'success' => $result['success'],
                        'meta_data' => [
                            'error' => $result['error'] ?? null,
                            'error_code' => $code,
                            'digest' => $notification->meta_data['digest'] ?? false,
                            'digest_type' => $notification->meta_data['digest_type'] ?? null,
                        ],
                    ]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Delivery status recorded successfully']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error recording Discord notification delivery status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function getChannelUpdates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $limit = $request->input('limit', 50);
        $batchKey = Carbon::now()->format('YmdHis').'-'.bin2hex(random_bytes(4));

        try {
            DB::beginTransaction();

            $announcements = DiscordChannelAnnouncement::query()
                ->with(['game', 'gameVersion'])
                ->whereHas('game', function ($query) {
                    $query->where('is_visible', true);
                })
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere(function ($query) {
                            // Recover batches that were fetched but never acknowledged
                            $query->where('status', 'processing')
                                ->where('updated_at', '<', now()->subMinutes(15));
                        });
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($announcements->isEmpty()) {
                DB::commit();

                return response()->json(['notifications' => [], 'batch_key' => $batchKey]);
            }

            DiscordChannelAnnouncement::whereIn('id', $announcements->pluck('id'))
                ->update([
                    'status' => 'processing',
                    'batch_key' => $batchKey,
                    'updated_at' => now(),
                ]);

            DB::commit();

            $notifications = $announcements->map(function ($announcement) {
                $game = $announcement->game;
                $version = $announcement->gameVersion;

                if (! $game?->is_visible || ! $version) {
                    return null;
                }

                return [
                    'announcement_id' => $announcement->id,
                    'name' => $game->name,
                    'version' => $version->version,
                    'published_at' => $version->published_at?->timestamp ?? $version->created_at->timestamp,
                    'url' => $game->url, // Multi-platform URLs as JSONB object
                    'devlog' => $version->devlog,
                ];
            })->filter()->values();

            return response()->json([
                'notifications' => $notifications,
                'batch_key' => $batchKey,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error fetching Discord channel updates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Record the delivery status of channel update announcements.
     * Failed announcements are requeued until they exhaust their attempts.
     */
    public function recordChannelDeliveryStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_key' => 'required|string',
            'results' => 'required|array',
            'results.*.announcement_id' => 'required|exists:discord_channel_announcements,id',
            'results.*.success' => 'required|boolean',
            'results.*.error' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->input('results') as $result) {
                $announcement = DiscordChannelAnnouncement::query()
                    ->where('id', $result['announcement_id'])
                    ->where('batch_key', $request->input('batch_key'))
                    ->lockForUpdate()
                    ->first();

                if (! $announcement) {
                    continue;
                }

                if ($result['success']) {
                    $announcement->update([
                        'status' => 'sent',
                        'error' => null,
                        'processed_at' => now(),
                    ]);

                    continue;
                }

                $attempts = $announcement->attempts + 1;
                $announcement->update([
                    'status' => $attempts < DiscordChannelAnnouncement::MAX_ATTEMPTS ? 'pending' : 'failed',
                    'attempts' => $attempts,
                    'error' => $result['error'] ?? null,
                    'processed_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Channel delivery status recorded successfully']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error recording Discord channel delivery status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function getPendingAdditionRequests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $limit = $request->input('limit', 20);
        try {
            // Start a transaction to prevent race conditions
            DB::beginTransaction();

            $requests = AdditionRequest::with(['users'])
                ->where('status', AdditionRequest::STATUS_PENDING)
                ->whereNull('discord_notified_at')
                ->where('discord_notify_attempts', '<', 3)
                ->where(function ($query): void {
                    $query->whereNull('discord_claimed_at')
                        ->orWhere('discord_claimed_at', '<', now()->subMinutes(15));
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($requests->isEmpty()) {
                DB::commit();

                return response()->json([
                    'notifications' => [],
                    'count' => 0,
                    'admin_panel_url' => config('app.url').'/admin/addition-requests',
                ]);
            }

            AdditionRequest::whereIn('id', $requests->pluck('id'))
                ->update([
                    'discord_claimed_at' => now(),
                    'discord_notify_attempts' => DB::raw('discord_notify_attempts + 1'),
                ]);

            DB::commit();

            $notifications = $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'url' => $request->game_url,
                    'platform' => $request->platform,
                    'created_at' => $request->created_at->toISOString(),
                    'user_count' => $request->users->count(),
                    'users' => $request->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'requested_at' => $user->pivot->created_at->toISOString(),
                        ];
                    })->toArray(),
                ];
            });

            return response()->json([
                'notifications' => $notifications,
                'count' => $notifications->count(),
                'admin_panel_url' => config('app.url').'/admin/addition-requests',
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error fetching pending addition requests for Discord', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function getPendingReviewReports(): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reports = ReviewReport::with(['rating.game:id,name,slug', 'rating.user:id,name', 'rating.rater:id,name', 'reporter:id,name'])
                ->where('status', 'pending')
                ->whereNull('discord_notified_at')
                ->where('discord_notify_attempts', '<', 3)
                ->where(function ($query): void {
                    $query->whereNull('discord_claimed_at')
                        ->orWhere('discord_claimed_at', '<', now()->subMinutes(15));
                })
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->lockForUpdate()
                ->get();

            if ($reports->isEmpty()) {
                DB::commit();

                return response()->json([
                    'notifications' => [],
                    'count' => 0,
                ]);
            }

            ReviewReport::whereIn('id', $reports->pluck('id'))
                ->update([
                    'discord_claimed_at' => now(),
                    'discord_notify_attempts' => DB::raw('discord_notify_attempts + 1'),
                ]);

            DB::commit();

            $notifications = $reports->map(function ($report) {
                $reviewAuthor = $report->rating?->user?->name ?? $report->rating?->rater?->name ?? 'Unknown';

                return [
                    'id' => $report->id,
                    'reason' => ReviewReport::REASONS[$report->reason] ?? $report->reason,
                    'details' => $report->details,
                    'reporter' => $report->reporter?->name ?? 'Unknown',
                    'review_author' => $reviewAuthor,
                    'game_name' => $report->rating?->game?->name ?? 'Unknown',
                    'game_slug' => $report->rating?->game?->slug,
                    'review_excerpt' => $report->rating?->review
                        ? mb_substr(strip_tags($report->rating->review), 0, 200)
                        : null,
                    'created_at' => $report->created_at->toISOString(),
                    'admin_panel_url' => config('app.url').'/admin/review-reports/'.$report->id,
                ];
            });

            return response()->json([
                'notifications' => $notifications,
                'count' => $notifications->count(),
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error fetching pending review reports for Discord', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function acknowledgeAdditionRequests(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:addition_requests,id'])['ids'];
        AdditionRequest::whereIn('id', $ids)->update(['discord_notified_at' => now(), 'discord_claimed_at' => null]);

        return response()->json(['success' => true]);
    }

    public function acknowledgeReviewReports(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:review_reports,id'])['ids'];
        ReviewReport::whereIn('id', $ids)->update(['discord_notified_at' => now(), 'discord_claimed_at' => null]);

        return response()->json(['success' => true]);
    }

    public function verifyDm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'discord_user_id' => 'required|string|max:20',
            'success' => 'required|boolean',
            'error_code' => 'nullable',
        ]);
        $account = SocialAccount::query()
            ->where('provider_name', 'discord')
            ->where('provider_id', $data['discord_user_id'])
            ->first();

        if (! $account?->user?->notificationPreferences) {
            return response()->json(['success' => false, 'error' => 'discord_not_linked'], 404);
        }

        if ($data['success']) {
            $account->user->notificationPreferences->markDiscordDeliverable();
        } else {
            $code = isset($data['error_code']) ? (string) $data['error_code'] : null;
            $reason = $code === '10013' ? 'account_missing' : (in_array($code, ['50007', '50278'], true) ? 'cannot_dm' : 'unknown');
            $account->user->notificationPreferences->markDiscordUndeliverable($reason);
        }

        return response()->json(['success' => true]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:ok,degraded,error',
            'passes' => 'required|array',
        ]);
        Cache::put('discord-bot:status', [...$data, 'received_at' => now()->toISOString()], 600);

        return response()->json(['success' => true]);
    }

    private function isValidDiscordSnowflake(string $value): bool
    {
        if (! preg_match('/^\d{1,20}$/', $value)) {
            return false;
        }

        $normalized = ltrim($value, '0') ?: '0';
        $maxSignedBigint = '9223372036854775807';

        return strlen($normalized) < strlen($maxSignedBigint)
            || (strlen($normalized) === strlen($maxSignedBigint) && strcmp($normalized, $maxSignedBigint) <= 0);
    }
}
