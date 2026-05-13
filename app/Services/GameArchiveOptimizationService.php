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
    private const METADATA_FILENAME = '.fvn-archive-metadata.json';

    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    private const AUDIO_EXTENSIONS = ['wav', 'flac', 'mp3'];

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
            $originalFileInventory = $this->archiveFileInventory($workDir);
            $previousOptimizedContext = $this->previousOptimizedArchiveContext($gameId, $versionId);
            $gameDir = $this->findGameDirectory($workDir);
            if ($gameDir === null) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Could not find a Ren\'Py game directory',
                    'original_path' => $archivePath,
                    'original_size' => $originalSize,
                ];
            }

            $contentDir = $gameDir.'/game';

            $rpaFiles = $this->filesWithExtensions($contentDir, ['rpa']);
            $this->reportProgress($progress, sprintf('Unpacking %d RPA file(s)', count($rpaFiles)));
            $this->unpackRpaFiles($rpaFiles, $contentDir);

            $rpycFiles = $this->filesWithExtensions($contentDir, ['rpyc']);
            $this->reportProgress($progress, sprintf('Decompiling missing RPY sources from %d RPYC file(s)', count($rpycFiles)));
            $rpycDecompileFailures = $this->decompileRpycFiles($rpycFiles, $progress);

            $imageResult = $this->optimizeImages($contentDir, $gameDir, $workDir, $previousOptimizedContext, $progress);
            $audioResult = $this->optimizeAudio($contentDir, $gameDir, $workDir, $previousOptimizedContext, $progress);
            $this->reportProgress($progress, 'Updating script references');
            $referencesUpdated = $this->replaceScriptReferences(
                $contentDir,
                array_merge($imageResult['replacements'], $audioResult['replacements'])
            );

            $this->writeOptimizationMetadata(
                $workDir,
                $archivePath,
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
        $archive = collect($files)
            ->first(fn (string $file): bool => str_contains(pathinfo($file, PATHINFO_FILENAME), '.optimized'));

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

    private function findGameDirectory(string $basePath): ?string
    {
        if (File::isDirectory($basePath.'/game')) {
            return $basePath;
        }

        return collect(File::directories($basePath))
            ->first(fn (string $dir): bool => File::isDirectory($dir.'/game'));
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

    /**
     * @param  array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null  $previousOptimizedContext
     * @return array{optimized: int, reused: int, replacements: array<string, string>}
     */
    private function optimizeImages(
        string $contentDir,
        string $gameDir,
        string $sourceDir,
        ?array $previousOptimizedContext,
        ?callable $progress = null
    ): array {
        $optimized = 0;
        $reused = 0;
        $replacements = [];
        $files = $this->filesWithExtensions($contentDir, self::IMAGE_EXTENSIONS);
        $total = count($files);
        $processed = 0;

        $this->reportProgress($progress, sprintf('Optimizing %d image file(s)', $total));

        foreach ($files as $file) {
            $processed++;
            $target = preg_replace('/\.(png|jpe?g)$/i', '.webp', $file);
            if ($target === null || $target === $file) {
                $this->reportMediaProgress($progress, 'images', $processed, $total, $optimized);

                continue;
            }

            if ($this->reusePreviousOptimizedMedia($file, $target, $sourceDir, $gameDir, $previousOptimizedContext)) {
                $replacements[$this->relativeGamePath($gameDir, $file)] = $this->relativeGamePath($gameDir, $target);
                File::delete($file);
                $optimized++;
                $reused++;
                $this->reportMediaProgress($progress, 'images', $processed, $total, $optimized);

                continue;
            }

            $process = new Process([
                $this->binary('magick') ?? $this->binary('convert') ?? 'magick',
                $file,
                '-strip',
                '-quality',
                '78',
                $target,
            ]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful() || ! File::exists($target) || File::size($target) >= File::size($file)) {
                File::delete($target);
                $this->reportMediaProgress($progress, 'images', $processed, $total, $optimized);

                continue;
            }

            $replacements[$this->relativeGamePath($gameDir, $file)] = $this->relativeGamePath($gameDir, $target);
            File::delete($file);
            $optimized++;
            $this->reportMediaProgress($progress, 'images', $processed, $total, $optimized);
        }

        return ['optimized' => $optimized, 'reused' => $reused, 'replacements' => $replacements];
    }

    /**
     * @param  array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null  $previousOptimizedContext
     * @return array{optimized: int, reused: int, replacements: array<string, string>}
     */
    private function optimizeAudio(
        string $contentDir,
        string $gameDir,
        string $sourceDir,
        ?array $previousOptimizedContext,
        ?callable $progress = null
    ): array {
        $optimized = 0;
        $reused = 0;
        $replacements = [];
        $files = $this->filesWithExtensions($contentDir, self::AUDIO_EXTENSIONS);
        $total = count($files);
        $processed = 0;

        $this->reportProgress($progress, sprintf('Optimizing %d audio file(s)', $total));

        foreach ($files as $file) {
            $processed++;
            $target = preg_replace('/\.(wav|flac|mp3)$/i', '.ogg', $file);
            if ($target === null || $target === $file) {
                $this->reportMediaProgress($progress, 'audio files', $processed, $total, $optimized);

                continue;
            }

            if ($this->reusePreviousOptimizedMedia($file, $target, $sourceDir, $gameDir, $previousOptimizedContext)) {
                $replacements[$this->relativeGamePath($gameDir, $file)] = $this->relativeGamePath($gameDir, $target);
                File::delete($file);
                $optimized++;
                $reused++;
                $this->reportMediaProgress($progress, 'audio files', $processed, $total, $optimized);

                continue;
            }

            $process = new Process([
                $this->binary('ffmpeg') ?? 'ffmpeg',
                '-y',
                '-i',
                $file,
                '-map_metadata',
                '-1',
                '-vn',
                '-c:a',
                'libvorbis',
                '-q:a',
                '4',
                $target,
            ]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful() || ! File::exists($target) || File::size($target) >= File::size($file)) {
                File::delete($target);
                $this->reportMediaProgress($progress, 'audio files', $processed, $total, $optimized);

                continue;
            }

            $replacements[$this->relativeGamePath($gameDir, $file)] = $this->relativeGamePath($gameDir, $target);
            File::delete($file);
            $optimized++;
            $this->reportMediaProgress($progress, 'audio files', $processed, $total, $optimized);
        }

        return ['optimized' => $optimized, 'reused' => $reused, 'replacements' => $replacements];
    }

    /**
     * @param  array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null  $previousOptimizedContext
     */
    private function reusePreviousOptimizedMedia(
        string $sourcePath,
        string $targetPath,
        string $sourceDir,
        string $gameDir,
        ?array $previousOptimizedContext
    ): bool {
        if ($previousOptimizedContext === null) {
            return false;
        }

        $relativeSourcePath = $this->relativeArchivePath($sourceDir, $sourcePath);
        $relativeGameSourcePath = $this->relativeGamePath($gameDir, $sourcePath);
        $previousSourceHash = $previousOptimizedContext['source_hashes'][$relativeGameSourcePath]
            ?? $previousOptimizedContext['source_hashes'][$relativeSourcePath]
            ?? null;
        if ($previousSourceHash === null || $previousSourceHash !== hash_file('sha256', $sourcePath)) {
            return false;
        }

        $relativeTargetPath = $previousOptimizedContext['target_paths'][$relativeGameSourcePath]
            ?? $this->relativeArchivePath($sourceDir, $targetPath);
        $previousTargetPath = $previousOptimizedContext['extract_path'].'/'.$relativeTargetPath;
        if (! File::isFile($previousTargetPath) || File::size($previousTargetPath) >= File::size($sourcePath)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($targetPath));

        return File::copy($previousTargetPath, $targetPath);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceScriptReferences(string $contentDir, array $replacements): int
    {
        if (empty($replacements)) {
            return 0;
        }

        $basenameCounts = [];
        foreach (array_keys($replacements) as $oldPath) {
            $basenameCounts[basename($oldPath)] = ($basenameCounts[basename($oldPath)] ?? 0) + 1;
        }

        $updated = 0;
        foreach ($this->filesWithExtensions($contentDir, ['rpy']) as $scriptFile) {
            $contents = File::get($scriptFile);
            $original = $contents;

            foreach ($replacements as $oldPath => $newPath) {
                $contents = str_replace([$oldPath, 'game/'.$oldPath], [$newPath, 'game/'.$newPath], $contents);

                $oldBasename = basename($oldPath);
                if (($basenameCounts[$oldBasename] ?? 0) === 1) {
                    $contents = str_replace(
                        ['"'.$oldBasename.'"', "'".$oldBasename."'"],
                        ['"'.basename($newPath).'"', "'".basename($newPath)."'"],
                        $contents
                    );
                }
            }

            if ($contents !== $original) {
                File::put($scriptFile, $contents);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    private function filesWithExtensions(string $dir, array $extensions): array
    {
        $files = [];
        $extensionLookup = array_fill_keys(array_map('strtolower', $extensions), true);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (isset($extensionLookup[strtolower($file->getExtension())])) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function reportMediaProgress(?callable $progress, string $label, int $processed, int $total, int $optimized): void
    {
        if ($progress === null || $processed === 0 || ($processed % 100 !== 0 && $processed !== $total)) {
            return;
        }

        $progress(sprintf('Processed %d/%d %s (%d kept smaller)', $processed, $total, $label, $optimized));
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
     * @return array<int, array{path: string, size: int, sha256: string}>
     */
    private function archiveFileInventory(string $sourceDir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $files[] = [
                'path' => $this->relativeArchivePath($sourceDir, $path),
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $path) ?: '',
            ];
        }

        usort($files, fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $files;
    }

    /**
     * @param  array<int, array{path: string, size: int, sha256: string}>  $originalFileInventory
     * @param  array<string, string>  $mediaReplacements
     */
    private function writeOptimizationMetadata(
        string $workDir,
        string $archivePath,
        int $originalSize,
        array $originalFileInventory,
        array $mediaReplacements
    ): void {
        $metadata = [
            'schema' => 'fvn.archive_optimization.v1',
            'generated_at' => now()->toIso8601String(),
            'optimized_by' => 'fvn.li archive optimizer',
            'original_archive' => [
                'filename' => basename($archivePath),
                'format' => $this->archiveExtension($archivePath),
                'size' => $originalSize,
                'sha256' => hash_file('sha256', $archivePath) ?: '',
            ],
            'original_files' => $originalFileInventory,
            'optimized_files' => $this->archiveFileInventory($workDir),
            'media_replacements' => $mediaReplacements,
        ];

        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode archive optimization metadata');
        }

        File::put($workDir.'/'.self::METADATA_FILENAME, $json."\n");
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

            $metadata = $this->readExtractedOptimizationMetadata($extractPath);
            if (! is_array($metadata)) {
                File::deleteDirectory($extractPath);

                continue;
            }

            $sourceHashes = $this->sourceHashesFromMetadata($metadata);
            $targetPaths = $this->targetPathsFromMetadata($metadata);
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

    private function readExtractedOptimizationMetadata(string $extractPath): ?array
    {
        $metadataPath = $extractPath.'/'.self::METADATA_FILENAME;
        if (! File::isFile($metadataPath)) {
            return null;
        }

        $metadata = json_decode(File::get($metadataPath), true);
        if (! is_array($metadata) || ($metadata['schema'] ?? null) !== 'fvn.archive_optimization.v1') {
            return null;
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function sourceHashesFromMetadata(array $metadata): array
    {
        $sourceHashes = [];
        foreach (($metadata['original_files'] ?? []) as $file) {
            if (! is_array($file) || ! isset($file['path'], $file['sha256'])) {
                continue;
            }

            $sourceHashes[(string) $file['path']] = (string) $file['sha256'];
            if (($gamePath = $this->metadataGamePath((string) $file['path'])) !== null) {
                $sourceHashes[$gamePath] = (string) $file['sha256'];
            }
        }

        return $sourceHashes;
    }

    /**
     * @return array<string, string>
     */
    private function targetPathsFromMetadata(array $metadata): array
    {
        $optimizedFiles = array_values(array_filter(
            $metadata['optimized_files'] ?? [],
            fn (mixed $file): bool => is_array($file) && isset($file['path'])
        ));
        $targetPaths = [];

        foreach (($metadata['media_replacements'] ?? []) as $sourcePath => $targetPath) {
            if (! is_string($sourcePath) || ! is_string($targetPath)) {
                continue;
            }

            $archiveTargetPath = collect($optimizedFiles)
                ->map(fn (array $file): string => (string) $file['path'])
                ->first(fn (string $path): bool => $path === 'game/'.$targetPath || str_ends_with($path, '/game/'.$targetPath));

            if ($archiveTargetPath !== null) {
                $targetPaths[$sourcePath] = $archiveTargetPath;
            }
        }

        return $targetPaths;
    }

    private function metadataGamePath(string $archivePath): ?string
    {
        if (str_starts_with($archivePath, 'game/')) {
            return substr($archivePath, strlen('game/'));
        }

        $needle = '/game/';
        $position = strpos($archivePath, $needle);
        if ($position === false) {
            return null;
        }

        return substr($archivePath, $position + strlen($needle));
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

    private function relativeGamePath(string $gameDir, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($gameDir.'/game/'))), '/');
    }

    private function relativeArchivePath(string $sourceDir, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($sourceDir) + 1)), '/');
    }
}
