<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

class ArchiveMediaOptimizer
{
    /**
     * @param  array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null  $previousOptimizedContext
     * @return array{optimized: int, reused: int, replacements: array<string, string>}
     */
    public function optimizeImages(
        string $contentDir,
        string $gameDir,
        string $sourceDir,
        ?array $previousOptimizedContext,
        ?callable $progress = null
    ): array {
        $files = $this->filesWithExtensions($contentDir, ['png', 'jpg', 'jpeg']);

        return $this->optimizeMedia(
            $files,
            'images',
            '/\.(png|jpe?g)$/i',
            '.webp',
            fn (string $file, string $target): Process => new Process([
                $this->binary('magick') ?? $this->binary('convert') ?? 'magick',
                $file,
                '-strip',
                '-quality',
                '78',
                $target,
            ]),
            $gameDir,
            $sourceDir,
            $previousOptimizedContext,
            $progress
        );
    }

    /**
     * @param  array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null  $previousOptimizedContext
     * @return array{optimized: int, reused: int, replacements: array<string, string>}
     */
    public function optimizeAudio(
        string $contentDir,
        string $gameDir,
        string $sourceDir,
        ?array $previousOptimizedContext,
        ?callable $progress = null
    ): array {
        $files = $this->filesWithExtensions($contentDir, ['wav', 'flac', 'mp3']);

        return $this->optimizeMedia(
            $files,
            'audio files',
            '/\.(wav|flac|mp3)$/i',
            '.ogg',
            fn (string $file, string $target): Process => new Process([
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
            ]),
            $gameDir,
            $sourceDir,
            $previousOptimizedContext,
            $progress
        );
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function replaceScriptReferences(string $contentDir, array $replacements): int
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
    public function filesWithExtensions(string $dir, array $extensions): array
    {
        $files = [];
        $extensionLookup = array_fill_keys(array_map('strtolower', $extensions), true);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->isFile() && isset($extensionLookup[strtolower($file->getExtension())])) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  array<int, string>  $files
     * @param  callable(string, string): Process  $processFactory
     * @param  array{extract_path: string, source_hashes: array<string, string>, target_paths: array<string, string>}|null  $previousOptimizedContext
     * @return array{optimized: int, reused: int, replacements: array<string, string>}
     */
    private function optimizeMedia(
        array $files,
        string $label,
        string $extensionPattern,
        string $replacementExtension,
        callable $processFactory,
        string $gameDir,
        string $sourceDir,
        ?array $previousOptimizedContext,
        ?callable $progress
    ): array {
        $optimized = 0;
        $reused = 0;
        $replacements = [];
        $total = count($files);

        $this->reportProgress($progress, sprintf('Optimizing %d %s', $total, $label));

        foreach ($files as $index => $file) {
            $processed = $index + 1;
            $target = preg_replace($extensionPattern, $replacementExtension, $file);
            if ($target === null || $target === $file) {
                $this->reportMediaProgress($progress, $label, $processed, $total, $optimized);

                continue;
            }

            if ($this->reusePreviousOptimizedMedia($file, $target, $sourceDir, $gameDir, $previousOptimizedContext)) {
                $replacements[$this->relativeGamePath($gameDir, $file)] = $this->relativeGamePath($gameDir, $target);
                File::delete($file);
                $optimized++;
                $reused++;
                $this->reportMediaProgress($progress, $label, $processed, $total, $optimized);

                continue;
            }

            $process = $processFactory($file, $target);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful() || ! File::exists($target) || File::size($target) >= File::size($file)) {
                File::delete($target);
                $this->reportMediaProgress($progress, $label, $processed, $total, $optimized);

                continue;
            }

            $replacements[$this->relativeGamePath($gameDir, $file)] = $this->relativeGamePath($gameDir, $target);
            File::delete($file);
            $optimized++;
            $this->reportMediaProgress($progress, $label, $processed, $total, $optimized);
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
        $previousTargetPath = $this->containedPath($previousOptimizedContext['extract_path'], $relativeTargetPath);
        if ($previousTargetPath === null || ! File::isFile($previousTargetPath) || File::size($previousTargetPath) >= File::size($sourcePath)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($targetPath));

        return File::copy($previousTargetPath, $targetPath);
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
        if ($progress !== null) {
            $progress($message);
        }
    }

    private function containedPath(string $basePath, string $relativePath): ?string
    {
        $relativePath = $this->safeRelativeArchivePath($relativePath);
        if ($relativePath === null) {
            return null;
        }

        $baseRealPath = realpath($basePath);
        if ($baseRealPath === false) {
            return null;
        }

        $candidateRealPath = realpath($baseRealPath.'/'.$relativePath);
        if ($candidateRealPath === false) {
            return null;
        }

        $baseRealPath = rtrim(str_replace('\\', '/', $baseRealPath), '/').'/';
        $candidateRealPath = str_replace('\\', '/', $candidateRealPath);

        return str_starts_with($candidateRealPath, $baseRealPath) ? $candidateRealPath : null;
    }

    private function safeRelativeArchivePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return null;
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                return null;
            }

            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
    }

    private function binary(string $name): ?string
    {
        $process = new Process(['which', $name]);
        $process->run();

        return $process->isSuccessful() ? (trim($process->getOutput()) ?: null) : null;
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
