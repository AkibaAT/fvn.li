<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\PushSubscription;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPushNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-push
                            {--limit=100 : Maximum number of notifications to process per run}
                            {--batch=20 : Number of notifications to process in a single batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending browser push notifications';

    /**
     * The notification service instance.
     */
    protected NotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Performance tracking
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        DB::enableQueryLog();

        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch');

        $this->info("Processing up to {$limit} push notifications in batches of {$batchSize}...");

        try {
            // Get pending notifications that are due to be sent
            $notifications = NotificationQueue::pending()
                ->forChannel('browser')
                ->due()
                ->with(['user', 'game', 'gameVersion'])
                ->limit($limit)
                ->get();

            $this->info('Found '.count($notifications).' notifications to process');

            if ($notifications->isEmpty()) {
                $this->info('No notifications to process');

                // Log performance metrics for early exit
                $this->logPerformanceMetrics(
                    startTime: $startTime,
                    startMemory: $startMemory,
                    notificationsProcessed: 0,
                    successCount: 0,
                    failedCount: 0
                );

                return 0;
            }

            // Group notifications by user for digest types
            $notificationsByUser = $notifications->groupBy('user_id');
            $processedCount = 0;
            $successCount = 0;
            $failedCount = 0;

            DB::beginTransaction();

            try {
                foreach ($notificationsByUser as $userId => $userNotifications) {
                    // Get the user's push subscriptions
                    $subscriptions = PushSubscription::where('user_id', $userId)->get();

                    if ($subscriptions->isEmpty()) {
                        // No subscriptions found, mark as failed
                        foreach ($userNotifications as $notification) {
                            $this->markAsFailed($notification, 'No valid push subscriptions found');
                            $failedCount++;
                        }

                        continue;
                    }

                    // Check if this is a digest notification
                    $firstNotification = $userNotifications->first();
                    $user = $firstNotification->user;
                    $isDigest = $user && $user->notificationPreferences &&
                        in_array($user->notificationPreferences->notification_digest, ['daily', 'weekly']);

                    if ($isDigest && $userNotifications->count() > 1) {
                        // This is a digest notification, combine them
                        $this->processDigestNotifications($userNotifications, $subscriptions);
                        $processedCount += $userNotifications->count();
                        $successCount += $userNotifications->count();
                    } else {
                        // Process individual notifications
                        foreach ($userNotifications as $notification) {
                            $result = $this->processIndividualNotification($notification, $subscriptions);
                            $processedCount++;

                            if ($result) {
                                $successCount++;
                            } else {
                                $failedCount++;
                            }
                        }
                    }
                }

                DB::commit();

                $this->info("Successfully processed {$processedCount} notifications");
                $this->info("Success: {$successCount}, Failed: {$failedCount}");

                // Log performance metrics
                $this->logPerformanceMetrics(
                    startTime: $startTime,
                    startMemory: $startMemory,
                    notificationsProcessed: $processedCount,
                    successCount: $successCount,
                    failedCount: $failedCount
                );

                return 0;
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            $this->error('Error processing push notifications: '.$e->getMessage());
            Log::error('Error in ProcessPushNotifications command', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Mark a notification as failed.
     */
    protected function markAsFailed(NotificationQueue $notification, string $error): void
    {
        $notification->status = 'failed';
        $notification->processed_at = Carbon::now();
        $notification->error = $error;
        $notification->save();
    }

    /**
     * Process a digest notification (combining multiple game updates).
     */
    protected function processDigestNotifications($notifications, $subscriptions): void
    {
        if ($notifications->isEmpty()) {
            return;
        }

        $firstNotification = $notifications->first();
        $user = $firstNotification->user;
        $digestType = $user->notificationPreferences->notification_digest;

        // Build a digest message
        $games = $notifications->map(function ($notification) {
            return [
                'id' => $notification->game_id,
                'name' => $notification->game->name,
                'version' => $notification->gameVersion->version,
                'url' => route('games.show', $notification->game->slug),
            ];
        })->unique('id');

        $title = 'Game Updates Digest';
        if ($digestType === 'daily') {
            $title = 'Daily Game Updates';
        } elseif ($digestType === 'weekly') {
            $title = 'Weekly Game Updates';
        }

        $body = count($games).' games you follow have been updated.';

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'url' => route('dashboard'),
                'digest' => true,
                'games' => $games->toArray(),
            ],
        ];

        // Send the notification
        $success = $this->notificationService->sendPushNotifications($subscriptions, $payload);

        // Mark all notifications as processed
        foreach ($notifications as $notification) {
            if ($success) {
                $this->markAsProcessed($notification);

                // Record in notification history
                NotificationHistory::create([
                    'user_id' => $notification->user_id,
                    'game_id' => $notification->game_id,
                    'game_version_id' => $notification->game_version_id,
                    'type' => 'browser',
                    'success' => true,
                    'meta_data' => [
                        'digest' => true,
                        'digest_type' => $digestType,
                    ],
                ]);
            } else {
                $this->markAsFailed($notification, 'Failed to send digest push notification');
            }
        }
    }

    /**
     * Mark a notification as processed.
     */
    protected function markAsProcessed(NotificationQueue $notification): void
    {
        $notification->status = 'sent';
        $notification->processed_at = Carbon::now();
        $notification->save();
    }

    /**
     * Process an individual notification.
     */
    protected function processIndividualNotification($notification, $subscriptions): bool
    {
        $payload = $notification->payload;

        // Send the notification
        $success = $this->notificationService->sendPushNotifications($subscriptions, $payload);

        if ($success) {
            $this->markAsProcessed($notification);

            // Record in notification history
            NotificationHistory::create([
                'user_id' => $notification->user_id,
                'game_id' => $notification->game_id,
                'game_version_id' => $notification->game_version_id,
                'type' => 'browser',
                'success' => true,
                'meta_data' => [
                    'digest' => false,
                ],
            ]);

            return true;
        } else {
            $this->markAsFailed($notification, 'Failed to send push notification');

            return false;
        }
    }

    /**
     * Log performance metrics for the command execution.
     */
    protected function logPerformanceMetrics(
        float $startTime,
        int $startMemory,
        int $notificationsProcessed,
        int $successCount,
        int $failedCount
    ): void {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds
        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = $peakMemory - $startMemory;
        $queryCount = count(DB::getQueryLog());

        $metrics = [
            'execution_time_ms' => $executionTime,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'query_count' => $queryCount,
            'notifications_processed' => $notificationsProcessed,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ];

        // Log to Laravel log
        Log::info('ProcessPushNotifications performance', $metrics);

        // Output summary to console
        $this->newLine();
        $this->info('Performance Metrics:');
        $this->line("  Execution Time: {$executionTime}ms");
        $this->line("  Memory Used: {$metrics['memory_used_mb']}MB (Peak: {$metrics['peak_memory_mb']}MB)");
        $this->line("  Database Queries: {$queryCount}");
        $this->line("  Notifications Processed: {$notificationsProcessed}");
        $this->line("  Success: {$successCount}, Failed: {$failedCount}");
    }
}
