<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Games\GamesDisplayController;
use App\Http\Controllers\Games\GamesReviewController;
use App\Http\Controllers\Games\GamesSearchController;
use App\Http\Controllers\Games\GamesVersionController;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\MeilisearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class GamesController extends Controller
{
    public function __construct(
        private GamesSearchController $searchController,
        private GamesDisplayController $displayController,
        private GamesVersionController $versionController,
        private GamesReviewController $reviewController
    ) {}

    /**
     * Display games with search and filtering capabilities
     */
    public function gamesIndex(Request $request, MeilisearchService $service): Response
    {
        return $this->searchController->index($request, $service);
    }

    /**
     * Return a random visible game slug
     */
    public function randomGame(): JsonResponse
    {
        return $this->searchController->randomGame();
    }

    /**
     * Display a single game page
     */
    public function gameShow(Game $game): Response
    {
        return $this->displayController->show($game);
    }

    /**
     * Simple API search for autocomplete
     */
    public function searchGames(Request $request): JsonResponse
    {
        return $this->searchController->searchGames($request);
    }

    /**
     * Enhanced game search using Meilisearch
     */
    public function searchGamesEnhanced(Request $request, MeilisearchService $service): JsonResponse
    {
        return $this->searchController->searchGamesEnhanced($request, $service);
    }

    /**
     * Global search across multiple content types
     */
    public function globalSearch(Request $request, MeilisearchService $service): JsonResponse
    {
        return $this->searchController->globalSearch($request, $service);
    }

    /**
     * Get game details for API consumption
     */
    public function gameDetails(Game $game): JsonResponse
    {
        return $this->displayController->details($game);
    }

    /**
     * Compare different versions of a game
     */
    public function compareGameVersions(Request $request, Game $game): JsonResponse
    {
        return $this->versionController->compareVersions($request, $game);
    }

    /**
     * Get reviews for a specific game
     */
    public function getGameReviews(Request $request, $gameId): JsonResponse
    {
        return $this->reviewController->getGameReviews($request, $gameId);
    }

    /**
     * Get all versions for a game
     */
    public function getGameVersions(Request $request, $gameId): JsonResponse
    {
        return $this->versionController->getGameVersions($request, $gameId);
    }

    /**
     * Get character statistics for a specific game version
     */
    public function getVersionCharacterStats(Game $game, GameVersion $version): JsonResponse
    {
        return $this->versionController->getVersionCharacterStats($game, $version);
    }

    /**
     * Get file statistics for a specific game version
     */
    public function getVersionFileStats(Game $game, GameVersion $version): JsonResponse
    {
        return $this->versionController->getVersionFileStats($game, $version);
    }
}
