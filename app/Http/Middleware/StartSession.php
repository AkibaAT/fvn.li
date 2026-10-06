<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Middleware\StartSession as Middleware;
use Illuminate\Session\Store;

class StartSession extends Middleware
{
    public function handle($request, Closure $next)
    {
        if (! CachePublicPage::appliesTo($request)) {
            return parent::handle($request, $next);
        }

        $session = new Store((string) config('session.cookie'), new ArraySessionHandler(0));
        $request->setLaravelSession($session);
        $session->start();

        return $next($request);
    }
}
