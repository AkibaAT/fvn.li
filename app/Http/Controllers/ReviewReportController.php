<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\ReviewReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReviewReportController extends Controller
{
    /**
     * List pending reports (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user->is_admin) {
            abort(403);
        }

        $reports = ReviewReport::with(['rating.game:id,name,slug', 'rating.user:id,name', 'reporter:id,name'])
            ->when($request->input('status', 'pending'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * Report a review for moderation.
     */
    public function store(Request $request, int $ratingId): JsonResponse
    {
        $request->validate([
            'reason' => ['required', Rule::in(array_keys(ReviewReport::REASONS))],
            'details' => 'nullable|string|max:1000',
        ]);

        $rating = Rating::findOrFail($ratingId);
        $user = Auth::user();

        // Cannot report own reviews
        if ($rating->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot report your own review.',
            ], 422);
        }

        // Check for existing report
        $existing = ReviewReport::where('rating_id', $ratingId)
            ->where('reporter_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this review.',
            ], 422);
        }

        ReviewReport::create([
            'rating_id' => $ratingId,
            'reporter_id' => $user->id,
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
        ]);

        // Auto-hide reviews with multiple reports
        $reportCount = ReviewReport::where('rating_id', $ratingId)
            ->where('status', 'pending')
            ->count();

        if ($reportCount >= 2) {
            $rating->update(['is_visible' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report submitted. Our team will review it shortly.',
        ]);
    }

    /**
     * Resolve a report (admin only).
     */
    public function resolve(Request $request, int $reportId): JsonResponse
    {
        $user = Auth::user();
        if (! $user->is_admin) {
            abort(403);
        }

        $request->validate([
            'status' => ['required', Rule::in(['dismissed', 'actioned'])],
            'admin_notes' => 'nullable|string|max:1000',
            'hide_review' => 'boolean',
        ]);

        $report = ReviewReport::findOrFail($reportId);
        $report->update([
            'status' => $request->input('status'),
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'admin_notes' => $request->input('admin_notes'),
        ]);

        // Optionally hide the review
        if ($request->boolean('hide_review') && $report->rating) {
            $report->rating->update(['is_visible' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report resolved.',
        ]);
    }
}
