<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Rating;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserReviewController extends Controller
{
    public function store(Request $request, int $gameId): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:10000',
            'has_spoilers' => 'boolean',
        ]);

        $game = Game::findOrFail($gameId);
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($user->is_review_banned) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to submit reviews.',
            ], 403);
        }

        $reviewText = $request->input('review') ?? '';
        $sanitizer = app(HtmlSanitizerService::class);

        // Strip image/media tags; reviews don't support image uploads
        $reviewText = preg_replace('/<img[^>]*>/i', '', $reviewText);
        $reviewText = preg_replace('/<video[^>]*>.*?<\/video>/is', '', $reviewText);
        $reviewText = preg_replace('/<audio[^>]*>.*?<\/audio>/is', '', $reviewText);
        $reviewText = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $reviewText);
        $reviewText = preg_replace('/<embed[^>]*>/i', '', $reviewText);
        $reviewText = preg_replace('/<object[^>]*>.*?<\/object>/is', '', $reviewText);
        // Strip script/style tags for safety
        $reviewText = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $reviewText);
        $reviewText = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $reviewText);
        $reviewText = $sanitizer->sanitizeFvnReview($reviewText) ?? '';

        $hasReviewText = ! empty(trim(strip_tags($reviewText)));

        $rating = Rating::updateOrCreate(
            [
                'user_id' => $user->id,
                'game_id' => $game->id,
            ],
            [
                'rating' => $request->input('rating'),
                'review' => $reviewText,
                'has_spoilers' => $request->boolean('has_spoilers', false),
                'is_visible' => true,
                'is_reviewed' => $hasReviewText,
                'source_platform' => 'fvn_li',
                'published_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $rating->wasRecentlyCreated ? 'Review submitted.' : 'Review updated.',
            'review' => $this->formatUserReview($rating),
        ]);
    }

    public function show(int $gameId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $rating = Rating::where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->first();

        if (! $rating) {
            return response()->json([
                'success' => true,
                'review' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'review' => $this->formatUserReview($rating),
        ]);
    }

    public function destroy(int $gameId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $rating = Rating::where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->first();

        if (! $rating) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $rating->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted.',
        ]);
    }

    private function formatUserReview(Rating $rating): array
    {
        $sanitizer = app(HtmlSanitizerService::class);

        return [
            'id' => $rating->id,
            'rating' => $rating->rating,
            'review' => $sanitizer->sanitizeFvnReview($rating->review),
            'has_spoilers' => $rating->has_spoilers,
            'published_at' => $rating->published_at?->toISOString(),
            'updated_at' => $rating->updated_at?->toISOString(),
        ];
    }
}
