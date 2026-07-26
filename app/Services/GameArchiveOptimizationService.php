<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use DirectoryIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class GameArchiveOptimizationService
{
    private const RECOMPILE_TIMEOUT_SECONDS = 600;

    public function __construct(
        private readonly GameStatsService $statsService,
        private readonly ?GameArchiveOptimizerDockerRunner $sandboxRunner = null
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
        $previousOptimizedArchivePath = $this->previousOptimizedArchivePath($gameId, $versionId);
        $optimizedArchive = null;

        try {
            $this->reportProgress(
                $progress,
                $this->shouldUseSandbox() ? 'Optimizing archive in sandbox' : 'Optimizing archive locally'
            );
            $optimization = $this->shouldUseSandbox()
                ? $this->sandboxRunner->optimize($archivePath, $previousOptimizedArchivePath)
                : $this->optimizeArchiveFile($archivePath, $previousOptimizedArchivePath, $progress);

            if ($optimization['status'] !== 'optimized' || ! isset($optimization['optimized_path'])) {
                return array_merge($optimization, [
                    'original_path' => $archivePath,
                    'original_size' => $originalSize,
                ]);
            }

            $optimizedArchive = $optimization['optimized_path'];
            $optimizedSize = File::size($optimizedArchive);
            $savedBytes = $originalSize - $optimizedSize;

            // Only whether stats can still be extracted; the contents are not needed.
            if ($validate && ! $this->statsService->canExtractStats($optimizedArchive)) {
                $validationError = $this->statsService->getLastExtractionError();
                $reason = 'Optimized archive did not pass stats extraction';
                if ($validationError !== null && $validationError !== '') {
                    $reason .= ": {$validationError}";
                }

                return [
                    'status' => 'skipped',
                    'reason' => $reason,
                    'original_path' => $archivePath,
                    'optimized_path' => $optimizedArchive,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'saved_bytes' => $savedBytes,
                    'rpa_files' => $optimization['rpa_files'] ?? 0,
                    'rpyc_files' => $optimization['rpyc_files'] ?? 0,
                    'images_optimized' => $optimization['images_optimized'] ?? 0,
                    'audio_optimized' => $optimization['audio_optimized'] ?? 0,
                    'images_reused' => $optimization['images_reused'] ?? 0,
                    'audio_reused' => $optimization['audio_reused'] ?? 0,
                    'references_updated' => $optimization['references_updated'] ?? 0,
                    'rpyc_decompile_failed' => $optimization['rpyc_decompile_failed'] ?? 0,
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
                    'rpa_files' => $optimization['rpa_files'] ?? 0,
                    'rpyc_files' => $optimization['rpyc_files'] ?? 0,
                    'images_optimized' => $optimization['images_optimized'] ?? 0,
                    'audio_optimized' => $optimization['audio_optimized'] ?? 0,
                    'images_reused' => $optimization['images_reused'] ?? 0,
                    'audio_reused' => $optimization['audio_reused'] ?? 0,
                    'references_updated' => $optimization['references_updated'] ?? 0,
                    'rpyc_decompile_failed' => $optimization['rpyc_decompile_failed'] ?? 0,
                ];
            }

            $result = [
                'status' => $dryRun ? 'would_optimize' : 'optimized',
                'original_path' => $archivePath,
                'optimized_path' => Storage::path($storagePath . '/' . $this->optimizedFilename($archivePath)),
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'saved_bytes' => $savedBytes,
                'rpa_files' => $optimization['rpa_files'] ?? 0,
                'rpyc_files' => $optimization['rpyc_files'] ?? 0,
                'images_optimized' => $optimization['images_optimized'] ?? 0,
                'audio_optimized' => $optimization['audio_optimized'] ?? 0,
                'images_reused' => $optimization['images_reused'] ?? 0,
                'audio_reused' => $optimization['audio_reused'] ?? 0,
                'references_updated' => $optimization['references_updated'] ?? 0,
                'rpyc_decompile_failed' => $optimization['rpyc_decompile_failed'] ?? 0,
            ];

            if (! $dryRun) {
                Storage::putFileAs($storagePath, $optimizedArchive, $this->optimizedFilename($archivePath));

                if ($replace) {
                    Storage::delete($this->storageRelativePath($archivePath));
                }
            }

            return $result;
        } finally {
            if (is_string($optimizedArchive) && File::exists($optimizedArchive)) {
                File::delete($optimizedArchive);
            }

            if (is_string($optimizedArchive) && $this->shouldUseSandbox()) {
                $this->deleteSandboxJobDirectory($optimizedArchive);
            }
        }
    }

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
    public function optimizeArchiveFile(string $archivePath, ?string $previousOptimizedArchivePath = null, ?callable $progress = null): array
    {
        $originalSize = File::size($archivePath);
        $tempRoot = $this->optimizerTempRoot();
        $workDir = $tempRoot . '/' . uniqid('archive_opt_', true);
        $optimizedArchiveBase = tempnam($tempRoot, 'optimized_');
        $previousOptimizedContext = null;

        if ($optimizedArchiveBase === false) {
            throw new RuntimeException('Could not create temporary optimized archive');
        }

        File::delete($optimizedArchiveBase);
        $optimizedArchive = $optimizedArchiveBase . '.' . $this->archiveExtension($archivePath);

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
            $previousOptimizedContext = $this->previousOptimizedArchiveContext($previousOptimizedArchivePath);
            $contentDir = $gameDir . '/game';

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
            $mediaReplacements = array_merge($imageResult['replacements'], $audioResult['replacements']);
            $referencesUpdated = $mediaOptimizer->replaceScriptReferences($contentDir, $mediaReplacements);

            if ($mediaReplacements !== []) {
                $this->reportProgress($progress, 'Dropping compiled scripts that shadow rewritten sources');
                $staleScripts = $this->removeShadowingCompiledScripts($contentDir);
                $this->reportProgress($progress, sprintf('Dropped %d compiled script(s)', $staleScripts));

                if ($staleScripts > 0) {
                    $this->reportProgress($progress, 'Recompiling scripts from the rewritten sources');
                    $compiled = $this->recompileScripts($gameDir, $contentDir, $progress);
                    $this->reportProgress($progress, $compiled > 0
                        ? sprintf('Recompiled %d script(s)', $compiled)
                        : 'Scripts ship as source; the game compiles them on first run');
                }
            }

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

            return [
                'status' => 'optimized',
                'original_path' => $archivePath,
                'optimized_path' => $optimizedArchive,
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'saved_bytes' => $originalSize - $optimizedSize,
                'rpa_files' => count($rpaFiles),
                'rpyc_files' => count($rpycFiles),
                'images_optimized' => $imageResult['optimized'],
                'audio_optimized' => $audioResult['optimized'],
                'images_reused' => $imageResult['reused'],
                'audio_reused' => $audioResult['reused'],
                'references_updated' => $referencesUpdated,
                'rpyc_decompile_failed' => $rpycDecompileFailures,
            ];
        } finally {
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }

            if (is_array($previousOptimizedContext) && File::exists($previousOptimizedContext['extract_path'])) {
                File::deleteDirectory($previousOptimizedContext['extract_path']);
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

    private function previousOptimizedArchivePath(int $gameId, int $versionId): ?string
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
            if ($archivePath !== null) {
                return $archivePath;
            }
        }

        return null;
    }

    private function extractArchive(string $archivePath, string $extractPath): void
    {
        $ext = $this->archiveExtension($archivePath);

        if ($ext === 'tar.gz' || $ext === 'tgz' || $ext === 'tar.bz2' || $ext === 'tbz2') {
            $process = new Process([
                'tar',
                '-x' . ($ext === 'tar.gz' || $ext === 'tgz' ? 'z' : 'j'),
                '-f',
                $archivePath,
                '-C',
                $extractPath,
            ]);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to extract tar archive: ' . $process->getErrorOutput());
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
                throw new RuntimeException('Failed to extract tar archive: ' . $process->getErrorOutput());
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
        $gamePath = $candidateDir . '/game';
        if (! File::isDirectory($gamePath) || is_link($gamePath)) {
            return false;
        }

        $resolvedBase = realpath($basePath);
        $resolvedGame = realpath($gamePath);
        if ($resolvedBase === false || $resolvedGame === false) {
            return false;
        }

        return str_starts_with($resolvedGame, rtrim($resolvedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
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
                throw new RuntimeException('Failed to unpack RPA file: ' . $process->getErrorOutput());
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
            fn (string $file): bool => ! $this->hasRpySource($file)
        ));

        if (empty($missingSources)) {
            return 0;
        }

        $binary = $this->binary('unrpyc');
        if ($binary === null) {
            throw new RuntimeException('RPYC files without matching RPY sources found, but no unrpyc binary is available');
        }

        $failed = 0;
        foreach ($missingSources as $rpycFile) {
            $process = new Process([$binary, '--clobber', $rpycFile], dirname($rpycFile));
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

    /**
     * Remove compiled scripts that have a source file beside them.
     *
     * Ren'Py runs the compiled script whenever it is not older than its source,
     * and repacking gives every file the same timestamp. Media references
     * rewritten into the sources would then be ignored in favour of compiled
     * code still pointing at the replaced files.
     *
     * @return int the number of compiled scripts removed
     */
    private function removeShadowingCompiledScripts(string $contentDir): int
    {
        $removed = 0;

        foreach (app(ArchiveMediaOptimizer::class)->filesWithExtensions($contentDir, ['rpyc']) as $compiled) {
            if (! $this->hasRpySource($compiled)) {
                continue;
            }

            File::delete($compiled);
            $removed++;
        }

        return $removed;
    }

    /**
     * Compile the rewritten sources back into bytecode.
     *
     * Ren'Py writes a .rpyc beside every script it loads, so running the title
     * once leaves the archive with compiled scripts that match its sources.
     * The game ships its own runtime, and the SDK stands in when that runtime
     * cannot run here.
     *
     * @return int the number of compiled scripts written
     */
    private function recompileScripts(string $gameDir, string $contentDir, ?callable $progress = null): int
    {
        $commands = $this->recompileCommands($gameDir);
        if ($commands === []) {
            return 0;
        }

        $home = $gameDir . '/.recompile-home';
        File::ensureDirectoryExists($home . '/.renpy/tokens', 0777, true);

        $environment = [
            'HOME' => $home,
            'RENPY_PATH_TO_SAVES' => $home . '/.renpy',
        ];

        $preexistingDebris = $this->runtimeDebris($gameDir, $contentDir);

        try {
            foreach ($commands as [$command, $workingDirectory]) {
                $process = new Process($command, $workingDirectory, $environment);
                $process->setTimeout(self::RECOMPILE_TIMEOUT_SECONDS);

                // Compiling a large script tree can exhaust the sandbox, which
                // kills the runtime. The archive is still worth shipping, so a
                // runtime that dies counts as a failed attempt, not a failure.
                $failure = null;
                try {
                    $process->run();
                } catch (ProcessRuntimeException $exception) {
                    $failure = $exception->getMessage();
                }

                $compiled = $this->fullyCompiledScriptCount($contentDir);
                if ($compiled > 0) {
                    return $compiled;
                }

                $this->reportProgress($progress, sprintf(
                    'Compiling with %s left sources uncompiled: %s',
                    basename((string) $command[0]),
                    $failure ?? $this->summarizeProcessFailure($process, 'runtime exited without compiling')
                ));
            }
        } finally {
            File::deleteDirectory($home);

            foreach ($this->runtimeDebris($gameDir, $contentDir) as $path) {
                if (in_array($path, $preexistingDebris, true)) {
                    continue;
                }

                is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
            }
        }

        return 0;
    }

    /**
     * The number of compiled scripts, counted only when every source has one.
     *
     * A runtime that aborts partway through loading leaves some sources
     * uncompiled, which is reported as a failure so the next runtime is tried.
     */
    private function fullyCompiledScriptCount(string $contentDir): int
    {
        $mediaOptimizer = app(ArchiveMediaOptimizer::class);
        $compiled = $mediaOptimizer->filesWithExtensions($contentDir, ['rpyc']);

        if ($compiled === []) {
            return 0;
        }

        $compiledBasePaths = array_flip(array_map(
            fn (string $path): string => substr($path, 0, -strlen('.rpyc')),
            $compiled
        ));

        foreach ($mediaOptimizer->filesWithExtensions($contentDir, ['rpy']) as $source) {
            if (! isset($compiledBasePaths[substr($source, 0, -strlen('.rpy'))])) {
                return 0;
            }
        }

        return count($compiled);
    }

    /**
     * Paths a Ren'Py run leaves behind that are not part of the release.
     *
     * @return list<string>
     */
    private function runtimeDebris(string $gameDir, string $contentDir): array
    {
        $candidates = [
            $contentDir . '/saves',
            $contentDir . '/cache',
        ];

        foreach ([$gameDir, $contentDir] as $directory) {
            foreach (['log.txt', 'errors.txt', 'traceback.txt'] as $file) {
                $candidates[] = $directory . '/' . $file;
            }
        }

        // Running the game byte-compiles the runtime's own Python modules.
        $candidates = array_merge($candidates, $this->pycachePaths($gameDir));

        return array_values(array_filter($candidates, fn (string $path): bool => file_exists($path)));
    }

    /**
     * Every byte-code cache directory below the game and the files inside them.
     *
     * Releases ship some of these already, so the files are listed individually
     * to tell an entry the archive came with from one this run produced.
     *
     * @return list<string>
     */
    private function pycachePaths(string $gameDir): array
    {
        if (! is_dir($gameDir)) {
            return [];
        }

        $paths = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($gameDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            if (! $entry->isDir() || $entry->getFilename() !== '__pycache__') {
                continue;
            }

            $paths[] = $entry->getPathname();

            foreach (glob($entry->getPathname() . '/*') ?: [] as $cached) {
                $paths[] = $cached;
            }
        }

        return $paths;
    }

    /**
     * @return list<array{0: list<string>, 1: string}>
     */
    private function recompileCommands(string $gameDir): array
    {
        $commands = [];

        $native = $this->findNativeLauncher($gameDir);
        if ($native !== null) {
            $this->makeNativeRuntimeExecutable($gameDir, $native);

            $commands[] = [[$native, 'game', 'test'], dirname($native)];
            $commands[] = [[$native], dirname($native)];
        }

        $sdkPath = $this->renpySdkPath();
        if ($sdkPath !== null) {
            $commands[] = [[$sdkPath . '/renpy.sh', $gameDir, 'compile'], $gameDir];
        }

        return $commands;
    }

    private function findNativeLauncher(string $gameDir): ?string
    {
        $launchers = glob($gameDir . '/*.sh') ?: [];
        sort($launchers);

        foreach ($launchers as $launcher) {
            if (is_file($launcher)) {
                return $launcher;
            }
        }

        return null;
    }

    private function makeNativeRuntimeExecutable(string $gameDir, string $launcher): void
    {
        @chmod($launcher, 0755);

        $libDir = $gameDir . '/lib';
        if (! is_dir($libDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($libDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                @chmod($file->getPathname(), 0755);
            }
        }
    }

    private function renpySdkPath(): ?string
    {
        $candidates = [
            config('services.renpy.sdk_container_path'),
            config('services.renpy.sdk_host_path'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate . '/renpy.sh')) {
                return $candidate;
            }
        }

        return null;
    }

    private function hasRpySource(string $rpycFile): bool
    {
        $basePath = substr($rpycFile, 0, -strlen('.rpyc'));

        return File::exists($basePath . '.rpy')
            || File::exists($basePath . '_ren.py');
    }

    private function summarizeProcessFailure(Process $process, string $fallback = 'decompiler exited unsuccessfully'): string
    {
        $output = trim($process->getErrorOutput()) ?: trim($process->getOutput());
        if ($output === '') {
            return $fallback;
        }

        $lines = preg_split('/\R/', $output) ?: [];
        $line = collect($lines)
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->last();

        return $line === null ? $fallback : str($line)->limit(180)->toString();
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
            throw new RuntimeException('Failed to create optimized archive: ' . $process->getErrorOutput());
        }
    }

    /**
     * @return array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null
     */
    private function previousOptimizedArchiveContext(?string $archivePath): ?array
    {
        if ($archivePath === null) {
            return null;
        }

        $extractPath = $this->optimizerTempRoot() . '/' . uniqid('previous_archive_opt_', true);
        File::makeDirectory($extractPath, 0755, true);
        $this->extractArchive($archivePath, $extractPath);

        $metadataService = app(ArchiveOptimizationMetadataService::class);
        $metadata = $metadataService->readExtracted($extractPath);
        if (! is_array($metadata)) {
            File::deleteDirectory($extractPath);

            return null;
        }

        $sourceHashes = $metadataService->sourceHashesFrom($metadata);
        $targetPaths = $metadataService->targetPathsFrom($metadata);
        if ($sourceHashes === []) {
            File::deleteDirectory($extractPath);

            return null;
        }

        return [
            'extract_path' => $extractPath,
            'source_hashes' => $sourceHashes,
            'target_paths' => $targetPaths,
        ];
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

        foreach (new DirectoryIterator($sourceDir) as $entry) {
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

    private function shouldUseSandbox(): bool
    {
        return $this->sandboxRunner !== null
            && (bool) config('services.archive_optimizer.sandbox_enabled', true);
    }

    private function optimizerTempRoot(): string
    {
        $path = rtrim(getenv('ARCHIVE_OPTIMIZER_WORK_DIR') ?: storage_path('app/temp'), '/');
        File::ensureDirectoryExists($path, 0755);

        return $path;
    }

    private function deleteSandboxJobDirectory(string $optimizedArchive): void
    {
        $outputDir = dirname($optimizedArchive);
        $jobDir = dirname($outputDir);
        $workDir = rtrim((string) config('services.archive_optimizer.container_work_dir'), '/');
        $resolvedWorkDir = realpath($workDir);
        $resolvedJobDir = realpath($jobDir);

        if ($resolvedWorkDir === false || $resolvedJobDir === false) {
            return;
        }

        if (str_starts_with($resolvedJobDir, $resolvedWorkDir . DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($resolvedJobDir);
        }
    }

    private function optimizedFilename(string $archivePath): string
    {
        $extension = $this->archiveExtension($archivePath);
        $suffixLength = strlen('.' . $extension);
        $basename = basename($archivePath);
        $name = substr($basename, 0, -$suffixLength);

        return $name . '.optimized.' . $extension;
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
