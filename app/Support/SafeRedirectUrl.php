<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

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

        $parsed = Url::parse($url);
        $host = $parsed?->getAsciiHost();
        $scheme = $parsed?->getScheme();
        if (! is_string($host) || $host === '' || $scheme !== 'https') {
            self::logRejected($url, $request, $host, $scheme);

            return null;
        }

        $appHost = Url::parse((string) config('app.url'))?->getAsciiHost();
        $allowedHosts = array_filter(array_unique([
            $request?->getHost(),
            $appHost,
        ]));

        if (! in_array(strtolower($host), array_map('strtolower', $allowedHosts), true)) {
            self::logRejected($url, $request, $host, $scheme);

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

    private static function logRejected(string $url, ?Request $request, ?string $host = null, ?string $scheme = null): void
    {
        Log::warning('Ignoring unsafe intended redirect URL', [
            'route' => $request?->route()?->getName(),
            'host' => $host ?? parse_url($url, PHP_URL_HOST),
            'scheme' => $scheme ?? parse_url($url, PHP_URL_SCHEME),
        ]);
    }
}
