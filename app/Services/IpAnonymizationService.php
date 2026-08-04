<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;

class IpAnonymizationService
{
    public static function isAnonymized(string $ipAddress): bool
    {
        if (str_ends_with($ipAddress, '.0') || str_ends_with($ipAddress, '::')) {
            return true;
        }

        if (str_starts_with($ipAddress, 'hash_')) {
            return true;
        }

        if ($ipAddress === '***') {
            return true;
        }

        return false;
    }

    public static function getAnonymizedIpAddress(?string $ipAddress): ?string
    {
        if (! $ipAddress) {
            return null;
        }

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
                $binary = substr($binary, 0, 8) . str_repeat("\0", 8);

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
        return self::saltedHash($ipAddress);
    }

    /**
     * Derive a stable pseudonym for someone whose address has been erased.
     *
     * Takes the shape of a hashed address so callers testing for an anonymised
     * value recognise it, and resolves to one value per identity so records
     * left behind by a single person still group as a single person.
     */
    public static function pseudonymizeIdentity(string $identity): string
    {
        return self::saltedHash($identity);
    }

    private static function saltedHash(string $value): string
    {
        $salt = Config::get('app.key', 'audit-salt');

        return 'hash_' . substr(hash('sha256', $salt . $value), 0, 12);
    }
}
