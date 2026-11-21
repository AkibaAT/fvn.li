<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Middleware\PerformanceMonitoring;
use App\Services\GameDataSyncService;

class FlushOctaneStaticCaches
{
    public function handle(): void
    {
        GameDataSyncService::clearAllHttpCache();
        PerformanceMonitoring::resetState();
    }
}
