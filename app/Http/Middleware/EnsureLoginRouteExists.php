<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginRouteExists
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if login route exists, if not, add a temporary one
        if (! Route::has('login')) {
            Route::get('/login', function () {
                return view('auth.login');
            })->name('login');
        }

        return $next($request);
    }
}
