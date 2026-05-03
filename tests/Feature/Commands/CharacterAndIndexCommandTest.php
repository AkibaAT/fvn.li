<?php

declare(strict_types=1);

use App\Services\CharacterNullAssignmentService;
use App\Services\CharacterSpecialAssignmentService;
use App\Services\CharacterStatsCalculationService;
use App\Services\CharacterVersionReferenceService;

it('runs all character fix steps and displays a dry-run summary', function () {
    $this->mock(CharacterNullAssignmentService::class, function ($mock) {
        $mock->shouldReceive('fixNullCharacterAssignments')->once()->with(12, true)->andReturn([
            'lines_updated' => 2,
            'narrator_characters_created' => 1,
            'games_processed' => 1,
        ]);
    });
    $this->mock(CharacterSpecialAssignmentService::class, function ($mock) {
        $mock->shouldReceive('fixSpecialCharacterAssignments')->once()->with(12, 'extend', true)->andReturn([
            'lines_reassigned' => 3,
            'versions_processed' => 2,
            'characters_processed' => 1,
        ]);
    });
    $this->mock(CharacterStatsCalculationService::class, function ($mock) {
        $stats = collect([
            (object) ['id' => 1, 'game_version_id' => 100, 'character_id' => 200, 'iso_code' => 'eng'],
            (object) ['id' => 2, 'game_version_id' => 101, 'character_id' => 201, 'iso_code' => 'eng'],
        ]);
        $mock->shouldReceive('getStatsWithIssues')->once()->with(12)->andReturn($stats);
        $mock->shouldReceive('filterSafeStatsToUpdate')->once()->with($stats)->andReturn($stats->take(1));
    });
    $this->mock(CharacterVersionReferenceService::class, function ($mock) {
        $mock->shouldReceive('fixVersionReferences')->once()->with(12, true)->andReturn([
            'characters_processed' => 5,
            'characters_updated' => 4,
            'stats_entries_created' => 1,
            'characters_deleted' => 1,
        ]);
    });

    $this->artisan('fix:characters', [
        '--game-id' => 12,
        '--dry-run' => true,
        '--character' => 'extend',
    ])
        ->expectsOutput('Starting comprehensive character fixes...')
        ->expectsOutput('DRY RUN MODE: No changes will be made')
        ->expectsOutput('Would recalculate 1 character statistics')
        ->expectsOutput('Would skip 1 stats entries with insufficient data level')
        ->expectsOutput('Would have processed:')
        ->assertExitCode(0);
});

it('runs only the requested character stats step for a specific version', function () {
    $this->mock(CharacterNullAssignmentService::class);
    $this->mock(CharacterSpecialAssignmentService::class);
    $this->mock(CharacterVersionReferenceService::class);
    $this->mock(CharacterStatsCalculationService::class, function ($mock) {
        $mock->shouldReceive('isVersionSafeToUpdate')->once()->with(44)->andReturnFalse();
        $mock->shouldReceive('getVersionDataLevel')->once()->with(44)->andReturn(CharacterStatsCalculationService::DATA_LEVEL_LANGUAGE_ONLY);
        $mock->shouldReceive('getDataLevelDescription')
            ->once()
            ->with(CharacterStatsCalculationService::DATA_LEVEL_LANGUAGE_ONLY)
            ->andReturn('Language statistics only (no character breakdown)');
    });

    $this->artisan('fix:characters', [
        '--version-id' => 44,
        '--step' => 'stats',
        '--dry-run' => true,
    ])
        ->expectsOutput('Processing only version ID: 44')
        ->expectsOutput('Running only step: stats')
        ->expectsOutput('Would skip version 44 - insufficient data level: Language statistics only (no character breakdown)')
        ->assertExitCode(0);
});

it('indexes no game dialogue texts when no games have dialogue', function () {
    $this->artisan('dialogue:index')
        ->expectsOutput('Found 0 games with dialogue to index')
        ->expectsOutput('✓ Indexing complete!')
        ->expectsOutput('  Total entries indexed: 0')
        ->assertExitCode(0);
});

it('reports no dialogue texts for a requested game without indexing', function () {
    $this->artisan('dialogue:index', ['--game' => 123456])
        ->expectsOutput('Indexing dialogue texts for game 123456...')
        ->expectsOutput('No dialogue texts found for game 123456')
        ->assertExitCode(0);
});
