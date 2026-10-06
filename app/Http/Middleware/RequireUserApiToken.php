<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequireUserApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! in_array('user-api', $token->abilities, true)) {
            throw new AuthenticationException;
        }

        $response = $next($request);
        $response->headers->set('X-Token-Expires-At', $token->expires_at?->toIso8601String() ?? '');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
