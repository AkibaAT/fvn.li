<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            cache()->store('redis')->put('health_check', 'ok', 1);
            cache()->store('redis')->get('health_check');

            $pipeline = Cache::remember('health.pipeline', 60, function (): array {
                $now = CarbonImmutable::now();
                $heartbeat = Cache::get('scheduler.heartbeat');
                $heartbeatAt = $heartbeat ? CarbonImmutable::parse($heartbeat) : null;
                $oldestDue = DB::table('notification_queue')
                    ->where('status', 'pending')
                    ->where('scheduled_at', '<=', $now)
                    ->min('scheduled_at');
                $oldestDueAt = $oldestDue ? CarbonImmutable::parse($oldestDue) : null;

                return [
                    'scheduler' => [
                        'heartbeat_at' => $heartbeatAt?->toISOString(),
                        'age_seconds' => $heartbeatAt ? max(0, (int) $heartbeatAt->diffInSeconds($now)) : null,
                        'stale' => ! $heartbeatAt || $heartbeatAt->lt($now->subSeconds(300)),
                    ],
                    'notification_queue' => [
                        'pending_due_count' => DB::table('notification_queue')
                            ->where('status', 'pending')
                            ->where('scheduled_at', '<=', $now)
                            ->count(),
                        'oldest_due_age_seconds' => $oldestDueAt ? max(0, (int) $oldestDueAt->diffInSeconds($now)) : null,
                        'stale_processing_count' => DB::table('notification_queue')
                            ->where('status', 'processing')
                            ->where('updated_at', '<', $now->subMinutes(15))
                            ->count(),
                    ],
                    'failed_jobs_count' => DB::table('failed_jobs')->count(),
                ];
            });

            return response()->json(['status' => 'ok', ...$pipeline], 200);
        } catch (Exception $e) {
            Log::warning('Health check failed', [
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            return response()->json(['status' => 'error'], 503);
        }
    }
}
