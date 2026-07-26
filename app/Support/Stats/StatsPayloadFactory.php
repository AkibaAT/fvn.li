<?php

declare(strict_types=1);

namespace App\Support\Stats;

use RuntimeException;

class StatsPayloadFactory
{
    /**
     * Open a stats document produced by the analyzer.
     *
     * @param  bool  $ownsFile  Whether releasing the payload should delete the file.
     */
    public static function fromFile(string $path, bool $ownsFile = false): StatsPayload
    {
        if (! is_file($path)) {
            throw new RuntimeException("Stats file not found: {$path}");
        }

        if (! self::hasSchemaMarker($path)) {
            throw new RuntimeException(
                'Unrecognized stats file. Expected the newline-delimited document the analyzer produces, '
                . 'whose first line is {"type":"meta","schema":"' . NdjsonStatsPayload::SCHEMA . '"}. '
                . 'Re-run the extraction to regenerate it.'
            );
        }

        return new NdjsonStatsPayload($path, $ownsFile);
    }

    private static function hasSchemaMarker(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            while (($line = fgets($handle, 4096)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $record = json_decode($line, true);

                return is_array($record)
                    && ($record['type'] ?? null) === 'meta'
                    && ($record['schema'] ?? null) === NdjsonStatsPayload::SCHEMA;
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
