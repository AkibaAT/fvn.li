<?php

declare(strict_types=1);

namespace App\Services;

use LengthException;

class RenpySaveParser
{
    public const MAX_UPLOAD_KIB = 2048;

    public const MAX_DECOMPRESSED_BYTES = 8 * 1024 * 1024;

    private const MAX_PICKLE_STRING_BYTES = 1048576;

    public function extractSeenLabels(string $rawData, array $knownLabels): array
    {
        $data = $this->decompress($rawData);

        $strings = $this->extractPickleStrings($data);

        $labelSet = array_flip($knownLabels);

        $seen = [];
        foreach ($strings as $str) {
            if (isset($labelSet[$str])) {
                $seen[] = $str;
            }
        }

        return array_values(array_unique($seen));
    }

    public function decompress(string $data): string
    {
        if ($this->isGzip($data)) {
            return $this->decodeCompressedSave($data, 'gzdecode');
        }

        if ($this->isZlib($data)) {
            return $this->decodeCompressedSave($data, 'gzuncompress');
        }

        $this->ensurePayloadSizeIsAllowed($data);

        return $data;
    }

    public function extractPickleStrings(string $data): array
    {
        $strings = [];
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            $byte = ord($data[$i]);

            if ($byte === 0x8C && $i + 1 < $len) {
                $strLen = ord($data[$i + 1]);
                if ($strLen > 0 && $i + 2 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 2, $strLen);
                    $i += 2 + $strLen;

                    continue;
                }
            }

            if ($byte === 0x58 && $i + 4 < $len) {
                $strLen = unpack('V', substr($data, $i + 1, 4))[1];
                if ($strLen > 0 && $strLen < self::MAX_PICKLE_STRING_BYTES && $i + 5 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 5, $strLen);
                    $i += 5 + $strLen;

                    continue;
                }
            }

            if ($byte === 0x8D && $i + 8 < $len) {
                $strLen = unpack('P', substr($data, $i + 1, 8))[1];
                if ($strLen > 0 && $strLen < self::MAX_PICKLE_STRING_BYTES && $i + 9 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 9, $strLen);
                    $i += 9 + $strLen;

                    continue;
                }
            }

            if ($byte === 0x55 && $i + 1 < $len) {
                $strLen = ord($data[$i + 1]);
                if ($strLen > 0 && $i + 2 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 2, $strLen);
                    $i += 2 + $strLen;

                    continue;
                }
            }

            if ($byte === 0x54 && $i + 4 < $len) {
                $strLen = unpack('V', substr($data, $i + 1, 4))[1];
                if ($strLen > 0 && $strLen < self::MAX_PICKLE_STRING_BYTES && $i + 5 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 5, $strLen);
                    $i += 5 + $strLen;

                    continue;
                }
            }

            if ($byte === 0x43 && $i + 1 < $len) {
                $strLen = ord($data[$i + 1]);
                if ($strLen > 0 && $i + 2 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 2, $strLen);
                    $i += 2 + $strLen;

                    continue;
                }
            }

            if ($byte === 0x42 && $i + 4 < $len) {
                $strLen = unpack('V', substr($data, $i + 1, 4))[1];
                if ($strLen > 0 && $strLen < self::MAX_PICKLE_STRING_BYTES && $i + 5 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 5, $strLen);
                    $i += 5 + $strLen;

                    continue;
                }
            }

            $i++;
        }

        return $strings;
    }

    private function decodeCompressedSave(string $data, string $decoder): string
    {
        $decoded = @$decoder($data, self::MAX_DECOMPRESSED_BYTES);

        if ($decoded === false) {
            throw new LengthException('Compressed Ren\'Py save files must be valid and no larger than 8 MiB after decompression.');
        }

        $this->ensurePayloadSizeIsAllowed($decoded);

        return $decoded;
    }

    private function ensurePayloadSizeIsAllowed(string $data): void
    {
        if (strlen($data) > self::MAX_DECOMPRESSED_BYTES) {
            throw new LengthException('Ren\'Py save files must be no larger than 8 MiB after decompression.');
        }
    }

    private function isGzip(string $data): bool
    {
        return strlen($data) >= 2
            && ord($data[0]) === 0x1F
            && ord($data[1]) === 0x8B;
    }

    private function isZlib(string $data): bool
    {
        if (strlen($data) < 2) {
            return false;
        }

        $compressionMethodAndFlags = ord($data[0]);
        $flags = ord($data[1]);

        return ($compressionMethodAndFlags & 0x0F) === 8
            && ($compressionMethodAndFlags >> 4) <= 7
            && (($compressionMethodAndFlags << 8) + $flags) % 31 === 0;
    }
}
