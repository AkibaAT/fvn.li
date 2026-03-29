<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\RenpySaveParser;
use App\Services\RouteGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class RouteMapController extends Controller
{
    public function __construct(
        private RouteGraphService $routeGraphService,
        private RenpySaveParser $renpySaveParser
    ) {}

    public function show(Game $game, Request $request): Response
    {
        $game->load(['gameVersions']);
        $game->append('tags_list');

        $versionId = $request->query('version_id');
        $version = $versionId
            ? $game->gameVersions()->where('id', $versionId)->first()
            : $game->latestVersion;

        if (! $version) {
            abort(404, 'No game version found');
        }

        $hasRouteData = $version->routeLabels()->exists();

        $graphData = $hasRouteData
            ? $this->routeGraphService->buildGraph($version)
            : ['has_graph_data' => false];

        $gameVersions = $game->gameVersions()
            ->whereHas('routeLabels')
            ->orderByDesc('published_at')
            ->get(['id', 'version', 'published_at']);

        // Get available languages for the route map
        $availableLanguages = $version->supportedLanguages()
            ->pluck('iso_code')
            ->toArray();

        return inertia('games/route-map', [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'tags_list' => $game->tags_list,
            ],
            'currentVersion' => [
                'id' => $version->id,
                'version' => $version->version,
            ],
            'gameVersions' => $gameVersions,
            'routeGraph' => $graphData,
            'availableLanguages' => $availableLanguages,
            'currentLanguage' => $request->query('lang'),
            'metaTags' => [
                'title' => $game->name . ' - Route Map - FVN.li',
                'description' => 'Interactive route map and branching visualization for ' . $game->name,
            ],
        ]);
    }

    public function getRouteGraph(Game $game, GameVersion $version): JsonResponse
    {
        $hasRouteData = $version->routeLabels()->exists();

        if (! $hasRouteData) {
            return response()->json(['has_graph_data' => false]);
        }

        return response()->json($this->routeGraphService->buildGraph($version));
    }

    public function parseSaveFile(Game $game, GameVersion $version, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $knownLabels = $version->routeLabels()->pluck('name')->toArray();

        if (empty($knownLabels)) {
            return response()->json(['seen_labels' => [], 'total' => 0]);
        }

        $rawData = $request->file('file')->getContent();

        $seenLabels = $this->renpySaveParser->extractSeenLabels($rawData, $knownLabels);

        return response()->json([
            'seen_labels' => $seenLabels,
            'total' => count($knownLabels),
            'matched' => count($seenLabels),
        ]);
    }
}
