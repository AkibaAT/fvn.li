<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdditionRequest;
use App\Models\User;
use App\Services\AdditionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardAdditionRequestController extends Controller
{
    public function submitAdditionRequest(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);
        $service = new AdditionRequestService;

        $urls = $service->parseUrls((string) $request->input('urls', ''));

        if (empty($urls)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid URLs provided',
                'errors' => [
                    'urls' => 'Please enter at least one valid game URL.',
                ],
            ], 422);
        }

        $result = $service->submitRequests($user, $urls);

        $success = $result['success_count'] > 0;
        $message = $success
            ? "Successfully submitted {$result['success_count']} request(s)."
            : 'No requests were submitted.';

        if ($result['duplicate_count'] > 0) {
            $message .= " {$result['duplicate_count']} duplicate(s) were ignored.";
        }

        if ($result['invalid_count'] > 0) {
            $message .= " {$result['invalid_count']} invalid URL(s) were ignored.";
        }

        if ($result['already_exists_count'] > 0) {
            $message .= " {$result['already_exists_count']} game(s) already exist on the site.";
        }

        if (! empty($result['errors'])) {
            $message .= ' Errors: ' . implode(', ', $result['errors']);
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'result' => $result,
            'errors' => $result['errors'] ?? [],
        ], $success ? 200 : 422);
    }

    public function getUserAdditionRequests(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $status = $request->input('status');

        if ($status === 'processing' || $status === 'completed') {
            $status = 'approved';
        }

        $query = $user->additionRequests();

        if ($status && in_array($status, AdditionRequest::getStatuses())) {
            $query->where('addition_requests.status', $status);
        }

        $requests = $query->with(['game', 'reviewer'])->orderBy('addition_request_users.created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'requests' => $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'game_url' => $request->game_url,
                    'platform' => $request->platform,
                    'status' => $request->status,
                    'status_label' => $request->status_label,
                    'status_color' => $request->status_color,
                    'submitted_at' => $request->created_at->toISOString(),
                    'created_at' => $request->created_at->toISOString(),
                    'reviewed_at' => $request->reviewed_at?->toISOString(),
                    'processed_at' => $request->reviewed_at?->toISOString(),
                    'game' => $request->game ? [
                        'id' => $request->game->id,
                        'name' => $request->game->name,
                        'slug' => $request->game->slug,
                    ] : null,
                    'reviewer' => $request->reviewer ? [
                        'name' => $request->reviewer->name,
                    ] : null,
                ];
            }),
        ]);
    }

    public function cancelAdditionRequest(AdditionRequest $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);
        $service = new AdditionRequestService;
        $result = $service->cancelUserRequest($user, $request);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
