<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps rather than extends ThrottleRequests so the middleware priority list,
 * which orders throttling after authentication, leaves it in front of auth and
 * anonymous requests are limited too.
 */
class ThrottleUserApiIp
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->throttle->handle($request, $next, 'user-api-ip');
    }
}
