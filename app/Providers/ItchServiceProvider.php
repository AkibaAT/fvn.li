<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ItchAuthService;
use App\Services\ItchFollowService;
use Illuminate\Support\ServiceProvider;

class ItchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ItchAuthService::class);
        $this->app->singleton(ItchFollowService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
