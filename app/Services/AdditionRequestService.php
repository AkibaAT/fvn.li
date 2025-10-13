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

            if (! $this->isValidItchUrl($url)) {
                $results['invalid_count']++;
                $results['errors'][] = "Invalid itch.io URL: {$url}";

                continue;
            }

            try {
                DB::beginTransaction();

                $result = AdditionRequest::findOrCreateForUrl($url);

                // If null is returned, the game already exists and is visible
                if ($result === null) {
                    $results['already_exists_count']++;
                    $results['errors'][] = "Game already exists on the site: {$url}";
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
                        'url' => $url,
                        'is_new_request' => $isNew,
                    ]);
                } else {
                    $results['duplicate_count']++;
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $results['errors'][] = "Error processing {$url}: " . $e->getMessage();
                Log::error('Error submitting addition request', [
                    'user_id' => $user->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Validate if a URL is a valid itch.io URL.
     */
    public function isValidItchUrl(string $url): bool
    {
        // Basic validation for itch.io URLs
        $pattern = '/^https?:\/\/(www\.)?[a-zA-Z0-9\-]+\.itch\.io\/[a-zA-Z0-9\-]+\/?$/';

        return preg_match($pattern, $url) === 1;
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
