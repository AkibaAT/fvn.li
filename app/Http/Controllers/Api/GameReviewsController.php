<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameReviewsController extends Controller
{
    public function index(Request $request, Game $game): JsonResponse
    {
        $reviewsPerPage = in_array($request->perPage, [5, 10, 25]) ? $request->perPage : 5;
        $showAllRatings = $request->boolean('showAllRatings');
        $selectedRating = $request->input('selectedRating');

        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->when(! $showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->when($selectedRating, fn ($query) => $query->where('rating', $selectedRating))
            ->with('rater')
            ->orderByDesc('published_at')
            ->paginate($reviewsPerPage);

        return response()->json($reviews);
    }
}
