<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountMergeService
{
    /**
     * Merge two user accounts.
     *
     * @param  User  $mergingUser  The user to keep (target)
     * @param  User  $otherUser  The user to merge and delete (source)
     */
    public function mergeAccounts(User $mergingUser, User $otherUser): void
    {
        DB::transaction(function () use ($mergingUser, $otherUser) {
            Log::info('Starting account merge transaction', [
                'merging_user_id' => $mergingUser->id,
                'other_user_id' => $otherUser->id,
                'other_user_lists_count' => $otherUser->vnLists->count(),
            ]);

            $this->mergeVnLists($mergingUser, $otherUser);
            $this->mergeSocialAccounts($mergingUser, $otherUser);
            $this->mergeGameProgress($mergingUser, $otherUser);
            $this->mergeNotificationHistory($mergingUser, $otherUser);

            $otherUser->delete();

            Log::info('Account merge transaction completed successfully');
        });
    }

    /**
     * Merge VN lists from other user to merging user.
     */
    protected function mergeVnLists(User $mergingUser, User $otherUser): void
    {
        foreach ($otherUser->vnLists as $list) {
            Log::info('Processing list for merge', [
                'list_id' => $list->id,
                'list_name' => $list->name,
                'is_default' => $list->is_default,
                'entries_count' => $list->entries->count(),
            ]);

            if ($list->is_default) {
                $this->mergeSystemList($mergingUser, $list);
            } else {
                $this->mergeCustomList($mergingUser, $list);
            }
        }
    }

    /**
     * Merge a system/default list.
     */
    protected function mergeSystemList(User $mergingUser, $list): void
    {
        $mergingUserList = $mergingUser->vnLists()
            ->where('is_default', true)
            ->where('name', $list->name)
            ->first();

        if ($mergingUserList) {
            // Move entries that don't exist in the merging user's list
            foreach ($list->entries as $entry) {
                $existsInOtherSystemList = $mergingUser->vnLists()
                    ->where('is_default', true)
                    ->where('id', '!=', $mergingUserList->id)
                    ->whereHas('entries', function ($query) use ($entry) {
                        $query->where('game_id', $entry->game_id);
                    })
                    ->exists();

                // Only move if the game doesn't exist in any system list
                if (! $existsInOtherSystemList &&
                    ! $mergingUserList->entries()
                        ->where('game_id', $entry->game_id)
                        ->exists()) {
                    $entry->vn_list_id = $mergingUserList->id;
                    $entry->save();
                }
            }
        }
    }

    /**
     * Merge a custom list.
     */
    protected function mergeCustomList(User $mergingUser, $list): void
    {
        // For custom lists, just change the user_id
        $list->user_id = $mergingUser->id;
        $list->save();
    }

    /**
     * Move social accounts to merging user.
     */
    protected function mergeSocialAccounts(User $mergingUser, User $otherUser): void
    {
        $otherUser->socialAccounts->each(function ($account) use ($mergingUser) {
            $account->update(['user_id' => $mergingUser->id]);
        });
    }

    /**
     * Merge game progress (discard duplicates).
     */
    protected function mergeGameProgress(User $mergingUser, User $otherUser): void
    {
        foreach ($otherUser->gameProgress as $progress) {
            $existingProgress = $mergingUser->gameProgress()
                ->where('game_id', $progress->game_id)
                ->first();

            if (! $existingProgress) {
                // No conflict, transfer the progress
                $progress->user_id = $mergingUser->id;
                $progress->save();
            } else {
                // Keep current user's data, discard duplicate
                $progress->delete();
            }
        }
    }

    /**
     * Merge notification history (discard duplicates).
     */
    protected function mergeNotificationHistory(User $mergingUser, User $otherUser): void
    {
        foreach ($otherUser->notificationHistory as $notification) {
            try {
                $notification->user_id = $mergingUser->id;
                $notification->save();
            } catch (QueryException) {
                // Discard duplicate notifications
                $notification->delete();
            }
        }
    }
}
