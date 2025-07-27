<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClickStat;
use App\Models\Game;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClickTrackingController extends Controller
{
    /**
     * Track an external project link click and redirect to the itch.io URL
     */
    public function redirectExternalProject(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'game_id' => 'required|integer|exists:games,id',
                'url' => 'nullable|url|max:2048', // Optional URL parameter for transparency
            ]);

            // Get the game to verify it exists
            $game = Game::findOrFail($validated['game_id']);

            if (! $game->url) {
                // If no URL, redirect to game page on our site
                return redirect()->route('games.show', $game->slug);
            }

            // Get session ID for deduplication
            $sessionId = $request->session()->getId();

            // Get additional tracking data
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $referrer = $request->header('referer');

            // Record the click
            ClickStat::recordClick(
                gameId: $game->id,
                type: ClickStat::TYPE_EXTERNAL_PROJECT,
                sessionId: $sessionId,
                linkId: null, // External project links don't have link IDs
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                referrer: $referrer
            );

            // Redirect to the itch.io project URL
            return redirect()->away($game->url);

        } catch (ValidationException $e) {
            // On validation error, redirect back
            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Failed to track external project click', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['game_id']),
            ]);

            // On error, still try to redirect if we have the game
            if (isset($game)) {
                return redirect()->route('games.show', $game->slug);
            }

            return redirect()->back();
        }
    }

    /**
     * Track a custom link click and redirect to the URL
     */
    public function redirectCustomLink(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'game_id' => 'required|integer|exists:games,id',
                'link_id' => 'required|string|max:255',
                'url' => 'nullable|url|max:2048', // Optional URL parameter for transparency
            ]);

            // Get the game to verify it exists and has the link
            $game = Game::findOrFail($validated['game_id']);

            // Find the specific link in the game's additional_links
            $targetLink = collect($game->additional_links)
                ->firstWhere('id', $validated['link_id']);

            if (! $targetLink) {
                // If link not found, redirect to game page
                return redirect()->route('games.show', $game->slug);
            }

            // Get session ID for deduplication
            $sessionId = $request->session()->getId();

            // Get additional tracking data
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $referrer = $request->header('referer');

            // Record the click (will be deduplicated by session)
            ClickStat::recordClick(
                gameId: $game->id,
                type: ClickStat::TYPE_CUSTOM_LINK,
                sessionId: $sessionId,
                linkId: $validated['link_id'],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                referrer: $referrer
            );

            // Redirect to the target URL
            return redirect()->away($targetLink['url']);

        } catch (ValidationException $e) {
            // On validation error, redirect back
            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Failed to track custom link click', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['game_id', 'link_id']),
            ]);

            // On error, still try to redirect if we have the game
            if (isset($game)) {
                return redirect()->route('games.show', $game->slug);
            }

            return redirect()->back();
        }
    }

    /**
     * Track a custom link click (AJAX endpoint - keeping for backward compatibility)
     */
    public function trackCustomLink(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'game_id' => 'required|integer|exists:games,id',
                'link_id' => 'required|string|max:255',
                'url' => 'required|url|max:2048',
            ]);

            // Get the game to verify it exists and has the link
            $game = Game::findOrFail($validated['game_id']);

            // Verify the link exists in the game's additional_links
            $linkExists = collect($game->additional_links)
                ->contains('id', $validated['link_id']);

            if (! $linkExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link not found for this game',
                ], 404);
            }

            // Get session ID for deduplication
            $sessionId = $request->session()->getId();

            // Get additional tracking data
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $referrer = $request->header('referer');

            // Record the click (will be deduplicated by session)
            $recorded = ClickStat::recordClick(
                gameId: $game->id,
                type: ClickStat::TYPE_CUSTOM_LINK,
                sessionId: $sessionId,
                linkId: $validated['link_id'],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                referrer: $referrer
            );

            return response()->json([
                'success' => true,
                'recorded' => $recorded, // false if already recorded for this session
                'redirect_url' => $validated['url'],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to track custom link click', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['game_id', 'link_id', 'url']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to track click',
            ], 500);
        }
    }

    /**
     * Get click statistics for a game (for developers)
     */
    public function getGameStats(Request $request, Game $game): JsonResponse
    {
        try {
            // Check if the user can view stats for this game
            // This should be restricted to game owners or admins
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Check if user owns this game (based on itch.io username)
            $itchioUsername = $user->getItchioUsername();
            if (! $itchioUsername || ! $game->url || ! str_contains($game->url, $itchioUsername . '.itch.io')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view stats for this game',
                ], 403);
            }

            // Get time period from request (default to last 30 days)
            $days = $request->input('days', 30);
            $since = now()->subDays($days);

            $stats = ClickStat::getGameStats($game->id, $since);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'period_days' => $days,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get game stats', [
                'error' => $e->getMessage(),
                'game_id' => $game->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats',
            ], 500);
        }
    }

    /**
     * Get daily analytics data for charts (AJAX endpoint)
     */
    public function getDailyAnalytics(Request $request, Game $game): JsonResponse
    {
        try {
            // Check if the user can view stats for this game
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Check if user owns this game
            $itchioUsername = $user->getItchioUsername();
            if (! $itchioUsername || ! $game->url || ! str_contains($game->url, $itchioUsername . '.itch.io')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view analytics for this game',
                ], 403);
            }

            // Get time period from request (default to last 30 days)
            $days = $request->input('days', 30);

            $dailyStats = ClickStat::getDailyStats($game->id, $days);
            $linkStats = ClickStat::getLinkStats($game->id, $days);

            return response()->json([
                'success' => true,
                'daily_stats' => $dailyStats,
                'link_stats' => $linkStats,
                'period_days' => $days,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get daily analytics', [
                'error' => $e->getMessage(),
                'game_id' => $game->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics',
            ], 500);
        }
    }
}
