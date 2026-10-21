<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

class PreventRequestForgery extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
        'browser-api/track/custom-link',
        'browser-api/games/*/versions/*/parse-save',
    ];

    protected function inExceptArray($request): bool
    {
        if (app()->environment('testing') && $request->is('browser-api/*')) {
            return true;
        }

        return parent::inExceptArray($request);
    }

    protected function addCookieToResponse($request, $response)
    {
        if (CachePublicPage::appliesTo($request) || ! StartSession::shouldPersist($request)) {
            return $response;
        }

        return parent::addCookieToResponse($request, $response);
    }
}
