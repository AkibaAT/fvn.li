<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class IconVersion
{
    public const CACHE_KEY = 'app.icon-version';

    /**
     * Cache-busting version for the site icons, derived from their mtimes.
     * Cached until the deploy script forgets the key.
     */
    public static function get(): int
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): int => max(
            (int) filemtime(public_path('favicon.ico')),
            (int) filemtime(public_path('icon-192.png')),
            (int) filemtime(public_path('apple-touch-icon.png')),
        ));
    }
}
