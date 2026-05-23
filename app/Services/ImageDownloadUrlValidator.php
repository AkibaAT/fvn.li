<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class ImageDownloadUrlValidator
{
    public function validate(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($url === '' || $parts === false) {
            throw new InvalidArgumentException('Invalid image URL');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Image downloads require HTTPS URLs');
        }

        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw new InvalidArgumentException('Image URL is missing a host');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Image URL must not contain credentials');
        }

        $this->assertPubliclyRoutableHost($host);

        return $url;
    }

    private function assertPubliclyRoutableHost(string $host): void
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            throw new InvalidArgumentException('Image URL cannot point to localhost');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPubliclyRoutableIp($host);

            return;
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            throw new InvalidArgumentException("Could not resolve image host: {$host}");
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && $ip !== '') {
                $this->assertPubliclyRoutableIp($ip);
            }
        }
    }

    private function assertPubliclyRoutableIp(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Image URL cannot resolve to a private or reserved IP address');
        }
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
