<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CharacterNullAssignmentService
{
    public function __construct(
        private readonly EssentialCharacterService $essentialCharacterService
    ) {}

    /**
     * Fix NULL character assignments by creating narrator characters and assigning them
     */
    public function fixNullCharacterAssignments(?int $gameId = null, bool $dryRun = false): array
    {
        Log::info('Checking for NULL character_id assignments...');

        $query = DB::table('version_dialogue_lines')
            ->whereNull('character_id');

        if ($gameId) {
            $query->join('game_versions', 'version_dialogue_lines.game_version_id', '=', 'game_versions.id')
                ->where('game_versions.game_id', $gameId);
        }

        $nullDialogueLines = $query->get();

        if ($nullDialogueLines->isEmpty()) {
            Log::info('No NULL character_id assignments found.');

            return [
                'lines_updated' => 0,
                'narrator_characters_created' => 0,
                'games_processed' => 0,
            ];
        }

        Log::info(sprintf('Found %d dialogue lines with NULL character_id', $nullDialogueLines->count()));

        $gameVersionsQuery = DB::table('version_dialogue_lines as vdl')
            ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
            ->whereNull('vdl.character_id')
            ->select('gv.game_id')
            ->distinct();

        if ($gameId) {
            $gameVersionsQuery->where('gv.game_id', $gameId);
        }

        $gameVersions = $gameVersionsQuery->pluck('game_id');

        Log::info(sprintf('Found %d games with NULL character assignments', $gameVersions->count()));

        $linesUpdated = 0;
        $narratorCharactersCreated = 0;

        foreach ($gameVersions as $currentGameId) {
            if ($dryRun) {
                // Count how many lines would be updated for this game
                $linesToUpdate = DB::table('version_dialogue_lines as vdl')
                    ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                    ->where('gv.game_id', $currentGameId)
                    ->whereNull('vdl.character_id')
                    ->count();

                Log::info("Would update {$linesToUpdate} NULL character assignments for game {$currentGameId}");
                $linesUpdated += $linesToUpdate;

                $narratorExists = Character::where('game_id', $currentGameId)
                    ->where('character_id', 'narrator')
                    ->exists();

                if (! $narratorExists) {
                    Log::info("Would create narrator character for game {$currentGameId}");
                    $narratorCharactersCreated++;
                }

                continue;
            }

            $narratorCharacter = $this->essentialCharacterService->getOrCreateNarratorCharacter($currentGameId);

            if ($narratorCharacter->wasRecentlyCreated) {
                $narratorCharactersCreated++;
                Log::info("Created narrator character for game {$currentGameId}");
            }

            $updated = DB::table('version_dialogue_lines as vdl')
                ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                ->where('gv.game_id', $currentGameId)
                ->whereNull('vdl.character_id')
                ->update(['vdl.character_id' => $narratorCharacter->id]);

            $linesUpdated += $updated;
            Log::info("Updated {$updated} NULL character assignments for game {$currentGameId}");
        }

        $result = [
            'lines_updated' => $linesUpdated,
            'narrator_characters_created' => $narratorCharactersCreated,
            'games_processed' => $gameVersions->count(),
        ];

        Log::info(sprintf(
            'Completed NULL character assignment fix: %d lines updated, %d narrator characters created, %d games processed',
            $result['lines_updated'],
            $result['narrator_characters_created'],
            $result['games_processed']
        ));

        return $result;
    }
}
