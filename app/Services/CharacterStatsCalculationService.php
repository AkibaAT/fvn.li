<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DialogueLine;
use App\Models\VersionCharacterStats;
use App\Models\VersionLanguageStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterStatsCalculationService
{
    /**
     * Data completeness levels for version statistics
     */
    public const DATA_LEVEL_NONE = 'none';

    public const DATA_LEVEL_LANGUAGE_ONLY = 'language_only';

    public const DATA_LEVEL_CHARACTER_STATS = 'character_stats';

    public const DATA_LEVEL_FULL_DETAIL = 'full_detail';

    /**
     * Check if we can safely create missing character stats for a version.
     * This is more permissive than updates - we can create if we have dialogue lines.
     */
    public function canCreateCharacterStats(int $versionId): bool
    {
        $dataLevel = $this->getVersionDataLevel($versionId);

        return in_array($dataLevel, [self::DATA_LEVEL_FULL_DETAIL]);
    }

    /**
     * Determine the data completeness level for a version.
     * This tells us what kind of updates are safe to perform.
     */
    public function getVersionDataLevel(int $versionId): string
    {
        // Check if we have full dialogue line details
        $hasDialogueLines = DB::table('version_dialogue_lines')
            ->where('game_version_id', $versionId)
            ->exists();

        if ($hasDialogueLines) {
            // Check if dialogue lines have text_id references to unique_dialogue_texts
            $hasTextDetails = DB::table('version_dialogue_lines')
                ->where('game_version_id', $versionId)
                ->whereNotNull('text_id')
                ->exists();

            if ($hasTextDetails) {
                return self::DATA_LEVEL_FULL_DETAIL;
            }
        }

        // Check if we have character-level stats
        $hasCharacterStats = DB::table('version_character_stats')
            ->where('game_version_id', $versionId)
            ->exists();

        if ($hasCharacterStats) {
            return self::DATA_LEVEL_CHARACTER_STATS;
        }

        // Check if we have language-level stats
        $hasLanguageStats = DB::table('version_language_stats')
            ->where('game_version_id', $versionId)
            ->exists();

        if ($hasLanguageStats) {
            return self::DATA_LEVEL_LANGUAGE_ONLY;
        }

        return self::DATA_LEVEL_NONE;
    }

    /**
     * Calculate and save character stats for a specific game version with data completeness protection
     * This method provides consistent calculation logic used by both
     * stats import and fix commands
     */
    public function calculateAndSaveStatsForVersionSafe(int $versionId): int
    {
        $dataLevel = $this->getVersionDataLevel($versionId);

        if (! $this->isVersionSafeToUpdate($versionId)) {
            Log::info("Skipping version {$versionId} - insufficient data level: {$this->getDataLevelDescription($dataLevel)}");

            return 0;
        }

        return $this->calculateAndSaveStatsForVersion($versionId);
    }

    /**
     * Check if a version is safe to update character statistics.
     * Only versions with full detail level can have their character stats recalculated.
     */
    public function isVersionSafeToUpdate(int $versionId): bool
    {
        return $this->getVersionDataLevel($versionId) === self::DATA_LEVEL_FULL_DETAIL;
    }

    /**
     * Get a human-readable description of the data level.
     */
    public function getDataLevelDescription(string $dataLevel): string
    {
        return match ($dataLevel) {
            self::DATA_LEVEL_FULL_DETAIL => 'Full dialogue line details with text content',
            self::DATA_LEVEL_CHARACTER_STATS => 'Character statistics only (no individual lines)',
            self::DATA_LEVEL_LANGUAGE_ONLY => 'Language statistics only (no character breakdown)',
            self::DATA_LEVEL_NONE => 'No detailed statistics available',
            default => 'Unknown data level',
        };
    }

    /**
     * Calculate and save character stats for a specific game version
     * This method provides consistent calculation logic used by both
     * stats import and fix commands
     * WARNING: This method does not check for legacy data protection
     */
    public function calculateAndSaveStatsForVersion(int $versionId): int
    {
        Log::info("Calculating character stats for version {$versionId}");

        // Check for any dialogue lines with null character_id and warn about them
        $nullCharacterCount = DialogueLine::where('game_version_id', $versionId)
            ->whereNull('character_id')
            ->count();

        if ($nullCharacterCount > 0) {
            Log::warning("Found {$nullCharacterCount} dialogue lines with null character_id for version {$versionId}. These will be skipped during stats calculation. Consider running fix:characters command to resolve this.");
        }

        // Get all dialogue lines for this version grouped by character and language
        // Use sophisticated SQL that matches Python's text.split() behavior
        // Filter out any dialogue lines with null character_id to prevent constraint violations
        $dialogueStats = DialogueLine::where('game_version_id', $versionId)
            ->whereNotNull('character_id')
            ->join('unique_dialogue_texts', 'version_dialogue_lines.text_id', '=', 'unique_dialogue_texts.id')
            ->select('character_id', 'iso_code')
            ->selectRaw('COUNT(*) as blocks')
            ->selectRaw('SUM(
                CASE
                    WHEN TRIM(unique_dialogue_texts.text_content) = \'\' THEN 0
                    ELSE ARRAY_LENGTH(
                        ARRAY_REMOVE(
                            STRING_TO_ARRAY(TRIM(unique_dialogue_texts.text_content), \' \'),
                            \'\'
                        ),
                        1
                    )
                END
            ) as words')
            ->groupBy('character_id', 'iso_code')
            ->get();

        $statsUpdated = 0;

        foreach ($dialogueStats as $stats) {
            // Update or create character stats
            VersionCharacterStats::updateOrCreate(
                [
                    'game_version_id' => $versionId,
                    'character_id' => $stats->character_id,
                    'iso_code' => $stats->iso_code,
                ],
                [
                    'blocks' => $stats->blocks,
                    'words' => $stats->words,
                ]
            );
            $statsUpdated++;
        }

        Log::info("Updated {$statsUpdated} character stats for version {$versionId}");

        // Also recalculate language totals to ensure consistency
        $languageStatsUpdated = $this->recalculateLanguageStats($versionId);
        Log::info("Updated {$languageStatsUpdated} language stats for version {$versionId}");

        return $statsUpdated;
    }

    /**
     * Calculate stats for multiple character/version/language combinations
     * Used for bulk recalculation in fix commands
     */
    public function calculateStatsForMultiple(array $statsToUpdate): array
    {
        $results = [];

        foreach ($statsToUpdate as $stat) {
            $calculatedStats = $this->calculateStatsForCharacter(
                $stat->game_version_id,
                $stat->character_id,
                $stat->iso_code
            );

            $results[] = [
                'stat' => $stat,
                'calculated' => $calculatedStats,
            ];
        }

        return $results;
    }

    /**
     * Calculate stats for a specific character/version/language combination
     * Used by fix commands for targeted recalculation
     */
    public function calculateStatsForCharacter(int $versionId, int $characterId, string $isoCode): array
    {
        $dialogueStats = DialogueLine::where('game_version_id', $versionId)
            ->where('character_id', $characterId)
            ->where('iso_code', $isoCode)
            ->join('unique_dialogue_texts', 'version_dialogue_lines.text_id', '=', 'unique_dialogue_texts.id')
            ->selectRaw('COUNT(*) as blocks')
            ->selectRaw('SUM(
                CASE
                    WHEN TRIM(unique_dialogue_texts.text_content) = \'\' THEN 0
                    ELSE ARRAY_LENGTH(
                        ARRAY_REMOVE(
                            STRING_TO_ARRAY(TRIM(unique_dialogue_texts.text_content), \' \'),
                            \'\'
                        ),
                        1
                    )
                END
            ) as words')
            ->first();

        return [
            'blocks' => $dialogueStats->blocks ?? 0,
            'words' => $dialogueStats->words ?? 0,
        ];
    }

    /**
     * Filter stats to only include those that are safe to update based on data completeness
     */
    public function filterSafeStatsToUpdate($statsCollection)
    {
        return $statsCollection->filter(function ($stat) {
            return $this->isVersionSafeToUpdate($stat->game_version_id);
        });
    }

    /**
     * Update character stats with calculated values with data completeness protection
     * Used by fix commands to apply the calculated stats
     */
    public function updateCharacterStatsSafe(array $calculatedResults, bool $dryRun = false): int
    {
        $statsUpdated = 0;
        $statsSkipped = 0;

        foreach ($calculatedResults as $result) {
            $stat = $result['stat'];
            $calculated = $result['calculated'];

            // Check if this stat is safe to update based on data completeness
            $dataLevel = $this->getVersionDataLevel($stat->game_version_id);
            if (! $this->isVersionSafeToUpdate($stat->game_version_id)) {
                $statsSkipped++;
                $description = $this->getDataLevelDescription($dataLevel);
                if ($dryRun) {
                    Log::info("Would skip stats ID {$stat->id} - insufficient data level: {$description}");
                } else {
                    Log::info("Skipped stats ID {$stat->id} - insufficient data level: {$description}");
                }

                continue;
            }

            if ($dryRun) {
                Log::info("Would update stats ID {$stat->id}: {$stat->blocks} -> {$calculated['blocks']} blocks, {$stat->words} -> {$calculated['words']} words");
            } else {
                $stat->blocks = $calculated['blocks'];
                $stat->words = $calculated['words'];
                $stat->save();
                Log::info("Updated stats ID {$stat->id}: {$calculated['blocks']} blocks, {$calculated['words']} words");
            }
            $statsUpdated++;
        }

        if ($statsSkipped > 0) {
            Log::info("Data completeness protection: skipped {$statsSkipped} stats entries with insufficient data level");
        }

        // If we updated any character stats, also recalculate language totals for affected versions
        if ($statsUpdated > 0 && ! $dryRun) {
            $this->recalculateLanguageStatsForAffectedVersions($calculatedResults);
        }

        return $statsUpdated;
    }

    /**
     * Update character stats with calculated values
     * Used by fix commands to apply the calculated stats
     * WARNING: This method does not check for legacy data protection
     */
    public function updateCharacterStats(array $calculatedResults, bool $dryRun = false): int
    {
        $statsUpdated = 0;

        foreach ($calculatedResults as $result) {
            $stat = $result['stat'];
            $calculated = $result['calculated'];

            if ($dryRun) {
                Log::info("Would update stats ID {$stat->id}: {$stat->blocks} -> {$calculated['blocks']} blocks, {$stat->words} -> {$calculated['words']} words");
            } else {
                $stat->blocks = $calculated['blocks'];
                $stat->words = $calculated['words'];
                $stat->save();
                Log::info("Updated stats ID {$stat->id}: {$calculated['blocks']} blocks, {$calculated['words']} words");
            }
            $statsUpdated++;
        }

        return $statsUpdated;
    }

    /**
     * Get character stats that have issues (narrator characters, zero stats, and special characters)
     */
    public function getStatsWithIssues(?int $gameId = null)
    {
        $query = VersionCharacterStats::query()
            ->join('characters', 'version_character_stats.character_id', '=', 'characters.id');

        if ($gameId) {
            $query->where('characters.game_id', $gameId);
        }

        // Get narrator characters, entries with zero blocks/words, and special characters
        $query->where(function ($q) {
            $q->where('characters.character_id', 'narrator')
                ->orWhere('version_character_stats.blocks', 0)
                ->orWhere('version_character_stats.words', 0)
                // Include all special characters that might need recalculation (excluding menu_choice)
                ->orWhereIn('characters.character_id', ['extend', 'centered', 'vcentered', 'nvl_narrator', 'wait']);
        });

        return $query->select('version_character_stats.*')->get();
    }

    /**
     * Recalculate language-level statistics by summing up character statistics
     * This ensures language totals are consistent with character breakdowns
     * Note: 'alt' characters are excluded as they contain alt text, not narrative content
     */
    private function recalculateLanguageStats(int $versionId): int
    {
        // Get language totals by summing character stats, excluding 'alt' characters
        // 'alt' contains alt text for images, not actual narrative content
        $languageTotals = DB::table('version_character_stats')
            ->join('characters', 'version_character_stats.character_id', '=', 'characters.id')
            ->where('game_version_id', $versionId)
            ->where('characters.character_id', '!=', 'alt')
            ->select('version_character_stats.iso_code')
            ->selectRaw('SUM(version_character_stats.blocks) as total_blocks')
            ->selectRaw('SUM(version_character_stats.words) as total_words')
            ->groupBy('version_character_stats.iso_code')
            ->get();

        $existingStats = VersionLanguageStats::where('game_version_id', $versionId)
            ->get()
            ->keyBy('iso_code');
        $characterLanguages = DB::table('version_character_stats')
            ->where('game_version_id', $versionId)
            ->distinct()
            ->pluck('iso_code');
        $languageCodes = $existingStats->keys()
            ->merge($characterLanguages)
            ->merge($languageTotals->pluck('iso_code'))
            ->unique()
            ->values();
        $languageTotalsByCode = $languageTotals->keyBy('iso_code');
        $languageStatsUpdated = 0;

        foreach ($languageCodes as $isoCode) {
            $langTotal = $languageTotalsByCode->get($isoCode);
            // Update or create language stats, preserving menus and options if they exist
            $existingLanguageStats = $existingStats->get($isoCode);

            VersionLanguageStats::updateOrCreate(
                [
                    'game_version_id' => $versionId,
                    'iso_code' => $isoCode,
                ],
                [
                    'blocks' => (int) ($langTotal->total_blocks ?? 0),
                    'words' => (int) ($langTotal->total_words ?? 0),
                    // Preserve existing menu/option counts if they exist
                    'menus' => $existingLanguageStats?->menus,
                    'options' => $existingLanguageStats?->options,
                ]
            );
            $languageStatsUpdated++;
        }

        return $languageStatsUpdated;
    }

    /**
     * Recalculate language stats for versions affected by character stats updates
     */
    private function recalculateLanguageStatsForAffectedVersions(array $calculatedResults): void
    {
        // Get unique version IDs from the calculated results
        $affectedVersions = collect($calculatedResults)
            ->pluck('stat.game_version_id')
            ->unique()
            ->values();

        foreach ($affectedVersions as $versionId) {
            $languageStatsUpdated = $this->recalculateLanguageStats($versionId);
            if ($languageStatsUpdated > 0) {
                Log::info("Recalculated {$languageStatsUpdated} language stats for version {$versionId} after character stats update");
            }
        }
    }
}
