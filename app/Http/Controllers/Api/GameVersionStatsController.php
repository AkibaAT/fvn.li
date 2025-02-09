<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GameVersionStatsController extends Controller
{
    public function versionHistory(Request $request, Game $game): JsonResponse
    {
        try {
            $versionsPerPage = in_array($request->perPage, [5, 10, 25]) ? $request->perPage : 5;

            $versions = $game->gameVersions()
                ->with([
                    'supportedLanguages.language',
                    'languageStats.language',
                ])
                ->paginate($versionsPerPage);

            // Transform to match the expected format
            $versions->through(fn ($version) => [
                'id' => $version->id,
                'version' => $version->version,
                'published_at' => $version->published_at,
                'rating' => $version->rating,
                'rating_count' => $version->rating_count,
                'is_windows' => $version->is_windows,
                'is_linux' => $version->is_linux,
                'is_mac' => $version->is_mac,
                'is_android' => $version->is_android,
                'is_web' => $version->is_web,
                'english_stats' => $version->getStatsForLanguage('eng'),
                'supported_languages' => $version->supportedLanguages->map(fn ($sl) => [
                    'iso_code' => $sl->iso_code,
                    'ref_name' => $sl->language->ref_name,
                    'flag_code' => $sl->language->flag_code,
                ]),
                'file_categories' => $version->fileCategories()->exists(),
            ]);

            return response()->json($versions);
        } catch (Exception $e) {
            Log::error('Error getting version history', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Error getting version history'], 500);
        }
    }

    public function characterStats(Game $game, int $version): JsonResponse
    {
        try {
            // Find the version and verify it belongs to this game
            $gameVersion = $game->gameVersions()->findOrFail($version);

            // Get all character stats with relationships
            $characterStats = $gameVersion->characterStatsWithoutPlaceholders()
                ->with(['character', 'language'])
                ->get();

            // Get unique languages
            $languages = $characterStats
                ->sortBy('language.ref_name')
                ->unique('language.id')
                ->values()
                ->map(fn ($stat) => [
                    'id' => $stat->language->id,
                    'name' => $stat->language->ref_name,
                    'flag' => $stat->language->flag_code,
                ]);

            // Create word count matrix (character x language)
            $characters = [];
            $wordCounts = [];
            foreach ($characterStats as $stat) {
                $displayName = $stat->character->getDisplayName($game->source_language_id);
                $characters[$displayName] = $displayName;
                if (! isset($wordCounts[$displayName][$stat->language->id])) {
                    $wordCounts[$displayName][$stat->language->id] = 0;
                }
                $wordCounts[$displayName][$stat->language->id] += $stat->words;
            }
            sort($characters, SORT_NATURAL | SORT_FLAG_CASE);

            // Calculate totals per language
            $languageTotals = [];
            foreach ($characterStats as $stat) {
                if (! isset($languageTotals[$stat->language->id])) {
                    $languageTotals[$stat->language->id] = 0;
                }
                $languageTotals[$stat->language->id] += $stat->words;
            }

            return response()->json([
                'characters' => $characters,
                'languages' => $languages,
                'wordCounts' => $wordCounts,
                'languageTotals' => $languageTotals,
            ]);
        } catch (Exception $e) {
            Log::error('Error getting character stats', [
                'game_id' => $game->id,
                'version_id' => $version,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Error getting character stats'], 500);
        }
    }

    public function fileStats(Game $game, int $version): JsonResponse
    {
        try {
            // Find the version and verify it belongs to this game
            $gameVersion = $game->gameVersions()->findOrFail($version);

            $fileCategories = $gameVersion->fileCategories()
                ->with(['fileTypes' => function ($query) {
                    $query->orderBy('extension');
                }])
                ->get()
                ->map(fn ($category) => [
                    'category' => $category->category,
                    'total_count' => $category->total_count,
                    'total_size' => $category->total_size,
                    'file_types' => $category->fileTypes->map(fn ($type) => [
                        'extension' => $type->extension,
                        'count' => $type->count,
                        'size' => $type->size,
                    ]),
                ]);

            return response()->json($fileCategories);
        } catch (Exception $e) {
            Log::error('Error getting file stats', [
                'game_id' => $game->id,
                'version_id' => $version,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Error getting file stats'], 500);
        }
    }
}
