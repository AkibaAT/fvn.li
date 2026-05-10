<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SafeRedirectUrl
{
    public static function intended(?string $url, ?Request $request = null): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (preg_match('/[\r\n]/', $url)) {
            return null;
        }

        if (Str::startsWith($url, '/') && ! Str::startsWith($url, ['//', '/\\'])) {
            return url($url);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($host) || ! is_string($scheme) || strtolower($scheme) !== 'https') {
            self::logRejected($url, $request);

            return null;
        }

        $allowedHosts = array_filter(array_unique([
            $request?->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ]));

        if (! in_array(strtolower($host), array_map('strtolower', $allowedHosts), true)) {
            self::logRejected($url, $request);

            return null;
        }

        return $url;
    }

    public static function intendedOrDefault(?string $url, string $default, ?Request $request = null): string
    {
        $safeUrl = self::intended($url, $request);
        if ($safeUrl === null || str_contains($safeUrl, route('login'))) {
            return $default;
        }

        return $safeUrl;
    }

    private static function logRejected(string $url, ?Request $request): void
    {
        Log::warning('Ignoring unsafe intended redirect URL', [
            'route' => $request?->route()?->getName(),
            'host' => parse_url($url, PHP_URL_HOST),
            'scheme' => parse_url($url, PHP_URL_SCHEME),
        ]);
    }
}
