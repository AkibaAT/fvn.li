<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterSpecialAssignmentService
{
    /**
     * Special characters that need to be reassigned to previous line's character
     */
    private const array EXTEND_CHARACTERS = ['extend'];

    /**
     * Special characters that need to be reassigned to narrator
     */
    private const array NARRATOR_CHARACTERS = ['centered', 'vcentered', 'nvl_narrator', 'menu_choice', 'wait'];

    /**
     * All special characters that need processing
     */
    private const array ALL_SPECIAL_CHARACTERS = ['extend', 'centered', 'vcentered', 'nvl_narrator', 'menu_choice', 'wait'];

    /**
     * Fix special character assignments by reassigning them appropriately
     */
    public function fixSpecialCharacterAssignments(?int $gameId = null, ?string $specificCharacter = null, bool $dryRun = false): array
    {
        Log::info('Starting special character assignment fixes...');

        $totalLinesReassigned = 0;
        $versionsProcessed = 0;
        $charactersToProcess = $specificCharacter ? [$specificCharacter] : self::ALL_SPECIAL_CHARACTERS;

        foreach ($charactersToProcess as $characterName) {
            Log::info("Processing special character: {$characterName}");

            // Get all versions that have this special character
            $versionsQuery = DB::table('version_dialogue_lines as vdl')
                ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                ->join('characters as c', 'vdl.character_id', '=', 'c.id')
                ->where('c.character_id', $characterName)
                ->select('vdl.game_version_id')
                ->distinct();

            if ($gameId) {
                $versionsQuery->where('gv.game_id', $gameId);
            }

            $versionIds = $versionsQuery->pluck('game_version_id');

            Log::info("Found {$versionIds->count()} versions with '{$characterName}' character");

            foreach ($versionIds as $versionId) {
                $linesReassigned = $this->processSpecialCharacterInVersion(
                    $versionId,
                    $characterName,
                    $dryRun
                );

                $totalLinesReassigned += $linesReassigned;

                if ($linesReassigned > 0) {
                    $versionsProcessed++;
                }
            }
        }

        $result = [
            'lines_reassigned' => $totalLinesReassigned,
            'versions_processed' => $versionsProcessed,
            'characters_processed' => count($charactersToProcess),
        ];

        Log::info(sprintf(
            'Completed special character assignment fixes: %d lines reassigned across %d versions for %d special characters',
            $result['lines_reassigned'],
            $result['versions_processed'],
            $result['characters_processed']
        ));

        return $result;
    }

    /**
     * Process special character assignments for a specific version
     */
    private function processSpecialCharacterInVersion(int $versionId, string $characterName, bool $dryRun): int
    {
        // Get the game ID for this version
        $gameId = DB::table('game_versions')->where('id', $versionId)->value('game_id');

        // Get the character ID for this special character in this specific game
        $specialCharacter = DB::table('characters')
            ->where('character_id', $characterName)
            ->where('game_id', $gameId)
            ->first();

        if (! $specialCharacter) {
            Log::info("No {$characterName} character found for game {$gameId}, version {$versionId}");

            return 0;
        }

        // Get all dialogue lines for this version with the special character, ordered by file and line number
        $specialLines = DB::table('version_dialogue_lines')
            ->where('game_version_id', $versionId)
            ->where('character_id', $specialCharacter->id)
            ->orderBy('file_path')
            ->orderBy('line_number')
            ->get(['id', 'file_path', 'line_number', 'character_id']);

        $linesReassigned = 0;

        // Determine target character based on special character type
        if (in_array($characterName, self::EXTEND_CHARACTERS)) {
            // 'extend' characters should be assigned to previous line's character
            $linesReassigned = $this->reassignToPreviousCharacter($specialLines, $versionId, $characterName, $specialCharacter->id, $dryRun);
        } elseif (in_array($characterName, self::NARRATOR_CHARACTERS)) {
            // Other special characters should be assigned to narrator
            $linesReassigned = $this->reassignToNarrator($specialLines, $versionId, $characterName, $specialCharacter->id, $dryRun);
        }

        return $linesReassigned;
    }

    /**
     * Reassign special character lines to the previous line's character (for 'extend')
     */
    private function reassignToPreviousCharacter($specialLines, int $versionId, string $characterName, int $specialCharacterId, bool $dryRun): int
    {
        $linesReassigned = 0;

        foreach ($specialLines as $specialLine) {
            // Find the previous line in the same file
            $previousLine = DB::table('version_dialogue_lines')
                ->where('game_version_id', $versionId)
                ->where('file_path', $specialLine->file_path)
                ->where('line_number', '<', $specialLine->line_number)
                ->orderBy('line_number', 'desc')
                ->first(['character_id']);

            if ($previousLine && $previousLine->character_id !== $specialCharacterId) {
                if ($dryRun) {
                    Log::info("Would reassign {$characterName} line {$specialLine->id} to character {$previousLine->character_id}");
                } else {
                    // Update the special line to use the previous line's character
                    DB::table('version_dialogue_lines')
                        ->where('id', $specialLine->id)
                        ->update(['character_id' => $previousLine->character_id]);

                    Log::info("Reassigned {$characterName} line {$specialLine->id} to character {$previousLine->character_id}");
                }

                $linesReassigned++;
            } else {
                // No valid previous line found - assign to narrator instead
                Log::warning("No valid previous line found for {$characterName} line {$specialLine->id}, assigning to narrator");
                $linesReassigned += $this->reassignToNarrator([$specialLine], $versionId, $characterName, $specialCharacterId, $dryRun);
            }
        }

        return $linesReassigned;
    }

    /**
     * Reassign special character lines to narrator (for menu_choice, centered, etc.)
     */
    private function reassignToNarrator($specialLines, int $versionId, string $characterName, int $specialCharacterId, bool $dryRun): int
    {
        $linesReassigned = 0;

        // Get the narrator character for this game
        $gameId = DB::table('game_versions')->where('id', $versionId)->value('game_id');
        $narratorCharacter = DB::table('characters')
            ->where('game_id', $gameId)
            ->where('character_id', 'narrator')
            ->first();

        if (! $narratorCharacter) {
            // Create a narrator character if it doesn't exist
            if ($dryRun) {
                Log::info("Would create narrator character for game {$gameId}");
                // For dry run, we'll simulate having a narrator character
                $narratorCharacterId = 999999; // Fake ID for dry run
            } else {
                $narratorCharacterId = DB::table('characters')->insertGetId([
                    'game_id' => $gameId,
                    'character_id' => 'narrator',
                    'display_names' => json_encode(['narrator']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Log::info("Created narrator character {$narratorCharacterId} for game {$gameId}");
            }
        } else {
            $narratorCharacterId = $narratorCharacter->id;
        }

        foreach ($specialLines as $specialLine) {
            if ($dryRun) {
                Log::info("Would reassign {$characterName} line {$specialLine->id} to narrator (character {$narratorCharacterId})");
            } else {
                // Update the special line to use the narrator character
                DB::table('version_dialogue_lines')
                    ->where('id', $specialLine->id)
                    ->update(['character_id' => $narratorCharacterId]);

                Log::info("Reassigned {$characterName} line {$specialLine->id} to narrator (character {$narratorCharacterId})");
            }

            $linesReassigned++;
        }

        return $linesReassigned;
    }
}
