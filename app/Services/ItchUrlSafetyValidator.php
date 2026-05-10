<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class ItchUrlSafetyValidator
{
    /**
     * @var list<string>
     */
    private const array DEFAULT_ALLOWED_HOSTS = [
        'itch.io',
    ];

    public function validate(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Invalid itch.io URL');
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            throw new InvalidArgumentException('Itch.io URL must use HTTPS');
        }

        $host = $this->normalizeHost((string) $parts['host']);
        if (! $this->isAllowedHost($host)) {
            throw new InvalidArgumentException("Untrusted itch.io host: {$host}");
        }

        $this->assertHostDoesNotResolveToPrivateAddress($host);
    }

    public function isApiRequest(string $url): bool
    {
        $parts = parse_url($url);
        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));

        return $host === 'api.itch.io';
    }

    private function isAllowedHost(string $host): bool
    {
        foreach ($this->allowedHosts() as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, ".{$allowedHost}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $configuredHosts = config('services.flaresolverr.allowed_itch_hosts');
        if (! is_array($configuredHosts) || $configuredHosts === []) {
            return self::DEFAULT_ALLOWED_HOSTS;
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): string => $this->normalizeHost((string) $host),
            $configuredHosts,
        ))));
    }

    private function assertHostDoesNotResolveToPrivateAddress(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host, $host);

            return;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            throw new InvalidArgumentException("Could not resolve itch.io host: {$host}");
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $this->assertPublicIp($ip, $host);
            }
        }
    }

    private function assertPublicIp(string $ip, string $host): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException("Itch.io host resolves to a non-public address: {$host}");
        }
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
