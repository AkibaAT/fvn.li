<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CharacterNullAssignmentService;
use App\Services\CharacterSpecialAssignmentService;
use App\Services\CharacterStatsCalculationService;
use App\Services\CharacterVersionReferenceService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class FixCharacters extends Command
{
    protected $signature = 'fix:characters
        {--game-id= : ID of the specific game to process}
        {--version-id= : ID of the specific version to process (overrides game-id)}
        {--step= : Run only a specific step (null-assignments|special-assignments|stats|version-references)}
        {--dry-run : Show what would be done without making changes}
        {--character= : Process only a specific special character (extend, centered)}';

    protected $description = 'Comprehensive character data fixes: NULL assignments, special characters, statistics, and version references';

    public function __construct(
        private readonly CharacterNullAssignmentService $nullAssignmentService,
        private readonly CharacterSpecialAssignmentService $specialAssignmentService,
        private readonly CharacterStatsCalculationService $statsService,
        private readonly CharacterVersionReferenceService $versionReferenceService
    ) {
        parent::__construct();
    }

    /**
     * Get the console command help text.
     */
    public function getHelp(): string
    {
        return <<<'HELP'
This command performs comprehensive character maintenance in the correct order:

1. NULL Assignments: Assigns dialogue lines with NULL character_id to narrator characters
2. Special Assignments: Fixes 'extend' and 'centered' character assignments to previous line's character
3. Statistics: Recalculates character and language statistics from dialogue lines
4. Version References: Updates first_seen/last_seen versions, creates missing stats, deletes orphaned characters

The steps run in dependency order to ensure data consistency. You can run individual steps or all steps together.

Data Completeness Protection: Only versions with full dialogue line details will have statistics updated.
This protects versions with incomplete data (e.g., Godot games with language totals only).

Options:
  --game-id=ID        Process only the specified game
  --version-id=ID     Process only the specified version (overrides --game-id)
  --step=STEP         Run only a specific step
  --dry-run           Show what would be done without making changes
  --character=NAME    Process only a specific special character (extend, centered)

Examples:
  php artisan fix:characters                                    # Fix all character issues for all games
  php artisan fix:characters --game-id=138                     # Fix all issues for game 138
  php artisan fix:characters --step=null-assignments           # Fix only NULL assignments
  php artisan fix:characters --step=stats --version-id=1734    # Recalculate stats for version 1734
  php artisan fix:characters --dry-run                         # Preview all changes
HELP;
    }

    public function handle(): int
    {
        $gameId = $this->option('game-id') ? (int) $this->option('game-id') : null;
        $versionId = $this->option('version-id') ? (int) $this->option('version-id') : null;
        $step = $this->option('step');
        $dryRun = $this->option('dry-run');
        $specificCharacter = $this->option('character');

        // Version ID overrides game ID
        if ($versionId) {
            $gameId = null;
        }

        $this->info('Starting comprehensive character fixes...');

        if ($gameId) {
            $this->info("Processing only game ID: {$gameId}");
        } elseif ($versionId) {
            $this->info("Processing only version ID: {$versionId}");
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made');
        }

        if ($step) {
            $this->info("Running only step: {$step}");
        }

        // Show data completeness protection info
        $this->info('Data completeness protection: Only versions with full dialogue line details will be updated');
        $this->info('This protects versions with incomplete data (e.g., Godot games with language totals only)');

        $results = [];

        // Step 1: Fix NULL character assignments
        if (! $step || $step === 'null-assignments') {
            $this->info('');
            $this->info('=== Step 1: Fixing NULL Character Assignments ===');
            $results['null_assignments'] = $this->nullAssignmentService->fixNullCharacterAssignments($gameId, $dryRun);
            $this->displayNullAssignmentResults($results['null_assignments']);
        }

        // Step 2: Fix special character assignments
        if (! $step || $step === 'special-assignments') {
            $this->info('');
            $this->info('=== Step 2: Fixing Special Character Assignments ===');
            $results['special_assignments'] = $this->specialAssignmentService->fixSpecialCharacterAssignments($gameId, $specificCharacter, $dryRun);
            $this->displaySpecialAssignmentResults($results['special_assignments']);
        }

        // Step 3: Recalculate character statistics
        if (! $step || $step === 'stats') {
            $this->info('');
            $this->info('=== Step 3: Recalculating Character Statistics ===');
            $results['stats'] = $this->recalculateStats($gameId, $versionId, $dryRun);
            $this->displayStatsResults($results['stats']);
        }

        // Step 4: Fix version references and cleanup
        if (! $step || $step === 'version-references') {
            $this->info('');
            $this->info('=== Step 4: Fixing Version References and Cleanup ===');
            $results['version_references'] = $this->versionReferenceService->fixVersionReferences($gameId, $dryRun);
            $this->displayVersionReferenceResults($results['version_references']);
        }

        // Display overall summary
        $this->info('');
        $this->info('=== Overall Summary ===');
        $this->displayOverallSummary($results, $dryRun);

        $this->info('');
        $this->info('Character fixes completed successfully!');

        return SymfonyCommand::SUCCESS;
    }

    private function recalculateStats(?int $gameId, ?int $versionId, bool $dryRun): array
    {
        if ($versionId) {
            // Recalculate for specific version
            if ($dryRun) {
                if ($this->statsService->isVersionSafeToUpdate($versionId)) {
                    $this->info("Would recalculate stats for version {$versionId}");
                } else {
                    $dataLevel = $this->statsService->getVersionDataLevel($versionId);
                    $description = $this->statsService->getDataLevelDescription($dataLevel);
                    $this->info("Would skip version {$versionId} - insufficient data level: {$description}");
                }

                return ['stats_updated' => 0, 'versions_processed' => 1];
            }

            $statsUpdated = $this->statsService->calculateAndSaveStatsForVersionSafe($versionId);

            return ['stats_updated' => $statsUpdated, 'versions_processed' => 1];
        }

        // Recalculate for all versions with issues (narrator characters and zero stats)
        $statsToUpdate = $this->statsService->getStatsWithIssues($gameId);
        $safeStatsToUpdate = $this->statsService->filterSafeStatsToUpdate($statsToUpdate);

        if ($dryRun) {
            $this->info("Would recalculate {$safeStatsToUpdate->count()} character statistics");
            if ($statsToUpdate->count() > $safeStatsToUpdate->count()) {
                $skippedCount = $statsToUpdate->count() - $safeStatsToUpdate->count();
                $this->info("Would skip {$skippedCount} stats entries with insufficient data level");
            }

            return ['stats_updated' => $safeStatsToUpdate->count(), 'versions_processed' => 0];
        }

        // Calculate new values for all safe stats
        $calculatedResults = [];
        foreach ($safeStatsToUpdate as $stat) {
            $calculated = $this->statsService->calculateStatsForCharacter(
                $stat->game_version_id,
                $stat->character_id,
                $stat->iso_code
            );
            $calculatedResults[] = ['stat' => $stat, 'calculated' => $calculated];
        }

        // Apply the updates
        $statsUpdated = $this->statsService->updateCharacterStatsSafe($calculatedResults, false);

        return ['stats_updated' => $statsUpdated, 'versions_processed' => 0];
    }

    private function displayNullAssignmentResults(array $results): void
    {
        $this->info("✓ Fixed {$results['lines_updated']} NULL character assignments");
        $this->info("✓ Created {$results['narrator_characters_created']} narrator characters");
        $this->info("✓ Processed {$results['games_processed']} games");
    }

    private function displaySpecialAssignmentResults(array $results): void
    {
        $this->info("✓ Reassigned {$results['lines_reassigned']} special character lines");
        $this->info("✓ Processed {$results['versions_processed']} versions");
        $this->info("✓ Handled {$results['characters_processed']} special character types");
    }

    private function displayStatsResults(array $results): void
    {
        $this->info("✓ Updated {$results['stats_updated']} character statistics");
        $this->info("✓ Processed {$results['versions_processed']} versions");
    }

    private function displayVersionReferenceResults(array $results): void
    {
        $this->info("✓ Updated {$results['characters_updated']} character version references");
        $this->info("✓ Created {$results['stats_entries_created']} missing stats entries");
        $this->info("✓ Deleted {$results['characters_deleted']} orphaned characters");
        $this->info("✓ Processed {$results['characters_processed']} characters total");
    }

    private function displayOverallSummary(array $results, bool $dryRun): void
    {
        $action = $dryRun ? 'Would have' : 'Successfully';

        $totalLines = ($results['null_assignments']['lines_updated'] ?? 0) +
                     ($results['special_assignments']['lines_reassigned'] ?? 0);
        $totalStats = $results['stats']['stats_updated'] ?? 0;
        $totalCharacters = $results['version_references']['characters_updated'] ?? 0;

        $this->info("{$action} processed:");
        $this->info("  • {$totalLines} dialogue line assignments fixed");
        $this->info("  • {$totalStats} character statistics recalculated");
        $this->info("  • {$totalCharacters} character version references updated");

        if (isset($results['version_references']['characters_deleted'])) {
            $this->info("  • {$results['version_references']['characters_deleted']} orphaned characters cleaned up");
        }
    }
}
