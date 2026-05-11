<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;
use Exception;

class GameVersionParser
{
    private const MAX_VERSION_LENGTH = 20;

    /**
     * Parse a version string into a normalized format.
     * Returns [array<int,int> $parts, string $suffix] or null.
     */
    public function parseSemanticVersion(string $version): ?array
    {
        // Remove leading 'v' or 'version'
        $version = preg_replace('/^[vV]ersion\s*/', '', $version);
        $version = preg_replace('/^[vV]\s*/', '', $version);

        // Match version pattern with optional letter suffix
        if (! preg_match('/^(\d+(?:\.\d+)*?)([a-zA-Z]+)?$/', $version, $matches)) {
            return null;
        }

        try {
            $parts = array_map('intval', explode('.', $matches[1]));
            $suffix = $matches[2] ?? '';

            return [$parts, $suffix];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Check if a string looks like a probable version number.
     */
    public function isProbableVersion(string $version): bool
    {
        if (empty($version)) {
            return false;
        }

        if (strlen($version) > self::MAX_VERSION_LENGTH) {
            return false;
        }

        $parsed = $this->parseSemanticVersion($version);
        if (! $parsed) {
            return false;
        }

        [$parts] = $parsed;

        // Reject if first number is too large or looks like a year
        if ($parts[0] > 2100 || ($parts[0] > 100 && strlen((string) $parts[0]) === 4)) {
            return false;
        }

        // Reject if any part is suspiciously large
        if (array_filter($parts, fn ($p) => $p > 10000)) {
            return false;
        }

        return true;
    }

    /**
     * Extract version from upload data with various fallback strategies.
     */
    public function extractVersion(array $upload, bool $allowDateFallback = false): ?string
    {
        // Collect version candidates with source and priority
        $candidates = [];

        // Check build.user_version first (highest priority)
        if (! empty($upload['build']['user_version'])) {
            $version = $upload['build']['user_version'];
            if ($this->isProbableVersion($version)) {
                $candidates[] = [$version, 3];
            }
        }

        if (! empty($upload['user_version'])) {
            $version = $upload['user_version'];
            if ($this->isProbableVersion($version)) {
                $candidates[] = [$version, 3];
            }
        }

        // Check display_name (high priority)
        if (! empty($upload['display_name'])) {
            // Look for version in parentheses first (highest priority for display name)
            if (preg_match('/\(([0-9]+(?:\.[0-9]+)*(?:[a-zA-Z]*)?)\)/', $upload['display_name'], $matches)) {
                if ($this->isProbableVersion($matches[1])) {
                    $candidates[] = [$matches[1], 3];
                }
            }

            // Look for explicit version
            preg_match_all(
                '/(?:[vV](?:ersion)?)?\s*([0-9]+\.[0-9]+(?:\.[0-9]+)*(?:[a-zA-Z]*)?)(?=[-_. ]|$)/i',
                $upload['display_name'],
                $matches
            );

            // Find the highest semantic version
            $highestVersion = null;
            foreach ($matches[1] as $version) {
                if ($this->isProbableVersion($version)) {
                    if (! $highestVersion || version_compare($version, $highestVersion) > 0) {
                        $highestVersion = $version;
                    }
                }
            }

            if ($highestVersion) {
                $candidates[] = [$highestVersion, 2];
            } else {
                // Fallback: only look for single numbers if no semantic version found,
                // but avoid matching numbers that are part of a dotted sequence.
                preg_match_all('/(?<!\.)\b(\d+)\b/', $upload['display_name'], $matches);
                foreach ($matches[1] as $version) {
                    if ($this->isProbableVersion($version)) {
                        if (! $highestVersion || version_compare($version, $highestVersion) > 0) {
                            $highestVersion = $version;
                        }
                    }
                }
                if ($highestVersion) {
                    $candidates[] = [$highestVersion, 1];  // Lower priority for single numbers
                }
            }
        }

        // Check filename (lowest priority)
        $filename = $upload['filename'] ?? '';
        $cleanedFilename = preg_replace('/\.(zip|tar\.bz2|tar\.gz)$/', '', $filename);

        // Look for build numbers
        if (preg_match('/[bB]uild[_\s-]*(\d+)/', $cleanedFilename, $matches)) {
            if ($this->isProbableVersion($matches[1])) {
                $candidates[] = [$matches[1], 1];
            }
        } else {
            // Look for version patterns in filename
            preg_match_all('/(?:[vV](?:ersion)?)?\s*(\d+(?:\.\d+)*[a-zA-Z]*)(?=[-_. ]|$)/i',
                $cleanedFilename, $matches);
            foreach ($matches[1] as $version) {
                if ($this->isProbableVersion($version)) {
                    $candidates[] = [$version, 0];
                }
            }
        }

        if (! empty($candidates)) {
            // Sort by priority (desc) then version string
            usort($candidates, fn ($a, $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));

            return $candidates[0][0];
        }

        // Only return date-based version if explicitly allowed
        if ($allowDateFallback) {
            $timestamp = new DateTime($upload['updated_at']);

            return $timestamp->format('Y.m.d');
        }

        return null;
    }
}
