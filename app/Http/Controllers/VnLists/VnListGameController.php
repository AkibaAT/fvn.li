<?php

declare(strict_types=1);

namespace App\Http\Controllers\VnLists;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddGameToCustomListRequest;
use App\Http\Requests\AddGameToListRequest;
use App\Http\Requests\ToggleUserProgressUpdatesRequest;
use App\Http\Requests\UpdateUserProgressRequest;
use App\Models\Game;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\VnListCacheService;
use App\Traits\SortsVnLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class VnListGameController extends Controller
{
    use SortsVnLists;

    public function addGameToList(AddGameToListRequest $request, Game $game): JsonResponse
    {

        // Handle both list_id and list_type parameters
        if ($request->has('list_id')) {
            $vnList = VnList::findOrFail($request->list_id);
        } elseif ($request->has('list_type')) {
            // Find the user's default list of the specified type
            $vnList = VnList::where('user_id', Auth::id())
                ->where('type', $request->list_type)
                ->where('is_default', true)
                ->first();

            if (! $vnList) {
                return response()->json([
                    'success' => false,
                    'message' => 'Default list not found for type: ' . $request->list_type,
                ], 404);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either list_id or list_type must be provided.',
            ], 422);
        }

        $this->authorize('update', $vnList);

        $existingEntry = VnListEntry::where('vn_list_id', $vnList->id)
            ->where('game_id', $game->id)
            ->first();

        // For default lists, toggle behavior: remove if exists, add if doesn't exist
        if ($request->has('list_type') && $existingEntry) {
            // Remove from list (toggle off)
            $existingEntry->delete();

            // Clear cache if this is a public list
            if ($vnList->is_public) {
                app(VnListCacheService::class)->clearPublicListsCache();
            }

            return response()->json([
                'success' => true,
                'message' => "Game removed from {$vnList->name} successfully.",
                'action' => 'removed',
            ]);
        }

        // For regular list_id requests, don't allow duplicates
        if ($request->has('list_id') && $existingEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Game is already in this list.',
            ], 422);
        }

        // For default lists with list_type, remove from other default lists first
        if ($request->has('list_type')) {
            // Remove game from all other default lists for this user
            $otherDefaultLists = VnList::where('user_id', Auth::id())
                ->where('is_default', true)
                ->where('type', '!=', $request->list_type)
                ->whereIn('type', ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped'])
                ->pluck('id');

            VnListEntry::whereIn('vn_list_id', $otherDefaultLists)
                ->where('game_id', $game->id)
                ->delete();
        }

        // Add to the specified list
        $entry = VnListEntry::create([
            'vn_list_id' => $vnList->id,
            'game_id' => $game->id,
            'sort_order' => ($vnList->entries()->max('sort_order') ?? 0) + 10,
        ]);

        // Clear cache if this is a public list
        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => "Game added to {$vnList->name} successfully.",
            'action' => 'added',
            'entry' => $entry->load('game'),
        ]);
    }

    public function addGameToCustomList(AddGameToCustomListRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $game = Game::findOrFail($request->game_id);

        $existingEntry = VnListEntry::where('vn_list_id', $vnList->id)
            ->where('game_id', $game->id)
            ->first();

        // Toggle behavior: remove if exists, add if doesn't exist
        if ($existingEntry) {
            $existingEntry->delete();

            // Clear cache if this is a public list
            if ($vnList->is_public) {
                app(VnListCacheService::class)->clearPublicListsCache();
            }

            return response()->json([
                'success' => true,
                'message' => "Game removed from {$vnList->name} successfully.",
                'action' => 'removed',
            ]);
        }

        $entry = VnListEntry::create([
            'vn_list_id' => $vnList->id,
            'game_id' => $game->id,
            'sort_order' => ($vnList->entries()->max('sort_order') ?? 0) + 10,
        ]);

        // Clear cache if this is a public list
        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => "Game added to {$vnList->name} successfully.",
            'action' => 'added',
            'entry' => $entry->load('game'),
        ]);
    }

    public function updateUserProgress(UpdateUserProgressRequest $request, Game $game): JsonResponse
    {

        $updateData = [
            'game_version_id' => $request->game_version_id ?: null,
            'personal_notes' => $request->personal_notes ?: null,
            'started_at' => $request->started_at ?: null,
            'completed_at' => $request->completed_at ?: null,
        ];

        if ($request->has('hours_played')) {
            $updateData['hours_played'] = $request->hours_played;
        }

        if ($request->has('progress')) {
            $updateData['progress'] = $request->progress;
        }

        $progress = UserGameProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $game->id,
            ],
            $updateData
        );

        // Clear cache if this game is in any public lists
        $publicListsContainingGame = VnList::where('is_public', true)
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            })
            ->exists();

        if ($publicListsContainingGame) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress updated successfully.',
            'progress' => $progress->fresh(['gameVersion']),
        ]);
    }

    public function toggleUserProgressUpdates(ToggleUserProgressUpdatesRequest $request, Game $game): JsonResponse
    {
        if ($game->is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'Notifications are not available for paid games.',
            ], 400);
        }

        $receiveUpdates = $request->boolean('receive_updates');

        // Use a direct update/insert query for maximum performance
        // This will be a single query instead of multiple
        UserGameProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $game->id,
            ],
            [
                'receive_updates' => $receiveUpdates,
            ]
        );

        $status = $receiveUpdates ? 'enabled' : 'disabled';

        return response()->json([
            'success' => true,
            'message' => "Notifications {$status}.",
            'receive_updates' => $receiveUpdates,
        ]);
    }

    public function getUserProgressStatus(Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Get the user's progress for this game
        $progress = UserGameProgress::where('user_id', $authId)
            ->where('game_id', $game->id)
            ->first();

        return response()->json([
            'success' => true,
            'receive_updates' => $progress ? $progress->receive_updates : false,
        ]);
    }

    public function getUserLists(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }
        $user = User::findOrFail($authId);

        $baseQuery = method_exists($user, 'vnLists') ? $user->vnLists() : VnList::where('user_id', $user->id);
        $lists = $baseQuery
            ->select('id', 'name', 'type', 'is_default')
            ->orderBy('created_at')
            ->get();

        $lists = $this->sortListsByType($lists);

        return response()->json([
            'success' => true,
            'lists' => $lists,
        ]);
    }

    public function getGameLists(Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Get all list IDs that contain this game for the authenticated user
        $listIds = VnListEntry::whereHas('list', function ($query) use ($authId) {
            $query->where('user_id', $authId);
        })
            ->where('game_id', $game->id)
            ->pluck('vn_list_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'list_ids' => $listIds,
        ]);
    }
}
