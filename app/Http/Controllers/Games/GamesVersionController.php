<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\DenKitStashPersistenceService;
use App\Services\RouteGraphService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $cacheKey = "version_comparison_v3_{$game->id}_{$fromVersionId}_{$toVersionId}";
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

        $versionIds = $versions->getCollection()->pluck('id')->all();
        $versionHasFileStats = [];
        $versionOptimizedArchiveAvailability = [];
        $routeDataVersionIds = [];
        if (! empty($versionIds)) {
            $fileStatsVersionIds = DB::table('version_file_categories')
                ->whereIn('game_version_id', $versionIds)
                ->distinct()
                ->pluck('game_version_id')
                ->all();

            $fileStatsVersionIds = array_flip($fileStatsVersionIds);
            foreach ($versionIds as $versionId) {
                $versionHasFileStats[$versionId] = isset($fileStatsVersionIds[$versionId]);
            }

            $routeGraphService = app(RouteGraphService::class);
            $routeDataVersionIds = $versions->getCollection()
                ->filter(fn (GameVersion $version) => $routeGraphService->storedGraph($version) !== null)
                ->pluck('id')
                ->flip()
                ->all();

            if ($game->canUserEdit($request->user())) {
                try {
                    $versionOptimizedArchiveAvailability = app(DenKitStashPersistenceService::class)
                        ->persistedArchiveAvailability($game, $versions->getCollection());
                } catch (Exception $exception) {
                    Log::warning('Could not resolve optimized archive availability for paginated versions', [
                        'game_id' => $game->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        // Filter out placeholder 'q' codes and null language relationships to prevent frontend errors
        $versions->getCollection()->transform(function ($version) use ($routeDataVersionIds) {
            $version->supportedLanguages = $version->supportedLanguages
                ->filter(fn ($sl) => $sl->language !== null && ! str_starts_with($sl->iso_code, 'q'))
                ->values();
            $version->languageStats = $version->languageStats
                ->filter(fn ($ls) => $ls->language !== null && ! str_starts_with($ls->iso_code, 'q'))
                ->values();
            $version->has_route_data = isset($routeDataVersionIds[$version->id]);

            return $version;
        });

        return response()->json([
            'success' => true,
            'versions' => $versions,
            'versionHasFileStats' => $versionHasFileStats,
            'versionOptimizedArchiveAvailability' => $versionOptimizedArchiveAvailability,
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

        // Get available language codes for this version
        $availableLanguages = $version->supportedLanguages()
            ->where('is_available', true)
            ->pluck('iso_code')
            ->toArray();

        $characterStats = $version->characterStats()
            ->with(['character', 'language'])
            ->orderBy('words', 'desc')
            ->get()
            ->filter(fn ($stat) => $stat->language !== null
                && ! str_starts_with($stat->iso_code, 'q')
                && $stat->character?->character_id !== 'alt' // Exclude alt text from word counts
                && in_array($stat->iso_code, $availableLanguages)); // Only include available languages

        // Group by language
        $groupedByLanguage = $characterStats->groupBy('iso_code');

        // Extract unique character names (ordered alphabetically by English display name)
        // Use English as the primary language for character names
        $characterIdToName = []; // Map character IDs to their English display names

        foreach ($characterStats as $stat) {
            $characterId = $stat->character_id;

            // Get the character's display name in English (or fallback to character_id)
            if (! isset($characterIdToName[$characterId])) {
                $characterIdToName[$characterId] = $stat->character->getDisplayName('eng')
                    ?? $stat->character->character_id
                    ?? 'Unknown';
            }
        }

        // Get unique character names and sort alphabetically (case-insensitive)
        $characters = array_unique(array_values($characterIdToName));
        sort($characters, SORT_STRING | SORT_FLAG_CASE);

        // Extract languages and sort (English first, then alphabetically by ISO code)
        $languages = $groupedByLanguage->map(function ($stats, $isoCode) {
            $language = $stats->first()->language;
            // Skip if language relationship is null
            if ($language === null) {
                return null;
            }

            return [
                'id' => $isoCode,
                'flag' => $language->flag_code,
                'name' => $language->ref_name,
            ];
        })->filter()->sortBy(function ($language) {
            // English first, then sort alphabetically by ISO code
            return $language['id'] === 'eng' ? '0' : '1' . $language['id'];
        })->values()->toArray();

        // Build word counts matrix: character -> language -> word count
        // Sum word counts for characters with the same display name (e.g., 'f' and 'f2' both named "Fred")
        $wordCounts = [];
        foreach ($characterStats as $stat) {
            $characterId = $stat->character_id;
            $characterName = $characterIdToName[$characterId];
            $isoCode = $stat->iso_code;
            if (! isset($wordCounts[$characterName])) {
                $wordCounts[$characterName] = [];
            }
            $wordCounts[$characterName][$isoCode] = ($wordCounts[$characterName][$isoCode] ?? 0) + $stat->words;
        }

        // Calculate language totals
        $languageTotals = [];
        foreach ($groupedByLanguage as $isoCode => $stats) {
            $languageTotals[$isoCode] = $stats->sum('words');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'characters' => $characters,
                'languages' => $languages,
                'wordCounts' => $wordCounts,
                'languageTotals' => $languageTotals,
            ],
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
            $fileCategories = $version->fileCategories()
                ->with('fileTypes')
                ->orderBy('category')
                ->get();

            $formattedCategories = $fileCategories->map(function ($category) {
                return [
                    'category' => $category->category,
                    'total_count' => $category->total_count,
                    'total_size' => $category->total_size,
                    'file_types' => $category->fileTypes->map(function ($fileType) {
                        return [
                            'extension' => $fileType->extension,
                            'count' => $fileType->count,
                            'size' => $fileType->size,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'version' => [
                        'version' => $version->version,
                    ],
                    'file_categories' => $formattedCategories,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving file stats', [
                'game_id' => $game->id,
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Unable to retrieve file statistics',
            ], 500);
        }
    }

    /**
     * Generate version comparison data
     */
    private function generateVersionComparison(Game $game, $fromVersionId, $toVersionId): array
    {
        $versions = GameVersion::query()
            ->select(['id', 'game_id', 'version', 'published_at'])
            ->whereIn('id', [$fromVersionId, $toVersionId])
            ->get()
            ->keyBy('id');

        $fromVersion = $versions->get($fromVersionId);
        $toVersion = $versions->get($toVersionId);

        if (! $fromVersion || ! $toVersion) {
            throw new Exception('Versions not found');
        }

        if ($fromVersion->game_id !== $game->id || $toVersion->game_id !== $game->id) {
            throw new Exception('Versions do not belong to the specified game');
        }

        if ($fromVersion->published_at > $toVersion->published_at) {
            $temp = $fromVersion;
            $fromVersion = $toVersion;
            $toVersion = $temp;
        }

        $displayLanguageCode = $game->source_language_id ?? 'eng';
        $fromData = $this->loadVersionComparisonStats($fromVersion->id, $displayLanguageCode);
        $toData = $this->loadVersionComparisonStats($toVersion->id, $displayLanguageCode);

        $languages = $this->mergeComparisonLanguages($fromData['languages'], $toData['languages']);
        $sortedCharacters = $this->mergeCharacterNames(
            array_keys($fromData['wordCounts']),
            array_keys($toData['wordCounts'])
        );

        $characterDiffs = [];
        foreach ($sortedCharacters as $characterName) {
            foreach ($languages as $language) {
                $isoCode = $language['id'];
                $fromWords = $fromData['wordCounts'][$characterName][$isoCode] ?? 0;
                $toWords = $toData['wordCounts'][$characterName][$isoCode] ?? 0;

                $characterDiffs[$characterName][$isoCode] = [
                    'from' => $fromWords,
                    'to' => $toWords,
                    'diff' => $toWords - $fromWords,
                ];
            }
        }

        $languageTotals = $this->buildLanguageTotals(
            $languages,
            $fromData['languageTotals'],
            $toData['languageTotals']
        );

        $fileCategoryComparisons = $this->buildFileCategoryComparisons($fromVersion, $toVersion);

        return [
            'fromVersion' => $this->formatVersionSummary($fromVersion),
            'toVersion' => $this->formatVersionSummary($toVersion),
            'characters' => $sortedCharacters,
            'languages' => $languages,
            'characterDiffs' => $characterDiffs,
            'languageTotals' => $languageTotals,
            'fileCategories' => $fileCategoryComparisons,
        ];
    }

    private function loadVersionComparisonStats(int $versionId, string $displayLanguageCode): array
    {
        $cacheKey = "version_comparison_stats_v1_{$versionId}_{$displayLanguageCode}";

        return cache()->remember($cacheKey, 3600, function () use ($versionId, $displayLanguageCode) {
            $rows = DB::table('version_character_stats as vcs')
                ->join('characters as c', 'c.id', '=', 'vcs.character_id')
                ->join('iso_639_3_languages as l', 'l.id', '=', 'vcs.iso_code')
                ->join('version_supported_languages as vsl', function ($join) use ($versionId) {
                    $join->on('vsl.iso_code', '=', 'vcs.iso_code')
                        ->where('vsl.game_version_id', '=', $versionId)
                        ->where('vsl.is_available', '=', true);
                })
                ->where('vcs.game_version_id', $versionId)
                ->where('vcs.iso_code', 'not like', 'q%')
                ->select([
                    'vcs.character_id',
                    'vcs.iso_code',
                    'vcs.words',
                    'c.character_id as character_key',
                    'c.display_names',
                    'c.display_name_corrections',
                    'l.ref_name as language_name',
                    'l.flag_code as language_flag',
                ])
                ->orderBy('vcs.character_id')
                ->orderBy('vcs.iso_code')
                ->get();

            $characterNames = [];
            $wordCounts = [];
            $languageTotals = [];
            $languages = [];

            foreach ($rows as $row) {
                $isoCode = (string) $row->iso_code;
                $characterId = (int) $row->character_id;
                $words = (int) $row->words;

                if (! isset($languages[$isoCode])) {
                    $languages[$isoCode] = [
                        'id' => $isoCode,
                        'name' => (string) $row->language_name,
                        'flag' => (string) $row->language_flag,
                    ];
                }

                if (! isset($characterNames[$characterId])) {
                    $characterNames[$characterId] = $this->resolveComparisonDisplayName(
                        $row->display_names,
                        $row->display_name_corrections,
                        $displayLanguageCode,
                        $row->character_key
                    );
                }

                $characterName = $characterNames[$characterId];
                $wordCounts[$characterName][$isoCode] = ($wordCounts[$characterName][$isoCode] ?? 0) + $words;
                $languageTotals[$isoCode] = ($languageTotals[$isoCode] ?? 0) + $words;
            }

            return [
                'languages' => $languages,
                'wordCounts' => $wordCounts,
                'languageTotals' => $languageTotals,
            ];
        });
    }

    private function resolveComparisonDisplayName(
        mixed $displayNames,
        mixed $displayNameCorrections,
        string $displayLanguageCode,
        ?string $characterKey
    ): string {
        $decodedDisplayNames = $this->decodeJsonMap($displayNames);
        $decodedCorrections = $this->decodeJsonMap($displayNameCorrections);

        return $decodedCorrections[$displayLanguageCode]
            ?? $decodedDisplayNames[$displayLanguageCode]
            ?? $decodedCorrections['eng']
            ?? $decodedDisplayNames['eng']
            ?? $characterKey
            ?? 'Unknown';
    }

    private function decodeJsonMap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mergeComparisonLanguages(array $fromLanguages, array $toLanguages): array
    {
        $languages = array_values($fromLanguages + $toLanguages);

        usort($languages, function (array $left, array $right): int {
            if ($left['id'] === 'eng') {
                return -1;
            }

            if ($right['id'] === 'eng') {
                return 1;
            }

            return strcmp($left['id'], $right['id']);
        });

        return $languages;
    }

    private function mergeCharacterNames(array $fromCharacters, array $toCharacters): array
    {
        $characters = array_values(array_unique([...$fromCharacters, ...$toCharacters]));
        sort($characters, SORT_STRING | SORT_FLAG_CASE);

        return $characters;
    }

    private function buildLanguageTotals(array $languages, array $fromTotals, array $toTotals): array
    {
        $languageTotals = [
            'from' => [],
            'to' => [],
            'diff' => [],
        ];

        foreach ($languages as $language) {
            $isoCode = $language['id'];
            $fromWords = $fromTotals[$isoCode] ?? 0;
            $toWords = $toTotals[$isoCode] ?? 0;

            $languageTotals['from'][$isoCode] = $fromWords;
            $languageTotals['to'][$isoCode] = $toWords;
            $languageTotals['diff'][$isoCode] = $toWords - $fromWords;
        }

        return $languageTotals;
    }

    private function buildFileCategoryComparisons(GameVersion $fromVersion, GameVersion $toVersion): array
    {
        $fromCategories = $this->mapFileCategories(
            $fromVersion->fileCategories()
                ->select(['id', 'game_version_id', 'category', 'total_count', 'total_size'])
                ->with(['fileTypes:id,version_file_category_id,extension,count,size'])
                ->get()
        );

        $toCategories = $this->mapFileCategories(
            $toVersion->fileCategories()
                ->select(['id', 'game_version_id', 'category', 'total_count', 'total_size'])
                ->with(['fileTypes:id,version_file_category_id,extension,count,size'])
                ->get()
        );

        $allCategories = array_values(array_unique([
            ...array_keys($fromCategories),
            ...array_keys($toCategories),
        ]));
        sort($allCategories, SORT_STRING);

        $comparisons = [];
        foreach ($allCategories as $categoryName) {
            $fromCategory = $fromCategories[$categoryName] ?? null;
            $toCategory = $toCategories[$categoryName] ?? null;
            $fromCount = $fromCategory['count'] ?? 0;
            $toCount = $toCategory['count'] ?? 0;
            $fromSize = $fromCategory['size'] ?? 0;
            $toSize = $toCategory['size'] ?? 0;

            $fileTypes = [];
            $extensions = array_values(array_unique([
                ...array_keys($fromCategory['fileTypes'] ?? []),
                ...array_keys($toCategory['fileTypes'] ?? []),
            ]));
            sort($extensions, SORT_STRING);

            foreach ($extensions as $extension) {
                $fromFileType = $fromCategory['fileTypes'][$extension] ?? ['count' => 0, 'size' => 0];
                $toFileType = $toCategory['fileTypes'][$extension] ?? ['count' => 0, 'size' => 0];

                $fileTypes[$extension] = [
                    'from' => $fromFileType,
                    'to' => $toFileType,
                    'diff' => [
                        'count' => $toFileType['count'] - $fromFileType['count'],
                        'size' => $toFileType['size'] - $fromFileType['size'],
                    ],
                ];
            }

            $comparisons[] = [
                'category' => $categoryName,
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
                'fileTypes' => $fileTypes,
            ];
        }

        return $comparisons;
    }

    private function mapFileCategories(Collection $categories): array
    {
        $mapped = [];

        foreach ($categories as $category) {
            $fileTypes = [];
            foreach ($category->fileTypes as $fileType) {
                $fileTypes[$fileType->extension] = [
                    'count' => (int) $fileType->count,
                    'size' => (int) $fileType->size,
                ];
            }

            $mapped[$category->category] = [
                'count' => (int) $category->total_count,
                'size' => (int) $category->total_size,
                'fileTypes' => $fileTypes,
            ];
        }

        return $mapped;
    }

    private function formatVersionSummary(GameVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'published_at' => $version->published_at,
        ];
    }
}
