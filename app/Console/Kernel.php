<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\AnonymizeClickStatIPs;
use App\Console\Commands\BackfillFeed;
use App\Console\Commands\BackfillRatings;
use App\Console\Commands\CleanupGameDownloads;
use App\Console\Commands\DownloadLatestGameArchive;
use App\Console\Commands\FetchGameJamDetails;
use App\Console\Commands\FixCharacters;
use App\Console\Commands\FixIncrementalPlatformSupport;
use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\ImportGameVersionStats;
use App\Console\Commands\ImportRatings;
use App\Console\Commands\OptimizeGameArchives;
use App\Console\Commands\PersistOptimizedGameVersionsToButler;
use App\Console\Commands\ProcessFeed;
use App\Console\Commands\ProcessGameScreenshots;
use App\Console\Commands\ProcessGameThumbnails;
use App\Console\Commands\ProcessPushNotifications;
use App\Console\Commands\QueueGameUpdateNotifications;
use App\Console\Commands\ReapNotifications;
use App\Console\Commands\RecalculateGameRatings;
use App\Console\Commands\RefreshFeedlessGames;
use App\Console\Commands\RefreshGames;
use App\Console\Commands\RefreshSteamGames;
use App\Console\Commands\RefreshTrendingScores;
use App\Console\Commands\RepairGameImages;
use App\Console\Commands\ReprocessCurrentGameArchive;
use App\Console\Commands\SanitizeReviewHtml;
use App\Console\Commands\SyncDiscordCatalogMessages;
use App\Console\Commands\UpdateWatchlist;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        AnonymizeClickStatIPs::class,
        BackfillFeed::class,
        BackfillRatings::class,
        CleanupGameDownloads::class,
        DownloadLatestGameArchive::class,
        FetchGameJamDetails::class,
        FixCharacters::class,
        FixIncrementalPlatformSupport::class,
        GenerateSitemap::class,
        ImportGameVersionStats::class,
        ImportRatings::class,
        OptimizeGameArchives::class,
        PersistOptimizedGameVersionsToButler::class,
        ProcessFeed::class,
        ProcessGameScreenshots::class,
        ProcessGameThumbnails::class,
        ProcessPushNotifications::class,
        QueueGameUpdateNotifications::class,
        ReapNotifications::class,
        RecalculateGameRatings::class,
        RepairGameImages::class,
        ReprocessCurrentGameArchive::class,
        RefreshFeedlessGames::class,
        RefreshGames::class,
        RefreshSteamGames::class,
        RefreshTrendingScores::class,
        SanitizeReviewHtml::class,
        UpdateWatchlist::class,
        SyncDiscordCatalogMessages::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sitemap:generate')->daily()->withoutOverlapping();
        $schedule->command('ratings:import')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('feed:process')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('games:refresh',
            ['--all', '--update-metadata', '--update-info'])->dailyAt('20:00')->withoutOverlapping();
        $schedule->command('games:refresh-feedless', ['--all'])->dailyAt('06:00')->withoutOverlapping();
        $schedule->command('games:refresh-steam',
            ['--all', '--update-data', '--update-reviews'])->dailyAt('22:00')->withoutOverlapping();
        $schedule->command('games:update-watchlist')->dailyAt('00:00')->withoutOverlapping();
        $schedule->command('game-jams:fetch-details')->hourly()->withoutOverlapping();
        $schedule->command('games:refresh-trending-scores')->hourly()->withoutOverlapping();

        // Session churn can only be judged across a user agent's whole body of
        // traffic, so it lands after the fact rather than at write time. Runs
        // ahead of the hourly trending refresh that consumes the verdict.
        $schedule->command('analytics:backfill-bot-flags',
            ['--days' => 7])->dailyAt('03:30')->withoutOverlapping();
        $schedule->command('fix:characters')->weekly()->sundays()->at('03:00')->withoutOverlapping();

        // Notification commands (performance optimized: reduced from everyMinute to everyFiveMinutes)
        $schedule->command('notifications:queue-game-updates', ['--days' => 3])->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('notifications:process-push')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('notifications:reap')->hourly()->withoutOverlapping();
        $schedule->command('discord:sync-catalog-messages')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Cleanup commands
        $schedule->command('games:cleanup-downloads',
            ['--all'])->weekly()->sundays()->at('02:00')->withoutOverlapping();
        $schedule->command('model:prune', ['--model' => MonitoredScheduledTaskLogItem::class])->daily();
        $schedule->command('model:prune')->dailyAt('02:30');
        $schedule->command('queue:prune-failed', ['--hours' => 168])->dailyAt('02:45');
        $schedule->call(fn () => Cache::put('scheduler.heartbeat', now()->toISOString(), 600))
            ->name('scheduler-heartbeat')
            ->everyMinute();

        // Database maintenance - create next month's audit log partition on the 1st of each month
        $schedule->command('audit:create-partitions')->monthlyOn(1, '00:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
