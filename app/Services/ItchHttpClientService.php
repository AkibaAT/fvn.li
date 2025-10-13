<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class ItchHttpClientService
{
    private ?Client $authenticatedClient = null;

    private Client $anonymousClient;

    /**
     * Create a new ItchHttpClientService instance.
     */
    public function __construct(
        private readonly ItchHttpClientFactory $clientFactory,
        private int $maxRetries = 5,
        private int $baseCooldown = 30
    ) {
        $this->anonymousClient = $this->clientFactory->createClient();
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
        // Set http_errors to false to prevent exceptions for 4xx/5xx responses
        $options['http_errors'] = false;

        // Select the appropriate client
        $client = $anonymous ? $this->anonymousClient : $this->getAuthenticatedClient();

        $retryCount = 0;
        $lastException = null;

        while ($retryCount <= $this->maxRetries) {
            try {
                $response = $client->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

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

            // Log success
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

    /**
     * Set the maximum number of retries.
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    /**
     * Set the base cooldown time in seconds.
     */
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

        // Calculate cooldown time, respecting Retry-After header if present
        $retryAfter = $response->getHeaderLine('Retry-After');
        $cooldownTime = $retryAfter ? (int) $retryAfter : $this->baseCooldown * $retryCount;

        // Ensure minimum cooldown of 30 seconds
        $cooldownTime = max($cooldownTime, 30 * $retryCount);

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
