<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\ListEntryResource;
use App\Http\Resources\UserApi\ListResource;
use App\Models\Game;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\VnListCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ListResource::collection($request->user()->vnLists()->withCount('entries')->reorder()->inDisplayOrder()->orderBy('id')->paginate(20)->withQueryString());
    }

    public function show(Request $request, int $list): ListResource
    {
        return new ListResource($this->owned($request, $list)->loadCount('entries'));
    }

    public function store(Request $request): ListResource
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('vn_lists')->where('user_id', $request->user()->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $list = $request->user()->vnLists()->create([
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'is_public' => $input['is_public'] ?? false,
            'is_default' => false,
            'type' => 'custom',
        ]);
        $this->clearPublic($list);

        return new ListResource($list->loadCount('entries'));
    }

    public function update(Request $request, int $list): ListResource
    {
        $owned = $this->owned($request, $list);
        $input = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('vn_lists')->where('user_id', $request->user()->id)->ignore($owned->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $wasPublic = $owned->is_public;
        $owned->update($input);
        if ($wasPublic || $owned->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return new ListResource($owned->loadCount('entries'));
    }

    public function destroy(Request $request, int $list): JsonResponse
    {
        $owned = $this->owned($request, $list);
        $this->authorize('delete', $owned);
        $owned->delete();
        $this->clearPublic($owned);

        return response()->json(status: 204);
    }

    public function entries(Request $request, int $list): AnonymousResourceCollection
    {
        $owned = $this->owned($request, $list);

        return ListEntryResource::collection($owned->entries()->with('game')
            ->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString());
    }

    public function addGame(Request $request, int $list, int $game): ListEntryResource|JsonResponse
    {
        $owned = $this->owned($request, $list);
        Game::query()->where('is_visible', true)->findOrFail($game);
        $entry = $owned->entries()->where('game_id', $game)->first();
        if ($entry) {
            return new ListEntryResource($entry->load('game'));
        }
        $entry = $owned->addGame($game);
        $this->clearPublic($owned);

        return (new ListEntryResource($entry->load('game')))->response()->setStatusCode(201);
    }

    public function removeGame(Request $request, int $list, int $game): JsonResponse
    {
        $owned = $this->owned($request, $list);
        if ($owned->entries()->where('game_id', $game)->delete()) {
            $this->clearPublic($owned);
        }

        return response()->json(status: 204);
    }

    public function updateEntry(Request $request, int $list, int $entry): ListEntryResource
    {
        $owned = $this->owned($request, $list);
        $record = $owned->entries()->findOrFail($entry);
        $input = $request->validate(['private_notes' => ['present', 'nullable', 'string', 'max:5000']]);
        $record->update($input);

        return new ListEntryResource($record->fresh()->load('game'));
    }

    public function moveEntry(Request $request, int $list, int $entry): ListEntryResource|JsonResponse
    {
        $source = $this->owned($request, $list);
        $record = $source->entries()->findOrFail($entry);
        $input = $request->validate(['target_list_id' => ['required', 'integer']]);
        $target = $this->owned($request, $input['target_list_id']);
        if ($target->is($source)) {
            return new ListEntryResource($record->load('game'));
        }
        if ($target->entries()->where('game_id', $record->game_id)->exists()) {
            return response()->json(['message' => 'Game is already in the target list.'], 409);
        }
        $target->addGame($record->game_id, $record);
        $this->clearPublic($source);
        $this->clearPublic($target);

        return new ListEntryResource($record->fresh()->load('game'));
    }

    public function reorder(Request $request, int $list): AnonymousResourceCollection
    {
        $owned = $this->owned($request, $list);
        $input = $request->validate([
            'entry_ids' => ['required', 'array', 'max:1000'],
            'entry_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        DB::transaction(function () use ($owned, $input) {
            $current = $owned->entries()->lockForUpdate()->pluck('id')->sort()->values()->all();
            $requested = collect($input['entry_ids'])->sort()->values()->all();
            if ($current !== $requested) {
                throw ValidationException::withMessages(['entry_ids' => 'Provide every entry in this list exactly once.']);
            }
            foreach ($input['entry_ids'] as $position => $id) {
                VnListEntry::whereKey($id)->where('vn_list_id', $owned->id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });
        $this->clearPublic($owned);

        return ListEntryResource::collection($owned->entries()->with('game')->orderBy('sort_order')->orderBy('id')->get());
    }

    private function owned(Request $request, int $list): VnList
    {
        return $request->user()->vnLists()->findOrFail($list);
    }

    private function clearPublic(VnList $list): void
    {
        if ($list->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }
    }
}
