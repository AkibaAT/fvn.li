<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Query Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable query logging in the PerformanceMonitoring middleware.
    | Query logging can be memory intensive in high-traffic production environments.
    | Set PERFORMANCE_LOG_QUERIES=false in production if memory usage becomes an issue.
    |
    */

    'log_queries' => env('PERFORMANCE_LOG_QUERIES', true),

    /*
    |--------------------------------------------------------------------------
    | Slow Request Threshold
    |--------------------------------------------------------------------------
    |
    | Threshold in milliseconds for logging slow requests.
    | Requests exceeding this threshold will be logged for investigation.
    |
    */

    'slow_request_threshold' => env('PERFORMANCE_SLOW_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Very Slow Request Threshold
    |--------------------------------------------------------------------------
    |
    | Threshold in milliseconds for logging very slow requests with warning level.
    | Requests exceeding this threshold will be logged as warnings.
    |
    */

    'very_slow_request_threshold' => env('PERFORMANCE_VERY_SLOW_THRESHOLD', 3000),

];
