<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\GameVersionAnalyzer;
use App\Services\ItchCollectionService;
use App\Services\ProxyRotator;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class GameAnalyzerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProxyRotator::class, function ($app) {
            return new ProxyRotator(
                config('services.proxy.user'),
                config('services.proxy.password'),
                config('services.itch.user'),
                config('services.itch.password'),
                config('services.proxy.list', [])
            );
        });

        $this->app->singleton(ItchCollectionService::class, function ($app) {
            if (! config('services.itch.collection_id')) {
                throw new RuntimeException('ITCH_COLLECTION_ID must be configured in .env');
            }

            return new ItchCollectionService(
                config('services.itch.collection_id'),
                config('services.itch.api_key'),
                $app->make(ProxyRotator::class)
            );
        });

        $this->app->singleton(GameVersionAnalyzer::class, function ($app) {
            if (! config('services.itch.api_key')) {
                throw new RuntimeException('ITCH_API_KEY must be configured in .env');
            }

            return new GameVersionAnalyzer(
                config('services.itch.api_key'),
                $app->make(ProxyRotator::class)
            );
        });
    }
}
