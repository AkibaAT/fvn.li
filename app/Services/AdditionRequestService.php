<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AdditionRequestSubmitted;
use App\Models\AdditionRequest;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdditionRequestService
{
    /**
     * Submit addition requests for multiple URLs by a user.
     * Returns array with success count, duplicate count, and any errors.
     */
    public function submitRequests(User $user, array $urls): array
    {
        $results = [
            'success_count' => 0,
            'duplicate_count' => 0,
            'invalid_count' => 0,
            'already_exists_count' => 0,
            'errors' => [],
            'requests' => [],
        ];

        foreach ($urls as $url) {
            $url = trim($url);

            if (empty($url)) {
                continue;
            }

            // Validate URL format
            $validation = $this->validateUrl($url);
            if (! $validation['valid']) {
                $results['invalid_count']++;
                $results['errors'][] = $validation['error'];

                continue;
            }

            // Normalize URL (strip query parameters, fragments, etc.)
            $normalizedUrl = $this->normalizeUrl($url);

            // Detect platform
            $platform = $this->detectPlatform($normalizedUrl);

            try {
                DB::beginTransaction();

                $result = AdditionRequest::findOrCreateForUrl($normalizedUrl, $platform);

                // If null is returned, the game already exists and is visible
                if ($result === null) {
                    $results['already_exists_count']++;
                    $results['errors'][] = "Game already exists on the site: {$normalizedUrl}";
                    DB::commit();

                    continue;
                }

                [$request, $isNew] = $result;
                $wasUserAdded = $request->addUser($user);

                if ($wasUserAdded) {
                    $results['success_count']++;
                    $results['requests'][] = $request;

                    // Fire event for Discord notifications (only for new requests)
                    if ($isNew) {
                        AdditionRequestSubmitted::dispatch($request, $user, $isNew);
                    }

                    Log::info('Addition request submitted', [
                        'user_id' => $user->id,
                        'request_id' => $request->id,
                        'url' => $normalizedUrl,
                        'platform' => $platform,
                        'is_new_request' => $isNew,
                    ]);
                } else {
                    $results['duplicate_count']++;
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $results['errors'][] = "Error processing {$normalizedUrl}: ".$e->getMessage();
                Log::error('Error submitting addition request', [
                    'user_id' => $user->id,
                    'url' => $normalizedUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Validate if a URL is valid and supported.
     * Returns array with 'valid' boolean and 'error' message if invalid.
     */
    public function validateUrl(string $url): array
    {
        // Check if it's a valid URL format
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'error' => "Invalid URL format: {$url}",
            ];
        }

        // Parse URL to check components
        $parsed = parse_url($url);
        if (! isset($parsed['scheme']) || ! isset($parsed['host'])) {
            return [
                'valid' => false,
                'error' => "Invalid URL structure: {$url}",
            ];
        }

        // Only allow http and https
        if (! in_array($parsed['scheme'], ['http', 'https'])) {
            return [
                'valid' => false,
                'error' => "Only HTTP/HTTPS URLs are supported: {$url}",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Normalize a URL by removing query parameters, fragments, www, and trailing slashes.
     */
    public function normalizeUrl(string $url): string
    {
        // Parse the URL
        $parsed = parse_url($url);

        // Rebuild without query string and fragment
        $normalized = $parsed['scheme'].'://';

        // Remove www. from host
        $host = $parsed['host'] ?? '';
        $host = preg_replace('/^www\./', '', $host);
        $normalized .= $host;

        // Add port if present and not default
        if (isset($parsed['port']) &&
            ! (($parsed['scheme'] === 'http' && $parsed['port'] === 80) ||
              ($parsed['scheme'] === 'https' && $parsed['port'] === 443))) {
            $normalized .= ':'.$parsed['port'];
        }

        // Add path, removing trailing slashes
        $path = $parsed['path'] ?? '/';
        $normalized .= rtrim($path, '/');

        return $normalized;
    }

    /**
     * Detect the platform from a URL.
     */
    public function detectPlatform(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return 'other';
        }

        // Remove www. prefix for matching
        $host = preg_replace('/^www\./', '', $host);

        // Check for itch.io
        if (str_ends_with($host, '.itch.io') || $host === 'itch.io') {
            return 'itch_io';
        }

        // Check for Steam
        if (str_contains($host, 'steampowered.com') || str_contains($host, 'store.steampowered.com')) {
            return 'steam';
        }

        return 'other';
    }

    /**
     * Get addition requests for a user with optional filtering.
     */
    public function getUserRequests(User $user, ?string $status = null): Collection
    {
        $query = $user->additionRequests();

        if ($status && in_array($status, AdditionRequest::getStatuses())) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Cancel a user's participation in an addition request.
     * Returns true if successful, false if the user can't cancel.
     */
    public function cancelUserRequest(User $user, AdditionRequest $request): array
    {
        if (! $request->canBeCancelledByUser($user)) {
            return [
                'success' => false,
                'message' => 'This request cannot be cancelled. It may have already been processed or you are not associated with it.',
            ];
        }

        try {
            DB::beginTransaction();

            $wasRemoved = $request->removeUser($user);

            if ($wasRemoved) {
                Log::info('User cancelled addition request', [
                    'user_id' => $user->id,
                    'request_id' => $request->id,
                    'url' => $request->itch_url,
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Your request has been cancelled successfully.',
                ];
            } else {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Unable to cancel the request. Please try again.',
                ];
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling addition request', [
                'user_id' => $user->id,
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while cancelling the request. Please try again.',
            ];
        }
    }

    /**
     * Get pending addition requests for admin review.
     */
    public function getPendingRequests(): Collection
    {
        return AdditionRequest::where('status', AdditionRequest::STATUS_PENDING)
            ->with(['users', 'reviewer'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Parse URLs from text input (handles multiple URLs separated by newlines).
     */
    public function parseUrls(string $input): array
    {
        $lines = explode("\n", $input);
        $urls = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (! empty($line)) {
                $urls[] = $line;
            }
        }

        return $urls;
    }

    /**
     * Get statistics for addition requests.
     */
    public function getStatistics(): array
    {
        return [
            'total' => AdditionRequest::count(),
            'pending' => AdditionRequest::where('status', AdditionRequest::STATUS_PENDING)->count(),
            'approved' => AdditionRequest::where('status', AdditionRequest::STATUS_APPROVED)->count(),
            'rejected' => AdditionRequest::where('status', AdditionRequest::STATUS_REJECTED)->count(),
            'unique_users' => DB::table('addition_request_users')->distinct('user_id')->count(),
        ];
    }
}
