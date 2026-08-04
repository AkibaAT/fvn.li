<?php

declare(strict_types=1);

namespace App\Http\Controllers\VnLists;

use App\Http\Controllers\Controller;
use App\Http\Requests\MoveListEntryRequest;
use App\Http\Requests\ReorderListEntriesRequest;
use App\Http\Requests\UpdateListEntryRequest;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\VnListCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VnListEntryController extends Controller
{
    public function updateListEntry(UpdateListEntryRequest $request, VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $progress = UserGameProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $entry->game_id,
            ],
            [
                'game_version_id' => $request->game_version_id ?: null,
                'personal_notes' => $request->personal_notes ?: null,
                'started_at' => $request->started_at ?: null,
                'completed_at' => $request->completed_at ?: null,
            ]
        );

        $entry->update([
            'private_notes' => $request->private_notes,
        ]);

        if ($entry->list->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Entry updated successfully.',
            'progress' => $progress->fresh(['gameVersion']),
            'entry' => $entry->fresh(),
        ]);
    }

    public function moveListEntry(MoveListEntryRequest $request, VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $targetList = VnList::findOrFail($request->target_list_id);
        $this->authorize('update', $targetList);

        $existingEntry = VnListEntry::where('vn_list_id', $targetList->id)
            ->where('game_id', $entry->game_id)
            ->first();

        if ($existingEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Game is already in the target list.',
            ], 422);
        }

        $sourceIsPublic = $entry->list->is_public;
        $targetIsPublic = $targetList->is_public;

        $entry->update(['vn_list_id' => $targetList->id]);

        if ($sourceIsPublic || $targetIsPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Game moved successfully.',
            'target_list' => $targetList,
        ]);
    }

    public function removeListEntry(VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $isPublic = $entry->list->is_public;
        $entry->delete();

        if ($isPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Game removed from list.',
        ]);
    }

    public function reorderListEntries(ReorderListEntriesRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $validEntryIds = VnListEntry::where('vn_list_id', $vnList->id)
            ->pluck('id')
            ->toArray();

        $entryIds = array_intersect($request->entry_ids, $validEntryIds);

        if (count($entryIds) !== count($request->entry_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Some entry IDs are invalid for this list.',
            ], 422);
        }

        $cases = [];
        $ids = [];
        foreach ($entryIds as $index => $entryId) {
            $sortOrder = ($index + 1) * 10;
            $cases[] = "WHEN id = {$entryId} THEN {$sortOrder}";
            $ids[] = $entryId;
        }

        if (! empty($cases)) {
            $casesString = implode(' ', $cases);
            $idsString = implode(',', $ids);

            DB::statement("
                UPDATE vn_list_entries
                SET sort_order = CASE {$casesString} END
                WHERE id IN ({$idsString}) AND vn_list_id = {$vnList->id}
            ");
        }

        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List order updated.',
        ]);
    }
}
