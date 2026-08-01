<?php

declare(strict_types=1);

namespace App\Services;

final class ArchiveFormatDetector
{
    public function detect(string $archivePath): string
    {
        $header = is_file($archivePath)
            ? file_get_contents($archivePath, false, null, 0, 512)
            : '';
        $header = is_string($header) ? $header : '';

        if (str_starts_with($header, "PK\x03\x04") || str_starts_with($header, "PK\x05\x06") || str_starts_with($header, "PK\x07\x08")) {
            return 'zip';
        }

        if (str_starts_with($header, "\x1F\x8B")) {
            return 'tar.gz';
        }

        if (str_starts_with($header, 'BZh')) {
            return 'tar.bz2';
        }

        if (substr($header, 257, 5) === 'ustar') {
            return 'tar';
        }

        return $this->detectFromFilename($archivePath);
    }

    public function detectFromFilename(string $archivePath): string
    {
        $suffix = $this->filenameSuffix($archivePath);

        return match ($suffix) {
            // itch.io sometimes serves tar archives with only the compression
            // suffix. The optimizer deals in archive formats, not suffixes.
            'bz2' => 'tar.bz2',
            'gz' => 'tar.gz',
            default => $suffix,
        };
    }

    public function filenameSuffix(string $archivePath): string
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
}
