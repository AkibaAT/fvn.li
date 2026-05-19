<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class ArchiveOptimizationMetadataService
{
    private const METADATA_FILENAME = '.fvn-archive-metadata.json';

    /**
     * @return array<int, array{path: string, size: int, sha256: string}>
     */
    public function inventory(string $sourceDir): array
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
    public function write(
        string $workDir,
        string $archivePath,
        string $archiveFormat,
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
                'format' => $archiveFormat,
                'size' => $originalSize,
                'sha256' => hash_file('sha256', $archivePath) ?: '',
            ],
            'original_files' => $originalFileInventory,
            'optimized_files' => $this->inventory($workDir),
            'media_replacements' => $mediaReplacements,
        ];

        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode archive optimization metadata');
        }

        File::put($workDir.'/'.self::METADATA_FILENAME, $json."\n");
    }

    public function readExtracted(string $extractPath): ?array
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
    public function sourceHashesFrom(array $metadata): array
    {
        $sourceHashes = [];
        foreach (($metadata['original_files'] ?? []) as $file) {
            if (! is_array($file) || ! isset($file['path'], $file['sha256'])) {
                continue;
            }

            $archivePath = $this->safeRelativeArchivePath((string) $file['path']);
            if ($archivePath === null) {
                continue;
            }

            $sourceHashes[$archivePath] = (string) $file['sha256'];
            if (($gamePath = $this->metadataGamePath($archivePath)) !== null) {
                $sourceHashes[$gamePath] = (string) $file['sha256'];
            }
        }

        return $sourceHashes;
    }

    /**
     * @return array<string, string>
     */
    public function targetPathsFrom(array $metadata): array
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

            $sourcePath = $this->safeRelativeArchivePath($sourcePath);
            $targetPath = $this->safeRelativeArchivePath($targetPath);
            if ($sourcePath === null || $targetPath === null) {
                continue;
            }

            $archiveTargetPath = collect($optimizedFiles)
                ->map(fn (array $file): ?string => $this->safeRelativeArchivePath((string) $file['path']))
                ->filter()
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

    private function safeRelativeArchivePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if (
            $path === '' ||
            str_contains($path, "\0") ||
            str_starts_with($path, '/') ||
            preg_match('/^[A-Za-z]:\//', $path) === 1
        ) {
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

    private function relativeArchivePath(string $sourceDir, string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen($sourceDir) + 1)), '/');
    }
}
