<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProxyRotator
{
    private const string COOKIE_CACHE_KEY = 'itch_session_cookie';
    private const string PROXY_BLACKLIST_PREFIX = 'proxy_blacklist:';
    private const int COOKIE_CACHE_TTL = 3600; // 1 hour
    private const int PROXY_BLACKLIST_TTL = 1800; // 30 minutes
    private const int MAX_RETRIES = 3;
    private const int BASE_WAIT = 30;
    private const int MAX_WAIT = 120;

    private Collection $proxies;
    private Collection $blacklistedProxies;

    public function __construct(
        private readonly string $proxyUser,
        private readonly string $proxyPassword,
        private readonly string $itchUser,
        private readonly string $itchPassword,
        array $proxyList
    ) {
        $this->proxies = collect($proxyList);
        $this->blacklistedProxies = collect();
        $this->loadBlacklistedProxies();
    }

    public function request(int $attempt = 1): PendingRequest
    {
        try {
            $proxy = $this->getWorkingProxy($attempt);
            $proxyUrl = "https://{$this->proxyUser}:{$this->proxyPassword}@{$proxy}";

            Log::debug("Using proxy: {$proxy} (attempt {$attempt})");

            $request = Http::withOptions([
                'proxy' => $proxyUrl,
                'timeout' => 30,
                'connect_timeout' => 10,
            ])->retry(2, 100, function ($exception) use ($proxy) {
                // Blacklist proxy on connection issues
                if ($this->isConnectionError($exception)) {
                    $this->blacklistProxy($proxy);
                    Log::warning("Blacklisting proxy {$proxy} due to connection error: {$exception->getMessage()}");

                    return true;
                }

                return false;
            })->withCookies(
                $this->getSessionCookies($attempt),
                'itch.io'
            );

            // Add small delay before request to avoid rate limits
            usleep(mt_rand(100000, 500000)); // 0.1-0.5 second

            return $request;

        } catch (Exception $e) {
            if ($attempt >= self::MAX_RETRIES) {
                throw new RuntimeException("All proxy attempts failed: {$e->getMessage()}", 0, $e);
            }

            $waitTime = min(self::BASE_WAIT * pow(2, $attempt - 1), self::MAX_WAIT);
            Log::warning("Proxy attempt {$attempt} failed: {$e->getMessage()}. Waiting {$waitTime}s before retry.");

            sleep($waitTime);

            return $this->request($attempt + 1);
        }
    }

    private function loadBlacklistedProxies(): void
    {
        $this->blacklistedProxies = collect();
        foreach ($this->proxies as $proxy) {
            if (Cache::has(self::PROXY_BLACKLIST_PREFIX . $proxy)) {
                $this->blacklistedProxies->push($proxy);
            }
        }
    }

    private function blacklistProxy(string $proxy): void
    {
        Cache::put(
            self::PROXY_BLACKLIST_PREFIX . $proxy,
            true,
            now()->addSeconds(self::PROXY_BLACKLIST_TTL)
        );
        $this->blacklistedProxies->push($proxy);
    }

    private function getWorkingProxy(int $attempt): string
    {
        $availableProxies = $this->proxies->diff($this->blacklistedProxies);

        if ($availableProxies->isEmpty()) {
            if ($attempt >= self::MAX_RETRIES) {
                throw new RuntimeException('No working proxies available');
            }

            // If all proxies are blacklisted, clear the blacklist and try again
            Log::warning('All proxies blacklisted, clearing blacklist and retrying');
            foreach ($this->blacklistedProxies as $proxy) {
                Cache::forget(self::PROXY_BLACKLIST_PREFIX . $proxy);
            }
            $this->blacklistedProxies = collect();

            return $this->getWorkingProxy($attempt + 1);
        }

        return $availableProxies->random();
    }

    private function isConnectionError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'connect') ||
            str_contains($message, 'timeout') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'refused') ||
            str_contains($message, 'unreachable') ||
            str_contains($message, 'could not resolve') ||
            str_contains($message, 'no route to host');
    }

    private function getSessionCookies(int $attempt = 1): array
    {
        // Try to get cached session
        $cookies = Cache::get(self::COOKIE_CACHE_KEY);
        if ($cookies) {
            return $cookies;
        }

        try {
            // Need to login and get new session
            $cookies = $this->login();

            // Cache the cookies
            Cache::put(self::COOKIE_CACHE_KEY, $cookies, self::COOKIE_CACHE_TTL);

            return $cookies;
        } catch (Exception $e) {
            if ($attempt >= self::MAX_RETRIES) {
                throw new RuntimeException('Failed to get session cookies: ' . $e->getMessage(), 0, $e);
            }

            $waitTime = min(self::BASE_WAIT * pow(2, $attempt - 1), self::MAX_WAIT);
            Log::warning("Login attempt {$attempt} failed: {$e->getMessage()}. Waiting {$waitTime}s before retry.");

            sleep($waitTime);
            Cache::forget(self::COOKIE_CACHE_KEY);

            return $this->getSessionCookies($attempt + 1);
        }
    }

    private function login(): array
    {
        $proxy = $this->getWorkingProxy(1);
        $proxyUrl = "https://{$this->proxyUser}:{$this->proxyPassword}@{$proxy}";

        Log::info("Logging in using proxy: {$proxy}");

        try {
            // Get CSRF token
            $response = Http::withOptions([
                'proxy' => $proxyUrl,
                'timeout' => 30,
                'connect_timeout' => 10,
            ])->get('https://itch.io/login');

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            preg_match('/<input[^>]*name="csrf_token"[^>]*value="([^"]*)"/', $response->body(), $matches);
            $csrfToken = $matches[1] ?? null;

            if (! $csrfToken) {
                throw new RuntimeException('Could not extract CSRF token');
            }

            // Add small delay before login to avoid rate limits
            sleep(2);

            // Perform login
            $loginResponse = Http::withOptions([
                'proxy' => $proxyUrl,
                'timeout' => 30,
                'connect_timeout' => 10,
            ])->asForm()->post('https://itch.io/login', [
                'username' => $this->itchUser,
                'password' => $this->itchPassword,
                'csrf_token' => $csrfToken,
            ]);

            if (! $loginResponse->successful()) {
                throw new RequestException($loginResponse);
            }

            // Check if login was successful by looking for cookies
            $cookies = [];
            foreach ($loginResponse->cookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }

            if (empty($cookies)) {
                throw new RuntimeException('No cookies received after login');
            }

            return $cookies;

        } catch (Exception $e) {
            if ($this->isConnectionError($e)) {
                $this->blacklistProxy($proxy);
                Log::warning("Login failed, blacklisting proxy {$proxy}: {$e->getMessage()}");
            }
            throw $e;
        }
    }
}
