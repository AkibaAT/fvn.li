<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\NotificationHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserNotificationsController extends Controller
{
    /**
     * Get users who have opted to receive notifications for specific games.
     *
     * @param  Request  $request  The incoming request
     */
    public function getGameSubscribers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'game_id' => 'required|exists:games,id',
            'game_version_id' => 'required|exists:game_versions,id',
            'notification_type' => 'required|in:discord,telegram,email,browser',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $gameId = $request->input('game_id');
        $gameVersionId = $request->input('game_version_id');
        $notificationType = $request->input('notification_type');

        // This includes users who enabled notifications without adding the game to a list
        $query = User::query()
            ->whereHas('gameProgress', function ($query) use ($gameId) {
                $query->where('game_id', $gameId)
                    ->where('receive_updates', true);
            });

        if ($notificationType === 'discord') {
            $query->whereHas('socialAccounts', function ($query) {
                $query->where('provider_name', 'discord');
            })->whereHas('notificationPreferences', function ($query) {
                $query->where('discord_notifications_enabled', true);
            });
        } elseif ($notificationType === 'telegram') {
            $query->whereHas('socialAccounts', function ($query) {
                $query->where('provider_name', 'telegram');
            });
            $users = $query->with([
                'socialAccounts' => function ($query) {
                    $query->where('provider_name', 'telegram');
                },
            ])->get();

            $telegramIds = $users->pluck('socialAccounts.0.provider_id')->filter()->values();

            return response()->json([
                'telegram_ids' => $telegramIds,
                'game' => Game::with('latestVersion')->find($gameId),
            ]);
        } elseif ($notificationType === 'email') {
            $query->whereHas('socialAccounts', function ($query) {
                $query->where('provider_name', 'google');
            })
                ->whereNotNull('email');

            $users = $query->get();
            $userEmails = $users->pluck('email')->filter()->values();

            return response()->json([
                'emails' => $userEmails,
                'game' => Game::with('latestVersion')->find($gameId),
            ]);
        } elseif ($notificationType === 'browser') {
            $query->whereHas('notificationPreferences', function ($query) {
                $query->where('browser_notifications_enabled', true);
            });
        }

        $users = $query->get();
        $userIds = $users->pluck('id')->values();

        return response()->json([
            'user_ids' => $userIds,
            'game' => Game::with('latestVersion')->find($gameId),
        ]);
    }

    /**
     * Record a notification sent to a user.
     *
     * @param  Request  $request  The incoming request
     */
    public function recordNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'game_id' => 'required|exists:games,id',
            'game_version_id' => 'required|exists:game_versions,id',
            'type' => 'required|in:discord,telegram,email,browser',
            'success' => 'boolean',
            'meta_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $notificationHistory = NotificationHistory::create([
            'user_id' => $request->input('user_id'),
            'game_id' => $request->input('game_id'),
            'game_version_id' => $request->input('game_version_id'),
            'type' => $request->input('type'),
            'success' => $request->input('success', true),
            'meta_data' => $request->input('meta_data'),
        ]);

        return response()->json([
            'message' => 'Notification recorded successfully',
            'notification' => $notificationHistory,
        ]);
    }
}
