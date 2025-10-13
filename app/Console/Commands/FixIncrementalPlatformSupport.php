<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class FixIncrementalPlatformSupport extends Command
{
    protected $signature = 'fix:platforms:incremental-support
                            {--game-id= : Report only a specific game by ID}';

    protected $description = 'Report platform support inconsistencies across game versions (platforms should only be gained, not lost)';

    private array $platforms = ['is_windows', 'is_linux', 'is_mac', 'is_android', 'is_web'];

    private array $platformNames = [
        'is_windows' => 'Win',
        'is_linux' => 'Linux',
        'is_mac' => 'Mac',
        'is_android' => 'Android',
        'is_web' => 'Web',
    ];

    private int $gamesProcessed = 0;

    private int $gamesWithIssues = 0;

    /**
     * Get the console command help text.
     */
    public function getHelp(): string
    {
        return <<<'HELP'
This command analyzes platform support across game versions to ensure consistency.

Platform Logic:
- Once a platform is supported in any version, it should remain supported in all subsequent versions
- The command identifies versions where previously supported platforms are missing
- This is a REPORT-ONLY command that doesn't make any changes

Output:
- Table showing game versions and their platform support
- Missing platforms highlighted in red
- Latest version indicator
- Summary of games with issues

Platforms checked: Windows, Linux, Mac, Android, Web

Use this command:
- Before releasing new game versions
- During platform support audits
- When investigating platform availability issues
- As part of quality assurance processes

Examples:
  php artisan fix:platforms:incremental-support
  php artisan fix:platforms:incremental-support --game-id=123
HELP;
    }

    public function handle(): int
    {
        $this->info('Analyzing platform support consistency across game versions...');
        $this->line('');

        $query = Game::query()
            ->whereHas('gameVersions', function ($q) {
                $q->where('is_latest', false); // Only process games with multiple versions
            })
            ->with([
                'gameVersions' => function ($q) {
                    $q->orderBy('published_at');
                },
            ]);

        if ($gameId = $this->option('game-id')) {
            $query->where('id', (int) $gameId);
        }

        $games = $query->get();

        if ($games->isEmpty()) {
            $this->info('No games found to analyze.');

            return SymfonyCommand::SUCCESS;
        }

        foreach ($games as $game) {
            $this->analyzeGame($game);
        }

        $this->displaySummary();

        return SymfonyCommand::SUCCESS;
    }

    private function analyzeGame(Game $game): void
    {
        $this->gamesProcessed++;

        $versions = $game->gameVersions->sortBy('published_at');

        if ($versions->count() < 2) {
            return;
        }

        $cumulativePlatforms = [
            'is_windows' => false,
            'is_linux' => false,
            'is_mac' => false,
            'is_android' => false,
            'is_web' => false,
        ];

        $versionIssues = [];
        $hasIssues = false;

        foreach ($versions as $version) {
            $currentPlatforms = [
                'is_windows' => $version->is_windows,
                'is_linux' => $version->is_linux,
                'is_mac' => $version->is_mac,
                'is_android' => $version->is_android,
                'is_web' => $version->is_web,
            ];

            // Update cumulative platforms (once a platform is supported, it stays supported)
            foreach ($this->platforms as $platform) {
                if ($currentPlatforms[$platform]) {
                    $cumulativePlatforms[$platform] = true;
                }
            }

            // Check if current version has fewer platforms than it should
            $missingPlatforms = [];
            foreach ($this->platforms as $platform) {
                if ($cumulativePlatforms[$platform] && ! $currentPlatforms[$platform]) {
                    $missingPlatforms[] = $platform;
                }
            }

            // Store version info for table display
            $versionIssues[] = [
                'version' => $version->version,
                'is_latest' => $version->is_latest,
                'platforms' => $currentPlatforms,
                'missing_platforms' => $missingPlatforms,
                'has_issues' => ! empty($missingPlatforms),
            ];

            if (! empty($missingPlatforms)) {
                $hasIssues = true;
            }
        }

        // Display games with issues and count them
        if ($hasIssues) {
            $this->gamesWithIssues++;
            $this->displayGameTable($game, $versionIssues);
        }
    }

    private function displayGameTable(Game $game, array $versionIssues): void
    {
        $this->warn("Game: {$game->name} (ID: {$game->id})");

        $headers = ['Version', 'Latest', 'Win', 'Linux', 'Mac', 'Android', 'Web', 'Issues'];
        $rows = [];

        foreach ($versionIssues as $versionInfo) {
            $platforms = $versionInfo['platforms'];
            $missingPlatforms = $versionInfo['missing_platforms'];

            $row = [
                $versionInfo['version'],
                $versionInfo['is_latest'] ? '✓' : '',
                $this->formatPlatformCell($platforms['is_windows'], in_array('is_windows', $missingPlatforms)),
                $this->formatPlatformCell($platforms['is_linux'], in_array('is_linux', $missingPlatforms)),
                $this->formatPlatformCell($platforms['is_mac'], in_array('is_mac', $missingPlatforms)),
                $this->formatPlatformCell($platforms['is_android'], in_array('is_android', $missingPlatforms)),
                $this->formatPlatformCell($platforms['is_web'], in_array('is_web', $missingPlatforms)),
                $versionInfo['has_issues'] ? $this->formatMissingPlatforms($missingPlatforms) : '',
            ];

            $rows[] = $row;
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    private function formatPlatformCell(bool $supported, bool $missing): string
    {
        if ($missing) {
            return '<fg=red>✗</fg=red>';
        }

        return $supported ? '<fg=green>✓</fg=green>' : '';
    }

    private function formatMissingPlatforms(array $missingPlatforms): string
    {
        $names = array_map(fn ($platform) => $this->platformNames[$platform], $missingPlatforms);

        return '<fg=red>' . implode(', ', $names) . '</fg=red>';
    }

    private function displaySummary(): void
    {
        $this->info('');
        $this->info('=== SUMMARY ===');
        $this->info("Games analyzed: {$this->gamesProcessed}");
        $this->info("Games with platform issues: {$this->gamesWithIssues}");

        if ($this->gamesWithIssues === 0) {
            $this->info('<fg=green>No platform support inconsistencies found!</fg=green>');
        } else {
            $this->warn("Found platform support inconsistencies in {$this->gamesWithIssues} games.");
            $this->line('');
            $this->line('Legend:');
            $this->line('  <fg=green>✓</fg=green> = Platform supported');
            $this->line('  <fg=red>✗</fg=red> = Platform should be supported but is missing');
            $this->line('  (empty) = Platform not supported');
        }
    }
}
