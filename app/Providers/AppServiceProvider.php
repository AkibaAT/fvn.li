<?php

declare(strict_types=1);

namespace App\Providers;

use App\Livewire\Auth\SocialLoginButtons;
use App\Models\Game;
use App\Observers\GameObserver;
use App\Observers\UniversalAuditObserver;
use App\Services\ItchHttpClientFactory;
use App\Services\ItchHttpClientService;
use App\Services\LanguageMappingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ReflectionClass;
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

        // Register universal audit observer for all Eloquent models
        $this->registerUniversalAuditObserver();

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

    /**
     * Register the universal audit observer with all Eloquent models
     */
    private function registerUniversalAuditObserver(): void
    {
        // Check if audit logging is enabled
        if (! config('audit.enabled', true)) {
            return;
        }

        // Get all model files and register the observer with each
        $modelPath = app_path('Models');
        if (! is_dir($modelPath)) {
            return;
        }

        $modelFiles = glob($modelPath . '/*.php');

        foreach ($modelFiles as $file) {
            $fileName = basename($file, '.php');
            $modelClass = "App\\Models\\{$fileName}";

            // Skip if class doesn't exist or isn't a model
            if (! class_exists($modelClass)) {
                continue;
            }

            // Check if it extends Model
            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            // Skip abstract classes
            $reflection = new ReflectionClass($modelClass);
            if ($reflection->isAbstract()) {
                continue;
            }

            // Register the observer
            $modelClass::observe(UniversalAuditObserver::class);
        }
    }
}
