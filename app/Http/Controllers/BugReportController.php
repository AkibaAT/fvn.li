<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BugReport;
use App\Models\BugReportComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BugReportController extends Controller
{
    private const int MAX_REQUEST_PARAMETER_COUNT = 25;

    private const int MAX_REQUEST_PARAMETER_KEY_LENGTH = 80;

    private const int MAX_REQUEST_PARAMETER_VALUE_LENGTH = 500;

    private const int MAX_USER_AGENT_LENGTH = 1024;

    public function index(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $reports = BugReport::where('user_id', $authId)
            ->withCount(['comments as unread_count' => function ($query) {
                $query->where('is_from_admin', true)->where('is_read', false);
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($report) => [
                'id' => $report->id,
                'page_url' => $report->page_url,
                'page_title' => $report->page_title,
                'description' => $report->description,
                'status' => $report->status,
                'status_label' => $report->status_label,
                'status_color' => $report->status_color,
                'unread_count' => $report->unread_count,
                'created_at' => $report->created_at->toISOString(),
                'resolved_at' => $report->resolved_at?->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'You must be logged in to submit a bug report.'], 401);
        }

        $validated = $request->validate([
            'page_url' => 'required|string|max:2048',
            'page_title' => 'nullable|string|max:255',
            'description' => 'required|string|min:10|max:5000',
            'request_parameters' => 'nullable|array|max:' . self::MAX_REQUEST_PARAMETER_COUNT,
        ]);

        $user = User::findOrFail($authId);
        $requestParameters = $this->sanitizeRequestParameters($validated['request_parameters'] ?? null);

        $bugReport = BugReport::create([
            'user_id' => $user->id,
            'page_url' => $validated['page_url'],
            'page_title' => $validated['page_title'] ?? null,
            'description' => $validated['description'],
            'request_parameters' => $requestParameters,
            'user_agent' => $this->truncateNullableString($request->userAgent(), self::MAX_USER_AGENT_LENGTH),
            'status' => BugReport::STATUS_OPEN,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your bug report! We will review it shortly.',
            'report_id' => $bugReport->id,
        ]);
    }

    public function show(BugReport $bugReport): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if ($bugReport->user_id !== $authId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $bugReport->markAdminRepliesAsRead();

        $comments = $bugReport->comments()
            ->with('user:id,name')
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'message' => $comment->message,
                'is_from_admin' => $comment->is_from_admin,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                ],
                'created_at' => $comment->created_at->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'report' => [
                'id' => $bugReport->id,
                'page_url' => $bugReport->page_url,
                'page_title' => $bugReport->page_title,
                'description' => $bugReport->description,
                'status' => $bugReport->status,
                'status_label' => $bugReport->status_label,
                'status_color' => $bugReport->status_color,
                'is_closed' => $bugReport->is_closed,
                'created_at' => $bugReport->created_at->toISOString(),
                'resolved_at' => $bugReport->resolved_at?->toISOString(),
            ],
            'comments' => $comments,
        ]);
    }

    /**
     * Add a comment to a bug report.
     */
    public function addComment(Request $request, BugReport $bugReport): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if ($bugReport->user_id !== $authId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Don't allow comments on closed reports
        if ($bugReport->isClosed()) {
            return response()->json(['success' => false, 'message' => 'This report is closed and cannot receive new comments.'], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:5|max:2000',
        ]);

        $comment = BugReportComment::create([
            'bug_report_id' => $bugReport->id,
            'user_id' => $authId,
            'message' => $validated['message'],
            'is_from_admin' => false,
            'is_read' => true, // User's own comments are already "read"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'comment' => [
                'id' => $comment->id,
                'message' => $comment->message,
                'is_from_admin' => $comment->is_from_admin,
                'user' => [
                    'id' => $authId,
                    'name' => User::find($authId)->name,
                ],
                'created_at' => $comment->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Close a bug report (user action).
     */
    public function close(BugReport $bugReport): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if ($bugReport->user_id !== $authId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Don't allow closing already closed reports
        if ($bugReport->isClosed()) {
            return response()->json(['success' => false, 'message' => 'This report is already closed.'], 422);
        }

        $bugReport->update([
            'is_closed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bug report closed successfully.',
            'is_closed' => true,
        ]);
    }

    /**
     * @param  array<mixed>|null  $parameters
     * @return array<string, string>|null
     */
    private function sanitizeRequestParameters(?array $parameters): ?array
    {
        if ($parameters === null || $parameters === []) {
            return null;
        }

        $sanitized = [];
        foreach ($parameters as $key => $value) {
            if (count($sanitized) >= self::MAX_REQUEST_PARAMETER_COUNT) {
                break;
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $key = $this->truncateNullableString((string) $key, self::MAX_REQUEST_PARAMETER_KEY_LENGTH);
            if ($key === null || $key === '') {
                continue;
            }

            $sanitized[$key] = $this->truncateNullableString($this->stringifyRequestParameterValue($value), self::MAX_REQUEST_PARAMETER_VALUE_LENGTH) ?? '';
        }

        return $sanitized === [] ? null : $sanitized;
    }

    private function stringifyRequestParameterValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    private function truncateNullableString(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
