<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\EdgeCache;
use App\Support\ViewPreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

// Edge cache must bypass every request this middleware keeps private.
class CachePublicPage
{
    public const ALIAS = 'cache.public';

    private const ATTRIBUTE = 'public_page_cache';

    private const VIEW_COOKIES_BY_ROUTE = [
        'home' => ViewPreference::HOME_COOKIE,
        'games.index' => ViewPreference::GAMES_COOKIE,
    ];

    public static function appliesTo(Request $request): bool
    {
        if (! $request->attributes->has(self::ATTRIBUTE)) {
            $request->attributes->set(self::ATTRIBUTE, $request->isMethodCacheable()
                && self::isPublicRoute($request)
                && ! $request->headers->has('Authorization')
                && ! self::carriesVisitorState($request));
        }

        return $request->attributes->get(self::ATTRIBUTE);
    }

    private static function isPublicRoute(Request $request): bool
    {
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (is_string($middleware) && ($middleware === self::ALIAS || str_starts_with($middleware, self::ALIAS . ':'))) {
                return true;
            }
        }

        return false;
    }

    private static function carriesVisitorState(Request $request): bool
    {
        foreach (array_keys($request->cookies->all()) as $name) {
            $name = (string) $name;

            if ($name === config('session.cookie') || $name === 'XSRF-TOKEN' || str_starts_with($name, 'remember_')) {
                return true;
            }
        }

        $viewCookie = self::VIEW_COOKIES_BY_ROUTE[$request->route()?->getName() ?? ''] ?? null;

        return $viewCookie !== null && $request->cookies->get($viewCookie) === ViewPreference::LIST;
    }

    public function handle(Request $request, Closure $next, string ...$tags): Response
    {
        $response = $next($request);
        $ttl = (int) config('fvn.public_page_cache_ttl');

        if (! self::appliesTo($request) || $ttl <= 0 || $request->inertia() || ! $this->isCacheable($response)) {
            return $response;
        }

        if ($tags !== []) {
            $response->headers->set('Cache-Tag', implode(',', $this->resolveTags($request, $tags)));

            if (EdgeCache::isEnabled()) {
                $ttl = (int) config('fvn.edge_cache_ttl');
            }
        }

        $response->headers->set('Cache-Control', "public, max-age=0, s-maxage={$ttl}");
        $response->setEtag(hash('xxh128', $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function resolveTags(Request $request, array $tags): array
    {
        return array_map(fn (string $tag): string => $tag === 'game' ? EdgeCache::game($request->route('game')->id) : $tag, $tags);
    }

    private function isCacheable(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && is_string($response->getContent())
            && $response->headers->getCookies() === []
            && Cookie::getQueuedCookies() === [];
    }
}
