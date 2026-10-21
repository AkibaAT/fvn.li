<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Middleware\StartSession as Middleware;
use Illuminate\Session\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class StartSession extends Middleware
{
    private const array BOOKKEEPING_KEYS = ['_token', '_previous', '_flash', 'filament.notifications.mailbox'];

    public static function shouldPersist(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        if ($request->user() !== null) {
            return true;
        }

        foreach (array_keys(Arr::dot($request->session()->all())) as $key) {
            if (! self::isBookkeeping((string) $key)) {
                return true;
            }
        }

        return false;
    }

    private static function isBookkeeping(string $key): bool
    {
        foreach (self::BOOKKEEPING_KEYS as $bookkeepingKey) {
            if ($key === $bookkeepingKey || str_starts_with($key, $bookkeepingKey . '.')) {
                return true;
            }
        }

        return false;
    }

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

    protected function addCookieToResponse(Response $response, Session $session)
    {
        $request = request();

        if (self::shouldPersist($request)) {
            parent::addCookieToResponse($response, $session);

            return;
        }

        if ($request->cookies->has($session->getName())) {
            $response->headers->setCookie(Cookie::forget($session->getName()));
        }
    }

    protected function saveSession($request)
    {
        if (self::shouldPersist($request)) {
            parent::saveSession($request);

            return;
        }

        if ($request->cookies->has($request->session()->getName())) {
            $request->session()->getHandler()->destroy($request->session()->getId());
        }
    }
}
