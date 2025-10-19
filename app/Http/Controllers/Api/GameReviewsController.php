<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Rating;
use App\Services\ItchAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GameReviewsController extends Controller
{
    public function __construct(
        private readonly ItchAuthService $itchAuthService
    ) {}

    /**
     * Get review data for a game by URL or game ID.
     * This endpoint is designed for the desktop client to fetch review information.
     */
    public function getGameReviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'nullable|string|url',
            'game_id' => 'nullable|integer|min:1',
            'itch_game_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Ensure at least one identifier is provided
        if (! $request->filled('url') && ! $request->filled('game_id') && ! $request->filled('itch_game_id')) {
            return response()->json([
                'error' => 'At least one of url, game_id, or itch_game_id must be provided',
            ], 422);
        }

        try {
            $game = $this->findGame($request);

            if (! $game) {
                return response()->json([
                    'error' => 'Game not found',
                    'has_reviews' => false,
                    'review_data' => null,
                ], 404);
            }

            // Check cache first (6 hour cache)
            $cacheKey = "game_reviews_{$game->id}";
            $reviewData = Cache::remember($cacheKey, 6 * 60 * 60, function () use ($game) {
                return $this->buildReviewData($game);
            });

            return response()->json([
                'success' => true,
                'has_reviews' => $reviewData['total_reviews'] > 0,
                'review_data' => $reviewData,
                'game' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'url' => $game->url,
                    'slug' => $game->slug,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching game reviews', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'has_reviews' => false,
                'review_data' => null,
            ], 500);
        }
    }

    /**
     * Get paginated reviews for a game with filtering options.
     * This endpoint supports endless scrolling and filtering by rating.
     */
    public function getPaginatedReviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'nullable|string|url',
            'game_id' => 'nullable|integer|min:1',
            'itch_game_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'rating_filter' => 'nullable|integer|min:1|max:5',
            'show_all_ratings' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Ensure at least one identifier is provided
        if (! $request->filled('url') && ! $request->filled('game_id') && ! $request->filled('itch_game_id')) {
            return response()->json([
                'error' => 'At least one of url, game_id, or itch_game_id must be provided',
            ], 422);
        }

        try {
            $game = $this->findGame($request);

            if (! $game) {
                return response()->json([
                    'error' => 'Game not found',
                ], 404);
            }

            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            $ratingFilter = $request->input('rating_filter');
            $showAllRatings = filter_var($request->input('show_all_ratings', false), FILTER_VALIDATE_BOOLEAN);

            // Build the query
            $query = Rating::where('game_id', $game->id)
                ->with(['rater']);

            // Apply rating filter
            if ($ratingFilter !== null) {
                $query->where('rating', $ratingFilter);
            }

            // Apply review filter (show only reviews vs all ratings)
            if (! $showAllRatings) {
                $query->where('is_reviewed', true);
            }

            // Order by published date (newest first)
            $query->orderBy('published_at', 'desc');

            // Get paginated results
            $ratings = $query->paginate($perPage, ['*'], 'page', $page);

            // Format the reviews
            $reviews = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'review' => $rating->review,
                    'is_reviewed' => $rating->is_reviewed,
                    'published_at' => $rating->published_at->toISOString(),
                    'rater' => [
                        'id' => $rating->rater->id,
                        'name' => $rating->rater->name,
                        'platform' => $rating->rater->external_platform ?? 'itch_io',
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'reviews' => $reviews,
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total(),
                    'last_page' => $ratings->lastPage(),
                    'has_more' => $ratings->hasMorePages(),
                ],
                'filters' => [
                    'rating_filter' => $ratingFilter,
                    'show_all_ratings' => $showAllRatings,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching paginated reviews', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Find a game based on the provided identifiers.
     */
    private function findGame(Request $request): ?Game
    {
        // Try by internal game ID first (most direct)
        if ($request->filled('game_id')) {
            $game = Game::where('id', $request->input('game_id'))
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }
        }

        // Try by itch.io game ID
        if ($request->filled('itch_game_id')) {
            $game = Game::where('itch_id', $request->input('itch_game_id'))
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }
        }

        // Try by URL (most complex, handles various URL formats)
        if ($request->filled('url')) {
            $url = $request->input('url');

            // First try direct URL match
            $game = Game::byUrl($url)
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }

            // Try to extract itch.io game ID from URL and find by that
            try {
                $itchGameId = $this->itchAuthService->getGameId($url);
                $game = Game::where('itch_id', $itchGameId)
                    ->fromItchio()
                    ->first();
                if ($game) {
                    return $game;
                }
            } catch (Exception $e) {
                Log::debug('Could not extract game ID from URL', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            // Try normalized URL matching (similar to AdditionRequest logic)
            $normalizedUrl = $this->normalizeUrl($url);
            $game = Game::where(function ($query) use ($url, $normalizedUrl) {
                $query->byUrl($url)
                    ->orByUrl('https://' . $normalizedUrl)
                    ->orByUrl('http://' . $normalizedUrl)
                    ->orByUrl('https://www.' . $normalizedUrl)
                    ->orByUrl('http://www.' . $normalizedUrl);
            })
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }
        }

        return null;
    }

    /**
     * Normalize a URL for matching (similar to AdditionRequest logic).
     */
    private function normalizeUrl(string $url): string
    {
        // Remove protocol, www, trailing slashes, and query parameters
        $normalized = preg_replace('/^https?:\/\/(www\.)?/', '', $url);
        $normalized = rtrim($normalized, '/');
        $normalized = strtok($normalized, '?'); // Remove query parameters

        return strtolower($normalized);
    }

    /**
     * Build review data for a game.
     */
    private function buildReviewData(Game $game): array
    {
        // Get all ratings for this game
        $ratings = Rating::where('game_id', $game->id)
            ->with(['rater:id,name'])
            ->orderBy('published_at', 'desc')
            ->get();

        $totalReviews = $ratings->count();

        if ($totalReviews === 0) {
            return [
                'total_reviews' => 0,
                'average_rating' => null,
                'rating_distribution' => [],
                'recent_reviews' => [],
            ];
        }

        // Calculate average rating
        $averageRating = round($ratings->avg('rating'), 2);

        // Calculate rating distribution
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $ratings->where('rating', $i)->count();
            $distribution[$i] = [
                'count' => $count,
                'percentage' => $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0,
            ];
        }

        // Get recent reviews (limit to 5 for desktop client)
        $recentReviews = $ratings->where('is_reviewed', true)
            ->take(5)
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'review' => $rating->review,
                    'published_at' => $rating->published_at->toISOString(),
                    'rater' => [
                        'id' => $rating->rater->id,
                        'name' => $rating->rater->name,
                        'platform' => $rating->rater->external_platform ?? 'itch_io',
                    ],
                ];
            })
            ->values();

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => $averageRating,
            'rating_distribution' => $distribution,
            'recent_reviews' => $recentReviews,
        ];
    }
}
