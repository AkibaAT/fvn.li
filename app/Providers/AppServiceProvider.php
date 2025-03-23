<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Game;
use App\Models\Rating;
use App\Observers\GameObserver;
use App\Observers\RatingObserver;
use App\Services\LanguageMappingService;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LanguageMappingService::class, function () {
            return new LanguageMappingService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Game::observe(GameObserver::class);
        Rating::observe(RatingObserver::class);
        Livewire::listen('mount', function ($component) {
            $component->enableBackButtonCache();
        });
    }
}
