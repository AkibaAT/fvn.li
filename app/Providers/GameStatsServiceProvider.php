<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\GameStatsService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class GameStatsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameStatsService::class, function ($app) {
            return new GameStatsService(
                new Client([
                    'timeout' => 300, // 5 minute timeout for large downloads
                    'connect_timeout' => 10,
                ])
            );
        });
    }
}
