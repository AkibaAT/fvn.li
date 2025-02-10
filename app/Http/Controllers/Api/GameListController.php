<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $visibilityScope = fn ($query) => ! auth()->user()?->can('viewHidden', Game::class)
            ? $query->where('is_visible', true)
            : $query;

        $query = Game::query()
            ->select([
                'games.*',
                'latest_versions.published_at as latest_version_published_at',
                'latest_versions.id as latest_version_id',
                'latest_versions.rating as rating',
                'latest_versions.rating_count as rating_count',
                'latest_versions.devlog as devlog',
                'latest_versions.is_windows as is_windows',
                'latest_versions.is_linux as is_linux',
                'latest_versions.is_mac as is_mac',
                'latest_versions.is_android as is_android',
                'latest_versions.is_web as is_web',
                'english_stats.words as english_word_count',
                DB::raw('(
                   SELECT json_agg(json_build_object(
                       \'iso_code\', l.id,
                       \'ref_name\', l.ref_name,
                       \'flag_code\', l.flag_code
                   ) ORDER BY l.ref_name)
                   FROM version_supported_languages vsl
                   JOIN iso_639_3_languages l ON l.id = vsl.iso_code
                   WHERE vsl.game_version_id = latest_versions.id
               ) as supported_languages'),
            ])
            ->leftJoin('game_versions as latest_versions', function ($join) {
                $join->on('games.id', '=', 'latest_versions.game_id')
                    ->where('latest_versions.is_latest', true);
            })
            ->leftJoin('version_language_stats as english_stats', function ($join) {
                $join->on('latest_versions.id', '=', 'english_stats.game_version_id')
                    ->where('english_stats.iso_code', '=', 'eng');
            });

        // Apply visibility scope
        $query->tap($visibilityScope);

        // Apply search filter
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('games.name', 'ilike', "%{$request->search}%")
                    ->orWhere('games.authors', 'ilike', "%{$request->search}%")
                    ->orWhere('games.tags', 'ilike', "%{$request->search}%")
                    ->orWhere('games.custom_tags', 'ilike', "%{$request->search}%");
            });
        }

        // Apply filters
        if (! empty($request->selectedStatuses)) {
            $query->whereIn('games.status', $request->selectedStatuses);
        }

        if (! empty($request->selectedEngines)) {
            $query->whereIn('games.game_engine', $request->selectedEngines);
        }

        if (! empty($request->selectedPlatforms)) {
            foreach ($request->selectedPlatforms as $platform) {
                $query->where("latest_versions.is_{$platform}", true);
            }
        }

        if (! empty($request->selectedLanguages)) {
            $query->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))
                    ->from('version_supported_languages')
                    ->join('game_versions', function ($join) {
                        $join->on('game_versions.id', '=', 'version_supported_languages.game_version_id')
                            ->where('game_versions.is_latest', true);
                    })
                    ->whereColumn('game_versions.game_id', 'games.id')
                    ->whereIn('version_supported_languages.iso_code', $request->selectedLanguages);
            });
        }

        // Apply NSFW/SFW filters
        if ($request->boolean('nsfw') xor $request->boolean('sfw')) {
            $query->where('games.is_nsfw', $request->boolean('nsfw'));
        }

        // Apply sorting
        $sortField = $request->input('sortField', 'latest_version_published_at');
        $sortDirection = $request->input('sortDirection', 'desc');

        $column = match ($sortField) {
            'latest_version_published_at' => 'latest_versions.published_at',
            'english_word_count' => 'english_stats.words',
            'rating_count' => 'latest_versions.rating_count',
            default => "games.{$sortField}"
        };

        $query->orderByRaw("{$column} {$sortDirection} NULLS LAST");

        // Pagination
        $perPage = in_array($request->perPage, [9, 18, 27]) ? $request->perPage : 9;
        $games = $query->paginate($perPage);

        // Transform supported languages into collection
        foreach ($games as $game) {
            $game->supported_languages = collect($game->supported_languages);
        }

        return response()->json($games->through(fn ($game) => [
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'url' => $game->url,
            'thumb_url' => $game->thumb_url,
            'authors' => $game->authors,
            'description' => $game->description,
            'status' => $game->status,
            'game_engine' => $game->game_engine,
            'is_nsfw' => $game->is_nsfw,
            'is_visible' => $game->is_visible,
            'tags' => $game->tags,
            'custom_tags' => $game->custom_tags,
            'initially_published_at' => $game->initially_published_at,
            'latest_version_published_at' => $game->latest_version_published_at,
            'english_word_count' => $game->english_word_count,
            'rating' => $game->rating,
            'rating_count' => $game->rating_count,
            'supported_languages' => collect($game->supported_languages),
            'platforms' => [
                'windows' => $game->is_windows,
                'linux' => $game->is_linux,
                'mac' => $game->is_mac,
                'android' => $game->is_android,
                'web' => $game->is_web,
            ],
        ]));
    }
}
