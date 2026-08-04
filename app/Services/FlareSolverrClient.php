<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Log;

class FlareSolverrClient
{
    private Client $client;

    private string $baseUrl;

    private int $maxTimeout;

    private ?string $sessionId = null;

    public function __construct()
    {
        $this->baseUrl = config('services.flaresolverr.url', 'http://flaresolverr:8191');
        $this->maxTimeout = (int) config('services.flaresolverr.max_timeout', 60000);

        $this->client = new Client([
            'timeout' => 120, // FlareSolverr can take a while
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Make a request through FlareSolverr to bypass Cloudflare
     *
     * @param  string  $url  The URL to request
     * @param  string  $method  HTTP method (GET or POST)
     * @param  array  $postData  POST data if method is POST
     * @param  CookieJar|null  $cookieJar  Optional cookie jar to use and update
     * @return array Response data including solution, cookies, and user agent
     *
     * @throws Exception
     */
    public function request(
        string $url,
        string $method = 'GET',
        array $postData = [],
        ?CookieJar $cookieJar = null,
        bool $useSession = true,
        ?string $sessionId = null
    ): array {
        $payload = [
            'cmd' => 'request.'.strtolower($method),
            'url' => $url,
            'maxTimeout' => $this->maxTimeout,
        ];

        $resolvedSessionId = $sessionId ?? $this->sessionId;
        if ($useSession && $resolvedSessionId !== null) {
            $payload['session'] = $resolvedSessionId;
        }

        if ($cookieJar !== null) {
            $cookies = [];
            foreach ($cookieJar->toArray() as $cookie) {
                $cookies[] = [
                    'name' => $cookie['Name'],
                    'value' => $cookie['Value'],
                ];
            }
            if (! empty($cookies)) {
                $payload['cookies'] = $cookies;
            }
        }

        if ($method === 'POST') {
            $payload['postData'] = ! empty($postData) ? http_build_query($postData) : '';
        }

        Log::info('FlareSolverr request', [
            'url' => $url,
            'method' => $method,
            'has_cookies' => $cookieJar !== null && ! $cookieJar->toArray(),
        ]);

        try {
            $response = $this->client->post($this->baseUrl.'/v1', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['status']) || $data['status'] !== 'ok') {
                $message = $data['message'] ?? 'Unknown error';

                if (stripos($message, 'captcha') !== false || stripos($message, 'challenge') !== false) {
                    Log::error('FlareSolverr encountered a CAPTCHA or unsolvable challenge', [
                        'url' => $url,
                        'error' => $message,
                        'suggestion' => 'This may require manual intervention or indicates itch.io has enhanced protection',
                    ]);

                    throw new Exception("FlareSolverr cannot solve CAPTCHA: {$message}. Manual intervention may be required.");
                }

                throw new Exception("FlareSolverr request failed: {$message}");
            }

            $solution = $data['solution'] ?? [];

            $responseBody = $solution['response'] ?? '';
            if ($this->containsCaptcha($responseBody)) {
                Log::error('FlareSolverr returned a page with CAPTCHA', [
                    'url' => $url,
                    'suggestion' => 'itch.io may be showing interactive CAPTCHA that cannot be solved automatically',
                ]);

                throw new Exception('FlareSolverr encountered an unsolvable CAPTCHA. Manual intervention required.');
            }

            if ($cookieJar !== null && isset($solution['cookies'])) {
                foreach ($solution['cookies'] as $cookieData) {
                    $cookie = new SetCookie([
                        'Name' => $cookieData['name'],
                        'Value' => $cookieData['value'],
                        'Domain' => $cookieData['domain'] ?? null,
                        'Path' => $cookieData['path'] ?? '/',
                        'Expires' => $cookieData['expires'] ?? null,
                        'Secure' => $cookieData['secure'] ?? false,
                        'HttpOnly' => $cookieData['httpOnly'] ?? false,
                        'SameSite' => $cookieData['sameSite'] ?? null,
                    ]);
                    $cookieJar->setCookie($cookie);
                }
            }

            Log::info('FlareSolverr request successful', [
                'url' => $url,
                'status' => $solution['status'] ?? null,
                'cookies_count' => isset($solution['cookies']) ? count($solution['cookies']) : 0,
            ]);

            return [
                'status' => $solution['status'] ?? 200,
                'url' => $solution['url'] ?? $url,
                'headers' => $solution['headers'] ?? [],
                'cookies' => $solution['cookies'] ?? [],
                'userAgent' => $solution['userAgent'] ?? null,
                'response' => $solution['response'] ?? '',
            ];
        } catch (Exception $e) {
            Log::error('FlareSolverr request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("FlareSolverr error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a new session in FlareSolverr
     *
     * @param  string|null  $sessionId  Optional session ID to use (prevents cross-talk between commands)
     * @return string Session ID
     *
     * @throws Exception
     */
    public function createSession(?string $sessionId = null): string
    {
        try {
            $payload = [
                'cmd' => 'sessions.create',
            ];

            // If a specific session ID is provided, use it
            if ($sessionId !== null) {
                $payload['session'] = $sessionId;
            }

            $response = $this->client->post($this->baseUrl.'/v1', [
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! isset($data['status']) || $data['status'] !== 'ok') {
                throw new Exception('Failed to create FlareSolverr session');
            }

            return $data['session'] ?? '';
        } catch (Exception $e) {
            Log::error('Failed to create FlareSolverr session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List all active FlareSolverr sessions
     *
     * @return array Array of session IDs
     */
    public function listSessions(): array
    {
        try {
            $response = $this->client->post($this->baseUrl.'/v1', [
                'json' => [
                    'cmd' => 'sessions.list',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['status']) && $data['status'] === 'ok') {
                return $data['sessions'] ?? [];
            }

            return [];
        } catch (Exception $e) {
            Log::warning('Failed to list FlareSolverr sessions', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Destroy a session in FlareSolverr
     *
     * @param  string  $sessionId  Session ID to destroy
     *
     * @throws Exception
     */
    public function destroySession(string $sessionId): void
    {
        $sessions = $this->listSessions();
        if (! in_array($sessionId, $sessions)) {
            Log::debug('FlareSolverr session already destroyed or expired', [
                'session_id' => $sessionId,
            ]);

            return;
        }

        try {
            $this->client->post($this->baseUrl.'/v1', [
                'json' => [
                    'cmd' => 'sessions.destroy',
                    'session' => $sessionId,
                ],
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to destroy FlareSolverr session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - session cleanup is not critical
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->client->get($this->baseUrl.'/health', [
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (Exception $exception) {
            Log::debug('FlareSolverr health check failed', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    public function getSessionId(): ?string
    {
        if ($this->sessionId === null) {
            try {
                $this->sessionId = $this->createSession();
            } catch (Exception $e) {
                Log::warning('Failed to create FlareSolverr session', ['error' => $e->getMessage()]);

                return null;
            }
        }

        return $this->sessionId;
    }

    /**
     * Ensure a session exists for this client
     */
    public function ensureSession(): bool
    {
        return $this->getSessionId() !== null;
    }

    /**
     * Check if the response body contains CAPTCHA indicators
     *
     * @param  string  $body  The response body to check
     * @return bool True if CAPTCHA indicators are found
     */
    private function containsCaptcha(string $body): bool
    {
        $captchaIndicators = [
            'g-recaptcha',
            'recaptcha',
            'hcaptcha',
            'h-captcha',
            'captcha-box',
            'captcha_image',
            'solve the captcha',
            'verify you are human',
            'prove you are not a robot',
        ];

        foreach ($captchaIndicators as $indicator) {
            if (stripos($body, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }
}
