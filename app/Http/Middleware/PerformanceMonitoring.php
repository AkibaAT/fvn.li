<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PerformanceMonitoring
{
    private static bool $queryListenerRegistered = false;

    private static int $trackingDepth = 0;

    private static int $activeQueryCount = 0;

    public static function resetState(): void
    {
        self::$trackingDepth = 0;
        self::$activeQueryCount = 0;
    }

    private static function ensureQueryListenerRegistered(): void
    {
        if (self::$queryListenerRegistered) {
            return;
        }

        DB::listen(static function (): void {
            if (self::$trackingDepth > 0) {
                self::$activeQueryCount++;
            }
        });

        self::$queryListenerRegistered = true;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Start performance tracking
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $shouldCountQueries = (bool) config('performance.log_queries', true);
        $previousQueryCount = self::$activeQueryCount;

        if ($shouldCountQueries) {
            self::ensureQueryListenerRegistered();
            self::$trackingDepth++;
            self::$activeQueryCount = 0;

            if (DB::logging()) {
                DB::flushQueryLog();
            }
        }

        try {
            $response = $next($request);

            $executionTime = (microtime(true) - $startTime) * 1000; // milliseconds
            $memoryUsed = max(0, memory_get_usage(true) - $startMemory);
            $queryCount = $shouldCountQueries ? self::$activeQueryCount : 0;

            if ($response instanceof Response && (config('performance.expose_headers', false) || config('app.debug', false))) {
                $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
                $response->headers->set('X-Memory-Usage', round($memoryUsed / 1024 / 1024, 2) . 'MB');
                $response->headers->set('X-Query-Count', (string) $queryCount);
            }

            $slowThreshold = (int) config('performance.slow_request_threshold', 1000);
            $verySlowThreshold = (int) config('performance.very_slow_request_threshold', 3000);

            if ($executionTime > $slowThreshold) {
                $this->logSlowRequest($request, $response, $executionTime, $memoryUsed, $queryCount, $verySlowThreshold);
            }

            $this->logRequestMetrics($request, $response, $executionTime, $memoryUsed, $queryCount);

            return $response;
        } finally {
            if ($shouldCountQueries) {
                self::$trackingDepth = max(0, self::$trackingDepth - 1);
                self::$activeQueryCount = self::$trackingDepth > 0 ? $previousQueryCount + self::$activeQueryCount : 0;

                if (DB::logging()) {
                    DB::disableQueryLog();
                    DB::flushQueryLog();
                }
            }
        }
    }

    /**
     * Log slow request details.
     */
    protected function logSlowRequest(
        Request $request,
        Response $response,
        float $executionTime,
        int $memoryUsed,
        int $queryCount,
        int $verySlowThreshold
    ): void {
        try {
            $level = $executionTime > $verySlowThreshold ? 'warning' : 'info';

            Log::$level('Slow request detected', [
                'execution_time_ms' => round($executionTime, 2),
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'query_count' => $queryCount,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => $request->route()?->getName(),
                'status_code' => $response->getStatusCode(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            // Silently fail to prevent breaking the application
            Log::error('Failed to log slow request', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }
    }

    /**
     * Log request metrics for all requests.
     */
    protected function logRequestMetrics(
        Request $request,
        Response $response,
        float $executionTime,
        int $memoryUsed,
        int $queryCount
    ): void {
        // Only log actual page requests, not assets
        if ($this->shouldLogRequest($request)) {
            try {
                Log::channel('performance')->info('Request metrics', [
                    'execution_time_ms' => round($executionTime, 2),
                    'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'query_count' => $queryCount,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route' => $request->route()?->getName(),
                    'status_code' => $response->getStatusCode(),
                    'is_ajax' => $request->ajax(),
                    'user_id' => $request->user()?->id,
                ]);
            } catch (Throwable $e) {
                // Silently fail to prevent breaking the application
                Log::error('Failed to log performance metrics', [
                    'error' => $e->getMessage(),
                    'path' => $request->path(),
                ]);
            }
        }
    }

    protected function shouldLogRequest(Request $request): bool
    {
        $path = $request->path();
        $skipPatterns = [
            'build/',
            'assets/',
            'storage/',
            'favicon.ico',
            'robots.txt',
            'health',
            '_ignition/health-check',
            '.css',
            '.js',
            '.map',
            '.png',
            '.jpg',
            '.jpeg',
            '.gif',
            '.svg',
            '.woff',
            '.woff2',
            '.ttf',
            '.eot',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
