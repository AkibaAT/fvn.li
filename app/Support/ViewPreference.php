<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ViewPreference
{
    public const HOME_COOKIE = 'home_view';

    public const GAMES_COOKIE = 'games_view';

    public const GRID = 'grid';

    public const LIST = 'list';

    public static function mode(string $cookie, ?Request $request = null): string
    {
        $request ??= request();

        return $request->cookie($cookie) === self::LIST ? self::LIST : self::GRID;
    }
}
