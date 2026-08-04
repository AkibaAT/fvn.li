<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SafeRedirectUrl;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $fullUrl = SafeRedirectUrl::intended($request->fullUrl(), $request);

        // Don't redirect to login page itself
        if ($fullUrl && ! str_contains($fullUrl, route('login'))) {
            session()->put('url.intended', $fullUrl);
            Log::info('Storing intended URL in Authenticate middleware', ['url' => $fullUrl]);
        }

        return route('login');
    }
}
