<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneLocalGameArchives extends Command
{
    protected $signature = 'games:prune-local-archives
        {--game-id= : Limit to a single game}
        {--force : Delete instead of reporting what would be deleted}';

    protected $description = 'Remove locally stored game archives; archives belong in DenKit Stash';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');
        if ($dryRun) {
            $this->info('Dry run. Pass --force to delete.');
        }

        $removed = 0;
        $bytes = 0;

        foreach ($this->storedVersionDirectories() as [$gameId, $versionId, $size, $path]) {
            if ($this->option('game-id') && (int) $this->option('game-id') !== $gameId) {
                continue;
            }

            $label = "{$path} (" . $this->humanBytes($size) . ')';

            if ($dryRun) {
                $this->line("  {$label}: would delete");
            } else {
                Storage::deleteDirectory($path);
                $this->line("  {$label}: deleted");
            }

            $removed++;
            $bytes += $size;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d archive director%s (%s).',
            $dryRun ? 'Would reclaim' : 'Reclaimed',
            $removed,
            $removed === 1 ? 'y' : 'ies',
            $this->humanBytes($bytes)
        ));

        if (! $dryRun && $removed > 0) {
            Log::info('Pruned local game archives', ['directories' => $removed, 'bytes' => $bytes]);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: int, 1: int, 2: int, 3: string}>
     */
    private function storedVersionDirectories(): array
    {
        $directories = [];

        foreach (Storage::directories('games') as $gameDirectory) {
            $gameId = (int) basename($gameDirectory);
            if ($gameId <= 0) {
                continue;
            }

            foreach (Storage::directories($gameDirectory) as $versionDirectory) {
                $versionId = (int) basename($versionDirectory);
                if ($versionId <= 0) {
                    continue;
                }

                $size = 0;
                foreach (Storage::files($versionDirectory) as $file) {
                    $size += Storage::size($file);
                }

                if ($size > 0) {
                    $directories[] = [$gameId, $versionId, $size, $versionDirectory];
                }
            }
        }

        return $directories;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }

        return round($bytes / 1048576) . ' MB';
    }
}
