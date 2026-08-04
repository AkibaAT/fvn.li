<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PerformanceMonitoring
{
    public function handle(Request $request, Closure $next): Response
    {
        // Start performance tracking
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Enable query logging (can be disabled via PERFORMANCE_LOG_QUERIES=false for high-traffic production)
        $shouldLogQueries = config('performance.log_queries', true);
        if ($shouldLogQueries) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        try {
            $response = $next($request);

            $executionTime = (microtime(true) - $startTime) * 1000; // milliseconds
            $peakMemory = memory_get_peak_usage(true);
            $memoryUsed = $peakMemory - $startMemory;
            $queryCount = $shouldLogQueries ? count(DB::getQueryLog()) : 0;

            if ($response instanceof Response) {
                $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
                $response->headers->set('X-Memory-Usage', round($memoryUsed / 1024 / 1024, 2) . 'MB');
                $response->headers->set('X-Query-Count', $queryCount);
            }

            $slowThreshold = config('performance.slow_request_threshold', 1000);
            $verySlowThreshold = config('performance.very_slow_request_threshold', 3000);

            if ($executionTime > $slowThreshold) {
                $this->logSlowRequest($request, $response, $executionTime, $memoryUsed, $queryCount, $verySlowThreshold);
            }

            $this->logRequestMetrics($request, $response, $executionTime, $memoryUsed, $queryCount);

            return $response;
        } finally {
            if ($shouldLogQueries) {
                DB::disableQueryLog();
                DB::flushQueryLog();
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
