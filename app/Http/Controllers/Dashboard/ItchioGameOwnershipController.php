<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\ItchioGameOwnershipSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ItchioGameOwnershipController extends Controller
{
    public function sync(Request $request, ItchioGameOwnershipSyncService $syncService): JsonResponse
    {
        $account = $request->user()->socialAccounts()
            ->where('provider_name', 'itchio')
            ->first();

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Connect your itch.io account before syncing games.',
            ], 422);
        }

        $previousGameIds = collect($account->itchio_game_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        try {
            $gameIds = $syncService->sync($account);
        } catch (Throwable $exception) {
            Log::warning('Failed to sync itch.io game ownership from the dashboard', [
                'user_id' => $request->user()->id,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not sync your itch.io games. Try reconnecting your itch.io account if the problem continues.',
            ], 502);
        }

        $syncedGameIds = collect($gameIds);
        $addedCount = $syncedGameIds->diff($previousGameIds)->count();
        $removedCount = $previousGameIds->diff($syncedGameIds)->count();
        $changed = $addedCount > 0 || $removedCount > 0;

        if ($changed) {
            $changes = collect([
                $addedCount > 0 ? sprintf('%d %s added', $addedCount, Str::plural('game', $addedCount)) : null,
                $removedCount > 0 ? sprintf('%d %s removed', $removedCount, Str::plural('game', $removedCount)) : null,
            ])->filter()->implode(' and ');
            $message = "Sync complete: {$changes}.";
        } else {
            $message = 'Sync complete: no changes found.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'game_count' => count($gameIds),
            'changed' => $changed,
            'added_count' => $addedCount,
            'removed_count' => $removedCount,
        ]);
    }
}
