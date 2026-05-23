<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordUser;
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
            'search_url' => $baseUrl.'/games/search?q='.urlencode($name),
            'games' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->latestVersion?->version,
                'url' => $baseUrl.'/games/'.$game->slug,
                'primary_url' => $game->getPrimaryUrl(),
                'english_word_count' => $game->english_word_count,
                'published_at' => $game->latestVersion?->published_at ? $game->latestVersion->published_at->timestamp : null,
            ]),
        ]);
    }

    public function getUpdates(): JsonResponse
    {
        // Get all subscribed users that need updates
        $users = DiscordUser::query()
            ->where('processed_at', '<', now()->subMinutes(25)) // Give some buffer before 30min mark
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['updates' => []]);
        }

        // Get games updated since last processed timestamp
        $games = Game::query()
            ->with('latestVersion')
            ->where('is_visible', true)
            ->whereHas('latestVersion', function ($query) use ($users) {
                $query->where('created_at', '>', $users->min('processed_at'));
            })
            ->orderBy('name')
            ->get();

        // Update processed_at timestamp for all fetched users
        DiscordUser::whereIn('id', $users->pluck('id'))
            ->update(['processed_at' => now()]);

        return response()->json([
            'discord_users' => $users->pluck('discord_id'),
            'updates' => $games->map(fn ($game) => [
                'name' => $game->name,
                'version' => $game->latestVersion?->version,
                'published_at' => $game->latestVersion?->published_at ? $game->latestVersion->published_at->timestamp : null,
                'url' => $game->url, // Multi-platform URLs as JSONB object
                'devlog' => $game->latestVersion?->devlog,
            ]),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discord_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = DiscordUser::firstOrNew([
            'discord_id' => $request->input('discord_id'),
        ]);

        if (! $user->exists) {
            $user->processed_at = now();
            $user->save();

            return response()->json(['message' => 'Subscribed successfully']);
        }

        return response()->json(['message' => 'Already subscribed']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discord_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $deleted = DiscordUser::where('discord_id', $request->input('discord_id'))->delete();

        return response()->json([
            'message' => $deleted ? 'Unsubscribed successfully' : 'Not subscribed',
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

        // Try exact match first
        $game = Game::query()
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
        $game = Game::with('latestVersion')->find($id);

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
                'fvn_li_url' => $baseUrl.'/games/'.$game->slug,
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
                ->where(function ($query) use ($url, $normalizedUrl) {
                    $query->whereRaw("url->>'itch_io' = ?", [$url])
                        ->orWhereRaw("url->>'steam' = ?", [$url])
                        ->orWhereRaw("url->>'other' = ?", [$url])
                        ->orWhereRaw("LOWER(url->>'itch_io') = ?", [$normalizedUrl])
                        ->orWhereRaw("LOWER(url->>'steam') = ?", [$normalizedUrl])
                        ->orWhereRaw("LOWER(url->>'other') = ?", [$normalizedUrl]);
                })
                ->first();

            // Try slug match if no URL match
            if (! $game && $slug) {
                $game = Game::query()
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
