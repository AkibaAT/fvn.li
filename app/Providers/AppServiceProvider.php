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
use App\Models\User;
use App\Observers\CharacterObserver;
use App\Observers\GameJamObserver;
use App\Observers\GameObserver;
use App\Observers\GameVersionObserver;
use App\Observers\RatingObserver;
use App\Observers\TagObserver;
use App\Observers\UniversalAuditObserver;
use App\Observers\UserObserver;
use App\Services\FlareSolverrClient;
use App\Services\FlareSolverrSessionManager;
use App\Services\GameArchiveOptimizationService;
use App\Services\GameArchiveOptimizerDockerRunner;
use App\Services\GameStatsService;
use App\Services\HtmlSanitizerService;
use App\Services\ItchHttpClientFactory;
use App\Services\ItchHttpClientService;
use App\Services\ItchIoProvider;
use App\Services\ItchUrlSafetyValidator;
use App\Services\LanguageMappingService;
use App\Support\Diagnostics\DiagnosticLogManager;
use App\Support\IconVersion;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /** @var list<class-string<Model>>|null */
    private static ?array $auditableModels = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->forgetInstance('log');
        $this->app->singleton('log', fn ($app) => new DiagnosticLogManager($app));

        $this->app->singleton(HtmlSanitizerService::class);

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
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->configureRateLimiting();

        View::share('iconVersion', IconVersion::get());

        // Register specific model observers for search index and user lifecycle updates
        Game::observe(GameObserver::class);
        GameJam::observe(GameJamObserver::class);
        GameVersion::observe(GameVersionObserver::class);
        Character::observe(CharacterObserver::class);
        Tag::observe(TagObserver::class);
        Rating::observe(RatingObserver::class);
        User::observe(UserObserver::class);

        $this->disableSearchSyncingForTests();

        // Register universal audit observer for all Eloquent models
        $this->registerUniversalAuditObserver();

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
            $event->extendSocialite('google', \SocialiteProviders\Google\Provider::class);
            $event->extendSocialite('telegram', \SocialiteProviders\Telegram\Provider::class);
            $event->extendSocialite('steam', \SocialiteProviders\Steam\Provider::class);
            $event->extendSocialite('itchio', ItchIoProvider::class);
        });

        $this->registerSlowQueryLogging();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('user-api-ip', fn (Request $request): Limit => Limit::perMinute(300)->by('ip:' . $request->ip()));
        $userLimit = static fn (string $bucket, int $max): callable => static fn (Request $request): Limit => Limit::perMinute($max)->by("user-api-{$bucket}:" . ($request->user()?->getKey() ?? $request->ip()));
        RateLimiter::for('user-api', $userLimit('requests', 120));
        RateLimiter::for('user-api-write', $userLimit('write', 30));
        RateLimiter::for('user-api-lookup', $userLimit('lookup', 10));

        RateLimiter::for('save-parser', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('notification-health-test', function (Request $request): Limit {
            return Limit::perHour(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });
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

        if (self::$auditableModels === null) {
            self::$auditableModels = [];
            $modelPath = app_path('Models');

            if (is_dir($modelPath)) {
                foreach (glob($modelPath . '/*.php') ?: [] as $file) {
                    $fileName = basename($file, '.php');
                    $modelClass = "App\\Models\\{$fileName}";

                    if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
                        continue;
                    }

                    $reflection = new ReflectionClass($modelClass);
                    if ($reflection->isAbstract()) {
                        continue;
                    }

                    self::$auditableModels[] = $modelClass;
                }
            }
        }

        foreach (self::$auditableModels as $modelClass) {
            $modelClass::observe(UniversalAuditObserver::class);
        }
    }

    /**
     * Register slow query logging.
     */
    private function registerSlowQueryLogging(): void
    {
        // Slow query threshold in milliseconds
        $slowQueryThreshold = (int) config('database.slow_query_threshold', 1000);

        DB::listen(function ($query) use ($slowQueryThreshold) {
            if ($query->time > $slowQueryThreshold) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings_count' => count($query->bindings),
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }
}
