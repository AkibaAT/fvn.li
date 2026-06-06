<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Rating;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;
use Spatie\ScheduleMonitor\Support\ScheduledTasks\ScheduledTasks;

class SystemStatusController extends Controller
{
    public function systemStatus(): Response
    {
        // Cache game stats until end of day
        $gameStats = Cache::remember('system_status.game_stats', now()->endOfDay(), function () {
            $stats = [
                'total' => Game::count(),
                'visible' => Game::where('is_visible', true)->count(),
                'latest_update' => Game::where('is_visible', true)
                    ->orderBy('updated_at', 'desc')
                    ->value('updated_at'),
            ];
            $stats['listing_rate'] = $stats['total'] > 0
                ? ($stats['visible'] / $stats['total'] * 100)
                : 0;

            return $stats;
        });

        // Cache release year distribution until end of day
        $releaseYearStats = Cache::remember('system_status.release_year_stats', now()->endOfDay(), function () {
            $yearDistribution = Game::query()
                ->where('is_visible', true)
                ->whereNotNull('initially_published_at')
                ->selectRaw('EXTRACT(YEAR FROM initially_published_at)::integer as year, COUNT(*) as count')
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(fn ($row) => [
                    'year' => (int) $row->year,
                    'count' => (int) $row->count,
                ])
                ->all();

            return [
                'year_distribution' => $yearDistribution,
            ];
        });

        // Bump cache key version when the payload shape changes
        $ratingStats = Cache::remember('system_status.rating_stats.v2', now()->endOfDay(), function () {
            // Base queries
            $visibleRatingsQuery = Rating::query()->where('is_visible', true);

            $visibleRatingsCount = (clone $visibleRatingsQuery)->count();
            $visibleReviewsCount = (clone $visibleRatingsQuery)->where('is_reviewed', true)->count();
            $averageRating = (clone $visibleRatingsQuery)->avg('rating');

            $visibleGameRatingsQuery = Rating::query()
                ->where('is_visible', true)
                ->whereHas('game', function ($query) {
                    $query->where('is_visible', true);
                });

            $visibleGameRatingsCount = (clone $visibleGameRatingsQuery)->count();
            $visibleGameReviewsCount = (clone $visibleGameRatingsQuery)->where('is_reviewed', true)->count();
            $visibleGameAverageRating = (clone $visibleGameRatingsQuery)->avg('rating');

            $latestVisibleRatingAt = (clone $visibleRatingsQuery)
                ->orderBy('published_at', 'desc')
                ->value('published_at');

            // Monthly trends (DB-driver aware)
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $monthExpr = "DATE_FORMAT(published_at, '%Y-%m-01')";
            } elseif ($driver === 'pgsql') {
                $monthExpr = "to_char(date_trunc('month', published_at), 'YYYY-MM-01')";
            } elseif ($driver === 'sqlite') {
                $monthExpr = "strftime('%Y-%m-01', published_at)";
            } else {
                // Fallback that should work reasonably across drivers
                $monthExpr = "to_char(date_trunc('month', published_at), 'YYYY-MM-01')";
            }

            $monthlyTrend = (clone $visibleRatingsQuery)
                ->selectRaw($monthExpr . ' as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => [
                    'month' => (string) $row->month,
                    'count' => (int) $row->count,
                ])
                ->all();

            $visibleGamesMonthlyTrend = (clone $visibleGameRatingsQuery)
                ->selectRaw($monthExpr . ' as month, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => [
                    'month' => (string) $row->month,
                    'count' => (int) $row->count,
                ])
                ->all();

            $payload = [
                'total' => (int) $visibleRatingsCount,
                'reviews' => [
                    'total' => (int) $visibleReviewsCount,
                    'review_rate' => $visibleRatingsCount > 0
                        ? ($visibleReviewsCount / $visibleRatingsCount * 100)
                        : 0,
                ],
                'average_rating' => $averageRating !== null ? round((float) $averageRating, 2) : null,
                'visible_games' => [
                    'total' => (int) $visibleGameRatingsCount,
                    'reviews' => (int) $visibleGameReviewsCount,
                    'review_rate' => $visibleGameRatingsCount > 0
                        ? ($visibleGameReviewsCount / $visibleGameRatingsCount * 100)
                        : 0,
                    'average_rating' => $visibleGameAverageRating !== null ? round((float) $visibleGameAverageRating,
                        2) : null,
                ],
                'latest' => $latestVisibleRatingAt,
                'monthly_trend' => $monthlyTrend,
                'visible_games_monthly_trend' => $visibleGamesMonthlyTrend,
            ];

            Log::debug('SystemStatus ratingStats payload', $payload);

