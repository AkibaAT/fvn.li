<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\GameResource;
use App\Models\Game;
use App\Models\PrivateTag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PrivateTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->privateTags()->withCount($this->visibleGamesCount())->orderBy('name')->get()
            ->map(fn ($tag) => $this->tagData($tag));

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $this->name($request);
        try {
            $tag = $request->user()->privateTags()->create(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'You already have a tag with this name.']);
        }

        return response()->json(['data' => $this->tagData($tag->loadCount($this->visibleGamesCount()))], 201);
    }

    public function update(Request $request, int $tag): JsonResponse
    {
        $owned = $this->owned($request, $tag);
        try {
            $owned->update(['name' => $this->name($request, $owned->id)]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'You already have a tag with this name.']);
        }

        return response()->json(['data' => $this->tagData($owned->loadCount($this->visibleGamesCount()))]);
    }

    public function destroy(Request $request, int $tag): JsonResponse
    {
        $this->owned($request, $tag)->delete();

        return response()->json(status: 204);
    }

    public function games(Request $request, int $tag): AnonymousResourceCollection
    {
        $owned = $this->owned($request, $tag);

        return GameResource::collection($owned->games()->where('is_visible', true)->orderBy('games.id')->paginate(20)->withQueryString());
    }

    public function forGame(Request $request, int $game): JsonResponse
    {
        $this->visibleGame($game);
        $tags = $request->user()->privateTags()->whereHas('games', fn ($q) => $q->whereKey($game))
            ->withCount($this->visibleGamesCount())->orderBy('name')->get()->map(fn ($tag) => $this->tagData($tag));

        return response()->json(['data' => $tags]);
    }

    public function attach(Request $request, int $game, int $tag): JsonResponse
    {
        $this->visibleGame($game);
        $owned = $this->owned($request, $tag);
        $owned->games()->syncWithoutDetaching([$game]);

        return response()->json(['data' => $this->tagData($owned->loadCount($this->visibleGamesCount()))]);
    }

    public function detach(Request $request, int $game, int $tag): JsonResponse
    {
        $owned = $this->owned($request, $tag);
        $owned->games()->detach($game);

        return response()->json(status: 204);
    }

    private function owned(Request $request, int $tag): PrivateTag
    {
        return $request->user()->privateTags()->findOrFail($tag);
    }

    private function visibleGame(int $game): void
    {
        Game::query()->where('is_visible', true)->findOrFail($game);
    }

    private function name(Request $request, ?int $except = null): string
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:64']])['name'];
        if ($request->user()->privateTags()->whereRaw('lower(name) = lower(?)', [$name])
            ->when($except, fn ($q) => $q->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['name' => 'You already have a tag with this name.']);
        }

        return $name;
    }

    private function visibleGamesCount(): array
    {
        return ['games' => fn ($q) => $q->where('is_visible', true)];
    }

    private function tagData(PrivateTag $tag): array
    {
        return ['id' => $tag->id, 'name' => $tag->name, 'games_count' => $tag->games_count];
    }
}
