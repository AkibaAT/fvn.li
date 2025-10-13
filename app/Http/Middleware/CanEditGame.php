<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CanEditGame
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // User must be authenticated
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Get the game from the route
        $game = $request->route('game');

        if (! $game instanceof Game) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found.',
            ], 404);
        }

        // Check if user can edit this game using the existing method
        if (! $game->canUserEdit($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this game.',
            ], 403);
        }

        return $next($request);
    }
}
