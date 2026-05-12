<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class ImageDownloadUrlValidator
{
    private const DEFAULT_ALLOWED_HOSTS = [
        'img.itch.zone',
        'img.itch.io',
        'shared.akamai.steamstatic.com',
        'cdn.akamai.steamstatic.com',
        'shared.cloudflare.steamstatic.com',
        'cdn.cloudflare.steamstatic.com',
        'steamcdn-a.akamaihd.net',
        'booth.pximg.net',
    ];

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

        if (! in_array($host, $this->allowedHosts(), true)) {
            throw new InvalidArgumentException("Untrusted image host: {$host}");
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $configuredHosts = function_exists('config')
            ? config('services.image_downloads.allowed_hosts')
            : null;

        if (is_string($configuredHosts)) {
            $configuredHosts = explode(',', $configuredHosts);
        }

        if (! is_array($configuredHosts) || $configuredHosts === []) {
            $configuredHosts = self::DEFAULT_ALLOWED_HOSTS;
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): string => $this->normalizeHost((string) $host),
            $configuredHosts
        ))));
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
