<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $preferences = $request->user()->preferences;

        return response()->json(['data' => [
            'preferred_languages' => $preferences?->preferred_languages ?? [],
            'excluded_tag_ids' => $preferences?->excluded_tags ?? [],
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $input = $request->validate([
            'preferred_languages' => ['sometimes', 'array'],
            'preferred_languages.*' => ['string', 'distinct', 'exists:iso_639_3_languages,id'],
            'excluded_tag_ids' => ['sometimes', 'array'],
            'excluded_tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
        ]);
        $values = [];
        if (array_key_exists('preferred_languages', $input)) {
            $values['preferred_languages'] = $input['preferred_languages'];
        }
        if (array_key_exists('excluded_tag_ids', $input)) {
            $values['excluded_tags'] = $input['excluded_tag_ids'];
        }
        $request->user()->preferences()->updateOrCreate([], $values);

        return $this->show($request);
    }

    public function ignored(Request $request): AnonymousResourceCollection
    {
        return GameResource::collection($request->user()->ignoredGames()
            ->where('is_visible', true)->orderBy('games.id')->paginate(20)->withQueryString());
    }

    public function ignore(Request $request, int $game): JsonResponse
    {
        Game::query()->where('is_visible', true)->findOrFail($game);
        $request->user()->ignoredGames()->syncWithoutDetaching([$game]);

        return response()->json(status: 204);
    }

    public function unignore(Request $request, int $game): JsonResponse
    {
        $request->user()->ignoredGames()->detach($game);

        return response()->json(status: 204);
    }
}
