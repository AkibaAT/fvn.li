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
    /**
     * Get the authenticated user's bug reports.
     */
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
            ->orderByDesc('created_at')
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

    /**
     * Store a new bug report.
     */
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
            'request_parameters' => 'nullable|array',
        ]);

        $user = User::findOrFail($authId);

        $bugReport = BugReport::create([
            'user_id' => $user->id,
            'page_url' => $validated['page_url'],
            'page_title' => $validated['page_title'] ?? null,
            'description' => $validated['description'],
            'request_parameters' => $validated['request_parameters'] ?? null,
            'user_agent' => $request->userAgent(),
            'status' => BugReport::STATUS_OPEN,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your bug report! We will review it shortly.',
            'report_id' => $bugReport->id,
        ]);
    }

    /**
     * Get a single bug report with its comments.
     */
    public function show(BugReport $bugReport): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Ensure user owns this report
        if ($bugReport->user_id !== $authId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Mark admin replies as read
        $bugReport->markAdminRepliesAsRead();

        // Also mark any related Laravel notifications as read
        $user = User::find($authId);
        if ($user) {
            $user->unreadNotifications()
                ->where('type', 'App\\Notifications\\BugReportAdminReplyNotification')
                ->where('data', 'like', '%"bug_report_id":' . $bugReport->id . '%')
                ->update(['read_at' => now()]);
        }

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
                'admin_notes' => $bugReport->admin_notes,
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

        // Ensure user owns this report
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

        // Ensure user owns this report
        if ($bugReport->user_id !== $authId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Don't allow closing already closed reports
        if ($bugReport->isClosed()) {
            return response()->json(['success' => false, 'message' => 'This report is already closed.'], 422);
        }

        $bugReport->update([
            'status' => BugReport::STATUS_CLOSED,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bug report closed successfully.',
            'status' => $bugReport->status,
            'status_label' => $bugReport->status_label,
            'status_color' => $bugReport->status_color,
        ]);
    }
}
