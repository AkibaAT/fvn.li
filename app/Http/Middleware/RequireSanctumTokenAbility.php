<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class RequireSanctumTokenAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException|MissingAbilityException
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token || $token instanceof TransientToken) {
            throw new AuthenticationException;
        }

        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                throw new MissingAbilityException($ability);
            }
        }

        return $next($request);
    }
}
