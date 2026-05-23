<?php

declare(strict_types=1);

namespace App\Services;

use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use RuntimeException;
use Throwable;

class ItchDownloadUrlResolver
{
    /**
     * @throws BindingResolutionException
     */
    public function resolve(string $gameUrl, int $uploadId, int $gameId): string
    {
        $gameUrl = $this->validateItchControlUrl($gameUrl, $gameUrl, 'itch.io game URL');
        $legacyResponse = App::make(ItchHttpClientService::class)->post($this->uploadDownloadEndpoint($gameUrl, $uploadId));
        $legacyDownloadInfo = $this->decodeDownloadInfo($legacyResponse->getBody()->getContents());

        if (isset($legacyDownloadInfo['url'])) {
            return $this->validateItchFileDownloadUrl((string) $legacyDownloadInfo['url'], $gameUrl, 'itch.io file download URL');
        }

        $flareSolverr = App::make(FlareSolverrClient::class);
        $cookieJar = new CookieJar;

        $gamePageResponse = $this->flareSolverrDownloadRequest($flareSolverr, 'GET', $gameUrl, cookieJar: $cookieJar);
        $gamePage = $gamePageResponse['response'] ?? '';
        if (($gamePageResponse['status'] ?? 500) >= 400) {
            throw new RuntimeException("Could not load itch.io game page before download: HTTP {$gamePageResponse['status']}");
        }

        $downloadEndpoint = $this->validateItchControlUrl(
            $this->extractDownloadUrlEndpoint($gamePage) ?? rtrim($gameUrl, '/').'/download_url',
            $gameUrl,
            'itch.io download URL endpoint'
        );
        $csrfToken = $this->extractCsrfToken($gamePage);
        if ($csrfToken === null) {
            throw new RuntimeException('Could not find itch.io CSRF token on game page');
        }

        $browserHttpClient = $this->createBrowserSessionHttpClient($cookieJar, $gamePageResponse['userAgent'] ?? null);
        $downloadPageInfo = $this->decodeDownloadInfo($browserHttpClient->post($downloadEndpoint, [
            'form_params' => [
                'csrf_token' => $csrfToken,
                'upload_id' => $uploadId,
            ],
            'headers' => $this->jsonRequestHeaders($gameUrl),
        ])->getBody()->getContents());

        $downloadPageUrl = $downloadPageInfo['url'] ?? null;
        if (! is_string($downloadPageUrl) || $downloadPageUrl === '') {
            throw new RuntimeException($this->downloadUrlErrorMessage('Could not get itch.io download page URL', $downloadPageInfo));
        }

        $downloadPageUrl = $this->validateItchControlUrl($downloadPageUrl, $gameUrl, 'itch.io download page URL');
        $downloadPageResponse = $this->flareSolverrDownloadRequest($flareSolverr, 'GET', $downloadPageUrl, cookieJar: $cookieJar);
        $downloadPage = $downloadPageResponse['response'] ?? '';
        if (($downloadPageResponse['status'] ?? 500) >= 400) {
            throw new RuntimeException("Could not load itch.io download page: HTTP {$downloadPageResponse['status']}");
        }

        $downloadPageCsrfToken = $this->extractCsrfToken($downloadPage);
        if ($downloadPageCsrfToken === null) {
            throw new RuntimeException('Could not find itch.io CSRF token on download page');
        }

        $fileEndpointBaseUrl = preg_replace('#/download/.*$#', '', $downloadPageUrl) ?: $gameUrl;
        $fileDownloadInfo = $this->decodeDownloadInfo($browserHttpClient->post($this->uploadDownloadEndpoint($fileEndpointBaseUrl, $uploadId), [
            'form_params' => [
                'csrf_token' => $downloadPageCsrfToken,
                'upload_id' => $uploadId,
            ],
            'headers' => $this->jsonRequestHeaders($downloadPageUrl),
        ])->getBody()->getContents());

        if (isset($fileDownloadInfo['url'])) {
            return $this->validateItchFileDownloadUrl((string) $fileDownloadInfo['url'], $gameUrl, 'itch.io file download URL');
        }

        throw new RuntimeException($this->downloadUrlErrorMessage('Could not get itch.io file download URL', $fileDownloadInfo));
    }

    /**
     * @param  array<string, mixed>  $postData
     * @return array{status?: int, headers?: array<string, mixed>, cookies?: array<int, mixed>, userAgent?: string|null, response?: string}
     *
     * @throws Exception
     */
    public function flareSolverrDownloadRequest(
        FlareSolverrClient $flareSolverr,
        string $method,
        string $url,
        array $postData = [],
        ?CookieJar $cookieJar = null
    ): array {
        return $flareSolverr->request($url, $method, $postData, $cookieJar, true);
    }

