<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class ArchiveMetadataReader
{
    private const OPTIMIZATION_METADATA_FILENAME = '.fvn-archive-metadata.json';

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $archivePath): ?array
    {
        if (! File::exists($archivePath)) {
            throw new RuntimeException("Archive file not found: {$archivePath}");
        }

        $json = $this->readJson($archivePath);
        if ($json === null || trim($json) === '') {
            return null;
        }

        $metadata = json_decode($json, true);

        return is_array($metadata) ? $metadata : null;
    }

    public function isOptimized(?array $metadata): bool
    {
        return ($metadata['schema'] ?? null) === 'fvn.archive_optimization.v1';
    }

    private function readJson(string $archivePath): ?string
    {
        $extension = $this->archiveExtension($archivePath);

        if ($extension === 'zip') {
            $zip = new ZipArchive;
            $result = $zip->open($archivePath);
            if ($result !== true) {
                return null;
            }

            try {
                $contents = $zip->getFromName(self::OPTIMIZATION_METADATA_FILENAME);

                return $contents === false ? null : $contents;
            } finally {
                $zip->close();
            }
        }

        if (in_array($extension, ['tar', 'tar.gz', 'tgz', 'tar.bz2', 'tbz2'], true)) {
            $flag = match ($extension) {
                'tar' => '-xOf',
                'tar.gz', 'tgz' => '-xzOf',
                'tar.bz2', 'tbz2' => '-xjOf',
            };
            $process = new Process(['tar', $flag, $archivePath, self::OPTIMIZATION_METADATA_FILENAME]);
            $process->setTimeout(60);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        }

        return null;
    }

    private function archiveExtension(string $archivePath): string
    {
        $header = (string) file_get_contents($archivePath, false, null, 0, 512);
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
