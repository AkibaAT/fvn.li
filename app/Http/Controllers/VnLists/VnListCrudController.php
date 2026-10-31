<?php

declare(strict_types=1);

namespace App\Http\Controllers\VnLists;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVnListRequest;
use App\Http\Requests\ToggleAllUpdatesRequest;
use App\Http\Requests\UpdateVnListRequest;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Services\VnListCacheService;
use App\Services\VnListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VnListCrudController extends Controller
{
    public function storeVnList(StoreVnListRequest $request, VnListService $lists): JsonResponse
    {
        $gameId = $request->filled('game_id') ? (int) $request->game_id : null;
        $vnList = $lists->create($request->user(), [
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public'),
        ], $gameId);

        return response()->json([
            'success' => true,
            'message' => $gameId !== null
                ? 'List created and game added successfully.'
                : 'List created successfully.',
            'list' => $vnList,
        ]);
    }

    public function updateVnList(UpdateVnListRequest $request, VnList $vnList, VnListService $lists): JsonResponse
    {
        $this->authorize('update', $vnList);

        $lists->update($vnList, [
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', $vnList->is_public),
        ]);

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

        $vnList->delete();

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
                $query->where('is_paid', false)->whereNull('delisted_at');
            })
            ->pluck('game_id')
            ->toArray();

        if (empty($freeGameIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No free games found in this list.',
            ], 422);
        }

        $receiveUpdates = $request->boolean('is_receiving_updates');

        UserGameProgress::where('user_id', Auth::id())
            ->whereIn('game_id', $freeGameIds)
            ->update(['is_receiving_updates' => $receiveUpdates]);

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
                    'is_receiving_updates' => $receiveUpdates,
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
            'is_receiving_updates' => $receiveUpdates,
        ]);
    }
}
