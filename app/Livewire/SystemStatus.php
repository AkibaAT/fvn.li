<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Models\Rating;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\View\View;
use Livewire\Component;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;
use Spatie\ScheduleMonitor\Support\ScheduledTasks\ScheduledTasks;

class SystemStatus extends Component
{
    public function render(): View
    {
        // Get game stats
        $gameStats = [
            'total' => Game::count(),
            'visible' => Game::where('is_visible', true)->count(),
            'latest_update' => Game::where('is_visible', true)
                ->orderByDesc('updated_at')
                ->value('updated_at'),
        ];

        // Get rating stats
        $ratingStats = [
            'visible' => Rating::where('is_visible', true)->count(),
            'with_reviews' => Rating::where('is_visible', true)
                ->where('is_reviewed', true)
                ->count(),
            'latest' => Rating::orderByDesc('published_at')->first()?->published_at,
        ];

        // Get scheduled tasks info
        $scheduledTasks = ScheduledTasks::createForSchedule();

        // Get the task instances to calculate next run dates
        $tasksByName = $scheduledTasks->uniqueTasks()
            ->filter(fn ($task) => $task->isBeingMonitored())
            ->mapWithKeys(fn ($task) => [$task->name() => $task]);

        // Get monitored tasks from database with their logs
        $monitoredTasks = MonitoredScheduledTask::query()
            ->with(['logItems' => fn ($query) => $query->latest()->take(1)])
            ->get()
            ->map(function (MonitoredScheduledTask $task) use ($tasksByName) {
                $scheduledTask = $tasksByName[$task->name] ?? null;

                return [
                    'name' => $task->name,
                    'type' => $task->type,
                    'schedule' => $task->cron_expression,
                    'timezone' => $task->timezone,
                    'last_started' => $task->last_started_at,
                    'last_finished' => $task->last_finished_at,
                    'last_failed' => $task->last_failed_at,
                    'last_skipped' => $task->last_skipped_at,
                    'last_pinged' => $task->last_pinged_at,
                    'registered_on_oh_dear' => $task->registered_on_oh_dear_at !== null,
                    'next_run' => $scheduledTask?->nextRunAt(),
                    'grace_time' => $task->grace_time_in_minutes,
                    'runs_on_one_server' => $task->run_on_one_server,
                    'runs_in_maintenance' => $task->run_in_maintenance_mode,
                    'latest_log' => $task->logItems->first(),
                ];
            })
            ->sortBy(function ($task) {
                return $task['next_run'] ?? now()->addYear();
            });

        // Tasks health summary
        $healthSummary = [
            'total' => $monitoredTasks->count(),
            'active' => $monitoredTasks->filter(function ($task) {
                if (! $task['last_finished']) {
                    return false;
                }

                return $task['last_finished']->diffInHours(now()) < 24;
            })->count(),
            'failed' => $monitoredTasks->filter(function ($task) {
                if (! $task['last_failed']) {
                    return false;
                }

                return $task['last_failed']->isAfter($task['last_finished'] ?? now());
            })->count(),
            'never_run' => $monitoredTasks->filter(fn ($task) => ! $task['last_started'])->count(),
            'monitored_on_oh_dear' => $monitoredTasks->filter(fn ($task) => $task['registered_on_oh_dear'])->count(),
        ];

        $metaTags = [
            'title' => 'System Status' . ' - ' . config('app.name'),
            'description' => 'System status information',
            'image' => null,
        ];

        app('view')->share('metaTags', $metaTags);

        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }

        return view('livewire.system-status', [
            'gameStats' => $gameStats,
            'ratingStats' => $ratingStats,
            'monitoredTasks' => $monitoredTasks,
            'tasks' => [
                'monitored' => $scheduledTasks->monitoredTasks(),
                'unmonitored' => $scheduledTasks->unmonitoredTasks(),
                'unnamed' => $scheduledTasks->unnamedTasks(),
                'ready' => $scheduledTasks->readyForMonitoringTasks(),
                'duplicate' => $scheduledTasks->duplicateTasks(),
            ],
            'healthSummary' => $healthSummary,
            'dateFormat' => config('schedule-monitor.date_format'),
            'metaTags' => $metaTags,
        ]);
    }
}
