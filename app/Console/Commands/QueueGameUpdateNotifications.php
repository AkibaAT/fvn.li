<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscordChannelAnnouncement;
use App\Models\Game;
use App\Models\NotificationQueue;
use App\Services\MultiServerNotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
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

    public function __construct(protected MultiServerNotificationService $multiServerNotificationService)
    {
        parent::__construct();
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
            $latestDate = Carbon::now()->subDays($days);

            // Quick check if there's any work to do (performance optimization)
            $hasRecentUpdates = Game::whereHas('gameVersions', function ($query) use ($latestDate) {
                $query->where('published_at', '>=', $latestDate)
                    ->where('is_latest', true);
            })
                ->where('is_paid', false)
                ->where('is_visible', true)
                ->exists();

            if (! $hasRecentUpdates) {
                $this->info('No recently updated games found, skipping notification processing');

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
                ->where('is_visible', true)
                ->with(['latestVersion'])
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $this->info('Found '.count($recentlyUpdatedGames).' recently updated games');

            $notificationCount = 0;
            $totalUsersNotified = 0;

            foreach ($recentlyUpdatedGames as $game) {
                $this->info("Processing notifications for game: {$game->name}");

                if (! $game->latestVersion) {
                    $this->warn("No latest version found for game {$game->name}, skipping...");

                    continue;
                }

                DiscordChannelAnnouncement::insertOrIgnore([
                    'game_id' => $game->id,
                    'game_version_id' => $game->latestVersion->id,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                try {
                    $this->multiServerNotificationService->queueGameUpdate($game, $game->latestVersion);
                } catch (Exception $exception) {
                    Log::error('Failed to queue multi-server game update', [
                        'game_id' => $game->id,
                        'game_version_id' => $game->latestVersion->id,
                        'exception' => $exception,
                    ]);
                }

                $usersToNotify = $this->getUsersToNotify($game->id, $game->latestVersion->id);

                $this->info('Found '.count($usersToNotify)." users to notify for {$game->name}");
                $totalUsersNotified += count($usersToNotify);

                // Queue notifications for these users
                foreach ($usersToNotify as $user) {
                    // For each notification channel they have enabled
                    $channelsToNotify = [];

                    // Only add browser channel if user has browser notifications enabled
                    if ((bool) $user->browser_notifications_enabled
                        && (bool) $user->has_browser_subscription
                        && ! (bool) $user->has_browser_history) {
                        $channelsToNotify[] = 'browser';
                    }

                    // Only add discord channel if user has discord notifications enabled AND has a Discord account
                    if ((bool) $user->discord_notifications_enabled
                        && $user->discord_dm_status !== 'undeliverable'
                        && (bool) $user->has_discord_account
                        && ! (bool) $user->has_discord_history) {
                        $channelsToNotify[] = 'discord';
                    }

                    foreach ($channelsToNotify as $channel) {
                        if ($this->queueNotification(
                            $user->user_id,
                            $game->id,
                            $game->latestVersion->id,
                            $channel,
                            $user->notification_digest,
                            $game
                        )) {
                            $notificationCount++;
                        } else {
                            $this->info("Notification already queued for user {$user->user_id}, game {$game->name}, channel {$channel}");
                        }
                    }
                }
            }

            $this->info("Successfully queued {$notificationCount} notifications");

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
            $this->error('Error queueing game update notifications: '.$e->getMessage());
            Log::error('Error in QueueGameUpdateNotifications command', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    protected function getUsersToNotify(int $gameId, int $gameVersionId): array
    {
        // This includes users who enabled notifications without adding the game to a list
        return DB::table('user_game_progress')
            ->select([
                'users.id as user_id',
                'user_notification_preferences.browser_notifications_enabled',
                'user_notification_preferences.discord_notifications_enabled',
                'user_notification_preferences.notification_digest',
                'user_notification_preferences.discord_dm_status',
                DB::raw("EXISTS(SELECT 1 FROM push_subscriptions WHERE push_subscriptions.user_id = users.id AND push_subscriptions.delivery_status != 'invalid') as has_browser_subscription"),
                DB::raw('EXISTS(SELECT 1 FROM social_accounts WHERE social_accounts.user_id = users.id AND social_accounts.provider_name = \'discord\') as has_discord_account'),
                DB::raw("EXISTS(SELECT 1 FROM notification_history WHERE notification_history.user_id = users.id AND notification_history.game_id = {$gameId} AND notification_history.game_version_id = {$gameVersionId} AND notification_history.type = 'browser') as has_browser_history"),
                DB::raw("EXISTS(SELECT 1 FROM notification_history WHERE notification_history.user_id = users.id AND notification_history.game_id = {$gameId} AND notification_history.game_version_id = {$gameVersionId} AND notification_history.type = 'discord') as has_discord_history"),
            ])
            ->join('users', 'user_game_progress.user_id', '=', 'users.id')
            ->join('user_notification_preferences', 'users.id', '=', 'user_notification_preferences.user_id')
            ->join('games', 'user_game_progress.game_id', '=', 'games.id')
            ->where('user_game_progress.game_id', '=', $gameId)
            ->where('user_game_progress.receive_updates', '=', true)
            ->where('games.is_paid', '=', false)
            ->where(function ($query) {
                $query->where('user_notification_preferences.browser_notifications_enabled', '=', true)
                    ->orWhere('user_notification_preferences.discord_notifications_enabled', '=', true);
            })
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
    ): bool {
        $scheduledAt = $this->calculateScheduledTime($digestType);

        $payload = [
            'title' => $game->name.' - New Update Available',
            'body' => 'Version '.$game->latestVersion->version.' is now available.',
            'data' => [
                'url' => route('games.show', $game->slug),
                'game_id' => $game->id,
                'game_version_id' => $game->latestVersion->id,
                'version' => $game->latestVersion->version,
            ],
            'icon' => $game->getThumbnailUrl('small'),
        ];

        return NotificationQueue::query()->insertOrIgnore([
            'user_id' => $userId,
            'game_id' => $gameId,
            'game_version_id' => $gameVersionId,
            'channel' => $channel,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    protected function calculateScheduledTime(string $digestType): Carbon
    {
        $now = Carbon::now();

        switch ($digestType) {
            case 'asap':
                return $now;

            case 'daily':
                return $now->copy()->addDay()->setHour(9)->setMinute(0)->setSecond(0);

            case 'weekly':
                $scheduledAt = $now->copy()->startOfDay();
                if (! $scheduledAt->isSunday()) {
                    $scheduledAt->next(Carbon::SUNDAY);
                }
                $scheduledAt->setTime(9, 0);

                return $scheduledAt->lte($now) ? $scheduledAt->addWeek() : $scheduledAt;

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
        $this->line('  Early Exit: '.($earlyExit ? 'Yes' : 'No'));
    }
}
