<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class ImageDownloadUrlValidator
{
    /**
     * @return array{url: string, options: array<string, mixed>}
     */
    public function validatedRequest(string $url): array
    {
        [$url, $host, $port] = $this->validateUrlParts($url);
        $ip = $this->publiclyRoutableIpForHost($host);

        return [
            'url' => $url,
            'options' => $this->resolveOptions($host, $port, $ip),
        ];
    }

    public function validate(string $url): string
    {
        return $this->validatedRequest($url)['url'];
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function validateUrlParts(string $url): array
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

        return [$url, $host, (int) ($parts['port'] ?? 443)];
    }

    private function publiclyRoutableIpForHost(string $host): string
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            throw new InvalidArgumentException('Image URL cannot point to localhost');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPubliclyRoutableIp($host);

            return $host;
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            throw new InvalidArgumentException("Could not resolve image host: {$host}");
        }

        $firstPublicIp = null;
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || $ip === '') {
                continue;
            }

            $this->assertPubliclyRoutableIp($ip);
            $firstPublicIp ??= $ip;
        }

        if ($firstPublicIp === null) {
            throw new InvalidArgumentException("Could not resolve image host: {$host}");
        }

        return $firstPublicIp;
    }

    private function assertPubliclyRoutableIp(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Image URL cannot resolve to a private or reserved IP address');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOptions(string $host, int $port, string $ip): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        if (! defined('CURLOPT_RESOLVE')) {
            throw new InvalidArgumentException('Image downloads require cURL DNS pinning support');
        }

        return [
            'curl' => [
                constant('CURLOPT_RESOLVE') => ["{$host}:{$port}:{$ip}"],
            ],
        ];
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
