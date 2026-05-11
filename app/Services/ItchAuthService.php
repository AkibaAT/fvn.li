<?php

declare(strict_types=1);

namespace App\Services;

use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ItchAuthService
{
    private const string CACHE_KEY = 'itch_cookies';

    private Client $client;

    private CookieJar $cookieJar;

    private ?FlareSolverrClient $flareSolverr = null;

    private bool $useFlareSolverr;

    public function __construct(
        private readonly ItchHttpClientFactory $clientFactory,
        ?FlareSolverrClient $flareSolverr = null
    ) {
        $this->cookieJar = $this->clientFactory->createCookieJar();
        $this->client = $this->clientFactory->createClient($this->cookieJar);
        $this->useFlareSolverr = config('services.flaresolverr.enabled', true);

        if ($this->useFlareSolverr && $flareSolverr !== null) {
            $this->flareSolverr = $flareSolverr;
        }
    }

    /**
     * Get an authenticated HTTP client for itch.io requests
     *
     * @throws RuntimeException If authentication fails
     * @throws GuzzleException
     */
    public function getClient(): Client
    {
        if (! $this->ensureAuthenticated()) {
            throw new RuntimeException('Failed to authenticate with itch.io');
        }

        return $this->client;
    }

    /**
     * Extract the itch.io game ID from a game page URL
     *
     * @throws RuntimeException|GuzzleException If the game ID cannot be found
     */
    public function getGameId(string $url): int
    {
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $meta = $doc->querySelector('meta[name="itch:path"]');

        if (! $meta) {
            throw new RuntimeException("Could not find game ID for URL: {$url}");
        }

        return (int) basename($meta->getAttribute('content'));
    }

    /**
     * @throws GuzzleException
     */
    public function getCsrfToken(): ?string
    {
        $response = $this->client->get('https://itch.io/');
        $html = $response->getBody()->getContents();

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $csrfToken = $doc->querySelector('meta[name="csrf_token"]');

        if (! $csrfToken) {
            return null;
        }

        $token = $csrfToken->getAttribute('content') ?: $csrfToken->getAttribute('value');

        return $token !== '' ? $token : null;
    }

    /**
     * Ensure we have a valid authenticated session
     *
     * @throws GuzzleException
     */
    private function ensureAuthenticated(): bool
    {
        try {
            // Try to use cached session first
            $cookies = Cache::get(self::CACHE_KEY);
            if ($cookies) {
                foreach ($cookies as $cookieData) {
                    $cookie = new SetCookie($cookieData);
                    $this->cookieJar->setCookie($cookie);
                }

                // Verify session is still valid
                if ($this->verifySession()) {
                    return true;
                }

                // Clear invalid cached cookies
                Cache::forget(self::CACHE_KEY);
                $this->cookieJar = $this->clientFactory->createCookieJar();
            }

            // Perform login
            if ($this->performLogin()) {
                // Cache cookies for future use
                Cache::put(self::CACHE_KEY, $this->cookieJar->toArray(), now()->addWeek());

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Itch.io authentication error', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Verify if the current session is still valid
     * Uses regular HTTP client with cached cookies (fast)
     */
    private function verifySession(): bool
    {
        try {
            $response = $this->client->get('https://itch.io/dashboard', ['allow_redirects' => false]);

            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            Log::warning('Session verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Perform login to itch.io
     * Uses FlareSolverr ONLY to get past Cloudflare and obtain cookies
     * After that, regular HTTP client is used with those cookies
     */
    private function performLogin(): bool
    {
        try {
            // Get login page and extract form data
            $html = $this->getLoginPageHtml();
            $formData = $this->getLoginFormData($html);

            // Perform login via FlareSolverr to bypass Cloudflare
            if ($this->useFlareSolverr && $this->flareSolverr !== null) {
                Log::info('Using FlareSolverr to bypass Cloudflare and login to itch.io');

                $result = $this->flareSolverr->request(
                    'https://itch.io/login',
                    'POST',
                    $formData,
                    $this->cookieJar
                );

                // Check if login was successful
                if ($result['status'] === 200 || $result['status'] === 302) {
                    Log::info('FlareSolverr login successful, cookies obtained');

                    // Now verify we can access the dashboard using regular HTTP with the cookies
                    return $this->verifySession();
                }

                Log::error('Itch.io login failed (FlareSolverr)', [
                    'status' => $result['status'],
                ]);

                return false;
            } else {
                // Direct HTTP login (will fail if Cloudflare is active)
                Log::info('Attempting direct HTTP login (FlareSolverr disabled)');

                $response = $this->client->post('https://itch.io/login', [
                    'form_params' => $formData,
                    'headers' => [
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'Origin' => 'https://itch.io',
                        'Referer' => 'https://itch.io/login',
                    ],
                    'allow_redirects' => false,
                ]);

                // Check for successful login
                if ($response->getStatusCode() === 302) {
                    // Follow redirect to verify login
                    $redirectUrl = $response->getHeader('Location')[0];
                    $response = $this->client->get($redirectUrl);

                    if ($response->getStatusCode() === 200) {
                        return true;
                    }
                }

                Log::error('Itch.io login failed', [
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);

                return false;
            }
        } catch (Exception $e) {
            Log::error('Login attempt failed', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Get the login page HTML
     * Uses FlareSolverr to bypass Cloudflare if enabled
     */
    private function getLoginPageHtml(): string
    {
        if ($this->useFlareSolverr && $this->flareSolverr !== null) {
            Log::info('Fetching login page via FlareSolverr to bypass Cloudflare');

            $result = $this->flareSolverr->request(
                'https://itch.io/login',
                'GET',
                [],
                $this->cookieJar
            );

            return $result['response'];
        } else {
            Log::info('Fetching login page via direct HTTP');

            $response = $this->client->get('https://itch.io/login');

            return $response->getBody()->getContents();
        }
    }

    /**
     * Extract and prepare login form data from the login page HTML
     *
     * @throws RuntimeException If the login form cannot be found or parsed
     */
    private function getLoginFormData(string $html): array
    {
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        // Find the login form specifically
        $loginForm = $doc->querySelector('div.login_form_widget form.form');
        if (! $loginForm) {
            throw new RuntimeException('Could not find login form');
        }

        // Get all input fields from this specific form
        $formData = [];
        foreach ($loginForm->querySelectorAll('input') as $input) {
            $name = $input->getAttribute('name');
            $value = $input->getAttribute('value');
            if ($name) {
                $formData[$name] = $value;
            }
        }

        // Add credentials
        $formData['username'] = config('services.itch.username');
        $formData['password'] = config('services.itch.password');

        return $formData;
    }
}
