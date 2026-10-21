<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClickStat;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GamePageViewController extends Controller
{
    public function __invoke(Request $request, Game $game): Response
    {
        $validated = $request->validate([
            'visitor_id' => ['required', 'uuid'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        abort_unless($game->is_visible, 404);

        ClickStat::recordClick(
            gameId: $game->id,
            type: ClickStat::TYPE_PAGE_VIEW,
            sessionId: $validated['visitor_id'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            referrer: $validated['referrer'] ?? null,
        );

        return response()->noContent();
    }
}
