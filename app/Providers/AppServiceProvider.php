<?php

declare(strict_types=1);

namespace App\Providers;

use App\Livewire\Auth\SocialLoginButtons;
use App\Models\Game;
use App\Observers\GameObserver;
use App\Services\ItchHttpClientFactory;
use App\Services\ItchHttpClientService;
use App\Services\LanguageMappingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        $this->app->singleton(ItchHttpClientService::class, function () {
            return new ItchHttpClientService(
                App::make(ItchHttpClientFactory::class),
                config('services.itch.max_retries'),
                config('services.itch.retry_cooldown')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Game::observe(GameObserver::class);

        // Ensure the keystores directory exists and is secured
        $keystoreDir = storage_path('app/keystores');
        if (! File::exists($keystoreDir)) {
            File::makeDirectory($keystoreDir, 0755, true, true);
        }
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
            $event->extendSocialite('google', \SocialiteProviders\Google\Provider::class);
            $event->extendSocialite('telegram', \SocialiteProviders\Telegram\Provider::class);
            $event->extendSocialite('steam', \SocialiteProviders\Steam\Provider::class);
            $event->extendSocialite('itchio', \App\Services\ItchIoProvider::class);
        });

        // Register Livewire components
        Livewire::component('auth.social-login-buttons', SocialLoginButtons::class);

        // Register new top level views
        $this->loadViewsFrom(resource_path('views/games'), 'games');
        $this->loadViewsFrom(resource_path('views/lists'), 'lists');
        $this->loadViewsFrom(resource_path('views/ratings'), 'ratings');
        $this->loadViewsFrom(resource_path('views/users'), 'users');
        $this->loadViewsFrom(resource_path('views/admin'), 'admin');
        $this->loadViewsFrom(resource_path('views/dialogue'), 'dialogue');
    }
}
