<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BugReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BugReportController extends Controller
{
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
}
