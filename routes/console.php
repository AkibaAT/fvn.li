<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sitemap:generate')->daily()->withoutOverlapping(120)->runInBackground();
Schedule::command('ratings:import')->everyFifteenMinutes()->withoutOverlapping(30)->runInBackground();
Schedule::command('ratings:refresh-stats')->everyFiveMinutes()->withoutOverlapping(15);
Schedule::command('feed:process')->everyFifteenMinutes()->withoutOverlapping(30)->runInBackground();
Schedule::command('games:retry-stats')->everyFifteenMinutes()->withoutOverlapping(30)->runInBackground();
Schedule::command('games:refresh', ['--all', '--update-metadata', '--update-info'])
    ->dailyAt('20:00')
    ->withoutOverlapping(240)
    ->runInBackground();
Schedule::command('games:refresh-feedless', ['--all'])
    ->dailyAt('06:00')
    ->withoutOverlapping(180)
    ->runInBackground();
Schedule::command('games:refresh-steam', ['--all', '--update-data', '--update-reviews'])
    ->dailyAt('22:00')
    ->withoutOverlapping(240)
    ->runInBackground();
Schedule::command('games:update-watchlist')->dailyAt('00:00')->withoutOverlapping(120)->runInBackground();
Schedule::command('game-jams:fetch-details')->hourly()->withoutOverlapping(60)->runInBackground();
Schedule::command('games:refresh-trending-scores')->hourly()->withoutOverlapping(60);

// Session churn can only be judged across a user agent's whole body of
// traffic, so it lands after the fact rather than at write time. Runs
// ahead of the hourly trending refresh that consumes the verdict.
Schedule::command('analytics:backfill-bot-flags', ['--days' => 7])
    ->dailyAt('03:30')
    ->withoutOverlapping(60)
    ->runInBackground();
Schedule::command('fix:characters')->weekly()->sundays()->at('03:00')->withoutOverlapping(120)->runInBackground();

// Notification commands (performance optimized: reduced from everyMinute to everyFiveMinutes)
Schedule::command('notifications:queue-game-updates', ['--days' => 3])->everyFiveMinutes()->withoutOverlapping(15);
Schedule::command('notifications:process-push')->everyFiveMinutes()->withoutOverlapping(15);
Schedule::command('notifications:reap')->hourly()->withoutOverlapping(30);
Schedule::command('discord:sync-catalog-messages')->everyFiveMinutes()->withoutOverlapping(15);

// Cleanup commands
Schedule::command('games:cleanup-downloads', ['--all'])
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping(120)
    ->runInBackground();
Schedule::command('model:prune', ['--model' => MonitoredScheduledTaskLogItem::class])->daily();
Schedule::command('model:prune')->dailyAt('02:30');
Schedule::command('queue:prune-failed', ['--hours' => 168])->dailyAt('02:45');
Schedule::call(fn () => Cache::put('scheduler.heartbeat', now()->toISOString(), 600))
    ->name('scheduler-heartbeat')
    ->everyMinute();

// Database maintenance - create next month's audit log partition on the 1st of each month
Schedule::command('audit:create-partitions')->monthlyOn(1, '00:00')->withoutOverlapping(60);
