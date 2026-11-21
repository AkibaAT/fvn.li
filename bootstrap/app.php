<?php

declare(strict_types=1);

use App\Exceptions\Handler;
use App\Http\Middleware\AdminPanelAccess;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PerformanceMonitoring;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RequireSanctumTokenAbility;
use App\Http\Middleware\RequireUserApiToken;
use App\Http\Middleware\StartSession;
use App\Http\Middleware\ThrottleUserApiIp;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as BasePreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        then: function (): void {
            Route::middleware('user-api')
                ->prefix('api/v1')
                ->group(base_path('routes/user-api.php'));

            Route::prefix('browser-api')
                ->group(base_path('routes/browser-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/');

        $middleware->web(
            prepend: [PerformanceMonitoring::class],
            append: [HandleInertiaRequests::class],
            replace: [
                BaseEncryptCookies::class => EncryptCookies::class,
                BaseStartSession::class => StartSession::class,
                BasePreventRequestForgery::class => PreventRequestForgery::class,
            ],
        );

        $middleware->api(
            prepend: [
                PerformanceMonitoring::class,
                ThrottleRequests::class . ':api',
            ],
        );

        $middleware->group('user-api', [
            PerformanceMonitoring::class,
            ThrottleUserApiIp::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            CachePublicPage::ALIAS => CachePublicPage::class,
            'admin' => AdminPanelAccess::class,
            'sanctum.token' => RequireSanctumTokenAbility::class,
            'user.api.token' => RequireUserApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            $missing = $e->getPrevious();
            if ($request->is('api/v1/*') && $missing instanceof MissingAbilityException) {
                return response()->json([
                    'message' => 'This token lacks the ' . implode(', ', $missing->abilities()) . ' permission.',
                ], 403);
            }
        });
    })
    ->withSingletons([
        ExceptionHandler::class => Handler::class,
    ])
    ->create();
