<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameIsVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');
        $game = $game instanceof Game ? $game : Game::find($game);

        abort_unless($game?->is_visible, 404);

        return $next($request);
    }
}
