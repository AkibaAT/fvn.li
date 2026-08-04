<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameDialogueText;
use App\Models\GameJam;
use App\Models\GameVersion;
use App\Models\Rating;
use App\Models\Tag;
use App\Observers\CharacterObserver;
use App\Observers\GameJamObserver;
use App\Observers\GameObserver;
use App\Observers\GameVersionObserver;
use App\Observers\RatingObserver;
use App\Observers\TagObserver;
use App\Observers\UniversalAuditObserver;
use App\Services\FlareSolverrClient;
use App\Services\FlareSolverrSessionManager;
use App\Services\GameArchiveOptimizationService;
use App\Services\GameArchiveOptimizerDockerRunner;
use App\Services\GameStatsService;
use App\Services\ItchHttpClientFactory;
use App\Services\ItchHttpClientService;
use App\Services\ItchIoProvider;
use App\Services\ItchUrlSafetyValidator;
use App\Services\LanguageMappingService;
use App\Support\Diagnostics\DiagnosticLogManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->forgetInstance('log');
        $this->app->singleton('log', fn ($app) => new DiagnosticLogManager($app));

        $this->app->singleton(LanguageMappingService::class, function () {
            return new LanguageMappingService;
        });

        $this->app->singleton(FlareSolverrClient::class, function () {
            return new FlareSolverrClient;
        });

        $this->app->singleton(FlareSolverrSessionManager::class, function ($app) {
            return new FlareSolverrSessionManager(
                $app->make(FlareSolverrClient::class)
            );
        });

        $this->app->singleton(ItchHttpClientService::class, function () {
            return new ItchHttpClientService(
                App::make(ItchHttpClientFactory::class),
                App::make(ItchUrlSafetyValidator::class),
                (int) config('services.itch.max_retries'),
                (int) config('services.itch.retry_cooldown')
            );
        });

        $this->app->bind(GameArchiveOptimizationService::class, function ($app) {
            return new GameArchiveOptimizationService(
                $app->make(GameStatsService::class),
                $app->environment('testing') ? null : $app->make(GameArchiveOptimizerDockerRunner::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register specific model observers for search index updates
        Game::observe(GameObserver::class);
        GameJam::observe(GameJamObserver::class);
        GameVersion::observe(GameVersionObserver::class);
        Character::observe(CharacterObserver::class);
        Tag::observe(TagObserver::class);
        Rating::observe(RatingObserver::class);

        $this->disableSearchSyncingForTests();

        // Register universal audit observer for all Eloquent models
        $this->registerUniversalAuditObserver();

        $keystoreDir = (string) config('services.android.keystore_path', storage_path('app/keystores'));
        if (! File::exists($keystoreDir)) {
            File::makeDirectory($keystoreDir, 0755, true, true);
        }

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
            $event->extendSocialite('google', \SocialiteProviders\Google\Provider::class);
            $event->extendSocialite('telegram', \SocialiteProviders\Telegram\Provider::class);
            $event->extendSocialite('steam', \SocialiteProviders\Steam\Provider::class);
            $event->extendSocialite('itchio', ItchIoProvider::class);
        });

        // Register new top level views
        $this->loadViewsFrom(resource_path('views/games'), 'games');
        $this->loadViewsFrom(resource_path('views/lists'), 'lists');
        $this->loadViewsFrom(resource_path('views/ratings'), 'ratings');
        $this->loadViewsFrom(resource_path('views/users'), 'users');
        $this->loadViewsFrom(resource_path('views/admin'), 'admin');
        $this->loadViewsFrom(resource_path('views/dialogue'), 'dialogue');

        $this->registerSlowQueryLogging();
    }

    private function disableSearchSyncingForTests(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        Game::disableSearchSyncing();
        GameDialogueText::disableSearchSyncing();
        Tag::disableSearchSyncing();
    }

    /**
     * Register the universal audit observer with all Eloquent models
     */
    private function registerUniversalAuditObserver(): void
    {
        if (! config('audit.enabled', true)) {
            return;
        }

        $modelPath = app_path('Models');
        if (! is_dir($modelPath)) {
            return;
        }

        $modelFiles = glob($modelPath . '/*.php');

        foreach ($modelFiles as $file) {
            $fileName = basename($file, '.php');
            $modelClass = "App\\Models\\{$fileName}";

            if (! class_exists($modelClass)) {
                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($modelClass);
            if ($reflection->isAbstract()) {
                continue;
            }

            // Register the observer
            $modelClass::observe(UniversalAuditObserver::class);
        }
    }

    /**
     * Register slow query logging.
     */
    private function registerSlowQueryLogging(): void
    {
        // Slow query threshold in milliseconds
        $slowQueryThreshold = config('database.slow_query_threshold', 1000);

        DB::listen(function ($query) use ($slowQueryThreshold) {
            if ($query->time > $slowQueryThreshold) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }
}
