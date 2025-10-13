<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
    ];

    /**
     * TEMP: Add diagnostic logging to see exactly what Laravel compares.
     * Remove after diagnosing 419s.
     */
    protected function tokensMatch($request)
    {
        try {
            $headerToken = $request->header('X-CSRF-TOKEN');
            $cookieXsrf = $request->cookie('XSRF-TOKEN');
            // Token Laravel will actually validate from the request (header/body/cookie)
            $tokenFromRequest = $this->getTokenFromRequest($request);
            // Session token Laravel expects
            $sessionToken = $request->session()?->token();

            Log::warning('CSRF DEBUG', [
                'path' => $request->path(),
                'method' => $request->method(),
                'expects_json' => $request->expectsJson(),
                'header_token_last8' => is_string($headerToken) ? substr($headerToken, -8) : null,
                'cookie_xsrf_last8' => is_string($cookieXsrf) ? substr(urldecode($cookieXsrf), -8) : null,
                'token_from_request_last8' => is_string($tokenFromRequest) ? substr($tokenFromRequest, -8) : null,
                'session_token_last8' => is_string($sessionToken) ? substr($sessionToken, -8) : null,
                'cookie_names' => array_keys($request->cookies->all() ?? []),
                'has_session' => $request->hasSession(),
                'session_id' => $request->session()?->getId(),
                'match' => (is_string($tokenFromRequest) && is_string($sessionToken)) ? hash_equals($sessionToken,
                    $tokenFromRequest) : false,
            ]);
        } catch (Throwable $e) {
            Log::error('CSRF DEBUG ERROR: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return parent::tokensMatch($request);
    }
}