            return $payload;
        });

        // Build monitored scheduled tasks and health summary (no cache - lightweight queries, real-time is better)
        $monitoredTasksModels = MonitoredScheduledTask::query()
            ->orderBy('name')
            ->get();

        // Use Spatie's scheduler to compute next run like before
        $scheduledTasks = ScheduledTasks::createForSchedule();
        $tasksByName = $scheduledTasks->uniqueTasks()
            ->filter(fn ($t) => $t->isBeingMonitored())
            ->mapWithKeys(fn ($t) => [$t->name() => $t]);

        $applicationTimezone = config('app.timezone', 'UTC');

        $monitoredTasks = $monitoredTasksModels->map(function (MonitoredScheduledTask $task) use (
            $tasksByName,
            $applicationTimezone
        ) {
            $timezone = $task->timezone ?: $applicationTimezone;

            $scheduledTask = $tasksByName[$task->name] ?? null;
            $nextRunIso = $scheduledTask && method_exists($scheduledTask, 'nextRunAt') && $scheduledTask->nextRunAt()
                ? Carbon::parse((string) $scheduledTask->nextRunAt())->toIso8601String()
                : null;

            $latestLog = $task->getMonitoredScheduleTaskLogItemModel()
                ->newQuery()
                ->where('monitored_scheduled_task_id', $task->id)
                ->orderBy('id', 'desc')
                ->first();

            // Old logic: last 24h = Active, last failure newer than finished = Failed, otherwise Inactive/Never Run
            $lastFailedAt = $task->last_failed_at;
            $lastFinishedAt = $task->last_finished_at;
            $lastStartedAt = $task->last_started_at;

            $hasFailedRecently = $lastFailedAt && (! $lastFinishedAt || $lastFailedAt->gt($lastFinishedAt));
            $hasRunRecently = $lastFinishedAt && $lastFinishedAt->gt(Carbon::now()->subDay());

            $statusColor = 'gray';
            $statusText = 'Never Run';
            if ($hasFailedRecently) {
                $statusColor = 'red';
                $statusText = 'Failed';
            } elseif ($hasRunRecently) {
                $statusColor = 'green';
                $statusText = 'Active';
            } elseif ($lastStartedAt) {
                $statusColor = 'yellow';
                $statusText = 'Inactive';
            }

            return [
                'name' => $task->name,
                'type' => $task->type,
                'schedule' => $task->cron_expression,
                'timezone' => $timezone,
                'last_started' => optional($task->last_started_at)?->toIso8601String(),
                'last_finished' => optional($task->last_finished_at)?->toIso8601String(),
                'last_failed' => optional($task->last_failed_at)?->toIso8601String(),
                'last_skipped' => optional($task->last_skipped_at)?->toIso8601String(),
                'last_pinged' => optional($task->last_pinged_at)?->toIso8601String(),
                'registered_on_oh_dear' => ! is_null($task->registered_on_oh_dear_at),
                'next_run' => $nextRunIso,
                'grace_time' => (int) $task->grace_time_in_minutes,
                'runs_on_one_server' => (bool) ($task->run_on_one_server ?? false),
                'runs_in_maintenance' => (bool) ($task->run_in_maintenance_mode ?? false),
                'status_text' => $statusText,
                'status_color' => $statusColor,
                'latest_log' => $latestLog ? [
                    'type' => $latestLog->type,
                    'meta' => $latestLog->meta,
                    'created_at' => optional($latestLog->created_at)?->toIso8601String(),
                ] : null,
            ];
        })
            ->sortBy(function (array $task) {
                return ! empty($task['next_run'])
                    ? Carbon::parse($task['next_run'])
                    : Carbon::now()->addYear();
            })
            ->values();

        // Health summary to match old behavior
        $now = Carbon::now();
        $determineStatus = function (array $task) use ($now): string {
            $lastFailed = ! empty($task['last_failed']) ? Carbon::parse($task['last_failed']) : null;
            $lastFinished = ! empty($task['last_finished']) ? Carbon::parse($task['last_finished']) : null;

            $hasFailed = $lastFailed && (! $lastFinished || $lastFailed->gt($lastFinished));
            $hasRunRecently = $lastFinished && $lastFinished->gt($now->copy()->subDay());

            if ($hasFailed) {
                return 'Failed';
            }
            if ($hasRunRecently) {
                return 'Active';
            }
            if (! empty($task['last_started'])) {
                return 'Inactive';
            }

            return 'Never Run';
        };

        $healthSummary = [
            'total' => $monitoredTasks->count(),
            'active' => $monitoredTasks->filter(fn (array $t
            ) => ($t['status_text'] ?? $determineStatus($t)) === 'Active')->count(),
            'failed' => $monitoredTasks->filter(fn (array $t
            ) => ($t['status_text'] ?? $determineStatus($t)) === 'Failed')->count(),
            'never_run' => $monitoredTasks->filter(fn (array $t
            ) => ($t['status_text'] ?? $determineStatus($t)) === 'Never Run')->count(),
            'monitored_on_oh_dear' => $monitoredTasks->filter(fn (array $t
            ) => (bool) $t['registered_on_oh_dear'])->count(),
        ];

        $dateFormat = config('schedule-monitor.date_format');

        $metaTags = [
            'title' => 'System Status',
            'description' => sprintf(
                'System health and performance metrics for FVN.li. Currently tracking %d games with %d visible listings, %d scheduled tasks, and %d health monitors.',
                $gameStats['total'],
                $gameStats['visible'],
                $healthSummary['total'],
                $healthSummary['monitored_on_oh_dear']
            ),
            'image' => asset(config('social.images.default')),
        ];

        return Inertia::render('system-status', [
            'gameStats' => $gameStats,
            'ratingStats' => $ratingStats,
            'releaseYearStats' => $releaseYearStats,
            'monitoredTasks' => $monitoredTasks,
            'healthSummary' => $healthSummary,
            'dateFormat' => $dateFormat,
            'metaTags' => $metaTags,
        ]);
    }
}
