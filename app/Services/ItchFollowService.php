<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ItchFollowService
{
    private ItchAuthService $authService;

    private ItchHttpClientService $itchClient;

    public function __construct(ItchAuthService $authService, ItchHttpClientService $itchClient)
    {
        $this->authService = $authService;
        $this->itchClient = $itchClient;
    }

    /**
     * Follow a creator based on game URL
     *
     * @param  string  $gameUrl  The game URL (e.g., https://username.itch.io/game-name)
     * @return bool Whether the follow operation was successful
     *
     * @throws GuzzleException
     */
    public function followCreatorFromGameUrl(string $gameUrl): bool
    {
        try {
            // Parse creator username from game URL
            $parsed = parse_url($gameUrl);
            if (! isset($parsed['host'])) {
                Log::error('Invalid game URL format', ['url' => $gameUrl]);

                return false;
            }

            $host = $parsed['host'];
            if (! str_ends_with($host, '.itch.io')) {
                Log::error('Not an itch.io URL', ['url' => $gameUrl]);

                return false;
            }

            // Extract username from hostname
            $username = explode('.', $host)[0];

            // Generate creator URL and follow URL
            $creatorUrl = "https://{$username}.itch.io";
            $followUrl = "{$creatorUrl}/-/follow?source=game";

            return $this->followCreator($followUrl);
        } catch (Exception $e) {
            Log::error('Error parsing game URL', [
                'url' => $gameUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Follow a creator using their follow URL
     *
     * @param  string  $followUrl  The follow URL for the creator
     * @return bool Whether the follow operation was successful
     *
     * @throws GuzzleException
     */
    public function followCreator(string $followUrl): bool
    {
        try {
            // Get CSRF token
            $csrfToken = $this->authService->getCsrfToken();
            if (! $csrfToken) {
                return false;
            }

            // Make follow request - use the authenticated client via ItchHttpClientService
            // which will handle the authentication internally
            $response = $this->itchClient->post($followUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept' => '*/*',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
                'form_params' => [
                    'csrf_token' => $csrfToken,
                ],
            ]);

            // Success if response is 200
            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            Log::error('Error following creator', [
                'url' => $followUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
