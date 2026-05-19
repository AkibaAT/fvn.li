<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class ArchiveDownloadPathService
{
    public function createTempDirectory(): string
    {
        $tempDir = tempnam(sys_get_temp_dir(), 'game_');
        if ($tempDir === false) {
            throw new RuntimeException('Could not create temporary directory');
        }

        if (! File::delete($tempDir) || ! File::makeDirectory($tempDir, 0755)) {
            throw new RuntimeException('Could not create temporary directory');
        }

        return $tempDir;
    }

    public function createTempFile(string $tempDir): string
    {
        $tempFile = tempnam($tempDir, 'download_');
        if ($tempFile === false) {
            throw new RuntimeException('Could not create temporary download file');
        }

        $this->ensurePathIsInsideDirectory($tempFile, $tempDir);

        return $tempFile;
    }

    public function tempPathForFilename(string $tempDir, string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $tempDir.DIRECTORY_SEPARATOR.$filename;

        $this->ensurePathIsInsideDirectory(dirname($path), $tempDir);

        return $path;
    }

    public function getDownloadFilename(ResponseInterface $response, string $fallbackFilename): string
    {
        $this->sanitizeFilename($fallbackFilename);

        $contentDisposition = $response->getHeaderLine('Content-Disposition');
        if (preg_match('/filename\\*=UTF-8\'\'([^;]+)/i', $contentDisposition, $matches)) {
            return $this->sanitizeFilename(rawurldecode(trim($matches[1], " \t\"'")));
        }

        if (preg_match('/filename="([^"]+)"/i', $contentDisposition, $matches) ||
            preg_match('/filename=([^;]+)/i', $contentDisposition, $matches)) {
            return $this->sanitizeFilename(trim($matches[1], " \t\"'"));
        }

        return $this->sanitizeFilename($fallbackFilename);
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return 'archive';
        }

        if (
            str_contains($filename, "\0") ||
            str_contains($filename, '/') ||
            str_contains($filename, '\\') ||
            $filename === '.' ||
            $filename === '..'
        ) {
            throw new RuntimeException('Archive filenames must not contain path separators or traversal segments.');
        }

        return $filename;
    }

    public function ensurePathIsInsideDirectory(string $path, string $directory): void
    {
        $directoryRealPath = realpath($directory);
        $pathRealPath = realpath($path);

        if ($directoryRealPath === false || $pathRealPath === false) {
            throw new RuntimeException('Could not verify temporary archive path');
        }

        if ($pathRealPath !== $directoryRealPath &&
            ! str_starts_with($pathRealPath, $directoryRealPath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Temporary archive path escaped its working directory');
        }
    }
}
