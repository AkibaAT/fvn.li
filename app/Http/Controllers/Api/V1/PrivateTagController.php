<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\GameResource;
use App\Services\PrivateTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function games(Request $request, int $tag): AnonymousResourceCollection
    {
        $owned = $this->tags->owned($request->user(), $tag);

        return GameResource::collection($this->tags->visibleGames($owned)->paginate(20)->withQueryString());
    }

    public function forGame(Request $request, int $game): JsonResponse
    {
        return response()->json(['data' => $this->tags->forGame($request->user(), $game)->map($this->tags->data(...))]);
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
