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
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use LengthException;

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
        $canInspectFullRouteMap = $game->canUserEdit($request->user());
        $includeUnreachable = $canInspectFullRouteMap && $request->boolean('include_unreachable');

        $versionId = $request->query('version_id');
        $version = $versionId
            ? $game->gameVersions()->where('id', $versionId)->first()
            : $game->latestVersion;

        if (! $version) {
            abort(404, 'No game version found');
        }

        // If the graph is already pre-computed on the version, use it directly
        // (buildGraph returns the cached data without hitting the route tables).
        // Only check routeLabels existence when there's no cached graph.
        $graphData = $version->route_graph_data
            ? $this->routeGraphService->buildGraph($version, includeUnreachable: $includeUnreachable)
            : ($version->routeLabels()->exists()
                ? $this->routeGraphService->buildGraph($version, includeUnreachable: $includeUnreachable)
                : ['has_graph_data' => false]);

        $gameVersions = $game->gameVersions()
            ->whereHas('routeLabels')
            ->orderBy('published_at', 'desc')
            ->get(['id', 'version', 'published_at']);

        $availableLanguages = $this->getAvailableLanguageCodes($version);
        $requestedLanguage = $request->query('lang');
        $currentLanguage = is_string($requestedLanguage) && in_array($requestedLanguage, $availableLanguages, true)
            ? $requestedLanguage
            : null;

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
            'canInspectFullRouteMap' => $canInspectFullRouteMap,
            'includeUnreachable' => $includeUnreachable,
            'availableLanguages' => $availableLanguages,
            'currentLanguage' => $currentLanguage,
            'metaTags' => [
                'title' => $game->name.' - Route Map - FVN.li',
                'description' => 'Interactive route map and branching visualization for '.$game->name,
            ],
        ]);
    }

    public function getRouteGraph(Game $game, GameVersion $version, Request $request): JsonResponse
    {
        $includeUnreachable = $game->canUserEdit($request->user()) && $request->boolean('include_unreachable');

        // Fast path: if pre-computed graph exists, return it without querying route tables
        if ($version->route_graph_data) {
            return response()->json($this->withAvailableLanguages(
                $this->routeGraphService->buildGraph($version, includeUnreachable: $includeUnreachable),
                $version
            ));
        }

        if (! $version->routeLabels()->exists()) {
            return response()->json($this->withAvailableLanguages(['has_graph_data' => false], $version));
        }

        return response()->json($this->withAvailableLanguages(
            $this->routeGraphService->buildGraph($version, includeUnreachable: $includeUnreachable),
            $version
        ));
    }

    public function parseSaveFile(Game $game, GameVersion $version, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.RenpySaveParser::MAX_UPLOAD_KIB],
        ]);

        $knownLabels = $version->routeLabels()->pluck('name')->toArray();

        if (empty($knownLabels)) {
            return response()->json(['seen_labels' => [], 'total' => 0]);
        }

        $rawData = $request->file('file')->getContent();

        try {
            $seenLabels = $this->renpySaveParser->extractSeenLabels($rawData, $knownLabels);
        } catch (LengthException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'seen_labels' => $seenLabels,
            'total' => count($knownLabels),
            'matched' => count($seenLabels),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableLanguageCodes(GameVersion $version): array
    {
        return $version->supportedLanguages()
            ->where('is_available', true)
            ->orderBy('iso_code')
            ->pluck('iso_code')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $graphData
     * @return array<string, mixed>
     */
    private function withAvailableLanguages(array $graphData, GameVersion $version): array
    {
        $graphData['available_languages'] = $this->getAvailableLanguageCodes($version);

        return $graphData;
    }
}
