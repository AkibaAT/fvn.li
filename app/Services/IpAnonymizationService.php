<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;

class IpAnonymizationService
{
    /**
     * Check if an IP address appears to be already anonymized
     */
    public static function isAnonymized(string $ipAddress): bool
    {
        // Check for subnet anonymization patterns
        if (str_ends_with($ipAddress, '.0') || str_ends_with($ipAddress, '::')) {
            return true;
        }

        // Check for hash anonymization pattern
        if (str_starts_with($ipAddress, 'hash_')) {
            return true;
        }

        // Check for full anonymization
        if ($ipAddress === '***') {
            return true;
        }

        return false;
    }

    /**
     * Get anonymized IP address based on audit privacy configuration
     * This method respects the global anonymization setting
     */
    public static function getAnonymizedIpAddress(?string $ipAddress): ?string
    {
        if (! $ipAddress) {
            return null;
        }

        // Check if IP anonymization is enabled globally
        if (! Config::get('audit.privacy.anonymize_ip_addresses', false)) {
            return $ipAddress;
        }

        return self::anonymize($ipAddress);
    }

    /**
     * Anonymize an IP address based on configuration
     */
    public static function anonymize(?string $ipAddress, ?string $method = null): ?string
    {
        if (! $ipAddress) {
            return null;
        }

        // Use provided method or fall back to audit config
        $anonymizationMethod = $method ?? Config::get('audit.privacy.ip_anonymization_method', 'subnet');

        return match ($anonymizationMethod) {
            'subnet' => self::anonymizeBySubnet($ipAddress),
            'hash' => self::anonymizeByHash($ipAddress),
            'full' => '***',
            default => $ipAddress,
        };
    }

    /**
     * Anonymize IP address by zeroing the last octet (IPv4) or last 64 bits (IPv6)
     * This is GDPR compliant and maintains useful analytics data
     */
    public static function anonymizeBySubnet(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Zero out the last octet (e.g., 192.168.1.123 -> 192.168.1.0)
            $parts = explode('.', $ipAddress);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Zero out the last 64 bits (interface identifier)
            $binary = inet_pton($ipAddress);
            if ($binary !== false) {
                // Zero out the last 8 bytes (64 bits)
                $binary = substr($binary, 0, 8).str_repeat("\0", 8);

                return inet_ntop($binary) ?: $ipAddress;
            }
        }

        return $ipAddress;
    }

    /**
     * Anonymize IP address using a one-way hash with salt
     */
    public static function anonymizeByHash(string $ipAddress): string
    {
        // Use application key as salt for consistent hashing
        $salt = Config::get('app.key', 'audit-salt');

        // Create a truncated hash for privacy while maintaining some uniqueness
        return 'hash_'.substr(hash('sha256', $salt.$ipAddress), 0, 12);
    }
}
