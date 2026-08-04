<?php

declare(strict_types=1);

namespace App\Http\Controllers\VnLists;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVnListRequest;
use App\Http\Requests\ToggleAllUpdatesRequest;
use App\Http\Requests\UpdateVnListRequest;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\VnListCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VnListCrudController extends Controller
{
    public function storeVnList(StoreVnListRequest $request): JsonResponse
    {

        $isPublic = $request->boolean('is_public', false);
        $vnList = VnList::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'type' => 'custom', // All user-created lists are custom type
            'is_public' => $isPublic,
            'is_default' => false, // User-created lists are never default
        ]);

        // If a game_id is provided, add the game to the new list
        if ($request->has('game_id')) {
            VnListEntry::create([
                'vn_list_id' => $vnList->id,
                'game_id' => $request->game_id,
                'sort_order' => 10,
            ]);
        }

        if ($isPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => $request->has('game_id')
                ? 'List created and game added successfully.'
                : 'List created successfully.',
            'list' => $vnList,
        ]);
    }

    public function updateVnList(UpdateVnListRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        // Note: List type cannot be changed after creation
        // System lists (is_default=true) have fixed types
        // User lists are always 'custom' type

        $wasPublic = $vnList->is_public;
        $vnList->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', $vnList->is_public),
        ]);

        if ($wasPublic || $vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List updated successfully.',
            'vnList' => $vnList->fresh(),
        ]);
    }

    public function destroyVnList(VnList $vnList): JsonResponse
    {
        $this->authorize('delete', $vnList);

        if ($vnList->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default lists.',
            ], 422);
        }

        $isPublic = $vnList->is_public;
        $vnList->delete();

        if ($isPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List deleted successfully.',
        ]);
    }

    public function toggleVnListVisibility(VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $vnList->update(['is_public' => ! $vnList->is_public]);
        $status = $vnList->is_public ? 'public' : 'private';

        app(VnListCacheService::class)->clearPublicListsCache();

        return response()->json([
            'success' => true,
            'message' => "List is now {$status}.",
            'is_public' => $vnList->is_public,
        ]);
    }

    public function toggleAllUpdates(ToggleAllUpdatesRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $freeGameIds = $vnList->entries()
            ->whereHas('game', function ($query) {
                $query->where('is_paid', false);
            })
            ->pluck('game_id')
            ->toArray();

        if (empty($freeGameIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No free games found in this list.',
            ], 422);
        }

        $receiveUpdates = $request->boolean('receive_updates');

        UserGameProgress::where('user_id', Auth::id())
            ->whereIn('game_id', $freeGameIds)
            ->update(['receive_updates' => $receiveUpdates]);

        // Also create records for games that don't have user_game_progress yet
        $existingGameIds = UserGameProgress::where('user_id', Auth::id())
            ->whereIn('game_id', $freeGameIds)
            ->pluck('game_id')
            ->toArray();

        $missingGameIds = array_diff($freeGameIds, $existingGameIds);

        if (! empty($missingGameIds)) {
            $insertData = array_map(function ($gameId) use ($receiveUpdates) {
                return [
                    'user_id' => Auth::id(),
                    'game_id' => $gameId,
                    'receive_updates' => $receiveUpdates,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $missingGameIds);

            UserGameProgress::insert($insertData);
        }

        $status = $receiveUpdates ? 'enabled' : 'disabled';

        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => "Notifications {$status} for all free games in this list.",
            'updated_game_ids' => $freeGameIds,
            'receive_updates' => $receiveUpdates,
        ]);
    }
}
