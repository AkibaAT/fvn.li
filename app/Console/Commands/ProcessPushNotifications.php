<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\WebPushConfigurationException;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\PushSubscription;
use App\Services\NotificationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPushNotifications extends Command
{
    protected $signature = 'notifications:process-push
                            {--limit=100 : Maximum number of notifications to process per run}
                            {--batch=20 : Number of notifications to process in a single batch}';

    protected $description = 'Process pending browser push notifications';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        DB::enableQueryLog();

        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch');
        $this->info("Processing up to {$limit} push notifications in batches of {$batchSize}...");

        try {
            $this->notificationService->assertConfigured();
        } catch (WebPushConfigurationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $notifications = $this->claimNotifications($limit);
            $this->info('Found '.$notifications->count().' notifications to process');

            if ($notifications->isEmpty()) {
                $this->info('No notifications to process');
                $this->logPerformanceMetrics($startTime, $startMemory, 0, 0, 0);

                return self::SUCCESS;
            }

            $processedCount = 0;
            $successCount = 0;
            $failedCount = 0;

            foreach ($notifications->groupBy('user_id') as $userId => $userNotifications) {
                $subscriptions = PushSubscription::where('user_id', $userId)->deliverable()->get();

                if ($subscriptions->isEmpty()) {
                    foreach ($userNotifications as $notification) {
                        $this->releaseBlocked($notification);
                    }

                    continue;
                }

                $preferences = $userNotifications->first()->user?->notificationPreferences;
                $isDigest = $preferences && in_array($preferences->notification_digest, ['daily', 'weekly'], true);

                if ($isDigest) {
                    $success = $this->processDigestNotifications($userNotifications, $subscriptions);
                    $processedCount += $userNotifications->count();
                    $successCount += $success ? $userNotifications->count() : 0;
                    $failedCount += $success ? 0 : $userNotifications->count();

                    continue;
                }

                foreach ($userNotifications as $notification) {
                    $this->processIndividualNotification($notification, $subscriptions) ? $successCount++ : $failedCount++;
                    $processedCount++;
                }
            }

            $this->info("Successfully processed {$processedCount} notifications");
            $this->info("Success: {$successCount}, Failed: {$failedCount}");
            $this->logPerformanceMetrics($startTime, $startMemory, $processedCount, $successCount, $failedCount);

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('Error processing push notifications: '.$exception->getMessage());
            Log::error('Error in ProcessPushNotifications command', ['exception' => $exception]);

            return self::FAILURE;
        }
    }

    private function claimNotifications(int $limit): EloquentCollection
    {
        $ids = DB::transaction(function () use ($limit): Collection {
            $notifications = NotificationQueue::query()
                ->claimable('browser')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('push_subscriptions')
                        ->whereColumn('push_subscriptions.user_id', 'notification_queue.user_id')
                        ->where('push_subscriptions.delivery_status', '!=', PushSubscription::STATUS_INVALID);
                })
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id']);

            if ($notifications->isNotEmpty()) {
                NotificationQueue::whereKey($notifications->pluck('id'))->update([
                    'status' => 'processing',
                    'batch_key' => (string) Str::uuid(),
                    'updated_at' => now(),
                ]);
            }

            return $notifications->pluck('id');
        });

        return NotificationQueue::with(['user.notificationPreferences', 'game', 'gameVersion'])
            ->whereKey($ids)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    private function processDigestNotifications(Collection $notifications, Collection $subscriptions): bool
    {
        $first = $notifications->first();
        $digestType = $first->user->notificationPreferences->notification_digest;
        $games = $notifications->map(fn (NotificationQueue $notification): array => [
            'id' => $notification->game_id,
            'name' => $notification->game->name,
            'version' => $notification->gameVersion->version,
            'url' => route('games.show', $notification->game->slug),
        ])->unique('id');

        $result = $this->notificationService->sendPushNotifications($subscriptions, [
            'title' => $digestType === 'daily' ? 'Daily Game Updates' : 'Weekly Game Updates',
            'body' => $games->count().' games you follow have been updated.',
            'data' => ['url' => route('dashboard'), 'digest' => true, 'games' => $games->values()->all()],
        ]);

        foreach ($notifications as $notification) {
            if ($result['sent'] > 0) {
                $this->markSent($notification, ['digest' => true, 'digest_type' => $digestType]);
            } elseif ($this->hasDeliverableSubscription($notification->user_id)) {
                $this->markRetryableFailure($notification, $this->resultError($result));
            } else {
                $this->releaseBlocked($notification);
            }
        }

        return $result['sent'] > 0;
    }

    private function processIndividualNotification(NotificationQueue $notification, Collection $subscriptions): bool
    {
        $result = $this->notificationService->sendPushNotifications($subscriptions, $notification->payload);
        if ($result['sent'] > 0) {
            $this->markSent($notification, ['digest' => false]);

            return true;
        }

        $this->hasDeliverableSubscription($notification->user_id)
            ? $this->markRetryableFailure($notification, $this->resultError($result))
            : $this->releaseBlocked($notification);

        return false;
    }

    private function markSent(NotificationQueue $notification, array $metaData): void
    {
        $notification->update(['status' => 'sent', 'processed_at' => now(), 'error' => null, 'batch_key' => null]);
        NotificationHistory::record([
            'user_id' => $notification->user_id,
            'game_id' => $notification->game_id,
            'game_version_id' => $notification->game_version_id,
            'type' => 'browser',
            'success' => true,
            'meta_data' => $metaData,
        ]);
    }

    private function releaseBlocked(NotificationQueue $notification): void
    {
        $notification->update([
            'status' => 'pending',
            'processed_at' => null,
            'error' => 'push_setup_required',
            'batch_key' => null,
        ]);
    }

    private function hasDeliverableSubscription(int $userId): bool
    {
        return PushSubscription::where('user_id', $userId)->deliverable()->exists();
    }

    private function markRetryableFailure(NotificationQueue $notification, string $error): void
    {
        $attempts = $notification->attempts + 1;
        $terminal = $attempts >= NotificationQueue::MAX_ATTEMPTS;
        $notification->update([
            'status' => $terminal ? 'failed' : 'pending',
            'attempts' => $attempts,
            'scheduled_at' => $terminal ? $notification->scheduled_at : now()->addMinutes(NotificationQueue::BACKOFF_MINUTES[$attempts - 1]),
            'processed_at' => $terminal ? now() : null,
            'error' => $error,
            'batch_key' => null,
        ]);
    }

    private function resultError(array $result): string
    {
        return $result['errors'][0] ?? 'push_delivery_failed';
    }

    private function logPerformanceMetrics(float $startTime, int $startMemory, int $notificationsProcessed, int $successCount, int $failedCount): void
    {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $peakMemory = memory_get_peak_usage(true);
        $metrics = [
            'execution_time_ms' => $executionTime,
            'memory_used_mb' => round(($peakMemory - $startMemory) / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'query_count' => count(DB::getQueryLog()),
            'notifications_processed' => $notificationsProcessed,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ];
        Log::info('ProcessPushNotifications performance', $metrics);
        $this->newLine();
        $this->info('Performance Metrics:');
        $this->line("  Execution Time: {$executionTime}ms");
        $this->line("  Memory Used: {$metrics['memory_used_mb']}MB (Peak: {$metrics['peak_memory_mb']}MB)");
        $this->line("  Database Queries: {$metrics['query_count']}");
        $this->line("  Notifications Processed: {$notificationsProcessed}");
        $this->line("  Success: {$successCount}, Failed: {$failedCount}");
    }
}
