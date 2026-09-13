<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

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
        if ($mergingUser->is($otherUser)) {
            throw new InvalidArgumentException('An account cannot be merged into itself.');
        }

        DB::transaction(function () use ($mergingUser, $otherUser) {
            $users = User::whereIn('id', [$mergingUser->id, $otherUser->id])->orderBy('id')->lockForUpdate()->get();
            if ($users->count() !== 2) {
                throw new InvalidArgumentException('Both accounts must exist to merge them.');
            }
            $mergingUser->refresh();
            $otherUser->refresh();
            Log::info('Starting account merge transaction', [
                'merging_user_id' => $mergingUser->id,
                'other_user_id' => $otherUser->id,
                'other_user_lists_count' => $otherUser->vnLists->count(),
            ]);

            $this->mergePreferences($mergingUser, $otherUser);
            $this->mergeVnLists($mergingUser, $otherUser);
            $this->mergeSocialAccounts($mergingUser, $otherUser);
            $this->mergeGameProgress($mergingUser, $otherUser);
            $this->mergeNotificationHistory($mergingUser, $otherUser);
            $this->mergeRatings($mergingUser, $otherUser);
            $this->mergeRemainingData($mergingUser, $otherUser);

            $mergingUser->update(['is_review_banned' => $mergingUser->is_review_banned || $otherUser->is_review_banned]);
            $otherUser->tokens()->delete();
            DB::table('sessions')->where('user_id', $otherUser->id)->delete();
            $otherUser->delete();
            app(VnListCacheService::class)->clearPublicListsCache();
            RatingStatsCacheService::clear();

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
        $mergingUserList = $mergingUser->vnLists()->firstOrCreate(
            ['is_default' => true, 'type' => $list->type],
            ['name' => $this->availableListName($mergingUser, $list->name), 'description' => $list->description, 'is_public' => $list->is_public],
        );

        // The target account's reading status wins on conflicts.
        foreach ($list->entries as $entry) {
            $existsInSystemList = $mergingUser->vnLists()
                ->where('is_default', true)
                ->whereHas('entries', fn ($query) => $query->where('game_id', $entry->game_id))
                ->exists();

            if (! $existsInSystemList) {
                $entry->vn_list_id = $mergingUserList->id;
                $entry->save();
            }
        }
    }

    /**
     * Merge a custom list.
     */
    protected function mergeCustomList(User $mergingUser, $list): void
    {
        // For custom lists, just change the user_id
        $list->name = $this->availableListName($mergingUser, $list->name);
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
        $this->mergeRows('notification_history', 'user_id', ['game_id', 'game_version_id', 'type'], $mergingUser->id, $otherUser->id);
    }

    private function availableListName(User $user, string $name): string
    {
        $candidate = $name;
        for ($number = 2; $user->vnLists()->where('name', $candidate)->exists(); $number++) {
            $suffix = " (merged {$number})";
            $candidate = mb_substr($name, 0, 255 - mb_strlen($suffix)) . $suffix;
        }

        return $candidate;
    }

    private function mergePreferences(User $target, User $source): void
    {
        if ($target->preferences && $source->preferences) {
            foreach (['preferred_languages', 'excluded_tags'] as $field) {
                if ($target->preferences->$field === null) {
                    $target->preferences->$field = $source->preferences->$field;
                }
            }
            $target->preferences->save();
        }

        if (! $target->socialAccounts()->where('provider_name', 'discord')->exists()
            && $target->notificationPreferences && $source->notificationPreferences) {
            $target->notificationPreferences->update($source->notificationPreferences->only([
                'discord_dm_status', 'discord_dm_status_reason', 'discord_dm_verified_at',
                'discord_dm_last_failed_at', 'discord_user_installed_at',
            ]));
        }
    }

    private function mergeRatings(User $target, User $source): void
    {
        foreach ($source->ratings()->lazyById() as $rating) {
            $existing = $target->ratings()->where('game_id', $rating->game_id)->first();
            if (! $existing) {
                $rating->update(['user_id' => $target->id]);

                continue;
            }

            // Keep one current review, with the other review retained in the account export.
            $metadata = $existing->external_metadata ?? [];
            $metadata['merged_reviews'][] = $rating->only([
                'id', 'rating', 'review', 'has_spoilers', 'published_at', 'is_visible', 'is_moderation_hidden', 'external_metadata',
            ]);
            $existing->update(['external_metadata' => $metadata]);
            // Preserve moderation reports attached to the superseded row.
            $rating->update(['user_id' => null, 'is_visible' => false]);
        }
    }

    private function mergeRemainingData(User $target, User $source): void
    {
        // Target settings win conflicts; unique source preferences and relationships are transferred.
        foreach ([
            'user_preferences' => [],
            'user_notification_preferences' => [],
            'user_ignored_games' => ['game_id'],
            'addition_request_users' => ['addition_request_id'],
            'notification_queue' => ['game_id', 'game_version_id', 'channel'],
        ] as $table => $uniqueColumns) {
            $this->mergeRows($table, 'user_id', $uniqueColumns, $target->id, $source->id);
        }
        $this->mergeRows('review_reports', 'reporter_id', ['rating_id'], $target->id, $source->id);

        foreach ([
            'bug_reports' => ['user_id', 'resolved_by'],
            'bug_report_comments' => ['user_id'],
            'android_builds' => ['user_id'],
            'push_subscriptions' => ['user_id'],
            'discord_servers' => ['owner_user_id'],
            'discord_server_members' => ['user_id'],
            'raters' => ['user_id'],
            'addition_requests' => ['reviewed_by'],
            'review_reports' => ['reviewed_by'],
            'games' => ['custom_page_updated_by'],
            'change_logs' => ['user_id'],
            'click_stats' => ['user_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->where($column, $source->id)->update([$column => $target->id]);
            }
        }
        $source->notifications()->update(['notifiable_id' => $target->id]);
    }

    private function mergeRows(string $table, string $ownerColumn, array $uniqueColumns, int $targetId, int $sourceId): void
    {
        foreach (DB::table($table)->where($ownerColumn, $sourceId)->lazyById() as $row) {
            $existing = DB::table($table)->where($ownerColumn, $targetId);
            foreach ($uniqueColumns as $column) {
                $existing->where($column, $row->$column);
            }
            $source = DB::table($table)->where('id', $row->id);
            if ($existing->exists()) {
                $source->delete();
            } else {
                $updates = [$ownerColumn => $targetId];
                if ($table === 'notification_queue' && $row->status === 'processing') {
                    $updates += ['status' => 'pending', 'batch_key' => null, 'updated_at' => now()];
                }
                $source->update($updates);
            }
        }
    }
}
