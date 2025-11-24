<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\NotificationQueue;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueGameUpdateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:queue-game-updates
                            {--days=1 : Check for games updated in the last N days}
                            {--limit=100 : Maximum number of games to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds recently updated games and queues notifications for users who follow them';

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
        $queryCount = 0;

        // Enable query logging for performance tracking
        DB::enableQueryLog();

        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $this->info("Checking for games updated in the last {$days} days...");

        try {
            // Find games that have been updated in the specified period
            $latestDate = Carbon::now()->subDays($days);

            // Quick check if there's any work to do (performance optimization)
            $hasRecentUpdates = Game::whereHas('gameVersions', function ($query) use ($latestDate) {
                $query->where('published_at', '>=', $latestDate)
                    ->where('is_latest', true);
            })
                ->where('is_paid', false)
                ->where('is_suspended', false)
                ->exists();

            if (! $hasRecentUpdates) {
                $this->info('No recently updated games found, skipping notification processing');

                // Log performance metrics for early exit
                $this->logPerformanceMetrics(
                    startTime: $startTime,
                    startMemory: $startMemory,
                    gamesProcessed: 0,
                    usersNotified: 0,
                    notificationsQueued: 0,
                    earlyExit: true
                );

                return 0;
            }

            $recentlyUpdatedGames = Game::whereHas('gameVersions', function ($query) use ($latestDate) {
                $query->where('published_at', '>=', $latestDate)
                    ->where('is_latest', true);
            })
                ->where('is_paid', false) // Only include free games
                ->where('is_suspended', false) // Exclude suspended games
                ->with(['latestVersion'])
                ->limit($limit)
                ->get();

            $this->info('Found ' . count($recentlyUpdatedGames) . ' recently updated games');

            $notificationCount = 0;
            $totalUsersNotified = 0;

            foreach ($recentlyUpdatedGames as $game) {
                $this->info("Processing notifications for game: {$game->name}");

                // Skip if no latest version
                if (! $game->latestVersion) {
                    $this->warn("No latest version found for game {$game->name}, skipping...");

                    continue;
                }

                // Find users who follow this game and should receive updates
                $usersToNotify = $this->getUsersToNotify($game->id, $game->latestVersion->id);

                $this->info('Found ' . count($usersToNotify) . " users to notify for {$game->name}");
                $totalUsersNotified += count($usersToNotify);

                // Queue notifications for these users
                foreach ($usersToNotify as $user) {
                    // For each notification channel they have enabled
                    $channelsToNotify = [];

                    // Only add browser channel if user has browser notifications enabled
                    if ((bool) $user->browser_notifications_enabled) {
                        $channelsToNotify[] = 'browser';
                    }

                    // Only add discord channel if user has discord notifications enabled AND has a Discord account
                    if ((bool) $user->discord_notifications_enabled && (bool) $user->has_discord_account) {
                        $channelsToNotify[] = 'discord';
                    }

                    // Add more channels here as needed

                    foreach ($channelsToNotify as $channel) {
                        // Database unique constraint will prevent duplicates more reliably
                        // than application-level checks, so we can remove the explicit check
                        try {
                            $this->queueNotification(
                                $user->user_id,
                                $game->id,
                                $game->latestVersion->id,
                                $channel,
                                $user->notification_digest,
                                $game
                            );
                            $notificationCount++;
                        } catch (QueryException $e) {
                            // If this is a duplicate key violation, just skip it silently
                            if (str_contains($e->getMessage(), 'notification_queue_unique_constraint')) {
                                $this->info("Notification already queued for user {$user->user_id}, game {$game->name}, channel {$channel}");

                                continue;
                            }
                            // Re-throw other database exceptions
                            throw $e;
                        }
                    }
                }
            }

            $this->info("Successfully queued {$notificationCount} notifications");

            // Log performance metrics for successful run
            $this->logPerformanceMetrics(
                startTime: $startTime,
                startMemory: $startMemory,
                gamesProcessed: count($recentlyUpdatedGames),
                usersNotified: $totalUsersNotified,
                notificationsQueued: $notificationCount,
                earlyExit: false
            );

            return 0;
        } catch (Exception $e) {
            $this->error('Error queueing game update notifications: ' . $e->getMessage());
            Log::error('Error in QueueGameUpdateNotifications command', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Get users who should be notified about a game update.
     *
     * IMPORTANT: This method was fixed to handle users who enable notifications
     * without adding games to their reading lists. Previously, it required users
     * to have the game in a VN list, but users can enable notifications independently
     * via the notification toggle on game pages.
     */
    protected function getUsersToNotify(int $gameId, int $gameVersionId): array
    {
        // Get all users who have receive_updates=true for this game in user_game_progress
        // This includes users who enabled notifications without adding the game to a list
        return DB::table('user_game_progress')
            ->select([
                'users.id as user_id',
                'user_notification_preferences.browser_notifications_enabled',
                'user_notification_preferences.discord_notifications_enabled',
                'user_notification_preferences.notification_digest',
                // Check if user has Discord account in a single query (performance optimization)
                DB::raw('EXISTS(SELECT 1 FROM social_accounts WHERE social_accounts.user_id = users.id AND social_accounts.provider_name = \'discord\') as has_discord_account'),
            ])
            ->join('users', 'user_game_progress.user_id', '=', 'users.id')
            ->join('user_notification_preferences', 'users.id', '=', 'user_notification_preferences.user_id')
            ->leftJoin('notification_history', function ($join) use ($gameId, $gameVersionId) {
                $join->on('notification_history.user_id', '=', 'users.id')
                    ->where('notification_history.game_id', '=', $gameId)
                    ->where('notification_history.game_version_id', '=', $gameVersionId);
            })
            ->where('user_game_progress.game_id', '=', $gameId)
            ->where('user_game_progress.receive_updates', '=', true)
            ->whereNull('notification_history.id') // Ensure notification hasn't been sent already
            // Ensure user has at least one notification channel enabled
            ->where(function ($query) {
                $query->where('user_notification_preferences.browser_notifications_enabled', '=', true)
                    ->orWhere('user_notification_preferences.discord_notifications_enabled', '=', true);
            })
            ->groupBy('users.id', 'user_notification_preferences.browser_notifications_enabled',
                'user_notification_preferences.discord_notifications_enabled',
                'user_notification_preferences.notification_digest')
            ->get()
            ->toArray();
    }

    /**
     * Queue a notification for a user.
     */
    protected function queueNotification(
        int $userId,
        int $gameId,
        int $gameVersionId,
        string $channel,
        string $digestType,
        Game $game
    ): void {
        // Calculate when this notification should be sent based on digest setting
        $scheduledAt = $this->calculateScheduledTime($digestType);

        // Prepare notification payload
        $payload = [
            'title' => $game->name . ' - New Update Available',
            'body' => 'Version ' . $game->latestVersion->version . ' is now available.',
            'data' => [
                'url' => route('games.show', $game->slug),
                'game_id' => $game->id,
                'game_version_id' => $game->latestVersion->id,
                'version' => $game->latestVersion->version,
            ],
            'icon' => $game->getThumbnailUrl('small'),
        ];

        // Queue the notification
        NotificationQueue::create([
            'user_id' => $userId,
            'game_id' => $gameId,
            'game_version_id' => $gameVersionId,
            'channel' => $channel,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'payload' => $payload,
        ]);
    }

    /**
     * Calculate when the notification should be scheduled based on digest type.
     */
    protected function calculateScheduledTime(string $digestType): Carbon
    {
        $now = Carbon::now();

        switch ($digestType) {
            case 'asap':
                // Send immediately
                return $now;

            case 'daily':
                // Send at 9 AM the next day
                return $now->copy()->addDay()->setHour(9)->setMinute(0)->setSecond(0);

            case 'weekly':
                // Send at 9 AM on Sunday
                $daysUntilSunday = 7 - $now->dayOfWeek;
                if ($daysUntilSunday === 0) {
                    $daysUntilSunday = 7; // If today is Sunday, schedule for next Sunday
                }

                return $now->copy()->addDays($daysUntilSunday)->setHour(9)->setMinute(0)->setSecond(0);

            default:
                // Default to immediate
                return $now;
        }
    }

    /**
     * Log performance metrics for the command execution.
     */
    protected function logPerformanceMetrics(
        float $startTime,
        int $startMemory,
        int $gamesProcessed,
        int $usersNotified,
        int $notificationsQueued,
        bool $earlyExit
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
            'games_processed' => $gamesProcessed,
            'users_notified' => $usersNotified,
            'notifications_queued' => $notificationsQueued,
            'early_exit' => $earlyExit,
        ];

        // Log to Laravel log
        Log::info('QueueGameUpdateNotifications performance', $metrics);

        // Output summary to console
        $this->newLine();
        $this->info('Performance Metrics:');
        $this->line("  Execution Time: {$executionTime}ms");
        $this->line("  Memory Used: {$metrics['memory_used_mb']}MB (Peak: {$metrics['peak_memory_mb']}MB)");
        $this->line("  Database Queries: {$queryCount}");
        $this->line("  Games Processed: {$gamesProcessed}");
        $this->line("  Users Notified: {$usersNotified}");
        $this->line("  Notifications Queued: {$notificationsQueued}");
        $this->line('  Early Exit: ' . ($earlyExit ? 'Yes' : 'No'));
    }
}
