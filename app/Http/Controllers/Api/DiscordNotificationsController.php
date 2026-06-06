<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdditionRequest;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\ReviewReport;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DiscordNotificationsController extends Controller
{
    /**
     * Get pending Discord notifications.
     * This endpoint is used by the Discord bot to fetch notifications that need to be sent.
     */
    public function getPendingNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
            'batch_key' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $limit = $request->input('limit', 50);
        $batchKey = $request->input('batch_key', Carbon::now()->format('YmdHis'));

        try {
            // Start a transaction to ensure we don't have race conditions
            DB::beginTransaction();

            // Get pending notifications and mark them as processing
            $notifications = NotificationQueue::query()
                ->where('channel', 'discord')
                ->where('status', 'pending')
                ->where('scheduled_at', '<=', now())
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($notifications->isEmpty()) {
                DB::commit();

                return response()->json(['notifications' => [], 'batch_key' => $batchKey]);
            }

            foreach ($notifications as $notification) {
                $metaData = $notification->meta_data ?? [];
                $metaData['batch_key'] = $batchKey;

                $notification->update([
                    'status' => 'processing',
                    'meta_data' => $metaData,
                ]);
            }

            DB::commit();

            // Format notifications for the Discord bot
            $formattedNotifications = $notifications->map(function ($notification) {
                $game = $notification->game;
                $user = $notification->user;

                if (! $game || ! $user || ! $game->latestVersion) {
                    return null;
                }

                // Get the user's Discord social account specifically
                $discordAccount = $user->socialAccounts->where('provider_name', 'discord')->first();
                if (! $discordAccount) {
                    return null;
                }

                // Get the user's last read version
                $lastReadVersion = $game->userProgress()
                    ->where('user_id', $user->id)
                    ->orderBy('game_version_id', 'desc')
                    ->first()?->gameVersion;

                // Get version to compare against (previous version or last read version)
                $compareToVersion = $lastReadVersion;
                if (! $compareToVersion && $game->gameVersions->count() > 1) {
                    $compareToVersion = $game->gameVersions[1]; // Second most recent version
                }

                // Calculate word count difference if we have a version to compare against
                $wordCountDiff = null;
                if ($compareToVersion) {
                    $latestStats = $game->latestVersion->getStatsForLanguage('eng');
                    $compareStats = $compareToVersion->getStatsForLanguage('eng');

                    if ($latestStats && $compareStats) {
                        $wordCountDiff = $latestStats->words - $compareStats->words;
                    }
                }

                return [
                    'notification_id' => $notification->id,
                    'discord_user_id' => $discordAccount->provider_id,
                    'game' => [
                        'id' => $game->id,
                        'name' => $game->name,
                        'version' => $game->latestVersion->version,
                        'url' => $game->url, // Multi-platform URLs as JSONB object
                        'thumbnail_url' => $game->getThumbnailUrl('small'),
                        'devlog_url' => $game->latestVersion->devlog,
                        'published_at' => $game->latestVersion->published_at?->timestamp ?? $game->latestVersion->created_at->timestamp,
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
            DB::rollBack();
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
            'batch_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->input('notifications') as $result) {
                $notification = NotificationQueue::find($result['notification_id']);

                // Skip if notification not found or doesn't match batch key
                if (! $notification ||
                    ($notification->meta_data['batch_key'] ?? null) !== $request->input('batch_key')) {
                    continue;
                }

                // Update notification status
                $notification->status = $result['success'] ? 'sent' : 'failed';
                $notification->processed_at = now();
                $notification->error = $result['success'] ? null : $result['error'];
                $notification->save();

                // Record in notification history
                NotificationHistory::create([
                    'user_id' => $notification->user_id,
                    'game_id' => $notification->game_id,
                    'game_version_id' => $notification->game_version_id,
                    'type' => 'discord',
                    'success' => $result['success'],
                    'meta_data' => [
                        'error' => $result['error'] ?? null,
                        'digest' => $notification->meta_data['digest'] ?? false,
                        'digest_type' => $notification->meta_data['digest_type'] ?? null,
                    ],
                ]);
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

    /**
     * Get pending addition request notifications for Discord.
     * This endpoint is used by the Discord bot to fetch new addition requests that need admin attention.
     */
    public function getPendingAdditionRequests(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:50',
            'since' => 'date|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $limit = $request->input('limit', 20);
        $since = $request->input('since', now()->subHour()); // Default to last hour

        try {
            // Start a transaction to prevent race conditions
            DB::beginTransaction();

            // Get pending addition requests that haven't been notified yet and lock them
            $requests = AdditionRequest::with(['users'])
                ->where('status', AdditionRequest::STATUS_PENDING)
                ->whereNull('discord_notified_at')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($requests->isEmpty()) {
                DB::commit();

                return response()->json([
                    'notifications' => [],
                    'count' => 0,
                    'since' => $since,
                    'admin_panel_url' => config('app.url') . '/admin/addition-requests',
                ]);
            }

            // Mark them as notified immediately to prevent duplicate processing
            AdditionRequest::whereIn('id', $requests->pluck('id'))
                ->update(['discord_notified_at' => now()]);

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
                'since' => $since,
                'admin_panel_url' => config('app.url') . '/admin/addition-requests',
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

    /**
     * Get pending review report notifications for Discord.
     */
    public function getPendingReviewReports(): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reports = ReviewReport::with(['rating.game:id,name,slug', 'rating.user:id,name', 'rating.rater:id,name', 'reporter:id,name'])
                ->where('status', 'pending')
                ->whereNull('discord_notified_at')
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
                ->update(['discord_notified_at' => now()]);

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
                    'admin_panel_url' => config('app.url') . '/admin/review-reports/' . $report->id,
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
}
