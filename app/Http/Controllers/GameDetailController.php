<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameDetailController extends Controller
{
    public function __invoke(Request $request, string $identifier): Response
    {
        // Determine if we're looking up by slug or game_id
        $game = is_numeric($identifier)
            ? Game::where('game_id', $identifier)->firstOrFail()
            : Game::where('slug', $identifier)->firstOrFail();

        // Eager load all the relationships we need
        $game->load([
            'latestVersion.languageStats.language',
            'latestVersion.supportedLanguages.language',
        ]);

        // Get reviews with pagination
        $reviewsPerPage = in_array($request->reviewsPerPage, [5, 10, 25]) ? $request->reviewsPerPage : 5;
        $reviews = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->with('rater')
            ->orderByDesc('published_at')
            ->paginate($reviewsPerPage);

        // Get available ratings
        $availableRatings = $game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->distinct()
            ->pluck('rating')
            ->sort()
            ->values();

        // Paginate game versions
        $versionsPerPage = in_array($request->versionsPerPage, [5, 10, 25]) ? $request->versionsPerPage : 5;
        $versions = $game->gameVersions()
            ->with([
                'supportedLanguages.language',
                'languageStats.language',
            ])
            ->paginate(10);

        $latestVersion = $game->latestVersion;
        $supportedLanguages = $latestVersion?->supportedLanguages
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'ref_name' => $sl->language->ref_name,
                'flag_code' => $sl->language->flag_code,
            ])
            ?? collect();

        // Calculate character counts for each version
        $versionCharacterCounts = [];
        if ($latestVersion) {
            $versionCharacterCounts[$latestVersion->id] = Character::countUniqueCharactersInLanguage(
                $game->id,
                $game->source_language_id,
                $latestVersion->id
            );
        }
        foreach ($versions as $version) {
            if ($version->id === $latestVersion?->id) {
                continue;
            }
            $versionCharacterCounts[$version->id] = Character::countUniqueCharactersInLanguage(
                $game->id,
                $game->source_language_id,
                $version->id
            );
        }

        return Inertia::render('GameDetail', [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'url' => $game->url,
                'thumb_url' => $game->thumb_url,
                'authors' => $game->authors,
                'description' => $game->description,
                'status' => $game->status,
                'game_engine' => $game->game_engine,
                'is_nsfw' => $game->is_nsfw,
                'is_visible' => $game->is_visible,
                'initially_published_at' => $game->initially_published_at,
                'tags' => $game->tags,
                'custom_tags' => $game->custom_tags,
            ],
            'reviews' => $reviews,
            'versions' => $versions,
            'latestVersion' => $latestVersion ? [
                'id' => $latestVersion->id,
                'version' => $latestVersion->version,
                'published_at' => $latestVersion->published_at,
                'rating' => $latestVersion->rating,
                'rating_count' => $latestVersion->rating_count,
                'devlog' => $latestVersion->devlog,
                'platforms' => [
                    'windows' => $latestVersion->is_windows,
                    'linux' => $latestVersion->is_linux,
                    'mac' => $latestVersion->is_mac,
                    'android' => $latestVersion->is_android,
                    'web' => $latestVersion->is_web,
                ],
            ] : null,
            'englishStats' => $latestVersion?->getStatsForLanguage('eng'),
            'supportedLanguages' => $supportedLanguages,
            'availableRatings' => $availableRatings,
            'versionCharacterCounts' => $versionCharacterCounts,
        ]);
    }
}
