<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Services\OwnedGameSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardStatsController extends Controller
{
    public function getUserGameStats(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $user = User::findOrFail($authId);

        $ownedGamesCount = 0;
        $gamesWithLinksCount = 0;

        $itchioUsername = app(OwnedGameSummaryService::class)->username($user);
        if ($itchioUsername) {
            $ownedGames = $user->getOwnedGames();
            $ownedGamesCount = $ownedGames->count();
            $gamesWithLinksCount = $ownedGames->filter(function ($game) {
                return $game->hasAdditionalLinks();
            })->count();
        }

        // Personal reading stats
        $progressStats = UserGameProgress::where('user_id', $authId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'reading' THEN 1 ELSE 0 END) as reading,
                SUM(CASE WHEN status = 'plan_to_read' THEN 1 ELSE 0 END) as plan_to_read,
                SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
                SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped
            ")
            ->first();

        // Reviews count
        $reviewsCount = Rating::where('user_id', $authId)->count();

        // Games completed per month (last 12 months)
        $monthlyCompletions = UserGameProgress::where('user_id', $authId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subMonths(12))
            ->selectRaw("TO_CHAR(completed_at, 'YYYY-MM') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Top tags from completed/reading games
        $userGameIds = UserGameProgress::where('user_id', $authId)
            ->whereIn('status', ['completed', 'reading'])
            ->pluck('game_id');

        $topTags = [];
        if ($userGameIds->isNotEmpty()) {
            $topTags = DB::table('game_tag')
                ->join('tags', 'tags.id', '=', 'game_tag.tag_id')
                ->whereIn('game_tag.game_id', $userGameIds)
                ->selectRaw('tags.name, COUNT(*) as count')
                ->groupBy('tags.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'name')
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'stats' => [
                'itchioUsername' => $itchioUsername,
                'ownedGamesCount' => $ownedGamesCount,
                'gamesWithLinksCount' => $gamesWithLinksCount,
                'progress' => [
                    'total' => (int) ($progressStats->total ?? 0),
                    'completed' => (int) ($progressStats->completed ?? 0),
                    'reading' => (int) ($progressStats->reading ?? 0),
                    'plan_to_read' => (int) ($progressStats->plan_to_read ?? 0),
                    'on_hold' => (int) ($progressStats->on_hold ?? 0),
                    'dropped' => (int) ($progressStats->dropped ?? 0),
                    'total_hours' => 0.0,
                ],
                'reviewsCount' => $reviewsCount,
                'monthlyCompletions' => $monthlyCompletions,
                'topTags' => $topTags,
            ],
        ]);
    }
}
