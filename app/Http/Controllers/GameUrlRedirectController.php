<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GameUrlRedirectController extends Controller
{
    public function __invoke(Request $request, string $url): RedirectResponse
    {
        $game = Game::byUrl($url)->first();

        if (! $game) {
            $decodedUrl = urldecode($url);
            $game = Game::byUrl($decodedUrl)->first();
        }

        if (! $game && str_starts_with($url, 'https:/') && ! str_starts_with($url, 'https://')) {
            $fixedUrl = str_replace('https:/', 'https://', $url);
            $game = Game::byUrl($fixedUrl)->first();
        }

        if (! $game) {
            $fullPath = $request->getPathInfo();
            $urlPart = substr($fullPath, strlen('/by-url/'));
            $game = Game::byUrl($urlPart)->first();
        }

        if (! $game) {
            abort(404);
        }

        return redirect(status: 301)->route('games.show', $game);
    }
}
