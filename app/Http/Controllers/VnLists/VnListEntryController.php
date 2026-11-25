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
    public function __construct(
        private readonly VnListCacheService $cacheService,
    ) {}

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
                'personal_notes' => $request->input('personal_notes'),
                'started_at' => $request->started_at ?: null,
                'completed_at' => $request->completed_at ?: null,
            ]
        );

        $entry->update([
            'private_notes' => $request->private_notes,
        ]);

        if ($entry->list->is_public) {
            $this->cacheService->clearPublicListsCache();
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

        $targetList->addGame($entry->game_id, $entry);

        if ($sourceIsPublic || $targetIsPublic) {
            $this->cacheService->clearPublicListsCache();
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
            $this->cacheService->clearPublicListsCache();
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

        $requestedEntryIds = array_values(array_unique(array_map('intval', $request->entry_ids)));
        $entryIds = array_values(array_intersect($requestedEntryIds, $validEntryIds));

        if (count($requestedEntryIds) !== count($request->entry_ids) || count($entryIds) !== count($requestedEntryIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some entry IDs are invalid for this list.',
            ], 422);
        }

        $cases = [];
        $bindings = [];
        foreach ($entryIds as $index => $entryId) {
            $cases[] = 'WHEN id = ? THEN CAST(? AS INTEGER)';
            $bindings[] = $entryId;
            $bindings[] = ($index + 1) * 10;
        }

        if ($cases !== []) {
            $casesString = implode(' ', $cases);
            $idPlaceholders = implode(',', array_fill(0, count($entryIds), '?'));
            $bindings = array_merge($bindings, $entryIds, [$vnList->id]);

            DB::statement(
                "UPDATE vn_list_entries SET sort_order = CASE {$casesString} END WHERE id IN ({$idPlaceholders}) AND vn_list_id = ?",
                $bindings
            );
        }

        if ($vnList->is_public) {
            $this->cacheService->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List order updated.',
        ]);
    }
}
