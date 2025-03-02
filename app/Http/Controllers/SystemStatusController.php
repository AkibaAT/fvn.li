<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Rating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;
use Spatie\ScheduleMonitor\Support\ScheduledTasks\ScheduledTasks;

class SystemStatusController extends Controller
{
    public function __invoke(): Response
    {
        // Get game stats
        $gameStats = [
            'total' => Game::count(),
            'visible' => Game::where('is_visible', true)->count(),
            'latest_update' => Game::where('is_visible', true)
                ->orderByDesc('updated_at')
                ->value('updated_at'),
        ];

        // Get rating stats (cached until end of day)
        $ratingStats = Cache::remember('system_status.rating_stats', now()->endOfDay(), function () {
            // Get rating stats
            $visibleRatingsCount = Rating::where('is_visible', true)->count();
            $visibleReviewsCount = Rating::where('is_visible', true)
                ->where('is_reviewed', true)
                ->count();

            // Get ratings for visible games
            $visibleGameRatingsCount = Rating::whereHas('game', function ($query) {
                $query->where('is_visible', true);
            })->count();

            $visibleGameReviewsCount = Rating::whereHas('game', function ($query) {
                $query->where('is_visible', true);
            })->where('is_reviewed', true)->count();

            // Get average ratings
            $averageRating = Rating::where('is_visible', true)->avg('rating');
            $visibleGamesAvgRating = Rating::whereHas('game', function ($query) {
                $query->where('is_visible', true);
            })->where('is_visible', true)->avg('rating');

            $monthlyTrend = Cache::remember('system_status.monthly_trend', now()->addMinutes(5), function () {
                return DB::table('ratings')
                    ->select(DB::raw('DATE_TRUNC(\'month\', published_at) as month'), DB::raw('COUNT(*) as count'))
                    ->where('is_visible', true)
                    ->where(DB::raw('DATE_TRUNC(\'month\', published_at)'), '<',
                        DB::raw('DATE_TRUNC(\'month\', CURRENT_DATE)'))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
            });

            $visibleGamesMonthlyTrend = Rating::query()
                ->selectRaw('DATE_TRUNC(\'month\', published_at) as month')
                ->selectRaw('COUNT(*) as count')
                ->where('is_visible', true)
                ->where(DB::raw('DATE_TRUNC(\'month\', published_at)'), '<',
                    DB::raw('DATE_TRUNC(\'month\', CURRENT_DATE)'))
                ->whereHas('game', function ($query) {
                    $query->where('is_visible', true);
                })
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            return [
                'total' => $visibleRatingsCount,
                'reviews' => [
                    'total' => $visibleReviewsCount,
                    'review_rate' => $visibleRatingsCount > 0 ? ($visibleReviewsCount / $visibleRatingsCount * 100) : 0,
                ],
                'average_rating' => $averageRating,
                'visible_games' => [
                    'total' => $visibleGameRatingsCount,
                    'reviews' => $visibleGameReviewsCount,
                    'review_rate' => $visibleGameRatingsCount > 0 ? ($visibleGameReviewsCount / $visibleGameRatingsCount * 100) : 0,
                    'average_rating' => $visibleGamesAvgRating,
                ],
                'latest' => Rating::orderByDesc('published_at')->first()?->published_at,
                'monthly_trend' => $monthlyTrend,
                'visible_games_monthly_trend' => $visibleGamesMonthlyTrend,
            ];
        });

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
            })
            ->values();

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

        return Inertia::render('SystemStatus', [
            'gameStats' => $gameStats,
            'ratingStats' => $ratingStats,
            'monitoredTasks' => $monitoredTasks,
            'healthSummary' => $healthSummary,
            'dateFormat' => config('schedule-monitor.date_format'),
        ]);
    }
}
