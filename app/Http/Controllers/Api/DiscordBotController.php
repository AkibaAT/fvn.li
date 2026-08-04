<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscordBotController extends Controller
{
    public function searchGames(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $name = $request->input('name');
        $baseUrl = rtrim(config('app.url'), '/');

        $games = Game::query()
            ->fromItchio()
            ->with('latestVersion')
            ->where('is_visible', true)
            ->where('name', 'ilike', "%{$name}%")
            ->limit(10)
            ->get();

        return response()->json([
            'matches' => $games->count(),
            'search_url' => $baseUrl . '/games/search?q=' . urlencode($name),
            'games' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->latestVersion?->version,
                'url' => $baseUrl . '/games/' . $game->slug,
                'primary_url' => $game->getPrimaryUrl(),
                'english_word_count' => $game->english_word_count,
                'published_at' => $game->latestVersion?->published_at ? $game->latestVersion->published_at->timestamp : null,
            ]),
        ]);
    }

    /**
     * Look up a game by URL (for bot migration/mapping)
     */
    public function findByUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $url = $request->input('url');
        $normalizedUrl = $this->normalizeUrl($url);

        $game = Game::query()
            ->where('is_visible', true)
            ->where(function ($query) use ($url, $normalizedUrl) {
                // Search in JSONB url field for all platforms
                $query->whereRaw("url->>'itch_io' = ?", [$url])
                    ->orWhereRaw("url->>'steam' = ?", [$url])
                    ->orWhereRaw("url->>'other' = ?", [$url])
                    // Also try normalized versions
                    ->orWhereRaw("LOWER(url->>'itch_io') = ?", [$normalizedUrl])
                    ->orWhereRaw("LOWER(url->>'steam') = ?", [$normalizedUrl])
                    ->orWhereRaw("LOWER(url->>'other') = ?", [$normalizedUrl]);
            })
            ->first();

        // If no exact match, try matching by URL slug
        if (! $game) {
            $slug = $this->extractSlugFromUrl($url);
            if ($slug) {
                $game = Game::query()
                    ->where('is_visible', true)
                    ->where(function ($query) use ($slug) {
                        $query->whereRaw("url->>'itch_io' ILIKE ?", ["%/{$slug}"])
                            ->orWhere('slug', $slug);
                    })
                    ->first();
            }
        }

        if (! $game) {
            return response()->json([
                'found' => false,
                'message' => 'No matching game found',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'game' => [
                'id' => $game->id,
                'itch_id' => $game->itch_id,
                'steam_app_id' => $game->steam_app_id,
                'name' => $game->name,
                'slug' => $game->slug,
                'url' => $game->url,
                'platform' => $game->platform,
            ],
        ]);
    }

    /**
     * Look up a game by ID
     */
    public function getGame(int $id): JsonResponse
    {
        $game = Game::query()
            ->with('latestVersion')
            ->where('is_visible', true)
            ->find($id);

        if (! $game) {
            return response()->json([
                'found' => false,
                'message' => 'Game not found',
            ], 404);
        }

        $baseUrl = rtrim(config('app.url'), '/');

        return response()->json([
            'found' => true,
            'game' => [
                'id' => $game->id,
                'itch_id' => $game->itch_id,
                'steam_app_id' => $game->steam_app_id,
                'name' => $game->name,
                'slug' => $game->slug,
                'description' => $game->description,
                'url' => $game->url,
                'platform' => $game->platform,
                'status' => $game->status,
                'thumb_url' => $game->getThumbnailUrl(),
                'fvn_li_url' => $baseUrl . '/games/' . $game->slug,
                'latest_version' => $game->latestVersion ? [
                    'version' => $game->latestVersion->version,
                    'published_at' => $game->latestVersion->published_at?->toIso8601String(),
                ] : null,
                'english_word_count' => $game->english_word_count,
                'abbreviations' => $game->abbreviations,
                'discord_tags' => $game->discord_tags,
            ],
        ]);
    }

    /**
     * Bulk lookup games by URLs (for migration)
     */
    public function bulkFindByUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'urls' => 'required|array|max:100',
            'urls.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $urls = $request->input('urls');
        $results = [];

        foreach ($urls as $url) {
            $normalizedUrl = $this->normalizeUrl($url);
            $slug = $this->extractSlugFromUrl($url);

            $game = Game::query()
                ->where('is_visible', true)
                ->where(function ($query) use ($url, $normalizedUrl) {
                    $query->whereRaw("url->>'itch_io' = ?", [$url])
                        ->orWhereRaw("url->>'steam' = ?", [$url])
                        ->orWhereRaw("url->>'other' = ?", [$url])
                        ->orWhereRaw("LOWER(url->>'itch_io') = ?", [$normalizedUrl])
                        ->orWhereRaw("LOWER(url->>'steam') = ?", [$normalizedUrl])
                        ->orWhereRaw("LOWER(url->>'other') = ?", [$normalizedUrl]);
                })
                ->first();

            if (! $game && $slug) {
                $game = Game::query()
                    ->where('is_visible', true)
                    ->where(function ($query) use ($slug) {
                        $query->whereRaw("url->>'itch_io' ILIKE ?", ["%/{$slug}"])
                            ->orWhere('slug', $slug);
                    })
                    ->first();
            }

            $results[$url] = $game ? [
                'found' => true,
                'matched_by' => $game->slug === $slug ? 'slug' : 'url',
                'game' => [
                    'id' => $game->id,
                    'itch_id' => $game->itch_id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                ],
            ] : [
                'found' => false,
            ];
        }

        return response()->json([
            'results' => $results,
            'matched' => collect($results)->where('found', true)->count(),
            'unmatched' => collect($results)->where('found', false)->count(),
        ]);
    }

    private function normalizeUrl(string $url): string
    {
        $url = preg_replace('#^https?://#', '', $url);
        $url = rtrim($url, '/');

        return strtolower($url);
    }

    private function extractSlugFromUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (! isset($parsed['path'])) {
            return null;
        }

        $path = trim($parsed['path'], '/');
        if (empty($path)) {
            return null;
        }

        $parts = explode('/', $path);

        return end($parts) ?: null;
    }
}
