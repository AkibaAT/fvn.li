<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('renders system status metrics and scheduled task health', function () {
    Cache::flush();

    $visibleGame = Game::factory()->create([
        'name' => 'Visible Status Game',
        'is_visible' => true,
        'initially_published_at' => now()->setDate(2024, 5, 10),
    ]);
    Game::factory()->create([
        'name' => 'Hidden Status Game',
        'is_visible' => false,
        'initially_published_at' => now()->setDate(2023, 5, 10),
    ]);

    $rater = Rater::factory()->create(['external_platform' => 'itch_io']);
    Rating::create([
        'game_id' => $visibleGame->id,
        'rater_id' => $rater->id,
        'rating' => 4,
        'review' => 'Visible review.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now()->setDate(2024, 5, 12),
    ]);
    Rating::create([
        'game_id' => $visibleGame->id,
        'rater_id' => $rater->id,
        'rating' => 2,
        'review' => '',
        'is_visible' => true,
        'is_reviewed' => false,
        'source_platform' => 'itch_io',
        'published_at' => now()->setDate(2024, 6, 12),
    ]);

    $activeTaskId = DB::table('monitored_scheduled_tasks')->insertGetId([
        'name' => 'active-task',
        'type' => 'command',
        'cron_expression' => '* * * * *',
        'timezone' => null,
        'last_started_at' => now()->subMinutes(10),
        'last_finished_at' => now()->subMinutes(5),
        'last_failed_at' => null,
        'registered_on_oh_dear_at' => now()->subDay(),
        'grace_time_in_minutes' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('monitored_scheduled_task_log_items')->insert([
        'monitored_scheduled_task_id' => $activeTaskId,
        'type' => 'finished',
        'meta' => json_encode(['runtime' => 12]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('monitored_scheduled_tasks')->insert([
        [
            'name' => 'failed-task',
            'type' => 'command',
            'cron_expression' => '0 * * * *',
            'timezone' => 'Europe/Vienna',
            'last_started_at' => now()->subHours(2),
            'last_finished_at' => now()->subHours(3),
            'last_failed_at' => now()->subHour(),
            'registered_on_oh_dear_at' => null,
            'grace_time_in_minutes' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'never-run-task',
            'type' => 'command',
            'cron_expression' => '0 0 * * *',
            'timezone' => null,
            'last_started_at' => null,
            'last_finished_at' => null,
            'last_failed_at' => null,
            'registered_on_oh_dear_at' => null,
            'grace_time_in_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->get(route('system.status'));

    $response->assertOk();
    $page = $response->viewData('page');
    $props = $page['props'];

    expect($page['component'])->toBe('system-status')
        ->and($props['gameStats']['total'])->toBe(2)
        ->and($props['gameStats']['visible'])->toBe(1)
        ->and($props['gameStats']['listing_rate'])->toBe(50.0)
        ->and($props['releaseYearStats']['year_distribution'])->toContain(['year' => 2024, 'count' => 1])
        ->and($props['ratingStats']['total'])->toBe(2)
        ->and($props['ratingStats']['reviews']['total'])->toBe(1)
        ->and($props['ratingStats']['reviews']['review_rate'])->toBe(50.0)
        ->and($props['ratingStats']['average_rating'])->toBe(3.0)
        ->and($props['ratingStats']['visible_games']['total'])->toBe(2)
        ->and($props['healthSummary']['total'])->toBe(3)
        ->and($props['healthSummary']['active'])->toBe(1)
        ->and($props['healthSummary']['failed'])->toBe(1)
        ->and($props['healthSummary']['never_run'])->toBe(1)
        ->and($props['healthSummary']['monitored_on_oh_dear'])->toBe(1)
        ->and($props['dateFormat'])->toBe(config('schedule-monitor.date_format'))
        ->and($props['metaTags']['title'])->toBe('System Status');

    $tasksByName = collect($props['monitoredTasks'])->keyBy('name');

    expect($tasksByName['active-task']['status_text'])->toBe('Active')
        ->and($tasksByName['active-task']['status_color'])->toBe('green')
        ->and($tasksByName['active-task']['timezone'])->toBe(config('app.timezone', 'UTC'))
        ->and($tasksByName['active-task']['latest_log']['type'])->toBe('finished')
        ->and($tasksByName['failed-task']['status_text'])->toBe('Failed')
        ->and($tasksByName['failed-task']['status_color'])->toBe('red')
        ->and($tasksByName['failed-task']['timezone'])->toBe('Europe/Vienna')
        ->and($tasksByName['never-run-task']['status_text'])->toBe('Never Run')
        ->and($tasksByName['never-run-task']['status_color'])->toBe('gray');
});

it('renders empty system status metrics without division errors', function () {
    Cache::flush();

    $response = $this->get(route('system.status'));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['gameStats']['total'])->toBe(0)
        ->and($props['gameStats']['visible'])->toBe(0)
        ->and($props['gameStats']['listing_rate'])->toBe(0)
        ->and($props['ratingStats']['total'])->toBe(0)
        ->and($props['ratingStats']['reviews']['review_rate'])->toBe(0)
        ->and($props['ratingStats']['average_rating'])->toBeNull()
        ->and($props['healthSummary']['total'])->toBe(0);
});
