<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ClickStat;
use App\Models\Game;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackPageViews
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track successful GET requests to avoid tracking API calls, form submissions, etc.
        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $this->trackPageView($request);
        }

        return $response;
    }

    /**
     * Track the page view for game detail pages
     */
    private function trackPageView(Request $request): void
    {
        try {
            $game = $request->route('game');

            if (! $game instanceof Game) {
                return;
            }

            $sessionId = $request->session()->getId();

            $userId = $request->user()?->id;

            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $referrer = $request->header('referer');

            // Record the page view (will be deduplicated by session)
            ClickStat::recordClick(
                gameId: $game->id,
                type: ClickStat::TYPE_PAGE_VIEW,
                sessionId: $sessionId,
                linkId: null,
                userId: $userId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                referrer: $referrer
            );
        } catch (Exception $e) {
            Log::warning('Failed to track page view', [
                'error' => $e->getMessage(),
                'url' => $request->url(),
            ]);
        }
    }
}
