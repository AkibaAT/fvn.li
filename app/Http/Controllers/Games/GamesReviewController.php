<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamesReviewController extends Controller
{
    /**
     * Get reviews for a specific game
     */
    public function getGameReviews(Request $request, $gameId): JsonResponse
    {
        $request->validate([
            'showAllRatings' => 'sometimes|in:true,false,1,0',
            'selectedRating' => 'nullable|integer|min:1|max:5',
            'page' => 'integer|min:1',
            'perPage' => 'integer|min:1|max:50',
        ]);

        $game = Game::findOrFail($gameId);

        $showAllRatingsParam = $request->input('showAllRatings', 'false');
        $showAllRatings = in_array($showAllRatingsParam, ['true', '1', 1, true], true);
        $selectedRating = $request->input('selectedRating');
        $perPage = $request->input('perPage', 5);

        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->when(! $showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->when($selectedRating !== null, fn ($query) => $query->where('rating', $selectedRating))
            ->with(['rater', 'user:id,name,avatar'])
            ->orderByDesc('published_at')
            ->paginate($perPage);

        $availableRatings = $game->ratings()
            ->where('is_visible', true)
            ->when(! $showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->distinct()
            ->pluck('rating')
            ->sort()
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'availableRatings' => $availableRatings,
        ]);
    }
}
