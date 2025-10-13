<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->unreadNotifications()->latest()->limit(20)->get()->map(function (DatabaseNotification $n) {
            return [
                'id' => $n->id,
                'type' => $n->data['type'] ?? $n->type,
                'message' => $n->data['message'] ?? '',
                'data' => $n->data,
                'created_at' => $n->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }
}
