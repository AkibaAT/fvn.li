<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterVersionReferenceService
{
    public function __construct(
        private readonly CharacterStatsCalculationService $characterStatsService
    ) {}

    /**
     * Fix character version references and cleanup orphaned characters
     */
    public function fixVersionReferences(?int $gameId = null, bool $dryRun = false): array
    {
        Log::info('Starting character version reference fixes...');

        $charactersUpdated = 0;
        $charactersProcessed = 0;
        $statsEntriesCreated = 0;

        // Process in chunks to avoid memory issues
        $characterQuery = Character::with(['game']);

        if ($gameId) {
            $characterQuery->where('game_id', $gameId);
        }

        $characterQuery->orderBy('id')->chunk(100,
            function ($characters) use (&$charactersUpdated, &$charactersProcessed, &$statsEntriesCreated, $dryRun) {
                foreach ($characters as $character) {
                    $charactersProcessed++;

                    $updates = [];

                    // Get all version IDs where this character appears from version_character_stats
                    $versionIdsFromStats = DB::table('version_character_stats')
                        ->where('character_id', $character->id)
                        ->pluck('game_version_id');

                    // Get all version IDs where this character appears from version_dialogue_lines
                    $versionIdsFromDialogue = DB::table('version_dialogue_lines')
                        ->where('character_id', $character->id)
                        ->pluck('game_version_id');

                    Log::info(sprintf(
                        'Character %d (%s) - Stats: %d versions, Dialogue: %d versions',
                        $character->id,
                        $character->character_id,
                        $versionIdsFromStats->count(),
                        $versionIdsFromDialogue->count()
                    ));

                    // Get all versions where this character appears in dialogue but not in stats
                    $dialogueOnlyVersions = $versionIdsFromDialogue->unique()->diff(
                        $versionIdsFromStats->unique()
                    );

                    // Create missing version_character_stats entries using the centralized service
                    if ($dialogueOnlyVersions->isNotEmpty()) {
                        Log::info(sprintf(
                            '  Calculating stats for %d missing version_character_stats entries',
                            $dialogueOnlyVersions->count()
                        ));

                        $insertedCount = 0;
                        $skippedCount = 0;

                        // Use the centralized service to calculate and save stats for each version
                        foreach ($dialogueOnlyVersions as $versionId) {
                            try {
                                if ($dryRun) {
                                    // Check if version would be safe to update
                                    if ($this->characterStatsService->isVersionSafeToUpdate($versionId)) {
                                        Log::info("Would create stats for version {$versionId}");
                                        $insertedCount++;
                                    } else {
                                        Log::info("Would skip version {$versionId} (insufficient data level)");
                                        $skippedCount++;
                                    }

                                    continue;
                                }

                                // Use the safe method that checks for data completeness
                                $statsCreated = $this->characterStatsService->calculateAndSaveStatsForVersionSafe($versionId);

                                if ($statsCreated > 0) {
                                    $insertedCount += $statsCreated;
                                    Log::info("Created {$statsCreated} stats entries for version {$versionId}");
                                } else {
                                    $skippedCount++;
                                    Log::info("Skipped version {$versionId} (insufficient data level)");
                                }
                            } catch (Exception $e) {
                                // Log any errors but continue with other versions
                                Log::warning("Error processing version {$versionId}: {$e->getMessage()}");

                                continue;
                            }
                        }

                        $statsEntriesCreated += $insertedCount;

                        Log::info(sprintf(
                            '  Created %d new version_character_stats entries total, skipped %d versions with insufficient data',
                            $insertedCount,
                            $skippedCount
                        ));

                        // Refresh the stats versions to include the newly created ones if any were inserted
                        if ($insertedCount > 0 && ! $dryRun) {
                            $versionIdsFromStats = DB::table('version_character_stats')
                                ->where('character_id', $character->id)
                                ->pluck('game_version_id');
                        }
                    }

                    // Combine and get unique version IDs
                    $allVersionIds = $versionIdsFromStats->concat($versionIdsFromDialogue)->unique();

                    if ($allVersionIds->isNotEmpty()) {
                        // Get the earliest and latest version from the combined results
                        $firstSeenVersion = DB::table('game_versions')
                            ->whereIn('id', $allVersionIds)
                            ->orderBy('published_at', 'asc')
                            ->first();

                        $lastSeenVersion = DB::table('game_versions')
                            ->whereIn('id', $allVersionIds)
                            ->orderBy('published_at', 'desc')
                            ->first();

                        // Update first_seen_in_version_id if needed
                        if ($firstSeenVersion) {
                            $needsFirstSeenUpdate = $character->first_seen_in_version_id === null ||
                                $character->first_seen_in_version_id != $firstSeenVersion->id;

                            if ($needsFirstSeenUpdate) {
                                $updates['first_seen_in_version_id'] = $firstSeenVersion->id;
                            }
                        }

                        // Update last_seen_in_version_id if needed
                        if ($lastSeenVersion) {
                            $needsLastSeenUpdate = $character->last_seen_in_version_id === null ||
                                $character->last_seen_in_version_id != $lastSeenVersion->id;

                            if ($needsLastSeenUpdate) {
                                $updates['last_seen_in_version_id'] = $lastSeenVersion->id;
                            }
                        }
                    }

                    // Only update if there are changes
                    if (! empty($updates)) {
                        if ($dryRun) {
                            Log::info("Would update character {$character->id} ({$character->character_id}) version references");
                        } else {
                            $character->update($updates);
                            Log::info("Updated character {$character->id} ({$character->character_id}) version references");
                        }
                        $charactersUpdated++;
                    }
                }
            });

        // Clean up orphaned characters
        $charactersDeleted = $this->deleteOrphanedCharacters($gameId, $dryRun);

        $result = [
            'characters_processed' => $charactersProcessed,
            'characters_updated' => $charactersUpdated,
            'stats_entries_created' => $statsEntriesCreated,
            'characters_deleted' => $charactersDeleted,
        ];

        Log::info(sprintf(
            'Completed version reference fixes: %d characters processed, %d updated, %d stats entries created, %d orphaned characters deleted',
            $result['characters_processed'],
            $result['characters_updated'],
            $result['stats_entries_created'],
            $result['characters_deleted']
        ));

        return $result;
    }

    /**
     * Delete characters that don't have dialogue lines or character stats.
     */
    private function deleteOrphanedCharacters(?int $gameId = null, bool $dryRun = false): int
    {
        Log::info('Checking for orphaned characters...');

        $orphanedQuery = Character::whereDoesntHave('dialogueLines')
            ->whereDoesntHave('versionStats');

        if ($gameId) {
            $orphanedQuery->where('game_id', $gameId);
        }

        $orphanedCharacters = $orphanedQuery->get();

        if ($orphanedCharacters->isEmpty()) {
            Log::info('No orphaned characters found.');

            return 0;
        }

        Log::info(sprintf('Found %d orphaned characters', $orphanedCharacters->count()));

        $deletedCount = 0;

        foreach ($orphanedCharacters as $character) {
            if ($dryRun) {
                Log::info("Would delete orphaned character {$character->id} ({$character->character_id})");
            } else {
                Log::info("Deleting orphaned character {$character->id} ({$character->character_id})");
                $character->delete();
            }
            $deletedCount++;
        }

        Log::info(sprintf('Deleted %d orphaned characters', $deletedCount));

        return $deletedCount;
    }
}
