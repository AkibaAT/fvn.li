<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ItchAuthService;
use App\Services\ItchFollowService;
use App\Services\ItchHttpClientService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class ItchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the ItchHttpClientService with default configuration
        $this->app->singleton(ItchHttpClientService::class, function () {
            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 5,
            ]);

            return new ItchHttpClientService(
                $client,
                config('services.itch.max_retries'),
                config('services.itch.retry_cooldown')
            );
        });

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
