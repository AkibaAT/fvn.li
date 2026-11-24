<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\HostAddresses;
use InvalidArgumentException;
use Uri\Rfc3986\Uri;

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
        $uri = $url !== '' ? Uri::parse($url) : null;

        if ($uri === null || $uri->getHost() === null || $uri->getHost() === '') {
            throw new InvalidArgumentException('Invalid image URL');
        }

        $scheme = strtolower((string) ($uri->getScheme() ?? ''));
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Image downloads require HTTPS URLs');
        }

        $host = $this->normalizeHost($uri->getHost());
        if ($host === '') {
            throw new InvalidArgumentException('Image URL is missing a host');
        }

        if ($uri->getRawUserInfo() !== null) {
            throw new InvalidArgumentException('Image URL must not contain credentials');
        }

        $port = $uri->getPort() ?? 443;
        if ($port !== 443) {
            throw new InvalidArgumentException('Image downloads must use the standard HTTPS port');
        }

        return [$url, $host, $port];
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

        $addresses = HostAddresses::resolve($host);
        if ($addresses === []) {
            throw new InvalidArgumentException("Could not resolve image host: {$host}");
        }

        foreach ($addresses as $ip) {
            $this->assertPubliclyRoutableIp($ip);
        }

        return $addresses[0];
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
        return rtrim(trim(strtolower(trim($host)), '[]'), '.');
    }
}
