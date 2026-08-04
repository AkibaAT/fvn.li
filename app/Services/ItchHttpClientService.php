<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class ItchHttpClientService
{
    private const MAX_RETRY_COOLDOWN_SECONDS = 300;

    private ?Client $authenticatedClient = null;

    private Client $anonymousClient;

    private ?FlareSolverrClient $flareSolverr = null;

    private ?FlareSolverrSessionManager $sessionManager = null;

    private ItchCloudflareChallengeDetector $challengeDetector;

    public function __construct(
        private readonly ItchHttpClientFactory $clientFactory,
        private readonly ItchUrlSafetyValidator $urlSafetyValidator,
        private int $maxRetries = 5,
        private int $baseCooldown = 30
    ) {
        $this->anonymousClient = $this->clientFactory->createClient();
        $this->challengeDetector = app(ItchCloudflareChallengeDetector::class);

        // Always use FlareSolverr for HTML requests (Cloudflare-protected).
        // API requests are automatically skipped below.
        $this->flareSolverr = app(FlareSolverrClient::class);
        $this->sessionManager = app(FlareSolverrSessionManager::class);
    }

    /**
     * Send a GET request with retry logic for itch.io domains.
     * Uses authenticated client by default.
     *
     * @param  string  $url  The URL to request
     * @param  array  $options  Request options
     * @param  bool  $anonymous  Whether to use anonymous client (no authentication)
     * @return ResponseInterface The response
     *
     * @throws Exception|GuzzleException If the request fails after all retries
     */
    public function get(string $url, array $options = [], bool $anonymous = false): ResponseInterface
    {
        $this->urlSafetyValidator->validate($url);

        // Route HTML requests through FlareSolverr (Cloudflare-protected)
        if ($this->shouldUseFlareSolverr($url)) {
            return $this->sendRequestViaFlareSolverr('GET', $url, $options);
        }

        return $this->sendRequest('GET', $url, $options, $anonymous);
    }

    /**
     * Send a request with retry logic for itch.io domains.
     *
     * @param  string  $method  The HTTP method
     * @param  string  $url  The URL to request
     * @param  array  $options  Request options
     * @param  bool  $anonymous  Whether to use anonymous client (no authentication)
     * @return ResponseInterface The response
     *
     * @throws Exception If the request fails after all retries
     * @throws GuzzleException
     */
    public function sendRequest(
        string $method,
        string $url,
        array $options = [],
        bool $anonymous = false
    ): ResponseInterface {
        $this->urlSafetyValidator->validate($url);

        $options['http_errors'] = false;

        // Select the appropriate client
        $client = $anonymous ? $this->anonymousClient : $this->getAuthenticatedClient();

        $retryCount = 0;
        $lastException = null;
        $cloudflareRetried = false;

        while ($retryCount <= $this->maxRetries) {
            try {
                $response = $client->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if (! $anonymous && ! $cloudflareRetried && $this->challengeDetector->isChallenge($response)) {
                    Log::warning('Cloudflare challenge detected, refreshing authentication', [
                        'url' => $url,
                        'status_code' => $statusCode,
                    ]);

                    // Invalidate cached cookies and force re-authentication
                    $this->invalidateAuthentication();
                    $cloudflareRetried = true;

                    $client = $this->getAuthenticatedClient();

                    // Retry the request with fresh cookies
                    continue;
                }

                // If we got a 429 status code, retry
                if ($statusCode === 429) {
                    $retryCount++;

                    if ($this->handleRateLimitResponse($response, $retryCount, $url, $method)) {
                        continue;
                    }
                }

                // For server errors (5xx), also implement retry logic
                if ($statusCode >= 500 && $statusCode < 600) {
                    $retryCount++;

                    if ($this->handleServerErrorResponse($response, $retryCount, $url, $method)) {
                        continue;
                    }

                    // If we've reached the maximum retries, return the response anyway
                    return $response;
                }

                // For all other responses, return immediately
                return $response;
            } catch (RequestException|Exception $e) {
                $lastException = $e;
                $retryCount++;

                // If the exception message contains "429 Too Many Requests", retry
                if (str_contains($e->getMessage(), '429 Too Many Requests')) {
                    if ($this->handleRateLimitException($e, $retryCount, $url, $method)) {
                        continue;
                    }
                }

                // For other exceptions, throw immediately
                throw $e;
            }
        }

        // If we've reached here, we've exhausted all retries
        if ($lastException) {
            throw $lastException;
        }

        // This should never happen, but just in case
        throw new Exception("Failed to send request to {$url} after {$this->maxRetries} retries");
    }

    /**
     * Send a POST request with retry logic for itch.io domains.
     *
     * @param  string  $url  The URL to request
     * @param  array  $options  Request options
     * @param  bool  $anonymous  Whether to use anonymous client (no authentication)
     * @return ResponseInterface The response
     *
     * @throws Exception|GuzzleException If the request fails after all retries
     */
    public function post(string $url, array $options = [], bool $anonymous = false): ResponseInterface
    {
        $this->urlSafetyValidator->validate($url);

        // Route HTML requests through FlareSolverr (Cloudflare-protected)
        if ($this->shouldUseFlareSolverr($url)) {
            return $this->sendRequestViaFlareSolverr('POST', $url, $options);
        }

        return $this->sendRequest('POST', $url, $options, $anonymous);
    }

    /**
     * Send a PUT request with retry logic for itch.io domains.
     *
     * @param  string  $url  The URL to request
     * @param  array  $options  Request options
     * @param  bool  $anonymous  Whether to use anonymous client (no authentication)
     * @return ResponseInterface The response
     *
     * @throws Exception|GuzzleException If the request fails after all retries
     */
    public function put(string $url, array $options = [], bool $anonymous = false): ResponseInterface
    {
        return $this->sendRequest('PUT', $url, $options, $anonymous);
    }

    /**
     * Send a DELETE request with retry logic for itch.io domains.
     *
     * @param  string  $url  The URL to request
     * @param  array  $options  Request options
     * @param  bool  $anonymous  Whether to use anonymous client (no authentication)
     * @return ResponseInterface The response
     *
     * @throws Exception|GuzzleException If the request fails after all retries
     */
    public function delete(string $url, array $options = [], bool $anonymous = false): ResponseInterface
    {
        return $this->sendRequest('DELETE', $url, $options, $anonymous);
    }

    /**
     * Execute a callback function with retry logic for itch.io operations
     *
     * @param  callable  $callback  The function to execute
     * @param  string  $operationName  Name of the operation for logging
     * @param  callable|null  $onSuccess  Optional callback to run on success
     * @param  callable|null  $onError  Optional callback to run on error
     * @return mixed The result of the callback function
     *
     * @throws Exception If the operation fails after all retries
     */
    public function executeWithRetry(
        callable $callback,
        string $operationName,
        ?callable $onSuccess = null,
        ?callable $onError = null
    ): mixed {
        try {
            // Execute the callback
            $result = $callback();

            Log::info("Operation '{$operationName}' completed successfully");

            // Call success callback if provided
            if ($onSuccess !== null) {
                $onSuccess($operationName);
            }

            return $result;
        } catch (Exception $e) {
            // If it's not a rate limiting error (which would have been handled by our request methods),
            // or if we've exhausted all retries, re-throw the exception
            Log::error("Error during operation '{$operationName}'", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            // Call error callback if provided
            if ($onError !== null) {
                $onError($operationName, $e->getMessage());
            }

            throw $e;
        }
    }

    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    public function setBaseCooldown(int $baseCooldown): self
    {
        $this->baseCooldown = $baseCooldown;

        return $this;
    }

    /**
     * Get the authenticated client, initializing it if necessary
     *
     * @throws GuzzleException
     */
    private function getAuthenticatedClient(): Client
    {
        if ($this->authenticatedClient === null) {
            $authService = app(ItchAuthService::class);
            $this->authenticatedClient = $authService->getClient();
        }

        return $this->authenticatedClient;
    }

    /**
     * Invalidate the current authentication and clear cached cookies
     */
    private function invalidateAuthentication(): void
    {
        $this->authenticatedClient = null;

        Cache::forget('itch_cookies');

        Log::info('Authentication invalidated, cookies cleared');
    }

    /**
     * Check if a URL should be routed through FlareSolverr
     *
     * @param  string  $url  The URL to check
     * @return bool True if should use FlareSolverr
     */
    private function shouldUseFlareSolverr(string $url): bool
    {
        // Only use FlareSolverr if it's enabled
        if ($this->flareSolverr === null) {
            return false;
        }

        if ($this->isApiRequest($url)) {
            return false;
        }

        // All other requests (HTML) should use FlareSolverr
        return true;
    }

    /**
     * Check if a URL is an API/AJAX request that doesn't need Cloudflare bypass
     *
     * These endpoints return JSON, not HTML, and are not Cloudflare-protected
     *
     * @param  string  $url  The URL to check
     * @return bool True if this is an API/AJAX request
     */
    private function isApiRequest(string $url): bool
    {
        if ($this->urlSafetyValidator->isApiRequest($url)) {
            return true;
        }

        // AJAX endpoints that return JSON (not HTML)

        // Pattern: {game_url}/file/{upload_id} - returns download URL as JSON
        if (preg_match('#/file/\d+#', $url)) {
            return true;
        }

        // Feed endpoints that return JSON
        if (str_contains($url, '/feed?') && str_contains($url, 'format=json')) {
            return true;
        }

        // Follow/unfollow endpoints - AJAX actions
        if (str_contains($url, '/-/follow')) {
            return true;
        }

        return false;
    }

    /**
     * Send a request through FlareSolverr session
     *
     * @param  string  $method  The HTTP method
     * @param  string  $url  The URL to request
     * @param  array  $options  Request options
     * @return ResponseInterface The response
     *
     * @throws Exception If the request fails
     */
    private function sendRequestViaFlareSolverr(string $method, string $url, array $options): ResponseInterface
    {
        if ($this->flareSolverr === null) {
            throw new Exception('FlareSolverr is not initialized');
        }

        try {
            $useSession = false;
            $sessionId = null;
            if ($this->sessionManager !== null && $this->sessionManager->isSessionActive()) {
                $useSession = true;
                $sessionId = $this->sessionManager->getActiveSessionId();
                Log::debug('Using active FlareSolverr session', [
                    'session_id' => $sessionId,
                    'url' => $url,
                ]);
            } else {
                $this->flareSolverr->ensureSession();
                $useSession = true;
            }

            $postData = [];
            if ($method === 'POST' && isset($options['form_params'])) {
                $postData = $options['form_params'];
            } elseif ($method === 'POST' && isset($options['json'])) {
                $postData = $options['json'];
            }

            // Make request through FlareSolverr with session
            $result = $this->flareSolverr->request(
                $url,
                $method,
                $postData,
                null,
                $useSession,
                $sessionId
            );

            return new Response(
                $result['status'],
                $result['headers'] ?? [],
                $result['response'] ?? ''
            );
        } catch (Exception $e) {
            Log::error('FlareSolverr request failed', [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a rate limit response (HTTP 429)
     *
     * @param  ResponseInterface  $response  The response with a 429 status code
     * @param  int  $retryCount  The current retry count
     * @param  string  $url  The URL that was requested
     * @param  string  $method  The HTTP method that was used
     * @return bool True if the request should be retried, false otherwise
     *
     * @throws Exception If the maximum number of retries has been reached
     */
    private function handleRateLimitResponse(
        ResponseInterface $response,
        int $retryCount,
        string $url,
        string $method
    ): bool {
        // If we've reached the maximum retries, throw an exception
        if ($retryCount > $this->maxRetries) {
            throw new Exception("429 Too Many Requests - Rate limit exceeded after {$this->maxRetries} retries");
        }

        $retryAfter = $response->getHeaderLine('Retry-After');
        $cooldownTime = $this->calculateRateLimitCooldown($response, $retryCount);

        Log::warning('Rate limited when accessing itch.io, retrying', [
            'url' => $url,
            'method' => $method,
            'attempt' => $retryCount,
            'max_retries' => $this->maxRetries,
            'cooldown' => $cooldownTime,
            'retry_after' => $retryAfter,
        ]);

        // Sleep before retrying
        sleep($cooldownTime);

        return true;
    }

    private function calculateRateLimitCooldown(ResponseInterface $response, int $retryCount): int
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        $cooldownTime = $retryAfter ? (int) $retryAfter : $this->baseCooldown * $retryCount;

        $cooldownTime = max($cooldownTime, 30 * $retryCount);

        return min($cooldownTime, self::MAX_RETRY_COOLDOWN_SECONDS);
    }

    /**
     * Handle a server error response (HTTP 5xx)
     *
     * @param  ResponseInterface  $response  The response with a 5xx status code
     * @param  int  $retryCount  The current retry count
     * @param  string  $url  The URL that was requested
     * @param  string  $method  The HTTP method that was used
     * @return bool True if the request should be retried, false otherwise
     */
    private function handleServerErrorResponse(
        ResponseInterface $response,
        int $retryCount,
        string $url,
        string $method
    ): bool {
        // If we've reached the maximum retries, return false to indicate we should stop retrying
        if ($retryCount > $this->maxRetries) {
            return false;
        }

        $statusCode = $response->getStatusCode();
        $cooldownTime = $this->baseCooldown * $retryCount;

        Log::warning('Server error from itch.io, retrying', [
            'url' => $url,
            'method' => $method,
            'status_code' => $statusCode,
            'attempt' => $retryCount,
            'max_retries' => $this->maxRetries,
            'cooldown' => $cooldownTime,
        ]);

        // Sleep before retrying
        sleep($cooldownTime);

        return true;
    }

    /**
     * Handle a rate limit exception
     *
     * @param  Exception  $exception  The exception that was thrown
     * @param  int  $retryCount  The current retry count
     * @param  string  $url  The URL that was requested
     * @param  string  $method  The HTTP method that was used
     * @return bool True if the request should be retried, false otherwise
     *
     * @throws Exception If the maximum number of retries has been reached
     */
    private function handleRateLimitException(Exception $exception, int $retryCount, string $url, string $method): bool
    {
        // If we've reached the maximum retries, throw the exception
        if ($retryCount > $this->maxRetries) {
            throw $exception;
        }

        $cooldownTime = $this->baseCooldown * $retryCount;

        Log::warning('Rate limit exception when accessing itch.io, retrying', [
            'url' => $url,
            'method' => $method,
            'attempt' => $retryCount,
            'max_retries' => $this->maxRetries,
            'cooldown' => $cooldownTime,
            'exception' => $exception->getMessage(),
        ]);

        // Sleep before retrying
        sleep($cooldownTime);

        return true;
    }
}
