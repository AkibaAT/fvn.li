<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class GameArchiveOptimizationService
{
    public function __construct(
        private readonly GameStatsService $statsService
    ) {}

    /**
     * @return array{
     *     status: string,
     *     reason?: string,
     *     original_path?: string,
     *     optimized_path?: string,
     *     original_size?: int,
     *     optimized_size?: int,
     *     saved_bytes?: int,
     *     rpa_files?: int,
     *     rpyc_files?: int,
     *     images_optimized?: int,
     *     audio_optimized?: int,
     *     images_reused?: int,
     *     audio_reused?: int,
     *     references_updated?: int,
     *     rpyc_decompile_failed?: int
     * }
     */
    public function optimizeStoredArchive(
        int $gameId,
        int $versionId,
        bool $dryRun = true,
        bool $replace = false,
        bool $force = false,
        bool $validate = true,
        ?callable $progress = null
    ): array {
        $storagePath = "games/{$gameId}/{$versionId}";
        $archivePath = $this->storedArchivePath($storagePath);

        if ($archivePath === null) {
            return [
                'status' => 'skipped',
                'reason' => 'No stored archive found',
            ];
        }

        $originalSize = File::size($archivePath);
        $workDir = storage_path('app/temp/'.uniqid('archive_opt_', true));
        $optimizedArchiveBase = tempnam(sys_get_temp_dir(), 'optimized_');
        $previousOptimizedContext = null;

        if ($optimizedArchiveBase === false) {
            throw new RuntimeException('Could not create temporary optimized archive');
        }

        File::delete($optimizedArchiveBase);
        $optimizedArchive = $optimizedArchiveBase.'.'.$this->archiveExtension($archivePath);

        try {
            File::makeDirectory($workDir, 0755, true);

            $this->reportProgress($progress, 'Extracting archive');
            $this->extractArchive($archivePath, $workDir);
            if ($this->archiveContainsUnsafeEntries($workDir)) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Archive contains linked or special content and cannot be optimized safely',
                    'original_path' => $archivePath,
                    'original_size' => $originalSize,
                ];
            }

            $gameDir = $this->findGameDirectory($workDir);
            if ($gameDir === null) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Could not find a Ren\'Py game directory',
                    'original_path' => $archivePath,
                    'original_size' => $originalSize,
                ];
            }

            $metadataService = app(ArchiveOptimizationMetadataService::class);
            $originalFileInventory = $metadataService->inventory($workDir);
            $previousOptimizedContext = $this->previousOptimizedArchiveContext($gameId, $versionId);
            $contentDir = $gameDir.'/game';

            $rpaFiles = app(ArchiveMediaOptimizer::class)->filesWithExtensions($contentDir, ['rpa']);
            $this->reportProgress($progress, sprintf('Unpacking %d RPA file(s)', count($rpaFiles)));
            $this->unpackRpaFiles($rpaFiles, $contentDir);

            $rpycFiles = app(ArchiveMediaOptimizer::class)->filesWithExtensions($contentDir, ['rpyc']);
            $this->reportProgress($progress, sprintf('Decompiling missing RPY sources from %d RPYC file(s)', count($rpycFiles)));
            $rpycDecompileFailures = $this->decompileRpycFiles($rpycFiles, $progress);

            $mediaOptimizer = app(ArchiveMediaOptimizer::class);
            $imageResult = $mediaOptimizer->optimizeImages($contentDir, $gameDir, $workDir, $previousOptimizedContext, $progress);
            $audioResult = $mediaOptimizer->optimizeAudio($contentDir, $gameDir, $workDir, $previousOptimizedContext, $progress);
            $this->reportProgress($progress, 'Updating script references');
            $referencesUpdated = $mediaOptimizer->replaceScriptReferences(
                $contentDir,
                array_merge($imageResult['replacements'], $audioResult['replacements'])
            );

            $metadataService->write(
                $workDir,
                $archivePath,
                $this->archiveExtension($archivePath),
                $originalSize,
                $originalFileInventory,
                array_merge($imageResult['replacements'], $audioResult['replacements'])
            );

            $this->reportProgress($progress, 'Repacking optimized archive');
            $this->createArchiveFromDirectory($workDir, $optimizedArchive, $archivePath);
            $optimizedSize = File::size($optimizedArchive);
            $savedBytes = $originalSize - $optimizedSize;

            if ($validate && $this->statsService->extractGameStats($optimizedArchive) === null) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Optimized archive did not pass stats extraction',
                    'original_path' => $archivePath,
                    'optimized_path' => $optimizedArchive,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'saved_bytes' => $savedBytes,
                    'rpa_files' => count($rpaFiles),
                    'rpyc_files' => count($rpycFiles),
                    'images_optimized' => $imageResult['optimized'],
                    'audio_optimized' => $audioResult['optimized'],
                    'images_reused' => $imageResult['reused'],
                    'audio_reused' => $audioResult['reused'],
                    'references_updated' => $referencesUpdated,
                    'rpyc_decompile_failed' => $rpycDecompileFailures,
                ];
            }

            if (! $force && $savedBytes <= 0) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Optimized archive would not be smaller',
                    'original_path' => $archivePath,
                    'optimized_path' => $optimizedArchive,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'saved_bytes' => $savedBytes,
                    'rpa_files' => count($rpaFiles),
                    'rpyc_files' => count($rpycFiles),
                    'images_optimized' => $imageResult['optimized'],
                    'audio_optimized' => $audioResult['optimized'],
                    'images_reused' => $imageResult['reused'],
                    'audio_reused' => $audioResult['reused'],
                    'references_updated' => $referencesUpdated,
                    'rpyc_decompile_failed' => $rpycDecompileFailures,
                ];
            }

            $result = [
                'status' => $dryRun ? 'would_optimize' : 'optimized',
                'original_path' => $archivePath,
                'optimized_path' => Storage::path($storagePath.'/'.$this->optimizedFilename($archivePath)),
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'saved_bytes' => $savedBytes,
                'rpa_files' => count($rpaFiles),
                'rpyc_files' => count($rpycFiles),
                'images_optimized' => $imageResult['optimized'],
                'audio_optimized' => $audioResult['optimized'],
                'images_reused' => $imageResult['reused'],
                'audio_reused' => $audioResult['reused'],
                'references_updated' => $referencesUpdated,
                'rpyc_decompile_failed' => $rpycDecompileFailures,
            ];

            if (! $dryRun) {
                Storage::putFileAs($storagePath, $optimizedArchive, $this->optimizedFilename($archivePath));

                if ($replace) {
                    Storage::delete($this->storageRelativePath($archivePath));
                }
            }

            return $result;
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }

            if (is_array($previousOptimizedContext) && File::exists($previousOptimizedContext['extract_path'])) {
                File::deleteDirectory($previousOptimizedContext['extract_path']);
            }

            if (File::exists($optimizedArchive)) {
                File::delete($optimizedArchive);
            }
        }
    }

    private function storedArchivePath(string $storagePath): ?string
    {
        $files = Storage::files($storagePath);
        $archive = collect($files)
            ->first(fn (string $file): bool => ! str_contains(pathinfo($file, PATHINFO_FILENAME), '.optimized'));

        return $archive === null ? null : Storage::path($archive);
    }

    private function optimizedArchivePath(string $storagePath): ?string
    {
        $files = Storage::files($storagePath);
        $optimizedFilenames = collect($files)
            ->reject(fn (string $file): bool => str_contains(pathinfo($file, PATHINFO_FILENAME), '.optimized'))
            ->map(fn (string $file): string => $this->optimizedFilename(Storage::path($file)))
            ->flip();

        $archive = collect($files)
            ->first(fn (string $file): bool => $optimizedFilenames->has(basename($file)));

        return $archive === null ? null : Storage::path($archive);
    }

    private function extractArchive(string $archivePath, string $extractPath): void
    {
        $ext = $this->archiveExtension($archivePath);

        if ($ext === 'tar.gz' || $ext === 'tgz' || $ext === 'tar.bz2' || $ext === 'tbz2') {
            $process = new Process([
                'tar',
                '-x'.($ext === 'tar.gz' || $ext === 'tgz' ? 'z' : 'j'),
                '-f',
                $archivePath,
                '-C',
                $extractPath,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to extract tar archive: '.$process->getErrorOutput());
            }

            return;
        }

        if ($ext === 'zip') {
            $zip = new ZipArchive;
            $result = $zip->open($archivePath);

            if ($result !== true) {
                throw new RuntimeException("Failed to open zip archive: {$result}");
            }

            try {
                if (! $zip->extractTo($extractPath)) {
                    throw new RuntimeException('Failed to extract zip archive');
                }
            } finally {
                $zip->close();
            }

            return;
        }

        if ($ext === 'tar') {
            $process = new Process(['tar', '-xf', $archivePath, '-C', $extractPath]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to extract tar archive: '.$process->getErrorOutput());
            }

            return;
        }

        throw new RuntimeException("Unsupported archive format: {$ext}");
    }

    private function archiveContainsUnsafeEntries(string $path): bool
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                return true;
            }

            if (! $item->isFile() && ! $item->isDir()) {
                return true;
            }

            $stat = @stat($item->getPathname());
            if ($item->isFile() && is_array($stat) && ($stat['nlink'] ?? 1) > 1) {
                return true;
            }
        }

        return false;
    }

    private function findGameDirectory(string $basePath): ?string
    {
        if ($this->hasSafeGameDirectory($basePath, $basePath)) {
            return $basePath;
        }

        return collect(File::directories($basePath))
            ->first(fn (string $dir): bool => $this->hasSafeGameDirectory($dir, $basePath));
    }

    private function hasSafeGameDirectory(string $candidateDir, string $basePath): bool
    {
        $gamePath = $candidateDir.'/game';
        if (! File::isDirectory($gamePath) || is_link($gamePath)) {
            return false;
        }

        $resolvedBase = realpath($basePath);
        $resolvedGame = realpath($gamePath);
        if ($resolvedBase === false || $resolvedGame === false) {
            return false;
        }

        return str_starts_with($resolvedGame, rtrim($resolvedBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    /**
     * @param  array<int, string>  $rpaFiles
     */
    private function unpackRpaFiles(array $rpaFiles, string $extractDir): void
    {
        if (empty($rpaFiles)) {
            return;
        }

        $binary = $this->binary('unrpa') ?? $this->binary('rpatool');
        if ($binary === null) {
            throw new RuntimeException('RPA files found, but no unrpa/rpatool binary is available');
        }

        foreach ($rpaFiles as $rpaFile) {
            $process = str_contains(basename($binary), 'rpatool')
                ? new Process([$binary, '-x', $rpaFile], dirname($rpaFile))
                : new Process([$binary, '-m', '-p', $extractDir, $rpaFile], $extractDir);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to unpack RPA file: '.$process->getErrorOutput());
            }

            File::delete($rpaFile);
        }
    }

    /**
     * @param  array<int, string>  $rpycFiles
     */
    private function decompileRpycFiles(array $rpycFiles, ?callable $progress = null): int
    {
        $missingSources = array_values(array_filter(
            $rpycFiles,
            fn (string $file): bool => ! File::exists(preg_replace('/c$/', '', $file) ?? $file)
        ));

        if (empty($missingSources)) {
            return 0;
        }

        $binary = $this->binary('unrpyc') ?? $this->binary('rpycdec');
        if ($binary === null) {
            throw new RuntimeException('RPYC files without matching RPY sources found, but no unrpyc/rpycdec binary is available');
        }

        $failed = 0;
        foreach ($missingSources as $rpycFile) {
            $process = str_contains(basename($binary), 'rpycdec')
                ? new Process([$binary, 'decompile', $rpycFile], dirname($rpycFile))
                : new Process([$binary, '--clobber', $rpycFile], dirname($rpycFile));
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $failed++;
                $this->reportProgress($progress, sprintf(
                    'Skipped decompiling %s: %s',
                    basename($rpycFile),
                    $this->summarizeProcessFailure($process)
                ));
                Log::warning('Failed to decompile RPYC file while optimizing archive', [
                    'path' => $rpycFile,
                    'error' => trim($process->getErrorOutput()) ?: trim($process->getOutput()),
                ]);
            }
        }

        return $failed;
    }

    private function summarizeProcessFailure(Process $process): string
    {
        $output = trim($process->getErrorOutput()) ?: trim($process->getOutput());
        if ($output === '') {
            return 'decompiler exited unsuccessfully';
        }

        $lines = preg_split('/\R/', $output) ?: [];
        $line = collect($lines)
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->last();

        return $line === null ? 'decompiler exited unsuccessfully' : str($line)->limit(180)->toString();
    }

    private function reportProgress(?callable $progress, string $message): void
    {
        if ($progress === null) {
            return;
        }

        $progress($message);
    }

    private function createArchiveFromDirectory(string $sourceDir, string $targetPath, string $originalArchivePath): void
    {
        $extension = $this->archiveExtension($originalArchivePath);

        if ($extension === 'zip') {
            $this->createZipFromDirectory($sourceDir, $targetPath);

            return;
        }

        $entries = $this->topLevelArchiveEntries($sourceDir);
        if (empty($entries)) {
            throw new RuntimeException('Cannot create optimized archive from an empty directory');
        }

        $args = match ($extension) {
            'tar' => ['tar', '-cf', $targetPath, '-C', $sourceDir, '--', ...$entries],
            'tar.gz', 'tgz' => ['tar', '-czf', $targetPath, '-C', $sourceDir, '--', ...$entries],
            'tar.bz2', 'tbz2' => ['tar', '-cjf', $targetPath, '-C', $sourceDir, '--', ...$entries],
            default => throw new RuntimeException("Unsupported archive format: {$extension}"),
        };

        $process = new Process($args);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to create optimized archive: '.$process->getErrorOutput());
        }
    }

    /**
     * @return array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null
     */
    private function previousOptimizedArchiveContext(int $gameId, int $versionId): ?array
    {
        $currentVersion = GameVersion::find($versionId);
        if ($currentVersion === null) {
            return null;
        }

        $previousVersions = GameVersion::query()
            ->where('game_id', $gameId)
            ->where(function ($query) use ($currentVersion): void {
                if ($currentVersion->published_at === null) {
                    $query->where('id', '<', $currentVersion->id);

                    return;
                }

                $query->where('published_at', '<', $currentVersion->published_at)
                    ->orWhere(function ($query) use ($currentVersion): void {
                        $query->where('published_at', $currentVersion->published_at)
                            ->where('id', '<', $currentVersion->id);
                    });
            })
            ->reorder()
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($previousVersions as $previousVersion) {
            $archivePath = $this->optimizedArchivePath("games/{$gameId}/{$previousVersion->id}");
            if ($archivePath === null) {
                continue;
            }

            $extractPath = storage_path('app/temp/'.uniqid('previous_archive_opt_', true));
            File::makeDirectory($extractPath, 0755, true);
            $this->extractArchive($archivePath, $extractPath);

            $metadataService = app(ArchiveOptimizationMetadataService::class);
            $metadata = $metadataService->readExtracted($extractPath);
            if (! is_array($metadata)) {
                File::deleteDirectory($extractPath);

                continue;
            }

            $sourceHashes = $metadataService->sourceHashesFrom($metadata);
            $targetPaths = $metadataService->targetPathsFrom($metadata);
            if ($sourceHashes === []) {
                File::deleteDirectory($extractPath);

                continue;
            }

            return [
                'extract_path' => $extractPath,
                'source_hashes' => $sourceHashes,
                'target_paths' => $targetPaths,
            ];
        }

        return null;
    }

    private function createZipFromDirectory(string $sourceDir, string $targetPath): void
    {
        $zip = new ZipArchive;
        $result = $zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException("Failed to create optimized archive: {$result}");
        }

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $zip->addFile($file->getPathname(), $this->relativeArchivePath($sourceDir, $file->getPathname()));
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function topLevelArchiveEntries(string $sourceDir): array
    {
        $entries = [];

        foreach (new \DirectoryIterator($sourceDir) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $entries[] = $entry->getFilename();
        }

        sort($entries);

        return $entries;
    }

    private function binary(string $name): ?string
    {
        $process = new Process(['which', $name]);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }

    private function optimizedFilename(string $archivePath): string
    {
        $extension = $this->archiveExtension($archivePath);
        $suffixLength = strlen('.'.$extension);
        $basename = basename($archivePath);
        $name = substr($basename, 0, -$suffixLength);

        return $name.'.optimized.'.$extension;
    }

    private function archiveExtension(string $archivePath): string
    {
        $basename = strtolower(basename($archivePath));

        return match (true) {
            str_ends_with($basename, '.tar.gz') => 'tar.gz',
            str_ends_with($basename, '.tgz') => 'tgz',
            str_ends_with($basename, '.tar.bz2') => 'tar.bz2',
            str_ends_with($basename, '.tbz2') => 'tbz2',
            str_ends_with($basename, '.tar') => 'tar',
            str_ends_with($basename, '.zip') => 'zip',
            default => strtolower(pathinfo($archivePath, PATHINFO_EXTENSION)),
        };
    }

    private function storageRelativePath(string $path): string
    {
        return ltrim(str_replace(Storage::path(''), '', $path), '/');
    }

    private function relativeArchivePath(string $sourceDir, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($sourceDir) + 1)), '/');
    }
}
