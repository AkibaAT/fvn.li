<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameVersion;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GamesVersionController extends Controller
{
    /**
     * Compare different versions of a game
     */
    public function compareVersions(Request $request, Game $game): JsonResponse
    {
        if (! $request->has('fromVersionId') || ! $request->has('toVersionId')) {
            return response()->json(['error' => 'Missing required parameters: fromVersionId and toVersionId'], 400);
        }

        $request->validate([
            'fromVersionId' => ['required', 'exists:game_versions,id'],
            'toVersionId' => ['required', 'exists:game_versions,id'],
        ]);

        $fromVersionId = $request->fromVersionId;
        $toVersionId = $request->toVersionId;

        // Cache key versioned to avoid serving stale payloads when response shape changes
        $cacheKey = "version_comparison_v2_{$game->id}_{$fromVersionId}_{$toVersionId}";
        $cachedResult = cache()->remember($cacheKey, 3600, function () use ($game, $fromVersionId, $toVersionId) {
            return $this->generateVersionComparison($game, $fromVersionId, $toVersionId);
        });

        return response()->json($cachedResult);
    }

    /**
     * Get all versions for a game
     */
    public function getGameVersions(Request $request, $gameId): JsonResponse
    {
        $game = Game::findOrFail($gameId);

        $versions = $game->gameVersions()
            ->with([
                'supportedLanguages.language',
                'languageStats.language',
            ])
            ->orderBy('published_at', 'desc')
            ->paginate($request->integer('perPage', 10));

        return response()->json([
            'success' => true,
            'versions' => $versions,
        ]);
    }

    /**
     * Get character statistics for a specific game version
     */
    public function getVersionCharacterStats(Game $game, GameVersion $version): JsonResponse
    {
        if ($version->game_id !== $game->id) {
            return response()->json(['error' => 'Version does not belong to this game'], 400);
        }

        $characterStats = $version->characterStats()
            ->with(['character', 'language'])
            ->orderBy('lines', 'desc')
            ->get();

        $groupedStats = $characterStats->groupBy('iso_code')->map(function ($stats, $isoCode) {
            $language = $stats->first()->language;

            return [
                'language' => [
                    'iso_code' => $isoCode,
                    'ref_name' => $language->ref_name,
                    'flag_code' => $language->flag_code,
                ],
                'characters' => $stats->map(function ($stat) {
                    return [
                        'character_id' => $stat->character_id,
                        'character_name' => $stat->character->name ?? 'Unknown',
                        'lines' => $stat->lines,
                        'words' => $stat->words,
                        'percentage' => $stat->percentage,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
            ],
            'version' => [
                'id' => $version->id,
                'version' => $version->version,
                'published_at' => $version->published_at,
            ],
            'character_stats' => $groupedStats,
        ]);
    }

    /**
     * Get file statistics for a specific game version
     */
    public function getVersionFileStats(Game $game, GameVersion $version): JsonResponse
    {
        if ($version->game_id !== $game->id) {
            return response()->json(['error' => 'Version does not belong to this game'], 400);
        }

        try {
            $fileStats = $version->fileStats()
                ->with('language')
                ->orderBy('iso_code')
                ->orderBy('filename')
                ->get();

            $groupedStats = $fileStats->groupBy('iso_code')->map(function ($stats, $isoCode) {
                $language = $stats->first()->language;

                return [
                    'language' => [
                        'iso_code' => $isoCode,
                        'ref_name' => $language?->ref_name ?? $isoCode,
                        'flag_code' => $language?->flag_code ?? strtolower(substr($isoCode, 0, 2)),
                    ],
                    'files' => $stats->map(function ($stat) {
                        return [
                            'filename' => $stat->filename,
                            'lines' => $stat->lines,
                            'words' => $stat->words,
                            'characters' => $stat->characters,
                            'size_bytes' => $stat->size_bytes,
                        ];
                    })->values(),
                    'totals' => [
                        'files' => $stats->count(),
                        'lines' => $stats->sum('lines'),
                        'words' => $stats->sum('words'),
                        'characters' => $stats->sum('characters'),
                        'size_bytes' => $stats->sum('size_bytes'),
                    ],
                ];
            })->values();

            return response()->json([
                'game' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                ],
                'version' => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'published_at' => $version->published_at,
                ],
                'file_stats' => $groupedStats,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving file stats', [
                'game_id' => $game->id,
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to retrieve file statistics',
            ], 500);
        }
    }

    /**
     * Generate version comparison data
     */
    private function generateVersionComparison(Game $game, $fromVersionId, $toVersionId): array
    {
        $fromVersion = GameVersion::find($fromVersionId);
        $toVersion = GameVersion::find($toVersionId);

        if (! $fromVersion || ! $toVersion) {
            throw new \Exception('Versions not found');
        }

        if ($fromVersion->game_id !== $game->id || $toVersion->game_id !== $game->id) {
            throw new \Exception('Versions do not belong to the specified game');
        }

        if ($fromVersion->published_at > $toVersion->published_at) {
            $temp = $fromVersion;
            $fromVersion = $toVersion;
            $toVersion = $temp;
        }

        $fromStats = $fromVersion->characterStats()
            ->whereIn('iso_code', function ($query) use ($fromVersion) {
                $query->select('iso_code')
                    ->from('version_supported_languages')
                    ->where('game_version_id', $fromVersion->id)
                    ->where('is_available', true);
            })
            ->where('iso_code', 'not like', 'q%')
            ->with(['character', 'language'])
            ->get();

        $toStats = $toVersion->characterStats()
            ->whereIn('iso_code', function ($query) use ($toVersion) {
                $query->select('iso_code')
                    ->from('version_supported_languages')
                    ->where('game_version_id', $toVersion->id)
                    ->where('is_available', true);
            })
            ->where('iso_code', 'not like', 'q%')
            ->with(['character', 'language'])
            ->get();

        $fromLanguages = $fromStats->pluck('language.id')->unique();
        $toLanguages = $toStats->pluck('language.id')->unique();
        $allLanguages = $fromLanguages->merge($toLanguages)->unique();

        $languages = [];
        foreach ($allLanguages as $langId) {
            $lang = null;
            if ($fromStats->where('language.id', $langId)->first()) {
                $lang = $fromStats->where('language.id', $langId)->first()->language;
            } elseif ($toStats->where('language.id', $langId)->first()) {
                $lang = $toStats->where('language.id', $langId)->first()->language;
            }
            if ($lang) {
                $languages[] = [
                    'id' => $lang->id,
                    'name' => $lang->ref_name,
                    'flag' => $lang->flag_code,
                ];
            }
        }

        $allCharacterNames = collect($fromStats->pluck('character')->concat($toStats->pluck('character')))
            ->unique('id')
            ->map(fn ($c) => $c->getDisplayName($game->source_language_id))
            ->unique()
            ->sort()
            ->values();

        $sortedCharacters = $allCharacterNames->toArray();

        $characterDiffs = [];
        // Track language totals for both versions to compute diffs
        $languageTotalsFrom = [];
        $languageTotalsTo = [];
        foreach ($sortedCharacters as $characterName) {
            $characterDiffs[$characterName] = [];
            foreach ($languages as $lang) {
                $iso = $lang['id'];
                $fromStat = $fromStats->first(function ($s) use ($characterName, $iso, $game) {
                    return $s->character->getDisplayName($game->source_language_id) === $characterName && $s->iso_code === $iso;
                });
                $toStat = $toStats->first(function ($s) use ($characterName, $iso, $game) {
                    return $s->character->getDisplayName($game->source_language_id) === $characterName && $s->iso_code === $iso;
                });
                $fromWords = $fromStat?->words ?? 0;
                $toWords = $toStat?->words ?? 0;
                $characterDiffs[$characterName][$iso] = [
                    'from' => $fromWords,
                    'to' => $toWords,
                    'diff' => $toWords - $fromWords,
                ];
                $languageTotalsFrom[$iso] = ($languageTotalsFrom[$iso] ?? 0) + $fromWords;
                $languageTotalsTo[$iso] = ($languageTotalsTo[$iso] ?? 0) + $toWords;
            }
        }
        // Build language totals with from/to/diff shape expected by frontend
        $languageTotals = [
            'from' => $languageTotalsFrom,
            'to' => $languageTotalsTo,
            'diff' => collect($languageTotalsTo)
                ->map(function ($toTotal, $iso) use ($languageTotalsFrom) {
                    return $toTotal - ($languageTotalsFrom[$iso] ?? 0);
                })
                ->toArray(),
        ];

        // Get actual categories and file types from both versions
        $fromCategories = $fromVersion->fileCategories()->with('fileTypes')->get();
        $toCategories = $toVersion->fileCategories()->with('fileTypes')->get();

        // Get all unique categories and extensions from both versions
        $allCategories = $fromCategories->pluck('category')
            ->merge($toCategories->pluck('category'))
            ->unique()
            ->sort()
            ->values();

        $allExtensions = $fromCategories->flatMap(fn ($cat) => $cat->fileTypes->pluck('extension'))
            ->merge($toCategories->flatMap(fn ($cat) => $cat->fileTypes->pluck('extension')))
            ->unique()
            ->sort()
            ->values();

        $fileCategoryComparisons = [];
        foreach ($allCategories as $category) {
            $fromCategory = $fromCategories->firstWhere('category', $category);
            $toCategory = $toCategories->firstWhere('category', $category);
            $fromCount = $fromCategory?->total_count ?? 0;
            $toCount = $toCategory?->total_count ?? 0;
            $fromSize = $fromCategory?->total_size ?? 0;
            $toSize = $toCategory?->total_size ?? 0;

            $categoryComparison = [
                'category' => $category,
                'from' => [
                    'count' => $fromCount,
                    'size' => $fromSize,
                ],
                'to' => [
                    'count' => $toCount,
                    'size' => $toSize,
                ],
                'diff' => [
                    'count' => $toCount - $fromCount,
                    'size' => $toSize - $fromSize,
                ],
                'fileTypes' => [],
            ];

            // Get extensions for this specific category from both versions
            $categoryExtensions = collect();
            if ($fromCategory) {
                $categoryExtensions = $categoryExtensions->merge($fromCategory->fileTypes->pluck('extension'));
            }
            if ($toCategory) {
                $categoryExtensions = $categoryExtensions->merge($toCategory->fileTypes->pluck('extension'));
            }
            $categoryExtensions = $categoryExtensions->unique()->sort()->values();

            foreach ($categoryExtensions as $extension) {
                $fromFileType = $fromCategory ? $fromCategory->fileTypes->firstWhere('extension', $extension) : null;
                $toFileType = $toCategory ? $toCategory->fileTypes->firstWhere('extension', $extension) : null;
                $categoryComparison['fileTypes'][$extension] = [
                    'from' => [
                        'count' => $fromFileType ? $fromFileType->count : 0,
                        'size' => $fromFileType ? $fromFileType->size : 0,
                    ],
                    'to' => [
                        'count' => $toFileType ? $toFileType->count : 0,
                        'size' => $toFileType ? $toFileType->size : 0,
                    ],
                    'diff' => [
                        'count' => ($toFileType ? $toFileType->count : 0) - ($fromFileType ? $fromFileType->count : 0),
                        'size' => ($toFileType ? $toFileType->size : 0) - ($fromFileType ? $fromFileType->size : 0),
                    ],
                ];
            }

            $fileCategoryComparisons[] = $categoryComparison;
        }

        return [
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'characters' => $sortedCharacters,
            'languages' => $languages,
            'characterDiffs' => $characterDiffs,
            'languageTotals' => $languageTotals,
            'fileCategories' => $fileCategoryComparisons,
        ];
    }
}
