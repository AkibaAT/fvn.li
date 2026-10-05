<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('user-api-ip', fn (Request $request) => Limit::perMinute(300)->by('ip:' . $request->ip()));
        $userLimit = static fn (string $bucket, int $max): callable => static fn (Request $request): Limit => Limit::perMinute($max)->by("user-api-{$bucket}:" . $request->user()->getKey());
        RateLimiter::for('user-api', $userLimit('requests', 120));
        RateLimiter::for('user-api-write', $userLimit('write', 30));
        RateLimiter::for('user-api-lookup', $userLimit('lookup', 10));

        RateLimiter::for('save-parser', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('notification-health-test', function (Request $request) {
            return Limit::perHour(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('user-api')
                ->prefix('api/v1')
                ->group(base_path('routes/user-api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Browser API routes remain under /browser-api
            Route::prefix('browser-api')
                ->group(base_path('routes/browser-api.php'));
        });
    }
}
