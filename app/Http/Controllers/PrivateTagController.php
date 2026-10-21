<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PrivateTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivateTagController extends Controller
{
    public function __construct(private readonly PrivateTagService $tags) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->tags->all($request->user())->map($this->tags->data(...))]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $request->validate(PrivateTagService::NAME_RULES)['name'];

        return response()->json(['data' => $this->tags->data($this->tags->create($request->user(), $name))], 201);
    }

    public function update(Request $request, int $tag): JsonResponse
    {
        $owned = $this->tags->owned($request->user(), $tag);
        $name = $request->validate(PrivateTagService::NAME_RULES)['name'];

        return response()->json(['data' => $this->tags->data($this->tags->rename($owned, $name))]);
    }

    public function destroy(Request $request, int $tag): JsonResponse
    {
        $this->tags->owned($request->user(), $tag)->delete();

        return response()->json(status: 204);
    }

    public function games(Request $request, int $tag): JsonResponse
    {
        $page = $this->tags->visibleGames($this->tags->owned($request->user(), $tag))
            ->simplePaginate(20, ['games.id', 'games.name', 'games.slug']);

        return response()->json([
            'data' => collect($page->items())->map(fn ($game) => ['id' => $game->id, 'name' => $game->name, 'slug' => $game->slug]),
            'links' => ['next' => $page->nextPageUrl()],
        ]);
    }

    public function attach(Request $request, int $game, int $tag): JsonResponse
    {
        $owned = $this->tags->owned($request->user(), $tag);

        return response()->json(['data' => $this->tags->data($this->tags->attach($owned, $game))]);
    }

    public function detach(Request $request, int $game, int $tag): JsonResponse
    {
        $this->tags->detach($this->tags->owned($request->user(), $tag), $game);

        return response()->json(status: 204);
    }
}
