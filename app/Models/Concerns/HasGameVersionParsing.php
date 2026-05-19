<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Exception;

trait HasGameVersionParsing
{
    private function isProbableVersion(string $version): bool
    {
        if (empty($version)) {
            return false;
        }

        $parsed = $this->parseSemanticVersion($version);
        if (! $parsed) {
            return false;
        }

        [$parts] = $parsed;

        if ($parts[0] > 2100 || ($parts[0] > 100 && strlen((string) $parts[0]) === 4)) {
            return false;
        }

        return ! array_filter($parts, fn ($part) => $part > 10000);
    }

    private function parseSemanticVersion(string $version): ?array
    {
        $version = preg_replace('/^[vV]ersion\s*/', '', $version);
        $version = preg_replace('/^[vV]\s*/', '', $version);

        if (! preg_match('/^(\d+(?:\.\d+)*?)([a-zA-Z]+)?$/', $version, $matches)) {
            return null;
        }

        try {
            return [array_map('intval', explode('.', $matches[1])), $matches[2] ?? ''];
        } catch (Exception) {
            return null;
        }
    }
}
