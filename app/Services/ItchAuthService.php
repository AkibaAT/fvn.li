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
    private ItchHttpClientService $itchClient;

    public function __construct(ItchHttpClientService $itchClient)
    {
        $this->cookieJar = new CookieJar;
        $this->client = new Client([
            'cookies' => $this->cookieJar,
            'timeout' => 30,
            'connect_timeout' => 5,
        ]);
        $this->itchClient = $itchClient;
    }

    /**
     * Get an authenticated HTTP client for itch.io requests
     *
     * @throws RuntimeException If authentication fails
     */
    public function getClient(): Client
    {
        if (! $this->ensureAuthenticated()) {
            throw new RuntimeException('Failed to authenticate with itch.io');
        }

        return $this->client;
    }

    /**
     * Get the cookie jar used for authentication
     *
     * @throws RuntimeException If authentication fails
     */
    public function getCookieJar(): CookieJar
    {
        if (! $this->ensureAuthenticated()) {
            throw new RuntimeException('Failed to authenticate with itch.io');
        }

        return $this->cookieJar;
    }

    /**
     * Extract the itch.io game ID from a game page URL
     *
     * @throws RuntimeException|GuzzleException If the game ID cannot be found
     */
    public function getGameId(string $url): int
    {
        $response = $this->itchClient->get($url, ['cookies' => $this->cookieJar]);
        $html = $response->getBody()->getContents();

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $meta = $doc->querySelector('meta[name="itch:path"]');

        if (! $meta) {
            throw new RuntimeException("Could not find game ID for URL: {$url}");
        }

        return (int) basename($meta->getAttribute('content'));
    }

    public function getCsrfToken(): ?string
    {
        $response = $this->itchClient->get('https://itch.io/', ['cookies' => $this->cookieJar]);
        $html = $response->getBody()->getContents();

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $csrfToken = $doc->querySelector('meta[name="csrf_token"]');

        if (! $csrfToken) {
            return null;
        }

        return $csrfToken->getAttribute('value');
    }

    /**
     * Ensure we have a valid authenticated session
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
                $response = $this->itchClient->get('https://itch.io/dashboard', [
                    'allow_redirects' => false,
                    'cookies' => $this->cookieJar,
                ]);
                if ($response->getStatusCode() === 200) {
                    return true;
                }

                // Clear invalid cached cookies
                Cache::forget(self::CACHE_KEY);
            }

            // Get login page and extract form data
            $response = $this->itchClient->get('https://itch.io/login', ['cookies' => $this->cookieJar]);
            $html = $response->getBody()->getContents();
            $formData = $this->getLoginFormData($html);

            // Perform login
            $response = $this->itchClient->post('https://itch.io/login', [
                'form_params' => $formData,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Origin' => 'https://itch.io',
                    'Referer' => 'https://itch.io/login',
                ],
                'allow_redirects' => false,
                'cookies' => $this->cookieJar,
            ]);

            // Check for successful login
            if ($response->getStatusCode() === 302) {
                // Follow redirect to verify login
                $redirectUrl = $response->getHeader('Location')[0];
                $response = $this->itchClient->get($redirectUrl, ['cookies' => $this->cookieJar]);

                if ($response->getStatusCode() === 200) {
                    // Cache cookies for future use
                    Cache::put(self::CACHE_KEY, $this->cookieJar->toArray(), now()->addWeek());

                    return true;
                }
            }

            Log::error('Itch.io login failed', [
                'status_code' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Itch.io authentication error', ['exception' => $e]);

            return false;
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
