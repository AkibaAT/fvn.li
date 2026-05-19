<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Games\GamesVersionController;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardVersionComparisonController extends Controller
{
    public function getVersionComparison(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'gameId' => ['required', 'exists:games,id'],
            'fromVersionId' => ['required', 'exists:game_versions,id'],
            'toVersionId' => ['required', 'exists:game_versions,id'],
        ]);

        // Get the game and verify user has access to it
        $game = Game::findOrFail($request->gameId);

        // Check if user has this game in their lists or has rated it
        $user = User::findOrFail($authId);
        $hasAccess = $user->vnLists()
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            })
            ->exists() ||
            $user->ratings()
                ->where('game_id', $game->id)
                ->where('is_visible', true)
                ->exists();

        if (! $hasAccess) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this game'], 403);
        }

        // Use the existing GamesVersionController method to get the comparison data
        $versionController = app(GamesVersionController::class);
        $comparisonData = $versionController->compareVersions($request, $game);

        return $comparisonData;
    }
}
