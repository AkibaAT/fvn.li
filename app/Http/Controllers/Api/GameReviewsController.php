<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Rating;
use App\Services\HtmlSanitizerService;
use App\Services\ItchAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GameReviewsController extends Controller
{
    private const int REVIEW_CACHE_VERSION = 2;

    public function __construct(
        private readonly ItchAuthService $itchAuthService
    ) {}

    public function getGameReviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'nullable|string|url:http,https',
            'game_id' => 'nullable|integer|min:1',
            'itch_game_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

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

            $cacheKey = sprintf('game_reviews_v%d_%d', self::REVIEW_CACHE_VERSION, $game->id);
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

    public function getPaginatedReviews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'nullable|string|url:http,https',
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

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);
            $ratingFilter = $request->input('rating_filter') !== null ? (int) $request->input('rating_filter') : null;
            $showAllRatings = filter_var($request->input('show_all_ratings', false), FILTER_VALIDATE_BOOLEAN);

            $query = Rating::where('game_id', $game->id)
                ->where('is_visible', true)
                ->with(['rater']);

            if ($ratingFilter !== null) {
                $query->where('rating', $ratingFilter);
            }

            if (! $showAllRatings) {
                $query->where('is_reviewed', true);
            }

            // Order by published date (newest first)
            $query->orderBy('published_at', 'desc');

            $ratings = $query->paginate($perPage, ['*'], 'page', $page);

            $sanitizer = app(HtmlSanitizerService::class);

            $reviews = $ratings->map(function ($rating) use ($sanitizer) {
                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'review' => $sanitizer->sanitizeReview($rating->review),
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

    private function findGame(Request $request): ?Game
    {
        if ($request->filled('game_id')) {
            $game = Game::where('id', $request->input('game_id'))
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }
        }

        if ($request->filled('itch_game_id')) {
            $game = Game::where('itch_id', $request->input('itch_game_id'))
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }
        }

        if ($request->filled('url')) {
            $url = $request->input('url');

            $game = Game::byUrl($url)
                ->fromItchio()
                ->first();
            if ($game) {
                return $game;
            }

            // This performs an outbound HTTP request, so keep it restricted to HTTPS itch.io hosts.
            if ($this->isAllowedItchUrl($url)) {
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
            }

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

    private function isAllowedItchUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        return $host === 'itch.io' || str_ends_with($host, '.itch.io');
    }

    /**
     * Normalize a URL for matching (similar to AdditionRequest logic).
     */
    private function normalizeUrl(string $url): string
    {
        $normalized = preg_replace('/^https?:\/\/(www\.)?/', '', $url);
        $normalized = strtok($normalized, '?'); // Remove query parameters
        $normalized = rtrim($normalized, '/');

        return strtolower($normalized);
    }

    private function buildReviewData(Game $game): array
    {
        // are exposed only through explicit history flows, not broad review APIs.
        $ratings = Rating::where('game_id', $game->id)
            ->where('is_visible', true)
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

        $averageRating = round($ratings->avg('rating'), 2);

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $ratings->where('rating', $i)->count();
            $distribution[$i] = [
                'count' => $count,
                'percentage' => $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0,
            ];
        }

        $sanitizer = app(HtmlSanitizerService::class);

        $recentReviews = $ratings->where('is_reviewed', true)
            ->take(5)
            ->map(function ($rating) use ($sanitizer) {
                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'review' => $sanitizer->sanitizeReview($rating->review),
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
