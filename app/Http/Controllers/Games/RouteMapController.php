<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\RenpySaveParser;
use App\Services\RouteGraphService;
use App\Support\Seo\MetaTags;
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
        $canEdit = $game->canUserEdit($request->user());

        $versionId = $request->query('version_id');
        $version = $versionId
            ? $game->gameVersions()->where('id', $versionId)->first()
            : $game->latestVersion;

        if (! $version) {
            abort(404, 'No game version found');
        }

        $includeUnreachable = $canEdit && $request->boolean('include_unreachable');
        $graphData = $this->routeGraphService->storedGraph($version, $includeUnreachable);
        abort_if($graphData === null, 404, 'Route map data has not been generated for this version.');
        $canInspectFullRouteMap = $canEdit && $this->routeGraphService->storedGraph($version, includeUnreachable: true) !== null;

        $gameVersions = $game->gameVersions()
            ->whereNotNull('route_graph_data')
            ->orderBy('published_at', 'desc')
            ->get(['id', 'version', 'published_at', 'route_graph_data'])
            ->filter(function (GameVersion $candidate) use ($canInspectFullRouteMap) {
                return $this->routeGraphService->storedGraph($candidate) !== null
                    && (! $canInspectFullRouteMap || $this->routeGraphService->storedGraph($candidate, includeUnreachable: true) !== null);
            })
            ->map(fn (GameVersion $candidate) => $candidate->only(['id', 'version', 'published_at']))
            ->values();

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
            'metaTags' => new MetaTags(
                title: $game->name . ' - Route Map',
                description: 'Interactive route map and branching visualization for ' . $game->name,
            )->toArray(),
        ]);
    }

    public function getRouteGraph(Game $game, GameVersion $version, Request $request): JsonResponse
    {
        $includeUnreachable = $game->canUserEdit($request->user()) && $request->boolean('include_unreachable');

        $graph = $this->routeGraphService->storedGraph($version, $includeUnreachable);
        abort_if($graph === null, 404, 'Route map data has not been generated for this version.');

        return response()->json($this->withAvailableLanguages(
            $graph,
            $version
        ));
    }

    public function parseSaveFile(Game $game, GameVersion $version, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:' . RenpySaveParser::MAX_UPLOAD_KIB],
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
