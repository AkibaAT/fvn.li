<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\BackfillRatings;
use App\Console\Commands\CleanupGameDownloads;
use App\Console\Commands\FetchGameJamDetails;
use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\ImportGameVersionStats;
use App\Console\Commands\ImportRatings;
use App\Console\Commands\ProcessFeed;
use App\Console\Commands\ProcessGameScreenshots;
use App\Console\Commands\ProcessGameThumbnails;
use App\Console\Commands\ProcessPushNotifications;
use App\Console\Commands\QueueGameUpdateNotifications;
use App\Console\Commands\RefreshFeedlessGames;
use App\Console\Commands\RefreshGames;
use App\Console\Commands\UpdateWatchlist;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        BackfillRatings::class,
        CleanupGameDownloads::class,
        FetchGameJamDetails::class,
        GenerateSitemap::class,
        ImportGameVersionStats::class,
        ImportRatings::class,
        ProcessFeed::class,
        ProcessGameScreenshots::class,
        ProcessGameThumbnails::class,
        ProcessPushNotifications::class,
        QueueGameUpdateNotifications::class,
        RefreshFeedlessGames::class,
        RefreshGames::class,
        UpdateWatchlist::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('model:prune', ['--model' => MonitoredScheduledTaskLogItem::class])->daily();
        $schedule->command('sitemap:generate')->daily()->withoutOverlapping();
        $schedule->command('ratings:import')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('feed:process')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('games:refresh-feedless')->dailyAt('06:00')->withoutOverlapping();
        $schedule->command('games:update-watchlist')->dailyAt('00:00')->withoutOverlapping();
        $schedule->command('games:process-screenshots')->dailyAt('03:00')->withoutOverlapping();
        $schedule->command('game-jams:fetch-details', ['--limit' => 20])->hourly()->withoutOverlapping();
        $schedule->command('games:cleanup-downloads', ['--all'])->weekly()->sundays()->at('02:00')->withoutOverlapping();

        // Notification commands
        $schedule->command('notifications:queue-game-updates')->everyMinute()->withoutOverlapping();
        $schedule->command('notifications:process-push')->everyFiveMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
