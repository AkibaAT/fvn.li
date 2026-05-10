<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    /**
     * RSS feed of recently added/updated games.
     */
    public function newGames(): Response
    {
        $games = Game::where('is_visible', true)
            ->publicContent()
            ->whereNotNull('slug')
            ->orderBy('first_visible_at', 'desc')
            ->limit(50)
            ->get();

        $content = view('feeds.games-rss', [
            'games' => $games,
            'title' => 'FVN.li - New Visual Novels',
            'description' => 'Recently added visual novels on FVN.li',
            'link' => url('/'),
        ])->render();

        return response($content)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    /**
     * RSS feed of recently updated games (new versions).
     */
    public function updatedGames(): Response
    {
        $games = Game::where('is_visible', true)
            ->publicContent()
            ->whereNotNull('slug')
            ->whereHas('latestVersion')
            ->with('latestVersion:id,game_id,published_at')
            ->orderBy(
                GameVersion::select('published_at')
                    ->whereColumn('game_id', 'games.id')
                    ->where('is_latest', true)
                    ->limit(1),
                'desc'
            )
            ->limit(50)
            ->get();

        $content = view('feeds.games-rss', [
            'games' => $games,
            'title' => 'FVN.li - Updated Visual Novels',
            'description' => 'Recently updated visual novels on FVN.li',
            'link' => url('/'),
            'isUpdates' => true,
        ])->render();

        return response($content)
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
