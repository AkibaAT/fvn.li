<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class ItchCloudflareChallengeDetector
{
    public function isChallenge(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $response->getBody()->rewind();

        if ($statusCode === 403 && $this->hasChallengeBody($body, $statusCode)) {
            return true;
        }

        if (($statusCode === 302 || $statusCode === 301) && $this->hasChallengeRedirect($response, $statusCode)) {
            return true;
        }

        return $this->hasCloudflareServerHeader($response, $statusCode);
    }

    private function hasChallengeBody(string $body, int $statusCode): bool
    {
        foreach ($this->indicators() as $indicator) {
            if (stripos($body, $indicator) !== false) {
                Log::info('Cloudflare challenge detected', [
                    'status_code' => $statusCode,
                    'indicator' => $indicator,
                ]);

                return true;
            }
        }

        return false;
    }

    private function hasChallengeRedirect(ResponseInterface $response, int $statusCode): bool
    {
        $location = $response->getHeaderLine('Location');
        if (stripos($location, 'cdn-cgi/challenge') === false) {
            return false;
        }

        Log::info('Cloudflare challenge redirect detected', [
            'status_code' => $statusCode,
            'location' => $location,
        ]);

        return true;
    }

    private function hasCloudflareServerHeader(ResponseInterface $response, int $statusCode): bool
    {
        $server = $response->getHeaderLine('Server');
        if (stripos($server, 'cloudflare') === false || $statusCode !== 403) {
            return false;
        }

        Log::info('Cloudflare 403 detected', [
            'status_code' => $statusCode,
            'server' => $server,
        ]);

        return true;
    }

    private function indicators(): array
    {
        return [
            'cf-challenge',
            'cf-captcha-container',
            'Checking your browser',
            'Just a moment',
            'Enable JavaScript and cookies to continue',
            'cf-error-details',
            'cloudflare',
        ];
    }
}