    public function createBrowserSessionHttpClient(CookieJar $cookieJar, ?string $userAgent): Client
    {
        return new Client([
            'cookies' => $cookieJar,
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => false,
            'headers' => [
                'User-Agent' => $userAgent ?: 'Mozilla/5.0',
            ],
        ]);
    }

    public function uploadDownloadEndpoint(string $gameUrl, int $uploadId): string
    {
        return rtrim($gameUrl, '/').'/file/'.$uploadId;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeDownloadInfo(string $body): array
    {
        $downloadInfo = json_decode($body, true);

        return is_array($downloadInfo) ? $downloadInfo : [];
    }

    public function extractCsrfToken(string $html): ?string
    {
        if (preg_match('/<meta\s+name="csrf_token"\s+value="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/<input[^>]+name="csrf_token"[^>]+value="([^"]*)"/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    public function extractDownloadUrlEndpoint(string $html): ?string
    {
        try {
            $document = HTMLDocument::createFromString($html, LIBXML_NOERROR | LIBXML_COMPACT);
        } catch (Throwable) {
            return null;
        }

        foreach ($document->getElementsByTagName('script') as $script) {
            $scriptText = $script->textContent;
            if (! str_contains($scriptText, '"generate_download_url"')) {
                continue;
            }

            if (preg_match('/(?:^|[,{])\s*"generate_download_url"\s*:\s*("(?:(?:\\\\.)|[^"\\\\])*")/s', $scriptText, $matches)) {
                $endpoint = json_decode($matches[1]);

                return is_string($endpoint) && $endpoint !== '' ? $endpoint : null;
            }
        }

        return null;
    }

    public function validateItchControlUrl(string $url, string $gameUrl, string $description): string
    {
        $url = $this->normalizeRelativeUrl($url, $gameUrl);
        $parts = $this->validatedUrlParts($url, $description);
        $host = $this->normalizeHost((string) $parts['host']);
        $gameHost = $this->normalizeHost((string) parse_url($gameUrl, PHP_URL_HOST));

        if (! $this->isItchHost($host)) {
            throw new RuntimeException("Untrusted {$description} host: {$host}");
        }

        if ($host !== $gameHost && $host !== 'itch.io') {
            throw new RuntimeException("Unexpected {$description} host: {$host}");
        }

        $this->assertPubliclyRoutableHost($host, $description);

        return $url;
    }

    public function validateItchFileDownloadUrl(string $url, string $gameUrl, string $description): string
    {
        $url = $this->normalizeRelativeUrl($url, $gameUrl);
        $parts = $this->validatedUrlParts($url, $description);
        $host = $this->normalizeHost((string) $parts['host']);

        $this->assertPubliclyRoutableHost($host, $description);

        return $url;
    }

    /**
     * @return array<string, string>
     */
    public function jsonRequestHeaders(string $referer): array
    {
        return [
            'Accept' => 'application/json',
            'Referer' => $referer,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    /**
     * @param  array<string, mixed>  $downloadInfo
     */
    public function downloadUrlErrorMessage(string $message, array $downloadInfo): string
    {
        $errors = $downloadInfo['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            return $message.': '.implode(', ', array_map('strval', $errors));
        }

        return $message;
    }

    /**
     * @return array{scheme: string, host: string}
     */
    private function validatedUrlParts(string $url, string $description): array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($url === '' || ! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException("Invalid {$description}");
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException("The {$description} must not contain credentials");
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https') {
            throw new RuntimeException("The {$description} must use HTTPS");
        }

        $host = $this->normalizeHost((string) $parts['host']);
        if ($host === '') {
            throw new RuntimeException("The {$description} is missing a host");
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
        ];
    }

    private function normalizeRelativeUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || parse_url($url, PHP_URL_SCHEME) !== null) {
            return $url;
        }

        $baseParts = parse_url($baseUrl);
        if (! is_array($baseParts) || ! isset($baseParts['scheme'], $baseParts['host'])) {
            throw new RuntimeException('Invalid itch.io game URL');
        }

        $origin = strtolower((string) $baseParts['scheme']).'://'.$this->normalizeHost((string) $baseParts['host']);
        if (str_starts_with($url, '//')) {
            return strtolower((string) $baseParts['scheme']).':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        $basePath = (string) ($baseParts['path'] ?? '/');
        $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';

        return $origin.$directory.$url;
    }

    private function isItchHost(string $host): bool
    {
        return $host === 'itch.io' || str_ends_with($host, '.itch.io');
    }

    private function assertPubliclyRoutableHost(string $host, string $description): void
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            throw new RuntimeException("The {$description} cannot point to localhost");
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPubliclyRoutableIp($host, $description);

            return;
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            throw new RuntimeException("Could not resolve {$description} host: {$host}");
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && $ip !== '') {
                $this->assertPubliclyRoutableIp($ip, $description);
            }
        }
    }

    private function assertPubliclyRoutableIp(string $ip, string $description): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException("The {$description} cannot resolve to a private or reserved IP address");
        }
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
