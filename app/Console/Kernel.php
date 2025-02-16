<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CalculateGameScores;
use App\Console\Commands\CalculateRaterWeights;
use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\ImportRatings;
use App\Console\Commands\ProcessFeed;
use App\Console\Commands\RefreshFeedlessGames;
use App\Console\Commands\RefreshGame;
use App\Console\Commands\UpdateWatchlist;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        GenerateSitemap::class,
        ImportRatings::class,
        ProcessFeed::class,
        RefreshFeedlessGames::class,
        RefreshGame::class,
        UpdateWatchlist::class,
        CalculateRaterWeights::class,
        CalculateGameScores::class,
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
        $schedule->command('raters:calculate-weights')->dailyAt('00:30')->withoutOverlapping()
            ->then(function () {
                Artisan::call('games:calculate-scores');
            });
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
