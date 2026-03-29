<?php

declare(strict_types=1);

namespace App\Services;

class RenpySaveParser
{
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
        $decoded = @gzdecode($data);

        if ($decoded !== false) {
            return $decoded;
        }

        $decoded = @gzuncompress($data);

        if ($decoded !== false) {
            return $decoded;
        }

        return $data;
    }

    public function extractPickleStrings(string $data): array
    {
        $strings = [];
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            $byte = ord($data[$i]);

            if ($byte === 0x8c && $i + 1 < $len) {
                $strLen = ord($data[$i + 1]);
                if ($strLen > 0 && $i + 2 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 2, $strLen);
                    $i += 2 + $strLen;
                    continue;
                }
            }

            if ($byte === 0x58 && $i + 4 < $len) {
                $strLen = unpack('V', substr($data, $i + 1, 4))[1];
                if ($strLen > 0 && $strLen < 1048576 && $i + 5 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 5, $strLen);
                    $i += 5 + $strLen;
                    continue;
                }
            }

            if ($byte === 0x8d && $i + 8 < $len) {
                $strLen = unpack('P', substr($data, $i + 1, 8))[1];
                if ($strLen > 0 && $strLen < 1048576 && $i + 9 + $strLen <= $len) {
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
                if ($strLen > 0 && $strLen < 1048576 && $i + 5 + $strLen <= $len) {
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
                if ($strLen > 0 && $strLen < 1048576 && $i + 5 + $strLen <= $len) {
                    $strings[] = substr($data, $i + 5, $strLen);
                    $i += 5 + $strLen;
                    continue;
                }
            }

            $i++;
        }

        return $strings;
    }
}
