<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ViewPreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CachePublicPage
{
    public const ALIAS = 'cache.public';

    private const ATTRIBUTE = 'public_page_cache';

    private const VIEW_COOKIES = [
        ViewPreference::HOME_COOKIE,
        ViewPreference::GAMES_COOKIE,
    ];

    public static function appliesTo(Request $request): bool
    {
        if (! $request->attributes->has(self::ATTRIBUTE)) {
            $request->attributes->set(self::ATTRIBUTE, $request->isMethodCacheable()
                && in_array(self::ALIAS, $request->route()?->gatherMiddleware() ?? [], true)
                && ! $request->headers->has('Authorization')
                && ! self::carriesVisitorState($request));
        }

        return $request->attributes->get(self::ATTRIBUTE);
    }

    private static function carriesVisitorState(Request $request): bool
    {
        foreach ($request->cookies->all() as $name => $value) {
            $name = (string) $name;

            if ($name === config('session.cookie') || $name === 'XSRF-TOKEN' || str_starts_with($name, 'remember_')) {
                return true;
            }

            if (in_array($name, self::VIEW_COOKIES, true) && $value === ViewPreference::LIST) {
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $ttl = (int) config('fvn.public_page_cache_ttl');

        if (! self::appliesTo($request) || $ttl <= 0 || $request->inertia() || ! $this->isCacheable($response)) {
            return $response;
        }

        $response->headers->set('Cache-Control', "public, max-age=0, s-maxage={$ttl}");
        $response->setEtag(hash('xxh128', $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    private function isCacheable(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && is_string($response->getContent())
            && $response->headers->getCookies() === []
            && Cookie::getQueuedCookies() === [];
    }
}
