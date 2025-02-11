<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Game;
use App\Observers\GameObserver;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Game::observe(GameObserver::class);
        Livewire::listen('mount', function ($component) {
            $component->enableBackButtonCache();
        });
    }
}
